<?php
// Полный оригинальный функционал с новым интерфейсом
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/functions/stock_movements.php';

// 🔥 ФУНКЦИЯ ЦЕНЫ
function getPrice($row, $qty) {
    if ($qty <= 4 && $row['price_1_4'] > 0) return $row['price_1_4'];
    if ($qty <= 9 && $row['price_5_9'] > 0) return $row['price_5_9'];
    if ($qty <= 19 && $row['price_10_19'] > 0) return $row['price_10_19'];
    if ($row['price_20_plus'] > 0) return $row['price_20_plus'];
    return 0;
}

// 🔥 ДОБАВЛЕНИЕ РУЛОНОВ
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && !isset($_POST['sell_rolls']) 
    && !isset($_POST['sell_meters']) 
    && (!isset($_POST['action']) || $_POST['action'] !== 'writeoff')
) {
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
        $price = getPrice($product, $qty);
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

        $success_msg = "✅ Продано рулонов: $qty | $total";
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
        $where[] = "(CAST(r.id AS CHAR) LIKE ? OR p.name LIKE ?)";
        $like = '%' . $filterSearch . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "
        SELECT 
            r.id,
            r.product_id,
            r.original_length,
            r.current_length,
            r.min_full_length,
            r.status,
            r.price_per_meter,
            r.price_1_4,
            r.price_5_9,
            r.price_10_19,
            r.price_20_plus,
            p.name as product_name,
            p.roll_length as product_roll_length
        FROM rolls r
        LEFT JOIN products p ON r.product_id = p.id
    ";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY r.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rolls) || (count($rolls) > 0 && empty($rolls[0]['product_name']))) {
        $rolls = $db->query("SELECT * FROM rolls ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        $products_data = array();
        $products_stmt = $db->query("SELECT id, name, roll_length FROM products");
        while ($product = $products_stmt->fetch(PDO::FETCH_ASSOC)) {
            $products_data[$product['id']] = $product;
        }
        
        foreach ($rolls as &$roll) {
            if (isset($products_data[$roll['product_id']])) {
                $roll['product_name'] = $products_data[$roll['product_id']]['name'];
                $roll['product_roll_length'] = $products_data[$roll['product_id']]['roll_length'];
            } else {
                $roll['product_name'] = 'Товар #' . $roll['product_id'] . ' (не найден)';
                $roll['product_roll_length'] = $roll['original_length'];
            }
        }
    }
} catch (Exception $e) {
    $rolls = $db->query("SELECT * FROM rolls ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rolls as &$roll) {
        $roll['product_name'] = 'Товар #' . $roll['product_id'];
        $roll['product_roll_length'] = $roll['original_length'];
    }
}

$products = $db->query("SELECT * FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
 
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
            <h2>Фильтры списка</h2>
            <form method="GET">
                <div class="form-row">
                    <div class="form-group">
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
                    <div class="form-group">
                        <label>Статус</label>
                        <select name="status">
                            <option value="">Все</option>
                            <?php foreach (array('active','cut','scrap','sold','written_off','waste') as $st): ?>
                                <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Поиск (ID/название)</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($filterSearch) ?>">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-light">Применить</button>
                        <a href="warehouse.php" class="btn btn-light">Сброс</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Добавление рулонов -->
        <div class="card">
            <h2>📦 Добавить рулоны</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Товар:</label>
                        <select name="product_id" required>
                            <option value="">Выберите товар</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['name']); ?> (<?php echo !empty($p['roll_length']) ? $p['roll_length'] : '30'; ?>м)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Мин. остаток (м):</label>
                        <input type="number" name="min_full" step="0.1" value="0.5" required>
                    </div>
                    <div class="form-group">
                        <label>Количество:</label>
                        <input type="number" name="quantity" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-success">➕ Добавить</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Продажа рулонов -->
        <div class="card">
            <h2>💰 Продажа рулонов</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Товар:</label>
                        <select name="sell_product_id" required>
                            <option value="">Выберите товар</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Количество рулонов:</label>
                        <input type="number" name="sell_qty" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="sell_rolls" class="btn btn-primary">💵 Продать</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Продажа в метрах -->
        <div class="card">
            <h2>📏 Продажа в метрах</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Товар:</label>
                        <select name="meter_product_id" required>
                            <option value="">Выберите товар</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Метров:</label>
                        <input type="number" name="meters" step="0.1" min="0.1" required>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="sell_meters" class="btn btn-primary">💵 Продать</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Списание -->
        <div class="card">
            <h2>🗑️ Списание</h2>
            <form method="POST">
                <input type="hidden" name="action" value="writeoff">
                <div class="form-row">
                    <div class="form-group">
                        <label>Рулон:</label>
                        <select name="writeoff_roll_id" required>
                            <option value="">Выберите рулон</option>
                            <?php foreach ($rolls as $r): ?>
                                <option value="<?php echo $r['id']; ?>">
                                    #<?php echo $r['id']; ?> | <?php echo htmlspecialchars($r['product_name']); ?> (остаток: <?php echo $r['current_length']; ?>м)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Метров к списанию:</label>
                        <input type="number" name="writeoff_meters" step="0.1" min="0.1" required>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-warning">🗑️ Списать</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Складские остатки -->
        <div class="card">
            <h2>📋 Складские остатки</h2>
            <?php if (count($rolls) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Товар</th>
                        <th>Длина</th>
                        <th>Остаток</th>
                        <th>Цена/м</th>
                        <th>1-4</th>
                        <th>5-9</th>
                        <th>10-19</th>
                        <th>20+</th>
                        <th>Статус</th>
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
                        <td><?php echo !empty($r['price_per_meter']) && $r['price_per_meter'] > 0 ? number_format($r['price_per_meter'], 0) . ' ₽' : '-'; ?></td>
                        <td><?php echo !empty($r['price_1_4']) && $r['price_1_4'] > 0 ? $r['price_1_4'] : '-'; ?></td>
                        <td><?php echo !empty($r['price_5_9']) && $r['price_5_9'] > 0 ? $r['price_5_9'] : '-'; ?></td>
                        <td><?php echo !empty($r['price_10_19']) && $r['price_10_19'] > 0 ? $r['price_10_19'] : '-'; ?></td>
                        <td><?php echo !empty($r['price_20_plus']) && $r['price_20_plus'] > 0 ? $r['price_20_plus'] : '-'; ?></td>
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
