<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/db.php';
require_once __DIR__ . '/api/bitrix/send.php';
require_once __DIR__ . '/functions/deal_rows_sync_service.php';
require_once __DIR__ . '/functions/sale_order_allocations.php';
require_once __DIR__ . '/functions/prg_flash.php';

$db = getDB();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$message = '';
$error = '';
$page_title = 'Рабочее место кладовщика';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function bitrixDealUrlById($dealId) {
    $id = intval($dealId);
    if ($id <= 0) {
        return '';
    }
    return 'https://llumar.bitrix24.kz/crm/deal/details/' . $id . '/';
}

function responsibleLabel($rawResponsible) {
    $raw = trim((string)$rawResponsible);
    if ($raw === '') {
        return '';
    }
    if (!preg_match('/^User\s+(\d+)$/i', $raw, $m)) {
        return $raw;
    }

    $uid = intval($m[1]);
    if ($uid <= 0) {
        return $raw;
    }

    static $cache = array();
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }

    $label = 'User ' . $uid;
    $resp = sendToBitrix('user.get', array(
        'FILTER' => array('ID' => $uid)
    ));
    if (is_array($resp) && isset($resp['result']) && is_array($resp['result']) && !empty($resp['result'][0])) {
        $u = $resp['result'][0];
        $parts = array();
        if (!empty($u['NAME'])) {
            $parts[] = trim((string)$u['NAME']);
        }
        if (!empty($u['LAST_NAME'])) {
            $parts[] = trim((string)$u['LAST_NAME']);
        }
        $fullName = trim(implode(' ', $parts));
        if ($fullName !== '') {
            $label .= ' (' . $fullName . ')';
        }
    }

    $cache[$uid] = $label;
    return $label;
}

function pickerStatusLabel($status) {
    $labels = array(
        'new' => 'Новая',
        'picked' => 'В подборе',
        'confirmed' => 'Одобрено',
        'shipped' => 'Отгружено',
        'cancelled' => 'Отклонено'
    );
    return isset($labels[$status]) ? $labels[$status] : (string)$status;
}

function pickerLineStatusLabel($status) {
    $labels = array(
        'new' => 'Новая',
        'in_progress' => 'В подборе',
        'completed' => 'Списана'
    );
    return isset($labels[$status]) ? $labels[$status] : (string)$status;
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

            if ($action === 'add_manual_cut') {
                $lineId = intval(isset($_POST['line_id']) ? $_POST['line_id'] : 0);
                $rollId = intval(isset($_POST['roll_id']) ? $_POST['roll_id'] : 0);
                $meters = 0.0;
                if (isset($_POST['quick_meters']) && floatval($_POST['quick_meters']) > 0) {
                    $meters = floatval($_POST['quick_meters']);
                } elseif (isset($_POST['meters'])) {
                    $meters = floatval($_POST['meters']);
                }

                if ($lineId <= 0 || $rollId <= 0 || $meters <= 0) {
                    $error = 'Неверные данные для добавления куска.';
                } elseif ((string)$request['status'] === 'completed' || (string)$request['picker_status'] === 'shipped') {
                    $error = 'Заявка уже отгружена или завершена.';
                } else {
                    ensureOrderAllocationsTable($db);
                    $db->beginTransaction();
                    try {
                        $lineStmt = $db->prepare("
                            SELECT l.*, r.b24_deal_id
                            FROM b24_sale_lines l
                            JOIN b24_sale_requests r ON r.id = l.request_id
                            WHERE l.id = ? AND l.request_id = ?
                            FOR UPDATE
                        ");
                        $lineStmt->execute(array($lineId, $requestId));
                        $line = $lineStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$line) {
                            throw new Exception('Строка заявки не найдена.');
                        }
                        if ((string)$line['status'] === 'completed') {
                            throw new Exception('Строка уже списана.');
                        }

                        $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id = ? FOR UPDATE");
                        $rollStmt->execute(array($rollId));
                        $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$roll) {
                            throw new Exception('Рулон не найден.');
                        }
                        if (intval($line['product_id']) !== intval($roll['product_id'])) {
                            throw new Exception('Выбран рулон другого товара.');
                        }
                        if (in_array((string)$roll['status'], array('sold', 'written_off', 'waste'), true) || floatval($roll['current_length']) <= 0) {
                            throw new Exception('Этот рулон недоступен для подбора.');
                        }

                        $dealIdForLine = intval($line['b24_deal_id']);
                        $sameDeal = intval($roll['reserved']) === 1 && intval($roll['deal_id']) === $dealIdForLine;
                        if (intval($roll['reserved']) === 1 && !$sameDeal) {
                            throw new Exception('Рулон уже зарезервирован под другую сделку.');
                        }
                        $available = $sameDeal
                            ? (floatval($roll['current_length']) - floatval($roll['reserved_length']))
                            : floatval($roll['current_length']);
                        if ($meters > $available + 0.0001) {
                            throw new Exception('Недостаточно доступных метров в выбранном рулоне.');
                        }

                        allocateRollToDeal($db, $dealIdForLine, $rollId, $meters);
                        $price = floatval(isset($line['price_per_unit']) ? $line['price_per_unit'] : 0);
                        $db->prepare("
                            INSERT INTO order_allocations
                            (sale_request_id, deal_id, product_id, roll_id, allocated_m, allocated_rolls, price_per_unit, line_total, source, created_at)
                            VALUES (?, ?, ?, ?, ?, 0, ?, ?, 'manual', NOW())
                        ")->execute(array($requestId, $dealIdForLine, intval($line['product_id']), $rollId, $meters, $price, $meters * $price));
                        $db->prepare("
                            INSERT INTO b24_sale_line_cuts (line_id, roll_id, meters, created_at)
                            VALUES (?, ?, ?, NOW())
                        ")->execute(array($lineId, $rollId, $meters));
                        $db->prepare("UPDATE b24_sale_lines SET status = 'in_progress' WHERE id = ? AND status = 'new'")
                            ->execute(array($lineId));
                        $db->prepare("
                            UPDATE b24_sale_requests
                            SET picker_status = IF(picker_status IN ('new', 'cancelled'), 'picked', picker_status),
                                picked_at = IFNULL(picked_at, NOW()),
                                status = IF(status IN ('new', 'cancelled'), 'in_progress', status),
                                picker_meta_json = ?,
                                cancelled_at = NULL,
                                updated_at = NOW()
                            WHERE id = ?
                        ")->execute(array($metaJson, $requestId));

                        $db->commit();
                        $message = 'Кусок добавлен в подбор.';
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $error = $e->getMessage();
                    }
                }
            } elseif ($action === 'remove_manual_cut') {
                $cutId = intval(isset($_POST['cut_id']) ? $_POST['cut_id'] : 0);
                if ($cutId <= 0) {
                    $error = 'Некорректный cut_id.';
                } elseif ((string)$request['status'] === 'completed' || (string)$request['picker_status'] === 'shipped') {
                    $error = 'Заявка уже отгружена или завершена.';
                } else {
                    ensureOrderAllocationsTable($db);
                    $db->beginTransaction();
                    try {
                        $cutStmt = $db->prepare("
                            SELECT c.*, l.product_id, l.request_id, l.status as line_status, r.b24_deal_id
                            FROM b24_sale_line_cuts c
                            JOIN b24_sale_lines l ON l.id = c.line_id
                            JOIN b24_sale_requests r ON r.id = l.request_id
                            WHERE c.id = ? AND l.request_id = ?
                            FOR UPDATE
                        ");
                        $cutStmt->execute(array($cutId, $requestId));
                        $cut = $cutStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$cut) {
                            throw new Exception('Кусок не найден.');
                        }
                        if ((string)$cut['line_status'] === 'completed') {
                            throw new Exception('Строка уже списана.');
                        }

                        releaseRollReservation($db, intval($cut['b24_deal_id']), intval($cut['roll_id']), floatval($cut['meters']));

                        $allocStmt = $db->prepare("
                            SELECT id
                            FROM order_allocations
                            WHERE sale_request_id = ?
                              AND product_id = ?
                              AND roll_id = ?
                              AND ABS(allocated_m - ?) < 0.0001
                            ORDER BY IF(source = 'manual', 0, 1), id DESC
                            LIMIT 1
                        ");
                        $allocStmt->execute(array($requestId, intval($cut['product_id']), intval($cut['roll_id']), floatval($cut['meters'])));
                        $alloc = $allocStmt->fetch(PDO::FETCH_ASSOC);
                        if ($alloc) {
                            $db->prepare("DELETE FROM order_allocations WHERE id = ?")->execute(array(intval($alloc['id'])));
                        }
                        $db->prepare("DELETE FROM b24_sale_line_cuts WHERE id = ?")->execute(array($cutId));

                        $sumStmt = $db->prepare("SELECT COALESCE(SUM(meters), 0) as allocated_m FROM b24_sale_line_cuts WHERE line_id = ?");
                        $sumStmt->execute(array(intval($cut['line_id'])));
                        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
                        $allocatedAfter = floatval(isset($sumRow['allocated_m']) ? $sumRow['allocated_m'] : 0);
                        $newLineStatus = $allocatedAfter > 0.0001 ? 'in_progress' : 'new';
                        $db->prepare("UPDATE b24_sale_lines SET status = ? WHERE id = ? AND status != 'completed'")
                            ->execute(array($newLineStatus, intval($cut['line_id'])));
                        $db->prepare("UPDATE b24_sale_requests SET updated_at = NOW(), picker_meta_json = ? WHERE id = ?")
                            ->execute(array($metaJson, $requestId));

                        $db->commit();
                        $message = 'Кусок убран из подбора.';
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $error = $e->getMessage();
                    }
                }
            } elseif ($action === 'save_pick') {
                $db->prepare("
                    UPDATE b24_sale_requests
                    SET picker_status = 'picked',
                        picker_problem_text = ?,
                        picker_meta_json = ?,
                        picked_at = IFNULL(picked_at, NOW()),
                        status = IF(status IN ('new', 'cancelled'), 'in_progress', status),
                        cancelled_at = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute(array($problemText, $metaJson, $requestId));
                $message = 'Подбор сохранен.';
            } elseif ($action === 'approve_pick' || $action === 'reject_pick') {
                $isApprove = ($action === 'approve_pick');
                $triggerWord = $isApprove ? 'Отгрузить' : 'Отклонить';
                $comment = 'Склад: ' . $triggerWord . '. Заявка #' . $requestId;
                if ($problemText !== '') {
                    $comment .= '. Комментарий: ' . $problemText;
                }

                $b24Response = sendToBitrix('crm.deal.update', array(
                    'id' => intval($request['b24_deal_id']),
                    'fields' => array(
                        'COMMENTS' => $comment
                    )
                ));

                if (!is_array($b24Response) || isset($b24Response['error'])) {
                    $error = 'Ошибка отправки в Б24: ' . (isset($b24Response['error_description']) ? $b24Response['error_description'] : 'unknown');
                } else {
                    $db->prepare("
                        UPDATE b24_sale_requests
                        SET picker_status = ?,
                            picker_problem_text = ?,
                            picker_meta_json = ?,
                            confirmed_at = IF(? = 'confirmed', NOW(), confirmed_at),
                            updated_at = NOW()
                        WHERE id = ?
                    ")->execute(array(
                        $isApprove ? 'confirmed' : 'picked',
                        $problemText,
                        $metaJson,
                        $isApprove ? 'confirmed' : 'picked',
                        $requestId
                    ));
                    $message = $isApprove
                        ? 'Отгрузка подтверждена и комментарий отправлен в Б24.'
                        : 'Отклонение отправлено в Б24.';
                }
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
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = $e->getMessage();
                }
            } elseif ($action === 'cancel_reserve') {
                ensureOrderAllocationsTable($db);
                $db->beginTransaction();
                try {
                    // Снимаем резерв через order_allocations + rolls (источник истины для автоподбора).
                    releaseRequestAllocations($db, $requestId, null);
                    $db->prepare("
                        DELETE c FROM b24_sale_line_cuts c
                        INNER JOIN b24_sale_lines l ON l.id = c.line_id
                        WHERE l.request_id = ?
                    ")->execute(array($requestId));
                    $db->prepare("
                        UPDATE b24_sale_lines
                        SET status = 'new'
                        WHERE request_id = ? AND status != 'completed'
                    ")->execute(array($requestId));
                    $dealIdRow = intval(isset($request['b24_deal_id']) ? $request['b24_deal_id'] : 0);
                    if ($dealIdRow > 0) {
                        $db->prepare("
                            UPDATE rolls
                            SET reserved = 0, deal_id = NULL, reserved_length = 0
                            WHERE deal_id = ?
                        ")->execute(array($dealIdRow));
                    }
                    $db->prepare("
                        UPDATE b24_sale_requests
                        SET picker_status = 'new',
                            status = 'new',
                            picker_problem_text = ?,
                            picker_meta_json = ?,
                            cancelled_at = NULL,
                            updated_at = NOW()
                        WHERE id = ?
                    ")->execute(array($problemText, $metaJson, $requestId));
                    $db->commit();
                    $message = 'Резерв снят, заявка возвращена в подбор.';
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = $e->getMessage();
                }
            } elseif ($action === 'delete_request') {
                ensureOrderAllocationsTable($db);
                $db->beginTransaction();
                try {
                    $dealIdRow = intval(isset($request['b24_deal_id']) ? $request['b24_deal_id'] : 0);
                    if ($dealIdRow > 0) {
                        releaseRequestAllocations($db, $requestId, null);
                        $db->prepare("
                            UPDATE rolls
                            SET reserved = 0, deal_id = NULL, reserved_length = 0
                            WHERE deal_id = ?
                        ")->execute(array($dealIdRow));
                    }

                    $db->prepare("
                        DELETE c FROM b24_sale_line_cuts c
                        INNER JOIN b24_sale_lines l ON l.id = c.line_id
                        WHERE l.request_id = ?
                    ")->execute(array($requestId));
                    $db->prepare("DELETE FROM order_allocations WHERE sale_request_id = ?")
                        ->execute(array($requestId));
                    $db->prepare("DELETE FROM b24_sale_lines WHERE request_id = ?")
                        ->execute(array($requestId));
                    $db->prepare("DELETE FROM b24_sale_requests WHERE id = ?")
                        ->execute(array($requestId));

                    $db->commit();
                    $message = 'Заявка удалена.';
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    prgFlashCommitAndRedirect303(
        'warehouse_orders.php',
        array(
            'success' => $message,
            'error' => $error,
        )
    );
}

$__woFlash = prgFlashConsume();
if (!empty($__woFlash['error'])) {
    $error = $__woFlash['error'];
    $message = '';
} elseif (!empty($__woFlash['success'])) {
    $message = $__woFlash['success'];
}

$filterStatusRaw = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';
$filterDealId = intval(isset($_GET['deal_id']) ? $_GET['deal_id'] : 0);

$allowedStatuses = array('new', 'picked', 'confirmed', 'shipped', 'cancelled');
$filterVisibleStatuses = array('new', 'picked', 'shipped', 'cancelled');
$where = array();
$params = array();

if (in_array($filterStatusRaw, $allowedStatuses, true)) {
    $where[] = "r.picker_status = ?";
    $params[] = $filterStatusRaw;
} elseif ($filterStatusRaw !== 'all') {
    // По умолчанию — только активная очередь (без отменённых и завершённых).
    $where[] = "r.status NOT IN ('cancelled', 'completed')";
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
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>Фильтры очереди</h3>
        <form method="GET" class="warehouse-orders-filter-form">
            <div class="warehouse-orders-filter-row">
                <div class="form-group">
                    <label>Статус</label>
                    <select name="status">
                        <option value="" <?= $filterStatusRaw === '' ? 'selected' : '' ?>>Активные</option>
                        <option value="all" <?= $filterStatusRaw === 'all' ? 'selected' : '' ?>>Все заявки</option>
                        <?php foreach ($filterVisibleStatuses as $status): ?>
                            <option value="<?= h($status) ?>" <?= $filterStatusRaw === $status ? 'selected' : '' ?>><?= h(pickerStatusLabel($status)) ?></option>
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
                    <div class="warehouse-orders-filter-actions">
                        <button type="submit" class="btn btn-light">Фильтровать</button>
                        <a href="warehouse_orders.php" class="btn btn-light">Сброс</a>
                    </div>
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
                    <h3 style="margin:0;">
                        <a href="<?= h(bitrixDealUrlById($dealId)) ?>" target="_blank" rel="noopener">Сделка #<?= $dealId ?></a>
                        / заявка #<?= $requestId ?>
                    </h3>
                    <div class="text-muted"><?= h($request['deal_name']) ?> | Ответственный: <?= h(responsibleLabel($request['responsible'])) ?></div>
                </div>
                <div>
                    <span class="<?= $statusClass ?>">Статус: <?= h(pickerStatusLabel((string)$request['picker_status'])) ?></span>
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
                            <td><?= h(pickerLineStatusLabel((string)$line['status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h4>Ручной подбор рулонов</h4>
            <div class="picker-lines">
                <?php foreach ($lines as $line): ?>
                    <?php
                        $lineId = intval($line['id']);
                        $lineProductId = intval(isset($line['product_id']) ? $line['product_id'] : 0);
                        $needMeters = floatval($line['quantity_m']);
                        $allocatedMeters = floatval($line['allocated_m']);
                        $remainingMeters = max(0, $needMeters - $allocatedMeters);
                        $lineComplete = (string)$line['status'] === 'completed';
                        $requestClosed = (string)$request['status'] === 'completed'
                            || (string)$request['picker_status'] === 'shipped';
                        $canEditPick = !$lineComplete && !$requestClosed && $lineProductId > 0;

                        $rollStmt = $db->prepare("
                            SELECT *
                            FROM rolls
                            WHERE product_id = ?
                              AND current_length > 0
                              AND status NOT IN ('sold','written_off','waste')
                              AND (
                                    reserved = 0
                                    OR (reserved = 1 AND deal_id = ?)
                              )
                            ORDER BY current_length ASC, id ASC
                        ");
                        $rollStmt->execute(array($lineProductId, $dealId));
                        $lineRolls = $rollStmt->fetchAll(PDO::FETCH_ASSOC);

                        $cutsStmt = $db->prepare("
                            SELECT c.*, r.current_length, r.status as roll_status
                            FROM b24_sale_line_cuts c
                            LEFT JOIN rolls r ON r.id = c.roll_id
                            WHERE c.line_id = ?
                            ORDER BY c.id DESC
                        ");
                        $cutsStmt->execute(array($lineId));
                        $cuts = $cutsStmt->fetchAll(PDO::FETCH_ASSOC);
                        $quickMeters = array(0.4, 2, 5, 10, 15, 30);
                    ?>
                    <details class="picker-line-panel" <?= !$lineComplete ? 'open' : '' ?>>
                        <summary class="picker-line-summary">
                            <span class="picker-line-name"><?= h($line['product_name']) ?></span>
                            <span>нужно <?= round($needMeters, 2) ?> м</span>
                            <span>собрано <?= round($allocatedMeters, 2) ?> м</span>
                            <span class="<?= $remainingMeters > 0.001 ? 'status-sold' : 'status-active' ?>">
                                <?= $remainingMeters > 0.001 ? ('осталось ' . round($remainingMeters, 2) . ' м') : 'закрыто по метражу' ?>
                            </span>
                        </summary>

                        <?php if (!empty($cuts)): ?>
                            <div class="picker-selected-cuts">
                                <div class="picker-subtitle">Уже выбрано</div>
                                <div class="picker-cut-list">
                                    <?php foreach ($cuts as $cut): ?>
                                        <div class="picker-cut-chip">
                                            <span>#<?= intval($cut['roll_id']) ?> · <?= round(floatval($cut['meters']), 2) ?> м</span>
                                            <?php if ($canEditPick): ?>
                                                <form method="POST" class="picker-inline-form">
                                                    <input type="hidden" name="request_id" value="<?= $requestId ?>">
                                                    <input type="hidden" name="action" value="remove_manual_cut">
                                                    <input type="hidden" name="cut_id" value="<?= intval($cut['id']) ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Убрать</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($canEditPick && $remainingMeters <= 0.001): ?>
                            <div class="alert alert-success">Метраж по строке собран. Чтобы заменить рулон или кусок, сначала уберите выбранный кусок.</div>
                        <?php elseif ($canEditPick): ?>
                            <?php if (empty($lineRolls)): ?>
                                <div class="alert alert-warning">Нет доступных рулонов или обрезков этого товара.</div>
                            <?php else: ?>
                                <div class="picker-roll-grid">
                                    <?php foreach ($lineRolls as $roll): ?>
                                        <?php
                                            $rollReserved = floatval(isset($roll['reserved_length']) ? $roll['reserved_length'] : 0);
                                            $rollCurrent = floatval(isset($roll['current_length']) ? $roll['current_length'] : 0);
                                            $sameDeal = intval($roll['reserved']) === 1 && intval($roll['deal_id']) === $dealId;
                                            $availableMeters = $sameDeal ? ($rollCurrent - $rollReserved) : $rollCurrent;
                                            $availableMeters = max(0, $availableMeters);
                                            if ($availableMeters <= 0.0001) {
                                                continue;
                                            }
                                            $suggestMeters = $remainingMeters > 0
                                                ? min($remainingMeters, $availableMeters)
                                                : min($availableMeters, $rollCurrent);
                                        ?>
                                        <div class="picker-roll-item">
                                            <div class="picker-roll-head">
                                                <strong>Рулон #<?= intval($roll['id']) ?></strong>
                                                <span class="badge <?= $rollCurrent < floatval($roll['original_length']) ? 'badge-warning' : 'badge-success' ?>">
                                                    <?= $rollCurrent < floatval($roll['original_length']) ? 'обрезок' : 'целый' ?>
                                                </span>
                                            </div>
                                            <div class="picker-roll-meta">
                                                <span>на рулоне <?= round($rollCurrent, 2) ?> м</span>
                                                <span>доступно <?= round($availableMeters, 2) ?> м</span>
                                                <?php if ($rollReserved > 0): ?>
                                                    <span>резерв <?= round($rollReserved, 2) ?> м</span>
                                                <?php endif; ?>
                                            </div>
                                            <form method="POST" class="picker-add-form">
                                                <input type="hidden" name="request_id" value="<?= $requestId ?>">
                                                <input type="hidden" name="action" value="add_manual_cut">
                                                <input type="hidden" name="line_id" value="<?= $lineId ?>">
                                                <input type="hidden" name="roll_id" value="<?= intval($roll['id']) ?>">
                                                <div class="picker-meter-row">
                                                    <input type="number" name="meters" min="0.1" max="<?= h(round($availableMeters, 2)) ?>" step="0.1" value="<?= h(round($suggestMeters, 2)) ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">Добавить</button>
                                                </div>
                                                <div class="picker-quick-row">
                                                    <?php foreach ($quickMeters as $quick): ?>
                                                        <?php if ($availableMeters + 0.0001 >= $quick): ?>
                                                            <button type="submit" name="quick_meters" value="<?= h($quick) ?>" class="btn btn-light btn-sm">+<?= h($quick) ?> м</button>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-muted">Подбор недоступен: заявка уже отгружена, завершена или строка списана.</div>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($problems)): ?>
                <h4>Блок проблем</h4>
                <div class="alert alert-danger">
                    <ul style="margin:0; padding-left:20px;">
                        <?php foreach ($problems as $problem): ?>
                            <li><?= h($problem) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="request_id" value="<?= $requestId ?>">
                <div class="form-group">
                    <label>Комментарий кладовщика / причина отклонения</label>
                    <textarea name="problem_text" rows="3" placeholder="Например: не могу предоставить данный товар, предлагаю аналог..."><?= h($request['picker_problem_text']) ?></textarea>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button class="btn btn-light" type="submit" name="action" value="save_pick">Сохранить комментарий</button>
                        <button class="btn btn-success" type="submit" name="action" value="approve_pick" onclick="return confirm('Отправить в Б24 триггер Отгрузить?');">Отгрузить</button>
                        <button class="btn btn-warning" type="submit" name="action" value="reject_pick" onclick="return confirm('Отправить в Б24 триггер Отклонить?');">Отклонить</button>
                        <button class="btn btn-danger" type="submit" name="action" value="cancel_reserve" onclick="return confirm('Снять резерв по заявке? Сделка в Б24 не будет закрыта.');">Снять резерв</button>
                    </div>
                    <button
                        class="btn btn-danger btn-sm"
                        type="submit"
                        name="action"
                        value="delete_request"
                        onclick="return confirm('Удалить заявку полностью? Будут удалены строки и подбор по этой заявке. Действие необратимо.');"
                    >Удалить заявку</button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
(function () {
    var form = document.querySelector('.warehouse-orders-filter-form');
    if (!form) {
        return;
    }
    var statusSelect = form.querySelector('select[name="status"]');
    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            form.submit();
        });
    }
})();
</script>
