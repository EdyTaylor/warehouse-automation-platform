<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/db.php';
require_once __DIR__ . '/functions/stock_movements.php';
require_once __DIR__ . '/functions/app_settings.php';
require_once __DIR__ . '/functions/integration_sync_control.php';
require_once __DIR__ . '/functions/stock_emergency_kill.php';
require_once __DIR__ . '/api/bitrix/send.php';

$db = getDB();
$message = '';
$error = '';
$page_title = 'Продажи Б24';
require __DIR__ . '/includes/header.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function ensureColumnExists($db, $tableName, $columnName, $columnSql) {
    $stmt = $db->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
    $stmt->execute([$columnName]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $db->exec("ALTER TABLE `{$tableName}` ADD COLUMN {$columnSql}");
    }
}

function ensureDealRowsSyncSchema($db) {
    ensureColumnExists($db, 'b24_sale_requests', 'deal_rows_sync_status', "`deal_rows_sync_status` varchar(20) NOT NULL DEFAULT 'pending'");
    ensureColumnExists($db, 'b24_sale_requests', 'deal_rows_sync_stage', "`deal_rows_sync_stage` varchar(64) DEFAULT NULL");
    ensureColumnExists($db, 'b24_sale_requests', 'deal_rows_sync_error', "`deal_rows_sync_error` text");
    ensureColumnExists($db, 'b24_sale_requests', 'deal_rows_sync_payload', "`deal_rows_sync_payload` longtext");
    ensureColumnExists($db, 'b24_sale_requests', 'deal_rows_sync_last_response', "`deal_rows_sync_last_response` longtext");
    ensureColumnExists($db, 'b24_sale_requests', 'deal_rows_sync_last_hash', "`deal_rows_sync_last_hash` varchar(64) DEFAULT NULL");
    ensureColumnExists($db, 'b24_sale_requests', 'deal_rows_sync_attempts', "`deal_rows_sync_attempts` int NOT NULL DEFAULT 0");
    ensureColumnExists($db, 'b24_sale_requests', 'deal_rows_sync_attempted_at', "`deal_rows_sync_attempted_at` datetime DEFAULT NULL");
    ensureColumnExists($db, 'b24_sale_requests', 'deal_rows_synced_at', "`deal_rows_synced_at` datetime DEFAULT NULL");
    ensureColumnExists($db, 'b24_sale_requests', 'deal_rows_verified_at', "`deal_rows_verified_at` datetime DEFAULT NULL");
    ensureColumnExists($db, 'rolls', 'receipt_doc_id', "`receipt_doc_id` int DEFAULT NULL");
    ensureColumnExists($db, 'rolls', 'cost_per_meter', "`cost_per_meter` decimal(14,4) NOT NULL DEFAULT 0");
    ensureColumnExists($db, 'sales', 'cost_fact', "`cost_fact` decimal(14,2) NOT NULL DEFAULT 0");
    ensureColumnExists($db, 'sales', 'gross_profit', "`gross_profit` decimal(14,2) NOT NULL DEFAULT 0");
    ensureColumnExists($db, 'sales', 'gross_margin_percent', "`gross_margin_percent` decimal(8,2) NOT NULL DEFAULT 0");

    $db->exec("
        CREATE TABLE IF NOT EXISTS b24_integration_errors (
            id int NOT NULL AUTO_INCREMENT,
            source varchar(64) NOT NULL,
            request_id int DEFAULT NULL,
            b24_deal_id int DEFAULT NULL,
            stage varchar(64) DEFAULT NULL,
            error_code varchar(190) DEFAULT NULL,
            error_description text,
            context_payload longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_b24_integration_errors_deal (b24_deal_id, created_at),
            KEY idx_b24_integration_errors_request (request_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensureSyncConflictSchema($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS b24_sync_conflicts (
            id int NOT NULL AUTO_INCREMENT,
            conflict_type varchar(50) NOT NULL,
            b24_product_id int DEFAULT NULL,
            local_product_id int DEFAULT NULL,
            local_value decimal(14,2) DEFAULT NULL,
            b24_value decimal(14,2) DEFAULT NULL,
            details text,
            status varchar(20) NOT NULL DEFAULT 'new',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_conflict_status (status, created_at),
            KEY idx_conflict_product (b24_product_id, local_product_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function resolveConflictStatus($db, $conflictId, $status, $detailsSuffix) {
    $db->prepare("
        UPDATE b24_sync_conflicts
        SET status = ?, details = CONCAT(COALESCE(details,''), ?), updated_at = NOW()
        WHERE id = ?
    ")->execute([$status, $detailsSuffix, $conflictId]);
}

function addMetersToLocalStock($db, $productId, $meters, $comment) {
    $emOff = stockEmergencyRollCreationStoppedMessage();
    $blockMsg = ($emOff !== '') ? $emOff : integrationStockRollCreationBlockedMessage($db);
    if ($blockMsg !== '') {
        throw new Exception($blockMsg);
    }
    $productStmt = $db->prepare("SELECT id, roll_length, purchase_price FROM products WHERE id = ? LIMIT 1");
    $productStmt->execute([$productId]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        throw new Exception('Товар для пополнения склада не найден.');
    }

    $rollLength = floatval(isset($product['roll_length']) ? $product['roll_length'] : 0);
    $purchasePrice = floatval(isset($product['purchase_price']) ? $product['purchase_price'] : 0);
    if ($rollLength <= 0) {
        throw new Exception('У товара не задана длина рулона.');
    }

    $toAdd = max(0, floatval($meters));
    if ($toAdd <= 0) {
        return 0;
    }

    $minFull = 0.5;
    $costPerMeter = $rollLength > 0 ? ($purchasePrice / $rollLength) : 0;
    $addedMeters = 0.0;
    $fullRolls = intval(floor($toAdd / $rollLength));
    $remainder = round($toAdd - ($fullRolls * $rollLength), 2);

    for ($i = 0; $i < $fullRolls; $i++) {
        $db->prepare("
            INSERT INTO rolls (product_id, original_length, current_length, min_full_length, status, cost_per_meter)
            VALUES (?, ?, ?, ?, 'active', ?)
        ")->execute([$productId, $rollLength, $rollLength, $minFull, $costPerMeter]);
        $rollId = intval($db->lastInsertId());
        logAndSyncMovement($db, [
            'product_id' => $productId,
            'roll_id' => $rollId,
            'movement_type' => 'receipt',
            'quantity_m' => $rollLength,
            'quantity_rolls' => 1,
            'price_per_unit' => $purchasePrice,
            'total' => $purchasePrice,
            'comment' => $comment
        ]);
        $addedMeters += $rollLength;
    }

    if ($remainder > 0.01) {
        $db->prepare("
            INSERT INTO rolls (product_id, original_length, current_length, min_full_length, status, cost_per_meter)
            VALUES (?, ?, ?, ?, 'cut', ?)
        ")->execute([$productId, $rollLength, $remainder, $minFull, $costPerMeter]);
        $rollId = intval($db->lastInsertId());
        logAndSyncMovement($db, [
            'product_id' => $productId,
            'roll_id' => $rollId,
            'movement_type' => 'receipt',
            'quantity_m' => $remainder,
            'quantity_rolls' => 1,
            'price_per_unit' => $purchasePrice,
            'total' => 0,
            'comment' => $comment . ' (частичный рулон)'
        ]);
        $addedMeters += $remainder;
    }

    return round($addedMeters, 2);
}

function buildConflictResolutionHint($conflict) {
    $type = isset($conflict['conflict_type']) ? (string)$conflict['conflict_type'] : '';
    $localVal = floatval(isset($conflict['local_value']) ? $conflict['local_value'] : 0);
    $b24Val = floatval(isset($conflict['b24_value']) ? $conflict['b24_value'] : 0);

    if ($type === 'stock_field_mismatch') {
        if ($b24Val > $localVal + 0.01) {
            return array(
                'warehouse_state' => 'На складе меньше, чем в Б24',
                'b24_state' => 'В Б24 остаток завышен',
                'recommended' => 'Обычно: "Выровнять Б24 по складу". Если Б24 корректен физически — "Добавить на склад (принять Б24)".'
            );
        }
        if ($localVal > $b24Val + 0.01) {
            return array(
                'warehouse_state' => 'На складе больше, чем в Б24',
                'b24_state' => 'В Б24 остаток занижен/нулевой',
                'recommended' => 'Обычно: "Выровнять Б24 по складу".'
            );
        }
        return array(
            'warehouse_state' => 'Остатки близки',
            'b24_state' => 'Остатки близки',
            'recommended' => 'Можно закрыть без изменений.'
        );
    }

    if ($type === 'price_mismatch') {
        if ($localVal > $b24Val + 0.01) {
            return array(
                'warehouse_state' => 'Цена в приложении выше',
                'b24_state' => 'Цена в Б24 ниже',
                'recommended' => 'Рекомендуется выравнивать Б24 по приложению (истина — склад/приложение).'
            );
        }
        if ($b24Val > $localVal + 0.01) {
            return array(
                'warehouse_state' => 'Цена в приложении ниже',
                'b24_state' => 'Цена в Б24 выше',
                'recommended' => 'Проверьте причину изменения, затем выровняйте в нужную сторону.'
            );
        }
    }

    return array(
        'warehouse_state' => 'Нужна проверка',
        'b24_state' => 'Нужна проверка',
        'recommended' => 'Откройте карточку товара и выберите действие вручную.'
    );
}

function logDealRowsSyncError($db, $requestId, $dealId, $stage, $response, $payload) {
    $errorCode = '';
    $errorDescription = '';
    if (is_array($response)) {
        $errorCode = isset($response['error']) ? (string)$response['error'] : '';
        $errorDescription = isset($response['error_description']) ? (string)$response['error_description'] : '';
    } else {
        $errorDescription = (string)$response;
    }
    $db->prepare("
        INSERT INTO b24_integration_errors
        (source, request_id, b24_deal_id, stage, error_code, error_description, context_payload, created_at)
        VALUES ('deal_productrows_sync', ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        $requestId > 0 ? $requestId : null,
        $dealId > 0 ? $dealId : null,
        (string)$stage,
        $errorCode,
        $errorDescription,
        json_encode([
            'payload' => $payload,
            'response' => $response
        ], JSON_UNESCAPED_UNICODE)
    ]);
}

function callBitrixWithRetry($method, $payload, $maxAttempts = 3, $sleepMs = 350) {
    $attempt = 0;
    $lastResp = null;
    while ($attempt < $maxAttempts) {
        $attempt++;
        $lastResp = sendToBitrix($method, $payload);
        if (is_array($lastResp) && !isset($lastResp['error'])) {
            return ['ok' => true, 'response' => $lastResp, 'attempts' => $attempt];
        }
        if ($attempt < $maxAttempts) {
            usleep(max(0, intval($sleepMs)) * 1000);
        }
    }
    return ['ok' => false, 'response' => $lastResp, 'attempts' => $attempt];
}

function normalizeDealProductRows($rows) {
    $normalized = [];
    if (!is_array($rows)) {
        return $normalized;
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $productId = intval(isset($row['PRODUCT_ID']) ? $row['PRODUCT_ID'] : (isset($row['productId']) ? $row['productId'] : 0));
        if ($productId <= 0) {
            continue;
        }
        $qty = floatval(isset($row['QUANTITY']) ? $row['QUANTITY'] : (isset($row['quantity']) ? $row['quantity'] : 0));
        $price = floatval(isset($row['PRICE']) ? $row['PRICE'] : (isset($row['price']) ? $row['price'] : 0));
        $total = floatval(isset($row['PRICE_EXCLUSIVE']) ? $row['PRICE_EXCLUSIVE'] : ($qty * $price));
        if (!isset($normalized[$productId])) {
            $normalized[$productId] = ['qty' => 0.0, 'price' => $price, 'total' => 0.0];
        }
        $normalized[$productId]['qty'] += $qty;
        $normalized[$productId]['price'] = $price;
        $normalized[$productId]['total'] += $total;
    }
    ksort($normalized);
    return $normalized;
}

function buildDealRowsPayloadForRequest($db, $requestId) {
    $stmt = $db->prepare("
        SELECT b24_product_id, product_name, quantity_m, price_per_unit
        FROM b24_sale_lines
        WHERE request_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$requestId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $payloadRows = [];
    foreach ($rows as $row) {
        $productId = intval(isset($row['b24_product_id']) ? $row['b24_product_id'] : 0);
        $qty = floatval(isset($row['quantity_m']) ? $row['quantity_m'] : 0);
        $price = floatval(isset($row['price_per_unit']) ? $row['price_per_unit'] : 0);
        if ($productId <= 0 || $qty <= 0) {
            continue;
        }
        $payloadRows[] = [
            'PRODUCT_ID' => $productId,
            'PRODUCT_NAME' => (string)(isset($row['product_name']) ? $row['product_name'] : ''),
            'PRICE' => round($price, 2),
            'QUANTITY' => round($qty, 2)
        ];
    }
    return $payloadRows;
}

function verifyDealRowsOnBitrix($expectedRows, $actualRows) {
    $expected = normalizeDealProductRows($expectedRows);
    $actual = normalizeDealProductRows($actualRows);
    if (count($expected) !== count($actual)) {
        return ['ok' => false, 'reason' => 'row_count_mismatch', 'expected' => $expected, 'actual' => $actual];
    }
    foreach ($expected as $productId => $exp) {
        if (!isset($actual[$productId])) {
            return ['ok' => false, 'reason' => 'missing_product', 'product_id' => $productId, 'expected' => $expected, 'actual' => $actual];
        }
        $act = $actual[$productId];
        if (abs($exp['qty'] - $act['qty']) > 0.01 || abs($exp['price'] - $act['price']) > 0.01 || abs($exp['total'] - $act['total']) > 0.05) {
            return [
                'ok' => false,
                'reason' => 'value_mismatch',
                'product_id' => $productId,
                'expected_row' => $exp,
                'actual_row' => $act
            ];
        }
    }
    return ['ok' => true, 'expected' => $expected, 'actual' => $actual];
}

function syncDealProductRowsForRequest($db, $requestId, $force = false) {
    $reqStmt = $db->prepare("
        SELECT id, b24_deal_id, deal_rows_sync_status, deal_rows_sync_last_hash
        FROM b24_sale_requests
        WHERE id = ?
        LIMIT 1
    ");
    $reqStmt->execute([$requestId]);
    $request = $reqStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        return ['ok' => false, 'stage' => 'request.load', 'error' => 'request_not_found'];
    }
    $dealId = intval($request['b24_deal_id']);
    if ($dealId <= 0) {
        return ['ok' => false, 'stage' => 'request.load', 'error' => 'invalid_deal_id'];
    }

    $rows = buildDealRowsPayloadForRequest($db, $requestId);
    if (empty($rows)) {
        return ['ok' => false, 'stage' => 'payload.build', 'error' => 'empty_rows'];
    }
    $payloadHash = hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE));

    if (!$force
        && (string)$request['deal_rows_sync_status'] === 'sent'
        && !empty($request['deal_rows_sync_last_hash'])
        && hash_equals((string)$request['deal_rows_sync_last_hash'], $payloadHash)
    ) {
        return ['ok' => true, 'stage' => 'idempotent.skip', 'idempotent' => true, 'b24_deal_id' => $dealId];
    }

    $db->prepare("
        UPDATE b24_sale_requests
        SET
            deal_rows_sync_status = 'in_progress',
            deal_rows_sync_stage = 'deal.productrows.set',
            deal_rows_sync_payload = ?,
            deal_rows_sync_last_hash = ?,
            deal_rows_sync_attempts = deal_rows_sync_attempts + 1,
            deal_rows_sync_attempted_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ")->execute([json_encode($rows, JSON_UNESCAPED_UNICODE), $payloadHash, $requestId]);

    $setPayload = ['id' => $dealId, 'rows' => $rows];
    $setResult = callBitrixWithRetry('crm.deal.productrows.set', $setPayload, 3, 450);
    if (!$setResult['ok']) {
        $response = $setResult['response'];
        $db->prepare("
            UPDATE b24_sale_requests
            SET deal_rows_sync_status='failed', deal_rows_sync_stage='deal.productrows.set', deal_rows_sync_error=?, deal_rows_sync_last_response=?, updated_at=NOW()
            WHERE id = ?
        ")->execute([
            is_array($response)
                ? (string)(isset($response['error_description']) ? $response['error_description'] : (isset($response['error']) ? $response['error'] : 'set_failed'))
                : 'set_failed',
            json_encode($response, JSON_UNESCAPED_UNICODE),
            $requestId
        ]);
        logDealRowsSyncError($db, $requestId, $dealId, 'deal.productrows.set', $response, $setPayload);
        return ['ok' => false, 'stage' => 'deal.productrows.set', 'response' => $response, 'b24_deal_id' => $dealId];
    }

    $getPayload = ['id' => $dealId];
    $getResult = callBitrixWithRetry('crm.deal.productrows.get', $getPayload, 3, 450);
    if (!$getResult['ok']) {
        $response = $getResult['response'];
        $db->prepare("
            UPDATE b24_sale_requests
            SET deal_rows_sync_status='failed', deal_rows_sync_stage='deal.productrows.get', deal_rows_sync_error=?, deal_rows_sync_last_response=?, deal_rows_synced_at = NOW(), updated_at=NOW()
            WHERE id = ?
        ")->execute([
            is_array($response)
                ? (string)(isset($response['error_description']) ? $response['error_description'] : (isset($response['error']) ? $response['error'] : 'get_failed'))
                : 'get_failed',
            json_encode($response, JSON_UNESCAPED_UNICODE),
            $requestId
        ]);
        logDealRowsSyncError($db, $requestId, $dealId, 'deal.productrows.get', $response, $getPayload);
        return ['ok' => false, 'stage' => 'deal.productrows.get', 'response' => $response, 'b24_deal_id' => $dealId];
    }

    $actualRows = [];
    if (is_array($getResult['response']) && isset($getResult['response']['result']) && is_array($getResult['response']['result'])) {
        $actualRows = $getResult['response']['result'];
    }
    $verify = verifyDealRowsOnBitrix($rows, $actualRows);
    if (!$verify['ok']) {
        $db->prepare("
            UPDATE b24_sale_requests
            SET deal_rows_sync_status='failed', deal_rows_sync_stage='deal.productrows.verify', deal_rows_sync_error=?, deal_rows_sync_last_response=?, deal_rows_synced_at = NOW(), updated_at=NOW()
            WHERE id = ?
        ")->execute([
            'verify_failed:' . (string)$verify['reason'],
            json_encode(['verify' => $verify, 'response' => $getResult['response']], JSON_UNESCAPED_UNICODE),
            $requestId
        ]);
        logDealRowsSyncError($db, $requestId, $dealId, 'deal.productrows.verify', $verify, ['expected_rows' => $rows]);
        return ['ok' => false, 'stage' => 'deal.productrows.verify', 'verify' => $verify, 'b24_deal_id' => $dealId];
    }

    $db->prepare("
        UPDATE b24_sale_requests
        SET
            deal_rows_sync_status='sent',
            deal_rows_sync_stage='done',
            deal_rows_sync_error=NULL,
            deal_rows_sync_last_response=?,
            deal_rows_synced_at=NOW(),
            deal_rows_verified_at=NOW(),
            updated_at=NOW()
        WHERE id = ?
    ")->execute([json_encode($getResult['response'], JSON_UNESCAPED_UNICODE), $requestId]);

    return [
        'ok' => true,
        'stage' => 'done',
        'b24_deal_id' => $dealId,
        'verify' => $verify,
        'set_attempts' => $setResult['attempts'],
        'get_attempts' => $getResult['attempts']
    ];
}

// If migrations are not applied yet, show readable message instead of HTTP 500.
try {
    $db->query("SELECT 1 FROM b24_sale_requests LIMIT 1");
    $db->query("SELECT 1 FROM b24_sale_lines LIMIT 1");
    $db->query("SELECT 1 FROM b24_sale_line_cuts LIMIT 1");
} catch (Exception $e) {
    echo '<h2>Продажи из Б24 (ручная реализация)</h2>';
    echo '<p style="color:red;">Страница временно недоступна: не применена миграция <code>migrations/003_b24_sales_manual_queue.sql</code>.</p>';
    echo '<p>Примени SQL миграцию в базе и обнови страницу.</p>';
    exit;
}
ensureDealRowsSyncSchema($db);
ensureSyncConflictSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'resolve_stock_conflict_bulk') {
        $selectedIds = isset($_POST['conflict_ids']) && is_array($_POST['conflict_ids']) ? $_POST['conflict_ids'] : array();
        $bulkMode = isset($_POST['bulk_mode']) ? trim($_POST['bulk_mode']) : '';
        $selectedIds = array_values(array_filter(array_map('intval', $selectedIds), function($v) { return $v > 0; }));

        if (empty($selectedIds)) {
            $error = 'Выберите хотя бы один конфликт для массового действия.';
        } elseif (!in_array($bulkMode, array('push_local_to_b24', 'accept_b24_to_local', 'dismiss'), true)) {
            $error = 'Выберите корректное массовое действие.';
        } else {
            $done = 0;
            $failed = 0;
            foreach ($selectedIds as $conflictId) {
                try {
                    $confStmt = $db->prepare("
                        SELECT *
                        FROM b24_sync_conflicts
                        WHERE id = ? AND status = 'new'
                        LIMIT 1
                    ");
                    $confStmt->execute([$conflictId]);
                    $conflict = $confStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$conflict) {
                        continue;
                    }

                    if ($bulkMode === 'push_local_to_b24') {
                        $localProductId = intval(isset($conflict['local_product_id']) ? $conflict['local_product_id'] : 0);
                        if ($localProductId <= 0) {
                            throw new Exception('Нет локального товара.');
                        }
                        syncProductAvailableToBitrix($db, $localProductId);
                        resolveConflictStatus($db, $conflictId, 'resolved', ' | Массово: Б24 выровнен по складу');
                    } elseif ($bulkMode === 'accept_b24_to_local') {
                        $localProductId = intval(isset($conflict['local_product_id']) ? $conflict['local_product_id'] : 0);
                        $localValue = floatval(isset($conflict['local_value']) ? $conflict['local_value'] : 0);
                        $b24Value = floatval(isset($conflict['b24_value']) ? $conflict['b24_value'] : 0);
                        $delta = round($b24Value - $localValue, 2);
                        if ($localProductId <= 0 || $delta <= 0.01) {
                            throw new Exception('Нельзя принять Б24: дельта <= 0.');
                        }
                        $db->beginTransaction();
                        $added = addMetersToLocalStock($db, $localProductId, $delta, 'Массовое принятие остатка из Б24, конфликт #' . $conflictId);
                        resolveConflictStatus($db, $conflictId, 'resolved', ' | Массово: склад пополнен из Б24, добавлено ' . $added . ' м');
                        $db->commit();
                    } else {
                        resolveConflictStatus($db, $conflictId, 'dismissed', ' | Массово: закрыто без изменений');
                    }
                    $done++;
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $failed++;
                }
            }
            if ($done > 0) {
                $message = 'Массовое действие выполнено. Обработано: ' . $done . ', ошибок: ' . $failed . '.';
            } else {
                $error = 'Не удалось выполнить массовое действие. Ошибок: ' . $failed . '.';
            }
        }
    }

    if ($action === 'resolve_stock_conflict') {
        $conflictId = intval(isset($_POST['conflict_id']) ? $_POST['conflict_id'] : 0);
        $mode = isset($_POST['mode']) ? trim($_POST['mode']) : '';
        if ($conflictId <= 0) {
            $error = 'Некорректный конфликт.';
        } else {
            $confStmt = $db->prepare("
                SELECT *
                FROM b24_sync_conflicts
                WHERE id = ? AND status = 'new'
                LIMIT 1
            ");
            $confStmt->execute([$conflictId]);
            $conflict = $confStmt->fetch(PDO::FETCH_ASSOC);
            if (!$conflict) {
                $error = 'Конфликт не найден или уже обработан.';
            } else {
                try {
                    if ($mode === 'push_local_to_b24') {
                        $localProductId = intval(isset($conflict['local_product_id']) ? $conflict['local_product_id'] : 0);
                        if ($localProductId <= 0) {
                            throw new Exception('Нет локального товара для синка.');
                        }
                        syncProductAvailableToBitrix($db, $localProductId);
                        resolveConflictStatus($db, $conflictId, 'resolved', ' | Решение: Б24 выровнен по складу');
                        $message = 'Расхождение обработано: Б24 выровнен по остатку склада.';
                    } elseif ($mode === 'accept_b24_to_local') {
                        $localProductId = intval(isset($conflict['local_product_id']) ? $conflict['local_product_id'] : 0);
                        $localValue = floatval(isset($conflict['local_value']) ? $conflict['local_value'] : 0);
                        $b24Value = floatval(isset($conflict['b24_value']) ? $conflict['b24_value'] : 0);
                        $delta = round($b24Value - $localValue, 2);
                        if ($localProductId <= 0) {
                            throw new Exception('Нет локального товара для пополнения.');
                        }
                        if ($delta <= 0.01) {
                            throw new Exception('Для принятия Б24 нужен положительный дельта-остаток.');
                        }
                        $db->beginTransaction();
                        $added = addMetersToLocalStock($db, $localProductId, $delta, 'Принятие остатка из Б24 по конфликту #' . $conflictId);
                        resolveConflictStatus($db, $conflictId, 'resolved', ' | Решение: склад пополнен из Б24, добавлено ' . $added . ' м');
                        $db->commit();
                        $message = 'Расхождение обработано: добавлено на склад ' . $added . ' м.';
                    } elseif ($mode === 'dismiss') {
                        resolveConflictStatus($db, $conflictId, 'dismissed', ' | Решение: закрыто вручную без изменений');
                        $message = 'Расхождение закрыто вручную.';
                    } else {
                        throw new Exception('Неизвестный режим обработки конфликта.');
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = $e->getMessage();
                }
            }
        }
    }

    if ($action === 'retry_deal_rows_sync') {
        $requestId = intval(isset($_POST['request_id']) ? $_POST['request_id'] : 0);
        if ($requestId <= 0) {
            $error = 'Некорректная заявка для повторной отправки в Б24.';
        } else {
            $syncResult = syncDealProductRowsForRequest($db, $requestId, true);
            if (!empty($syncResult['ok'])) {
                $message = 'Повторная отправка строк сделки в Б24 выполнена успешно.';
            } else {
                $stage = isset($syncResult['stage']) ? (string)$syncResult['stage'] : 'unknown';
                $error = 'Повторная отправка в Б24 завершилась с ошибкой на этапе: ' . $stage;
            }
        }
    }

    if ($action === 'add_cut') {
        $lineId = intval(isset($_POST['line_id']) ? $_POST['line_id'] : 0);
        $rollId = intval(isset($_POST['roll_id']) ? $_POST['roll_id'] : 0);
        $meters = floatval(isset($_POST['meters']) ? $_POST['meters'] : 0);

        if ($lineId <= 0 || $rollId <= 0 || $meters <= 0) {
            $error = 'Неверные данные для добавления куска.';
        } else {
            $lineStmt = $db->prepare("
                SELECT l.*, r.b24_deal_id
                FROM b24_sale_lines l
                JOIN b24_sale_requests r ON r.id = l.request_id
                WHERE l.id = ?
            ");
            $lineStmt->execute([$lineId]);
            $line = $lineStmt->fetch(PDO::FETCH_ASSOC);

            $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id = ?");
            $rollStmt->execute([$rollId]);
            $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);

            if (!$line || !$roll) {
                $error = 'Строка или рулон не найдены.';
            } elseif (intval($line['product_id']) !== intval($roll['product_id'])) {
                $error = 'Выбран рулон другого товара.';
            } else {
                $currentReserved = floatval($roll['reserved_length']);
                $sameDeal = intval($roll['deal_id']) === intval($line['b24_deal_id']);
                $available = $sameDeal
                    ? (floatval($roll['current_length']) - $currentReserved)
                    : floatval($roll['current_length']);

                if (intval($roll['reserved']) === 1 && !$sameDeal) {
                    $error = 'Рулон уже зарезервирован под другую сделку.';
                } elseif ($meters > $available) {
                    $error = 'Недостаточно доступных метров в выбранном рулоне.';
                } else {
                    $db->beginTransaction();
                    try {
                        $newReserved = $currentReserved + $meters;
                        $db->prepare("
                            UPDATE rolls
                            SET reserved = 1, deal_id = ?, reserved_length = ?
                            WHERE id = ?
                        ")->execute([intval($line['b24_deal_id']), $newReserved, $rollId]);

                        $db->prepare("
                            INSERT INTO b24_sale_line_cuts (line_id, roll_id, meters, created_at)
                            VALUES (?, ?, ?, NOW())
                        ")->execute([$lineId, $rollId, $meters]);

                        $db->prepare("
                            UPDATE b24_sale_lines SET status='in_progress'
                            WHERE id=? AND status='new'
                        ")->execute([$lineId]);
                        $db->prepare("
                            UPDATE b24_sale_requests SET status='in_progress'
                            WHERE id=? AND status='new'
                        ")->execute([intval($line['request_id'])]);

                        logAndSyncMovement($db, [
                            'product_id' => intval($line['product_id']),
                            'roll_id' => $rollId,
                            'movement_type' => 'reserve',
                            'quantity_m' => $meters,
                            'quantity_rolls' => 0,
                            'deal_id' => intval($line['b24_deal_id']),
                            'comment' => 'Ручной резерв в интерфейсе b24_sales'
                        ]);

                        $db->commit();
                        $message = 'Кусок добавлен в резерв.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }

    if ($action === 'remove_cut') {
        $cutId = intval(isset($_POST['cut_id']) ? $_POST['cut_id'] : 0);
        if ($cutId <= 0) {
            $error = 'Некорректный cut_id.';
        } else {
            $stmt = $db->prepare("
                SELECT c.*, l.product_id, l.request_id, r.b24_deal_id
                FROM b24_sale_line_cuts c
                JOIN b24_sale_lines l ON l.id = c.line_id
                JOIN b24_sale_requests r ON r.id = l.request_id
                WHERE c.id = ?
            ");
            $stmt->execute([$cutId]);
            $cut = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cut) {
                $error = 'Кусок не найден.';
            } else {
                $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id = ?");
                $rollStmt->execute([intval($cut['roll_id'])]);
                $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);

                if (!$roll) {
                    $error = 'Рулон не найден.';
                } else {
                    $db->beginTransaction();
                    try {
                        $newReserved = max(0, floatval($roll['reserved_length']) - floatval($cut['meters']));
                        if ($newReserved <= 0) {
                            $db->prepare("
                                UPDATE rolls SET reserved=0, deal_id=NULL, reserved_length=0
                                WHERE id=?
                            ")->execute([intval($cut['roll_id'])]);
                        } else {
                            $db->prepare("
                                UPDATE rolls SET reserved_length=?
                                WHERE id=?
                            ")->execute([$newReserved, intval($cut['roll_id'])]);
                        }

                        $db->prepare("DELETE FROM b24_sale_line_cuts WHERE id=?")->execute([$cutId]);

                        logAndSyncMovement($db, [
                            'product_id' => intval($cut['product_id']),
                            'roll_id' => intval($cut['roll_id']),
                            'movement_type' => 'reserve_release',
                            'quantity_m' => floatval($cut['meters']),
                            'quantity_rolls' => 0,
                            'deal_id' => intval($cut['b24_deal_id']),
                            'comment' => 'Удаление куска из резерва'
                        ]);

                        $db->commit();
                        $message = 'Кусок удален из резерва.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }

    if ($action === 'confirm_line') {
        $lineId = intval(isset($_POST['line_id']) ? $_POST['line_id'] : 0);
        if ($lineId <= 0) {
            $error = 'Некорректная строка.';
        } else {
            $stmt = $db->prepare("
                SELECT l.*, r.b24_deal_id
                FROM b24_sale_lines l
                JOIN b24_sale_requests r ON r.id = l.request_id
                WHERE l.id=?
            ");
            $stmt->execute([$lineId]);
            $line = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$line) {
                $error = 'Строка не найдена.';
            } else {
                $cutsStmt = $db->prepare("SELECT * FROM b24_sale_line_cuts WHERE line_id = ?");
                $cutsStmt->execute([$lineId]);
                $cuts = $cutsStmt->fetchAll(PDO::FETCH_ASSOC);

                $allocated = 0;
                foreach ($cuts as $c) { $allocated += floatval($c['meters']); }

                $need = floatval($line['quantity_m']);
                if ($allocated + 0.0001 < $need) {
                    $error = 'Недостаточно зарезервировано. Нужно: ' . $need . ' м, есть: ' . round($allocated, 2) . ' м.';
                } else {
                    $db->beginTransaction();
                    try {
                        $costFact = 0.0;
                        foreach ($cuts as $c) {
                            $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id=? FOR UPDATE");
                            $rollStmt->execute([intval($c['roll_id'])]);
                            $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);

                            if (!$roll) {
                                throw new Exception('Рулон не найден во время подтверждения.');
                            }

                            $take = floatval($c['meters']);
                            $newLen = floatval($roll['current_length']) - $take;
                            if ($newLen < 0) {
                                throw new Exception('В рулоне недостаточно метров при подтверждении.');
                            }
                            $rollCostPerMeter = floatval(isset($roll['cost_per_meter']) ? $roll['cost_per_meter'] : 0);
                            if ($rollCostPerMeter > 0) {
                                $costFact += ($take * $rollCostPerMeter);
                            }

                            $newReserved = max(0, floatval($roll['reserved_length']) - $take);
                            $newStatus = $newLen <= 0 ? 'sold' : 'cut';
                            $newLen = max(0, $newLen);

                            if ($newReserved <= 0) {
                                $db->prepare("
                                    UPDATE rolls
                                    SET current_length=?, status=?, reserved=0, deal_id=NULL, reserved_length=0
                                    WHERE id=?
                                ")->execute([$newLen, $newStatus, intval($c['roll_id'])]);
                            } else {
                                $db->prepare("
                                    UPDATE rolls
                                    SET current_length=?, status=?, reserved_length=?
                                    WHERE id=?
                                ")->execute([$newLen, $newStatus, $newReserved, intval($c['roll_id'])]);
                            }
                        }

                        $price = floatval($line['price_per_unit']);
                        $qty = floatval($line['quantity_m']);
                        $revenue = $qty * $price;
                        $grossProfit = $revenue - $costFact;
                        $grossMarginPercent = $revenue > 0 ? (($grossProfit / $revenue) * 100) : 0;
                        $db->prepare("
                            INSERT INTO sales (product_id, type, quantity, price_per_unit, total, deal_id, cost_fact, gross_profit, gross_margin_percent)
                            VALUES (?, 'meter', ?, ?, ?, ?, ?, ?, ?)
                        ")->execute([
                            intval($line['product_id']),
                            $qty,
                            $price,
                            $revenue,
                            intval($line['b24_deal_id']),
                            round($costFact, 2),
                            round($grossProfit, 2),
                            round($grossMarginPercent, 2)
                        ]);

                        logAndSyncMovement($db, [
                            'product_id' => intval($line['product_id']),
                            'movement_type' => 'sale_meter',
                            'quantity_m' => $qty,
                            'quantity_rolls' => 0,
                            'price_per_unit' => $price,
                            'total' => $revenue,
                            'deal_id' => intval($line['b24_deal_id']),
                            'comment' => 'Подтверждение строки продажи из B24 | Маржа: ' . round($grossMarginPercent, 2) . '%'
                        ]);

                        $db->prepare("UPDATE b24_sale_lines SET status='completed' WHERE id=?")->execute([$lineId]);

                        $checkStmt = $db->prepare("
                            SELECT COUNT(*) as cnt
                            FROM b24_sale_lines
                            WHERE request_id=? AND status != 'completed'
                        ");
                        $checkStmt->execute([intval($line['request_id'])]);
                        $left = intval($checkStmt->fetch(PDO::FETCH_ASSOC)['cnt']);
                        if ($left === 0) {
                            $db->prepare("UPDATE b24_sale_requests SET status='completed' WHERE id=?")
                                ->execute([intval($line['request_id'])]);
                            $db->prepare("UPDATE deals SET status='closed' WHERE b24_deal_id=?")
                                ->execute([intval($line['b24_deal_id'])]);
                        }

                        $db->commit();
                        $syncResult = syncDealProductRowsForRequest($db, intval($line['request_id']), false);
                        if (!empty($syncResult['ok'])) {
                            $message = 'Строка подтверждена и списана со склада. Строки сделки отправлены и сверены в Б24.';
                        } else {
                            $message = 'Строка подтверждена и списана со склада.';
                            $stage = isset($syncResult['stage']) ? (string)$syncResult['stage'] : 'unknown';
                            $error = 'Синк строк сделки в Б24 не прошел проверку. Этап: ' . $stage . '. Используйте кнопку повторной отправки.';
                        }
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }
}

$requestId = intval(isset($_GET['request_id']) ? $_GET['request_id'] : 0);
$productFilterId = intval(isset($_GET['product_id']) ? $_GET['product_id'] : 0);
$requestStatusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$requestSearch = isset($_GET['q']) ? trim($_GET['q']) : '';
$requestWhere = [];
$requestParams = [];
if (in_array($requestStatusFilter, ['new', 'in_progress', 'completed', 'cancelled'], true)) {
    $requestWhere[] = "status = ?";
    $requestParams[] = $requestStatusFilter;
}
if ($requestSearch !== '') {
    $requestWhere[] = "(deal_name LIKE ? OR responsible LIKE ? OR CAST(b24_deal_id AS CHAR) LIKE ?)";
    $like = '%' . $requestSearch . '%';
    $requestParams[] = $like;
    $requestParams[] = $like;
    $requestParams[] = $like;
}
$requestSql = "
    SELECT *
    FROM b24_sale_requests
";
if (!empty($requestWhere)) {
    $requestSql .= " WHERE " . implode(" AND ", $requestWhere);
}
$requestSql .= " ORDER BY FIELD(status,'new','in_progress','completed','cancelled'), updated_at DESC";
$requestsStmt = $db->prepare($requestSql);
$requestsStmt->execute($requestParams);
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

$lines = [];
$lineProductOptions = [];
$syncConflicts = [];
if ($requestId > 0) {
    $stmt = $db->prepare("
        SELECT l.*,
               COALESCE((SELECT SUM(meters) FROM b24_sale_line_cuts c WHERE c.line_id=l.id),0) as allocated_m
        FROM b24_sale_lines l
        WHERE l.request_id = ?
        ORDER BY l.id ASC
    ");
    $stmt->execute([$requestId]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($lines as $line) {
        $pid = intval($line['product_id']);
        if ($pid > 0 && !isset($lineProductOptions[$pid])) {
            $lineProductOptions[$pid] = $line['product_name'];
        }
    }
}

try {
    $syncConflicts = $db->query("
        SELECT c.*,
               p.name as local_product_name
        FROM b24_sync_conflicts c
        LEFT JOIN products p ON p.id = c.local_product_id
        WHERE c.status = 'new'
        ORDER BY c.id DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $syncConflicts = [];
}
?>

<main class="container">
<h2>Технический раздел Б24 (интеграция)</h2>
<p class="text-muted">
    Этот экран предназначен для сервисной работы: повтор синка строк сделки, ручные корректировки резервов и диагностика ошибок.
    Для ежедневной работы кладовщика используйте <a href="warehouse_orders.php">Место кладовщика</a>.
</p>

<?php if ($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

<h3>Расхождения склад ↔ Б24</h3>
<p class="text-muted">
    Автоматически не чистим. Для каждого расхождения выберите действие:
    выровнять Б24 по складу (истина — склад) или принять данные Б24 и добавить на склад.
</p>
<div class="alert alert-warning" style="margin-bottom:10px;">
    <strong>Как читать блок:</strong>
    "Локально" — факт в приложении склада, "В Б24" — факт в Bitrix24.
    Если не уверены, по умолчанию выбирайте <strong>«Выровнять Б24 по складу»</strong>.
</div>
<form method="POST" id="bulk-conflicts-form" style="margin-bottom:10px;">
    <input type="hidden" name="action" value="resolve_stock_conflict_bulk">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <label><input type="checkbox" id="check-all-conflicts"> Выбрать все</label>
        <select name="bulk_mode" required>
            <option value="">-- Действие для выбранных --</option>
            <option value="push_local_to_b24">Выровнять Б24 по складу</option>
            <option value="accept_b24_to_local">Добавить на склад (принять Б24)</option>
            <option value="dismiss">Закрыть без изменений</option>
        </select>
        <button type="submit" class="btn btn-warning" onclick="return confirm('Применить действие к выбранным расхождениям?');">Применить массово</button>
    </div>
</form>
<table border="1" cellpadding="6" cellspacing="0" style="margin-bottom:15px;">
    <tr>
        <th>✓</th>
        <th>ID</th>
        <th>Тип</th>
        <th>Товар</th>
        <th>Локально</th>
        <th>В Б24</th>
        <th>Склад (интерпретация)</th>
        <th>Б24 (интерпретация)</th>
        <th>Что выравнивать</th>
        <th>Что делаем</th>
    </tr>
    <?php foreach ($syncConflicts as $c): ?>
    <?php
        $localVal = floatval(isset($c['local_value']) ? $c['local_value'] : 0);
        $b24Val = floatval(isset($c['b24_value']) ? $c['b24_value'] : 0);
        $canAcceptB24 = ($b24Val - $localVal) > 0.01;
        $hint = buildConflictResolutionHint($c);
    ?>
    <tr>
        <td>
            <input type="checkbox" name="conflict_ids[]" value="<?= (int)$c['id'] ?>" form="bulk-conflicts-form" class="js-conflict-checkbox">
        </td>
        <td><?= (int)$c['id'] ?></td>
        <td><?= h($c['conflict_type']) ?></td>
        <td>
            <?= h(isset($c['local_product_name']) ? $c['local_product_name'] : ('ID ' . (int)$c['local_product_id'])) ?><br>
            <small>local: <?= (int)$c['local_product_id'] ?>, b24: <?= (int)$c['b24_product_id'] ?></small>
        </td>
        <td><?= h($c['local_value']) ?></td>
        <td><?= h($c['b24_value']) ?></td>
        <td><?= h(isset($hint['warehouse_state']) ? $hint['warehouse_state'] : '') ?></td>
        <td><?= h(isset($hint['b24_state']) ? $hint['b24_state'] : '') ?></td>
        <td><?= h(isset($hint['recommended']) ? $hint['recommended'] : '') ?></td>
        <td>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="resolve_stock_conflict">
                <input type="hidden" name="conflict_id" value="<?= (int)$c['id'] ?>">
                <input type="hidden" name="mode" value="push_local_to_b24">
                <button type="submit">Выровнять Б24 по складу</button>
            </form>
            <?php if ($canAcceptB24): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="resolve_stock_conflict">
                    <input type="hidden" name="conflict_id" value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="mode" value="accept_b24_to_local">
                    <button type="submit">Добавить на склад (принять Б24)</button>
                </form>
            <?php endif; ?>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="resolve_stock_conflict">
                <input type="hidden" name="conflict_id" value="<?= (int)$c['id'] ?>">
                <input type="hidden" name="mode" value="dismiss">
                <button type="submit">Закрыть без изменений</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<script>
(function () {
    var all = document.getElementById('check-all-conflicts');
    if (!all) return;
    all.addEventListener('change', function () {
        var items = document.querySelectorAll('.js-conflict-checkbox');
        for (var i = 0; i < items.length; i++) {
            items[i].checked = all.checked;
        }
    });
})();
</script>

<form method="GET" style="margin: 10px 0;">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
        <div>
            <label for="status"><b>Статус заявки</b></label><br>
            <select id="status" name="status">
                <option value="">Все</option>
                <option value="new" <?= $requestStatusFilter === 'new' ? 'selected' : '' ?>>new</option>
                <option value="in_progress" <?= $requestStatusFilter === 'in_progress' ? 'selected' : '' ?>>in_progress</option>
                <option value="completed" <?= $requestStatusFilter === 'completed' ? 'selected' : '' ?>>completed</option>
                <option value="cancelled" <?= $requestStatusFilter === 'cancelled' ? 'selected' : '' ?>>cancelled</option>
            </select>
        </div>
        <div>
            <label for="q"><b>Поиск</b></label><br>
            <input id="q" type="text" name="q" value="<?= h($requestSearch) ?>" placeholder="Сделка/ответственный/ID">
        </div>
        <div>
            <button type="submit">Фильтровать</button>
            <?php if ($requestStatusFilter !== '' || $requestSearch !== ''): ?>
                <a href="b24_sales.php">Сброс</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<table border="1" cellpadding="6" cellspacing="0">
    <tr>
        <th>ID</th>
        <th>Сделка Б24</th>
        <th>Название</th>
        <th>Ответственный</th>
        <th>Статус</th>
        <th>Синк строк</th>
        <th>Повтор</th>
        <th>Открыть</th>
    </tr>
    <?php foreach ($requests as $r): ?>
    <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= (int)$r['b24_deal_id'] ?></td>
        <td><?= h($r['deal_name']) ?></td>
        <td><?= h($r['responsible']) ?></td>
        <td><?= h($r['status']) ?></td>
        <td>
            <?= h(isset($r['deal_rows_sync_status']) ? $r['deal_rows_sync_status'] : 'pending') ?>
            <?php if (!empty($r['deal_rows_sync_stage'])): ?>
                (<?= h($r['deal_rows_sync_stage']) ?>)
            <?php endif; ?>
        </td>
        <td>
            <?php if (in_array((string)(isset($r['deal_rows_sync_status']) ? $r['deal_rows_sync_status'] : 'pending'), array('failed', 'pending', 'in_progress'), true)): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="retry_deal_rows_sync">
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <button type="submit">Повторить синк</button>
                </form>
            <?php else: ?>
                -
            <?php endif; ?>
        </td>
        <td><a href="?request_id=<?= (int)$r['id'] ?>">Открыть</a></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php if ($requestId > 0): ?>
    <h3>Строки заявки #<?= $requestId ?></h3>
    <form method="GET" style="margin: 10px 0;">
        <input type="hidden" name="request_id" value="<?= $requestId ?>">
        <label for="product_id"><b>Фильтр по товару:</b></label>
        <select name="product_id" id="product_id">
            <option value="0">Все товары из заявки</option>
            <?php foreach ($lineProductOptions as $pid => $pname): ?>
                <option value="<?= (int)$pid ?>" <?= $productFilterId === (int)$pid ? 'selected' : '' ?>>
                    <?= h($pname) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Применить</button>
        <?php if ($productFilterId > 0): ?>
            <a href="?request_id=<?= $requestId ?>">Сбросить</a>
        <?php endif; ?>
    </form>
    <?php foreach ($lines as $line): ?>
        <?php if ($productFilterId > 0 && intval($line['product_id']) !== $productFilterId) { continue; } ?>
        <?php
        $rollStmt = $db->prepare("
            SELECT *
            FROM rolls
            WHERE product_id = ?
              AND current_length > 0
              AND (
                    reserved = 0
                    OR (reserved = 1 AND deal_id = (SELECT b24_deal_id FROM b24_sale_requests WHERE id = ?))
              )
            ORDER BY current_length ASC
        ");
        $rollStmt->execute([intval($line['product_id']), $requestId]);
        $lineRolls = $rollStmt->fetchAll(PDO::FETCH_ASSOC);

        $cutsStmt = $db->prepare("
            SELECT c.*, r.current_length
            FROM b24_sale_line_cuts c
            LEFT JOIN rolls r ON r.id = c.roll_id
            WHERE c.line_id = ?
            ORDER BY c.id DESC
        ");
        $cutsStmt->execute([intval($line['id'])]);
        $cuts = $cutsStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <details style="border:1px solid #ccc; padding:10px; margin:10px 0;" <?= $line['status'] !== 'completed' ? 'open' : '' ?>>
            <summary style="cursor:pointer;">
                <b><?= h($line['product_name']) ?></b>
                | Нужно: <?= (float)$line['quantity_m'] ?> м
                | Зарезервировано: <?= round((float)$line['allocated_m'], 2) ?> м
                | Статус: <?= h($line['status']) ?>
            </summary>
            <div style="margin-top:8px;">
            <b><?= h($line['product_name']) ?></b><br>
            Нужно: <?= (float)$line['quantity_m'] ?> м |
            Зарезервировано: <?= round((float)$line['allocated_m'], 2) ?> м |
            Статус: <?= h($line['status']) ?>

            <?php if ($line['status'] !== 'completed'): ?>
            <form method="POST" style="margin-top:10px;">
                <input type="hidden" name="action" value="add_cut">
                <input type="hidden" name="line_id" value="<?= (int)$line['id'] ?>">
                <select name="roll_id" required>
                    <option value="">Выбери рулон</option>
                    <?php foreach ($lineRolls as $roll): ?>
                        <option value="<?= (int)$roll['id'] ?>">
                            #<?= (int)$roll['id'] ?> | остаток <?= (float)$roll['current_length'] ?> м | reserved <?= (float)$roll['reserved_length'] ?> м
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="meters" step="0.1" min="0.1" placeholder="Сколько метров" required>
                <button type="submit">Добавить кусок</button>
            </form>
            <div style="margin-top:6px;">
                Быстро добавить:
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="add_cut">
                    <input type="hidden" name="line_id" value="<?= (int)$line['id'] ?>">
                    <input type="hidden" name="meters" value="5">
                    <select name="roll_id" required>
                        <option value="">Рулон</option>
                        <?php foreach ($lineRolls as $roll): ?>
                            <option value="<?= (int)$roll['id'] ?>">#<?= (int)$roll['id'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">+5м</button>
                </form>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="add_cut">
                    <input type="hidden" name="line_id" value="<?= (int)$line['id'] ?>">
                    <input type="hidden" name="meters" value="10">
                    <select name="roll_id" required>
                        <option value="">Рулон</option>
                        <?php foreach ($lineRolls as $roll): ?>
                            <option value="<?= (int)$roll['id'] ?>">#<?= (int)$roll['id'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">+10м</button>
                </form>
            </div>

            <form method="POST" style="margin-top:8px;">
                <input type="hidden" name="action" value="confirm_line">
                <input type="hidden" name="line_id" value="<?= (int)$line['id'] ?>">
                <button type="submit">Подтвердить строку (списать)</button>
            </form>
            <?php endif; ?>

            <?php if ($cuts): ?>
                <table border="1" cellpadding="5" cellspacing="0" style="margin-top:10px;">
                    <tr>
                        <th>Кусок</th>
                        <th>Рулон</th>
                        <th>Метры</th>
                        <th>Действие</th>
                    </tr>
                    <?php foreach ($cuts as $cut): ?>
                    <tr>
                        <td>#<?= (int)$cut['id'] ?></td>
                        <td>#<?= (int)$cut['roll_id'] ?></td>
                        <td><?= (float)$cut['meters'] ?></td>
                        <td>
                            <?php if ($line['status'] !== 'completed'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="remove_cut">
                                <input type="hidden" name="cut_id" value="<?= (int)$cut['id'] ?>">
                                <button type="submit">Убрать</button>
                            </form>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
            </div>
        </details>
    <?php endforeach; ?>
<?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
