<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/db.php';
require_once __DIR__ . '/api/bitrix/send.php';
require_once __DIR__ . '/functions/deal_rows_sync_service.php';

$db = getDB();
$message = '';
$error = '';
$page_title = 'Рабочее место кладовщика';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hasPickerColumns($db) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM b24_sale_requests LIKE 'picker_status'");
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

function ensurePickerFinanceSchema($db) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM products LIKE 'min_margin_percent'");
        $exists = $stmt && $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$exists) {
            $db->exec("ALTER TABLE products ADD COLUMN min_margin_percent decimal(8,2) NOT NULL DEFAULT 0");
        }
    } catch (Exception $e) {
        // Keep picker page working even if schema update fails.
    }
}

function releaseRequestReserve($db, $requestId) {
    $cutsStmt = $db->prepare("
        SELECT c.id, c.roll_id, c.meters
        FROM b24_sale_line_cuts c
        JOIN b24_sale_lines l ON l.id = c.line_id
        WHERE l.request_id = ?
    ");
    $cutsStmt->execute(array($requestId));
    $cuts = $cutsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cuts as $cut) {
        $rollId = intval($cut['roll_id']);
        $meters = floatval($cut['meters']);

        $rollStmt = $db->prepare("SELECT reserved_length FROM rolls WHERE id = ?");
        $rollStmt->execute(array($rollId));
        $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);
        if (!$roll) {
            continue;
        }

        $newReserved = max(0, floatval($roll['reserved_length']) - $meters);
        if ($newReserved <= 0) {
            $db->prepare("
                UPDATE rolls
                SET reserved = 0, deal_id = NULL, reserved_length = 0
                WHERE id = ?
            ")->execute(array($rollId));
        } else {
            $db->prepare("
                UPDATE rolls
                SET reserved_length = ?
                WHERE id = ?
            ")->execute(array($newReserved, $rollId));
        }
    }

    $db->prepare("
        DELETE c FROM b24_sale_line_cuts c
        JOIN b24_sale_lines l ON l.id = c.line_id
        WHERE l.request_id = ?
    ")->execute(array($requestId));

    $db->prepare("
        UPDATE b24_sale_lines
        SET status = 'new'
        WHERE request_id = ? AND status != 'completed'
    ")->execute(array($requestId));
}

if (!hasPickerColumns($db)) {
    require __DIR__ . '/includes/header.php';
    echo '<main class="container">';
    echo '<h2>Рабочее место кладовщика</h2>';
    echo '<div class="alert alert-danger">Не применена миграция <code>migrations/004_b24_picker_queue_status.sql</code>.</div>';
    echo '<p>Примените миграцию и обновите страницу.</p>';
    echo '</main>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
ensurePickerFinanceSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $requestId = intval(isset($_POST['request_id']) ? $_POST['request_id'] : 0);
    $problemText = trim(isset($_POST['problem_text']) ? $_POST['problem_text'] : '');

    if ($requestId <= 0) {
        $error = 'Некорректный request_id.';
    } else {
        $requestStmt = $db->prepare("SELECT * FROM b24_sale_requests WHERE id = ?");
        $requestStmt->execute(array($requestId));
        $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            $error = 'Заявка не найдена.';
        } else {
            $metaJson = json_encode(array(
                'updated_from' => 'warehouse_orders.php',
                'updated_at' => date('c')
            ));

            if ($action === 'save_pick') {
                $db->prepare("
                    UPDATE b24_sale_requests
                    SET picker_status = 'picked',
                        picker_problem_text = ?,
                        picker_meta_json = ?,
                        picked_at = IFNULL(picked_at, NOW()),
                        status = IF(status = 'new', 'in_progress', status),
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute(array($problemText, $metaJson, $requestId));
                $message = 'Подбор сохранен.';
            } elseif ($action === 'confirm_ship') {
                $db->beginTransaction();
                try {
                    $db->prepare("
                        UPDATE b24_sale_requests
                        SET picker_status = 'confirmed',
                            picker_problem_text = ?,
                            picker_meta_json = ?,
                            confirmed_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ")->execute(array($problemText, $metaJson, $requestId));

                    $comment = "Склад: подбор подтвержден и отправлен. Заявка #" . $requestId;
                    if ($problemText !== '') {
                        $comment .= ". Проблемы: " . $problemText;
                    }

                    $b24Response = sendToBitrix('crm.deal.update', array(
                        'id' => intval($request['b24_deal_id']),
                        'fields' => array(
                            'COMMENTS' => $comment
                        )
                    ));

                    if (!is_array($b24Response) || isset($b24Response['error'])) {
                        throw new Exception('Ошибка отправки в Б24: ' . (isset($b24Response['error_description']) ? $b24Response['error_description'] : 'unknown'));
                    }

                    $db->prepare("
                        UPDATE b24_sale_requests
                        SET picker_status = 'shipped',
                            status = 'completed',
                            shipped_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ")->execute(array($requestId));

                    $db->commit();
                    $message = 'Подтверждено и отправлено в Б24.';
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = $e->getMessage();
                }
            } elseif ($action === 'cancel_reserve') {
                $db->beginTransaction();
                try {
                    releaseRequestReserve($db, $requestId);
                    $db->prepare("
                        UPDATE b24_sale_requests
                        SET picker_status = 'cancelled',
                            status = 'cancelled',
                            picker_problem_text = ?,
                            picker_meta_json = ?,
                            cancelled_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ")->execute(array($problemText, $metaJson, $requestId));
                    $db->commit();
                    $message = 'Резерв снят, заявка отменена.';
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = $e->getMessage();
                }
            } elseif ($action === 'retry_deal_rows_sync') {
                $syncResult = pickerSyncDealRowsForRequest($db, $requestId, true);
                if (!empty($syncResult['ok'])) {
                    $message = 'Синк строк сделки с Б24 выполнен. Deal #' . intval($syncResult['b24_deal_id']);
                } else {
                    $stage = isset($syncResult['stage']) ? (string)$syncResult['stage'] : 'unknown';
                    $error = 'Ошибка синка строк сделки (' . $stage . ').';
                }
            } else {
                $error = 'Неизвестное действие.';
            }
        }
    }
}

$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';
$filterDealId = intval(isset($_GET['deal_id']) ? $_GET['deal_id'] : 0);

$allowedStatuses = array('new', 'picked', 'confirmed', 'shipped', 'cancelled');
$where = array();
$params = array();

if (in_array($filterStatus, $allowedStatuses, true)) {
    $where[] = "r.picker_status = ?";
    $params[] = $filterStatus;
}
if ($filterDate !== '') {
    $where[] = "DATE(r.created_at) = ?";
    $params[] = $filterDate;
}
if ($filterDealId > 0) {
    $where[] = "r.b24_deal_id = ?";
    $params[] = $filterDealId;
}

$sql = "
    SELECT r.*
    FROM b24_sale_requests r
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY FIELD(r.picker_status, 'new', 'picked', 'confirmed', 'shipped', 'cancelled'), r.updated_at DESC";

$requestsStmt = $db->prepare($sql);
$requestsStmt->execute($params);
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>
<main class="container">
    <h2>🧰 Рабочее место кладовщика</h2>
    <p class="text-muted">Очередь заказов из Б24 с карточками подбора и подтверждением отправки.</p>
    <div class="card">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a href="warehouse.php" class="btn btn-light btn-sm">Открыть склад</a>
            <a href="stock_operations.php" class="btn btn-light btn-sm">Складские операции</a>
            <a href="sell.php" class="btn btn-light btn-sm">Продажи из Б24 (отчет)</a>
            <a href="b24_sales.php" class="btn btn-light btn-sm">Тех.раздел Б24</a>
        </div>
        <p class="text-muted" style="margin-top:8px;">
            Основная работа кладовщика выполняется здесь. Тех.раздел Б24 нужен только для ручной отладки синка и редких сервисных операций.
        </p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>Фильтры очереди</h3>
        <form method="GET">
            <div class="form-row">
                <div class="form-group">
                    <label>Статус</label>
                    <select name="status">
                        <option value="">Все</option>
                        <?php foreach ($allowedStatuses as $status): ?>
                            <option value="<?= h($status) ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Дата создания</label>
                    <input type="date" name="date" value="<?= h($filterDate) ?>">
                </div>
                <div class="form-group">
                    <label>Deal ID</label>
                    <input type="number" name="deal_id" min="1" value="<?= $filterDealId > 0 ? intval($filterDealId) : '' ?>">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-light">Фильтровать</button>
                    <a href="warehouse_orders.php" class="btn btn-light">Сброс</a>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($requests)): ?>
        <div class="card"><p>Очередь пуста по текущим фильтрам.</p></div>
    <?php endif; ?>

    <?php foreach ($requests as $request): ?>
        <?php
        $requestId = intval($request['id']);
        $dealId = intval($request['b24_deal_id']);
        $linesStmt = $db->prepare("
            SELECT
                l.*,
                COALESCE((SELECT SUM(c.meters) FROM b24_sale_line_cuts c WHERE c.line_id = l.id), 0) as allocated_m
            FROM b24_sale_lines l
            WHERE l.request_id = ?
            ORDER BY l.id ASC
        ");
        $linesStmt->execute(array($requestId));
        $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

        $lineProductIds = array();
        foreach ($lines as $lineItem) {
            $pid = intval(isset($lineItem['product_id']) ? $lineItem['product_id'] : 0);
            if ($pid > 0) {
                $lineProductIds[$pid] = $pid;
            }
        }

        $productFinanceMap = array();
        if (!empty($lineProductIds)) {
            $idsSql = implode(',', array_map('intval', array_values($lineProductIds)));
            $financeSql = "
                SELECT
                    p.id as product_id,
                    p.min_margin_percent,
                    COALESCE(AVG(CASE WHEN r.current_length > 0 THEN r.cost_per_meter END), 0) as avg_cost_per_meter
                FROM products p
                LEFT JOIN rolls r ON r.product_id = p.id
                    AND r.status NOT IN ('sold','written_off','waste')
                    AND r.current_length > 0
                WHERE p.id IN ($idsSql)
                GROUP BY p.id, p.min_margin_percent
            ";
            $financeRows = $db->query($financeSql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($financeRows as $fr) {
                $productFinanceMap[intval($fr['product_id'])] = array(
                    'avg_cost_per_meter' => floatval(isset($fr['avg_cost_per_meter']) ? $fr['avg_cost_per_meter'] : 0),
                    'min_margin_percent' => floatval(isset($fr['min_margin_percent']) ? $fr['min_margin_percent'] : 0)
                );
            }
        }

        $problems = array();
        foreach ($lines as $line) {
            $need = floatval($line['quantity_m']);
            $allocated = floatval($line['allocated_m']);
            if ($allocated + 0.001 < $need) {
                $problems[] = 'Недобор по товару "' . $line['product_name'] . '": нужно ' . round($need, 2) . ' м, собрано ' . round($allocated, 2) . ' м.';
            }
            $productId = intval(isset($line['product_id']) ? $line['product_id'] : 0);
            $pricePerUnit = floatval(isset($line['price_per_unit']) ? $line['price_per_unit'] : 0);
            $avgCost = isset($productFinanceMap[$productId]) ? floatval($productFinanceMap[$productId]['avg_cost_per_meter']) : 0;
            $minMargin = isset($productFinanceMap[$productId]) ? floatval($productFinanceMap[$productId]['min_margin_percent']) : 0;
            if ($pricePerUnit > 0 && $avgCost > 0) {
                $marginPercent = (($pricePerUnit - $avgCost) / $pricePerUnit) * 100;
                if ($marginPercent < $minMargin) {
                    $problems[] = 'Маржа ниже порога по "' . $line['product_name'] . '": текущая '
                        . round($marginPercent, 2) . '% при минимуме ' . round($minMargin, 2) . '%.';
                }
            }
        }

        $statusClass = 'status-active';
        if ($request['picker_status'] === 'cancelled') {
            $statusClass = 'status-sold';
        } elseif ($request['picker_status'] === 'picked' || $request['picker_status'] === 'confirmed') {
            $statusClass = 'status-cut';
        }
        ?>
        <div class="card" style="margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;">Сделка #<?= $dealId ?> / заявка #<?= $requestId ?></h3>
                    <div class="text-muted"><?= h($request['deal_name']) ?> | Ответственный: <?= h($request['responsible']) ?></div>
                </div>
                <div>
                    <span class="<?= $statusClass ?>">Статус: <?= h($request['picker_status']) ?></span>
                    <?php if (isset($request['deal_rows_sync_status'])): ?>
                        <span class="status-cut" style="margin-left:6px;">
                            Синк строк: <?= h((string)$request['deal_rows_sync_status']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <h4 style="margin-top:12px;">Строки товаров</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Нужно, м</th>
                        <th>Собрано, м</th>
                        <th>Цена</th>
                        <th>Себес./м (ср.)</th>
                        <th>Маржа</th>
                        <th>Статус строки</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): ?>
                        <?php
                            $productId = intval(isset($line['product_id']) ? $line['product_id'] : 0);
                            $linePrice = floatval(isset($line['price_per_unit']) ? $line['price_per_unit'] : 0);
                            $avgCost = isset($productFinanceMap[$productId]) ? floatval($productFinanceMap[$productId]['avg_cost_per_meter']) : 0;
                            $minMargin = isset($productFinanceMap[$productId]) ? floatval($productFinanceMap[$productId]['min_margin_percent']) : 0;
                            $marginPercent = ($linePrice > 0 && $avgCost > 0) ? (($linePrice - $avgCost) / $linePrice) * 100 : 0;
                            $marginWarn = ($linePrice > 0 && $avgCost > 0 && $marginPercent < $minMargin);
                        ?>
                        <tr>
                            <td><?= h($line['product_name']) ?></td>
                            <td><?= round(floatval($line['quantity_m']), 2) ?></td>
                            <td><?= round(floatval($line['allocated_m']), 2) ?></td>
                            <td><?= round(floatval($line['price_per_unit']), 2) ?></td>
                            <td><?= round($avgCost, 2) ?></td>
                            <td>
                                <?= round($marginPercent, 2) ?>%
                                <?php if ($marginWarn): ?>
                                    <span class="status-sold" style="margin-left:6px;">ниже порога <?= round($minMargin, 2) ?>%</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($line['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h4>Блок проблем</h4>
            <?php if (!empty($problems)): ?>
                <div class="alert alert-danger">
                    <ul style="margin:0; padding-left:20px;">
                        <?php foreach ($problems as $problem): ?>
                            <li><?= h($problem) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="alert alert-success">Автоматических проблем не найдено.</div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="request_id" value="<?= $requestId ?>">
                <div class="form-group">
                    <label>Комментарий кладовщика / проблемы</label>
                    <textarea name="problem_text" rows="3"><?= h($request['picker_problem_text']) ?></textarea>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn btn-light" type="submit" name="action" value="save_pick">Сохранить подбор</button>
                    <button class="btn btn-warning" type="submit" name="action" value="retry_deal_rows_sync">Повторить синк строк сделки</button>
                    <button class="btn btn-success" type="submit" name="action" value="confirm_ship" onclick="return confirm('Подтвердить и отправить в Б24?');">Подтвердить и отправить в Б24</button>
                    <button class="btn btn-danger" type="submit" name="action" value="cancel_reserve" onclick="return confirm('Снять резерв и отменить заявку?');">Отменить резерв</button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
