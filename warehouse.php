<?php
// Полный оригинальный функционал с новым интерфейсом
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/functions/stock_movements.php';
require_once __DIR__ . '/functions/pricing.php';
require_once __DIR__ . '/functions/app_settings.php';
require_once __DIR__ . '/functions/integration_sync_control.php';
require_once __DIR__ . '/functions/stock_emergency_kill.php';
require_once __DIR__ . '/api/bitrix/send.php';

// Compatibility wrapper for legacy calls in this file.
function getPrice($row, $qty) {
    $resolved = resolveTierPrice($row, $qty);
    return floatval($resolved['price']);
}

function hydrateMissingRollProductNamesFromBitrix($db, &$rolls) {
    if (!is_array($rolls) || empty($rolls)) {
        return;
    }

    $missingIds = array();
    foreach ($rolls as $roll) {
        $name = isset($roll['product_name']) ? trim((string)$roll['product_name']) : '';
        if ($name === '' || strpos($name, 'Архивный товар (ID ') === 0) {
            $pid = isset($roll['product_id']) ? intval($roll['product_id']) : 0;
            if ($pid > 0) {
                $missingIds[$pid] = true;
            }
        }
    }
    if (empty($missingIds)) {
        return;
    }

    $resolved = array();
    foreach (array_keys($missingIds) as $b24Id) {
        $item = null;
        $resp = sendToBitrix('crm.product.get', array('id' => $b24Id));
        if (is_array($resp) && !isset($resp['error']) && isset($resp['result']) && is_array($resp['result'])) {
            $item = $resp['result'];
        }
        if ($item === null) {
            $resp = sendToBitrix('crm.product.list', array(
                'filter' => array('ID' => $b24Id),
                'start' => 0
            ));
            if (is_array($resp) && !isset($resp['error']) && isset($resp['result']) && is_array($resp['result']) && !empty($resp['result'][0])) {
                $item = $resp['result'][0];
            }
        }
        if (!is_array($item)) {
            continue;
        }

        $name = isset($item['NAME']) ? trim((string)$item['NAME']) : '';
        if ($name === '') {
            continue;
        }
        $resolved[$b24Id] = $name;

        // Upsert minimal local product row to avoid repeated live calls.
        $sel = $db->prepare("SELECT id FROM products WHERE b24_product_id = ? LIMIT 1");
        $sel->execute(array($b24Id));
        $existing = $sel->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $db->prepare("UPDATE products SET name = ? WHERE id = ?")->execute(array($name, intval($existing['id'])));
        } else {
            $db->prepare("
                INSERT INTO products (name, roll_length, price_per_meter, b24_product_id)
                VALUES (?, 30, 0, ?)
            ")->execute(array($name, $b24Id));
        }
    }

    // Additional local fallback by either local id or b24_product_id.
    if (count($resolved) < count($missingIds)) {
        foreach (array_keys($missingIds) as $pid) {
            if (isset($resolved[$pid])) {
                continue;
            }
            $stmt = $db->prepare("
                SELECT name
                FROM products
                WHERE id = ? OR b24_product_id = ?
                ORDER BY CASE WHEN id = ? THEN 0 ELSE 1 END
                LIMIT 1
            ");
            $stmt->execute(array($pid, $pid, $pid));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['name']) && trim((string)$row['name']) !== '') {
                $resolved[$pid] = trim((string)$row['name']);
            }
        }
    }

    if (empty($resolved)) {
        return;
    }

    foreach ($rolls as &$roll) {
        $pid = isset($roll['product_id']) ? intval($roll['product_id']) : 0;
        if ($pid > 0 && isset($resolved[$pid])) {
            $roll['product_name'] = $resolved[$pid];
        }
    }
}

// 🔥 ДОБАВЛЕНИЕ РУЛОНОВ
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && !isset($_POST['sell_rolls']) 
    && !isset($_POST['sell_meters']) 
    && !isset($_POST['delete_roll'])
    && (!isset($_POST['action']) || $_POST['action'] !== 'writeoff')
) {
    $emOff = stockEmergencyRollCreationStoppedMessage();
    $blockMsg = ($emOff !== '') ? $emOff : integrationStockRollCreationBlockedMessage($db);
    if ($blockMsg !== '') {
        $error_msg = $blockMsg;
    } else {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $min = floatval($_POST['min_full']);

    $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute(array($product_id));
    $product = $stmt->fetch();

    for ($i = 0; $i < $quantity; $i++) {
        $stmt = $db->prepare("
            INSERT INTO rolls 
            (product_id, original_length, current_length, min_full_length, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute(array(
            $product_id,
            $product['roll_length'],
            $product['roll_length'],
            $min
        ));

        logAndSyncMovement($db, array(
            'product_id' => $product_id,
            'roll_id' => intval($db->lastInsertId()),
            'movement_type' => 'receipt',
            'quantity_m' => floatval($product['roll_length']),
            'quantity_rolls' => 1,
            'price_per_unit' => isset($product['purchase_price']) ? floatval($product['purchase_price']) : 0,
            'total' => isset($product['purchase_price']) ? floatval($product['purchase_price']) : 0,
            'comment' => 'Оприходование в приложении'
        ));
    }
    $success_msg = "✅ Добавлено рулонов: $quantity";
    }
}

// 🔥 УДАЛЕНИЕ РУЛОНА (только не для проданных/списанных)
if (isset($_POST['delete_roll'])) {
    $rollId = intval($_POST['delete_roll']);
    $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id = ?");
    $rollStmt->execute(array($rollId));
    $rollToDelete = $rollStmt->fetch(PDO::FETCH_ASSOC);
    if (!$rollToDelete) {
        $error_msg = "Рулон не найден";
    } else {
        if (in_array($rollToDelete['status'], array('sold', 'written_off', 'waste'), true)) {
            $error_msg = "Проданные/списанные рулоны удалять нельзя";
        } else {
            $db->prepare("DELETE FROM rolls WHERE id = ?")->execute(array($rollId));
            $success_msg = "✅ Рулон #{$rollId} удален";
        }
    }
}

// 🔥 СПИСАНИЕ
if (isset($_POST['action']) && $_POST['action'] === 'writeoff') {
    $roll_id = intval($_POST['writeoff_roll_id']);
    $meters = floatval($_POST['writeoff_meters']);

    $stmt = $db->prepare("SELECT * FROM rolls WHERE id=?");
    $stmt->execute(array($roll_id));
    $roll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$roll) {
        $error_msg = "Рулон не найден (ID: $roll_id)";
    } else {
        if ($meters > $roll['current_length']) {
            $error_msg = "Нельзя списать больше чем есть";
        } else {
            $new_length = $roll['current_length'] - $meters;
            if ($new_length <= 0) {
                $new_status = 'written_off';
                $new_length = 0;
            } else {
                $new_status = 'cut';
            }

            $stmt = $db->prepare("
                UPDATE rolls 
                SET current_length=?, status=? 
                WHERE id=?
            ");
            $stmt->execute(array($new_length, $new_status, $roll_id));

            $stmt = $db->prepare("
                INSERT INTO sales 
                (product_id, type, quantity, price_per_unit, total, deal_id, deal_url)
                VALUES (?, 'writeoff', ?, 0, 0, NULL, NULL)
            ");
            $stmt->execute(array($roll['product_id'], $meters));

            logAndSyncMovement($db, array(
                'product_id' => intval($roll['product_id']),
                'roll_id' => $roll_id,
                'movement_type' => 'writeoff',
                'quantity_m' => $meters,
                'quantity_rolls' => 0,
                'price_per_unit' => 0,
                'total' => 0,
                'comment' => 'Ручное списание в warehouse.php'
            ));

            $success_msg = "✅ Списано: $meters м";
        }
    }
}

// 🔥 ПРОДАЖА РУЛОНОВ
if (isset($_POST['sell_rolls'])) {
    $product_id = intval($_POST['sell_product_id']);
    $qty = intval($_POST['sell_qty']);

    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute(array($product_id));
    $product = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT * FROM rolls 
        WHERE product_id = ? 
        AND status = 'active'
        AND current_length = original_length
        ORDER BY id ASC
    ");
    $stmt->execute(array($product_id));
    $rollsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rollsList) < $qty) {
        $error_msg = "Недостаточно целых рулонов";
    } else {
        $priceMeta = resolveTierPrice($product, $qty);
        $price = floatval($priceMeta['price']);
        $total = $price * $qty;

        for ($i = 0; $i < $qty; $i++) {
            $stmt = $db->prepare("
                UPDATE rolls 
                SET status='sold', current_length=0 
                WHERE id=?
            ");
            $stmt->execute(array($rollsList[$i]['id']));
        }

        $stmt = $db->prepare("
            INSERT INTO sales (product_id, type, quantity, price_per_unit, total)
            VALUES (?, 'roll', ?, ?, ?)
        ");
        $stmt->execute(array($product_id, $qty, $price, $total));

        logAndSyncMovement($db, array(
            'product_id' => $product_id,
            'movement_type' => 'sale_roll',
            'quantity_m' => 0,
            'quantity_rolls' => $qty,
            'price_per_unit' => $price,
            'total' => $total,
            'comment' => 'Продажа рулонов'
        ));

        $sourceLabel = formatTierSourceLabel(isset($priceMeta['sourceTier']) ? $priceMeta['sourceTier'] : 'none');
        $fallbackNote = !empty($priceMeta['fallbackUsed']) ? ' (fallback)' : '';
        $success_msg = "✅ Продано рулонов: $qty | $total | Источник цены: {$sourceLabel}{$fallbackNote}";
    }
}

// 🔥 ПРОДАЖА МЕТРОВ
if (isset($_POST['sell_meters'])) {
    require_once __DIR__ . '/functions/rolls.php';

    $product_id = intval($_POST['meter_product_id']);
    $meters = floatval($_POST['meters']);

    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute(array($product_id));
    $product = $stmt->fetch();

    try {
        $cuts = allocateMeters($db, $product_id, $meters);
        $price = $product['price_per_meter'];
        $total = $price * $meters;

        $stmt = $db->prepare("
            INSERT INTO sales (product_id, type, quantity, price_per_unit, total)
            VALUES (?, 'meter', ?, ?, ?)
        ");
        $stmt->execute(array($product_id, $meters, $price, $total));

        logAndSyncMovement($db, array(
            'product_id' => $product_id,
            'movement_type' => 'sale_meter',
            'quantity_m' => $meters,
            'quantity_rolls' => 0,
            'price_per_unit' => $price,
            'total' => $total,
            'comment' => 'Продажа в метрах'
        ));

        $success_msg = "✅ Продано $meters м | $total";
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}

// Фильтры списка рулонов
$filterProductId = intval(isset($_GET['product_id']) ? $_GET['product_id'] : 0);
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterSearch = isset($_GET['q']) ? trim($_GET['q']) : '';
$viewMode = isset($_GET['view']) ? trim($_GET['view']) : 'active'; // active | history | all
$onlyScrap = isset($_GET['only_scrap']) && $_GET['only_scrap'] === '1';
$lowStockThreshold = floatval(isset($_GET['low_stock_below']) ? $_GET['low_stock_below'] : 0);
$withoutNameOnly = isset($_GET['without_name']) && $_GET['without_name'] === '1';

// Получаем данные с улучшенной обработкой
$rolls = array();
try {
    $where = array();
    $params = array();
    if ($filterProductId > 0) {
        $where[] = "r.product_id = ?";
        $params[] = $filterProductId;
    }
    if ($filterStatus !== '') {
        $where[] = "r.status = ?";
        $params[] = $filterStatus;
    }
    if ($filterSearch !== '') {
        $where[] = "(CAST(r.id AS CHAR) LIKE ? OR COALESCE(p_local.name, p_b24.name, '') LIKE ?)";
        $like = '%' . $filterSearch . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if ($viewMode === 'active') {
        $where[] = "r.status NOT IN ('sold','written_off','waste')";
    } elseif ($viewMode === 'history') {
        $where[] = "r.status IN ('sold','written_off','waste')";
    }
    if ($onlyScrap) {
        $where[] = "r.status = 'scrap'";
    }
    if ($lowStockThreshold > 0) {
        $where[] = "r.current_length < ?";
        $params[] = $lowStockThreshold;
    }
    if ($withoutNameOnly) {
        $where[] = "COALESCE(NULLIF(TRIM(p_local.name), ''), NULLIF(TRIM(p_b24.name), '')) IS NULL";
    }

    $sql = "
        SELECT 
            r.id,
            r.product_id,
            r.original_length,
            r.current_length,
            r.min_full_length,
            r.status,
            r.reserved,
            r.deal_id,
            COALESCE(p_local.price_per_meter, p_b24.price_per_meter, 0) as price_per_meter,
            COALESCE(
                NULLIF(TRIM(p_local.name), ''),
                NULLIF(TRIM(p_b24.name), ''),
                CONCAT('Архивный товар (ID ', r.product_id, ')')
            ) as product_name,
            COALESCE(p_local.roll_length, p_b24.roll_length, r.original_length) as product_roll_length
        FROM rolls r
        LEFT JOIN products p_local ON r.product_id = p_local.id
        LEFT JOIN products p_b24 ON r.product_id = p_b24.b24_product_id
    ";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY r.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    hydrateMissingRollProductNamesFromBitrix($db, $rolls);
} catch (Exception $e) {
    $rolls = $db->query("SELECT * FROM rolls ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rolls as &$roll) {
        $roll['product_name'] = 'Архивный товар (ID ' . $roll['product_id'] . ')';
        $roll['product_roll_length'] = $roll['original_length'];
    }
}

$products = $db->query("SELECT * FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
if (isset($_GET['debug_price_selfcheck']) && $_GET['debug_price_selfcheck'] === '1') {
    $pricingSelfCheck = tierPricingSelfCheckCases();
}
$b24Queue = null;
try {
    $b24Queue = $db->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('new','in_progress') THEN 1 ELSE 0 END) as reserve_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as paid_count
        FROM b24_sale_requests
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $b24Queue = null;
}
 
$page_title = 'Склад';
require 'includes/header.php';
?>
<main class="container">
        <h1>🏭 Управление складом</h1>

        <?php if (isset($success_msg)): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>🔄 Синхронизация с Б24</h2>
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:8px;">
                <a href="api/bitrix/sync_stock.php?push=1" class="btn btn-warning" target="_blank">📤 Синхронизировать остатки</a>
                <a href="api/sync_prices.php?action=to_b24" class="btn btn-warning" target="_blank">💰 Синхронизировать цены</a>
                <a href="api/bitrix/import_products.php" class="btn btn-success" target="_blank">📥 Импортировать товары из Б24</a>
                <a href="b24_sales.php" class="btn btn-light">📋 Очередь резерва Б24</a>
                <a href="warehouse_orders.php" class="btn btn-light">🧰 Рабочее место кладовщика</a>
            </div>
            <?php if ($b24Queue): ?>
                <p class="text-muted" style="margin:0;">
                    Сделок в очереди: <?= intval($b24Queue['total']) ?> |
                    <strong>Резерв:</strong> <?= intval($b24Queue['reserve_count']) ?> |
                    <strong>Оплачено:</strong> <?= intval($b24Queue['paid_count']) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Фильтры склада</h2>
            <form method="GET">
                <div class="warehouse-filter-grid">
                    <div class="form-group warehouse-filter-item">
                        <label>Товар</label>
                        <select name="product_id">
                            <option value="0">Все товары</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= intval($p['id']) ?>" <?= $filterProductId === intval($p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group warehouse-filter-item">
                        <label>Статус рулона</label>
                        <select name="status">
                            <option value="">Все</option>
                            <?php foreach (array('active','cut','scrap','sold','written_off','waste') as $st): ?>
                                <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group warehouse-filter-item">
                        <label>Режим списка</label>
                        <select name="view">
                            <option value="active" <?= $viewMode === 'active' ? 'selected' : '' ?>>Актуальные</option>
                            <option value="history" <?= $viewMode === 'history' ? 'selected' : '' ?>>История (продано/списано)</option>
                            <option value="all" <?= $viewMode === 'all' ? 'selected' : '' ?>>Все</option>
                        </select>
                    </div>
                    <div class="form-group warehouse-filter-item warehouse-filter-item-wide">
                        <label>Быстрые фильтры</label>
                        <div class="warehouse-filter-inline">
                            <label class="warehouse-filter-check">
                                <input type="checkbox" name="only_scrap" value="1" <?= $onlyScrap ? 'checked' : '' ?>>
                                Только обрезки
                            </label>
                            <label class="warehouse-filter-check">
                                Остаток меньше
                                <input type="number" name="low_stock_below" min="0" step="0.1" value="<?= htmlspecialchars((string)$lowStockThreshold) ?>" class="warehouse-filter-threshold">
                                м
                            </label>
                            <label class="warehouse-filter-check">
                                <input type="checkbox" name="without_name" value="1" <?= $withoutNameOnly ? 'checked' : '' ?>>
                                Без имени товара
                            </label>
                        </div>
                    </div>
                    <div class="form-group warehouse-filter-item">
                        <label>Поиск (ID / название)</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($filterSearch) ?>">
                    </div>
                </div>
                <div class="warehouse-filter-actions">
                    <button type="submit" class="btn btn-primary">Применить фильтры</button>
                    <a href="warehouse.php" class="btn btn-light">Сбросить</a>
                    <span class="text-muted">Показываем: <?= count($rolls) ?> рулонов</span>
                </div>
            </form>
        </div>

        <!-- Блок "Добавить рулоны" скрыт из UI.
             Логика POST сохранена для совместимости, фактическое добавление выполняется с dashboard.php. -->

        <!-- Блоки "Продажа рулонов/в метрах" скрыты из UI.
             Логика на POST сохранена вверху файла для совместимости. -->

        <!-- Блок "Списание" скрыт из UI.
             Логика writeoff в backend сохранена для совместимости интеграций/хуков. -->

        <!-- Складские остатки -->
        <div class="card">
            <h2>📋 Складские остатки</h2>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
                <a class="btn btn-light" href="?view=active">Рабочий склад</a>
                <a class="btn btn-light" href="?view=history">Архив движения</a>
                <a class="btn btn-light" href="?view=all">Полный список</a>
            </div>
            <?php
            $counts = array('active' => 0, 'history' => 0);
            foreach ($rolls as $rr) {
                if (in_array($rr['status'], array('sold','written_off','waste'), true)) {
                    $counts['history']++;
                } else {
                    $counts['active']++;
                }
            }
            ?>
            <p class="text-muted">
                В выборке: актуальных <?= $counts['active'] ?>, исторических <?= $counts['history'] ?>.
            </p>
            <?php if (count($rolls) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Товар</th>
                        <th>Длина</th>
                        <th>Остаток</th>
                        <th>Цена/м</th>
                        <th>Статус</th>
                        <th>Пометка</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rolls as $r): ?>
                    <tr>
                        <td><?php echo $r['id']; ?></td>
                        <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                        <td><?php echo !empty($r['original_length']) ? $r['original_length'] . ' м' : '-'; ?></td>
                        <td><strong><?php echo !empty($r['current_length']) ? $r['current_length'] . ' м' : '-'; ?></strong></td>
                        <td><?php echo !empty($r['price_per_meter']) && $r['price_per_meter'] > 0 ? number_format($r['price_per_meter'], 0) . ' KGS' : '-'; ?></td>
                        <td>
                            <?php
                            $statusClass = 'status-active';
                            $statusText = $r['status'];
                            switch ($r['status']) {
                                case 'active': $statusClass = 'status-active'; $statusText = 'Активный'; break;
                                case 'sold': $statusClass = 'status-sold'; $statusText = 'Продан'; break;
                                case 'cut': $statusClass = 'status-cut'; $statusText = 'В резке'; break;
                                case 'scrap': $statusClass = 'status-scrap'; $statusText = 'Обрезок'; break;
                                case 'written_off': $statusClass = 'status-sold'; $statusText = 'Списан'; break;
                                case 'waste': $statusClass = 'status-sold'; $statusText = 'Отход'; break;
                            }
                            ?>
                            <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </td>
                        <td>
                            <?php if (isset($r['reserved']) && intval($r['reserved']) === 1): ?>
                                <span class="status-cut">РЕЗЕРВ</span>
                            <?php elseif ($r['status'] === 'sold'): ?>
                                <span class="status-active">ОПЛАЧЕНО</span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_roll" value="<?php echo $r['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" 
                                        onclick="return confirm('Удалить рулон #<?php echo $r['id']; ?>?')">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>📦 Нет рулонов на складе</p>
            <?php endif; ?>
        </div>

        <!-- Статистика -->
        <div class="card">
            <h2>📊 Статистика</h2>
            <?php
            $stats = $db->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                    COUNT(CASE WHEN status = 'sold' THEN 1 END) as sold,
                    SUM(current_length) as total_meters
                FROM rolls
            ")->fetch(PDO::FETCH_ASSOC);
            ?>
            <p><strong>Всего рулонов:</strong> <?php echo $stats['total']; ?></p>
            <p><strong>Активных:</strong> <?php echo $stats['active']; ?></p>
            <p><strong>Продано:</strong> <?php echo $stats['sold']; ?></p>
            <p><strong>Всего метров:</strong> <?php echo number_format($stats['total_meters'], 1); ?></p>
        </div>
</main>

<?php require 'includes/footer.php'; ?>
