<?php

require_once __DIR__ . '/../api/bitrix/send.php';
require_once __DIR__ . '/b24_sale_pricing.php';

function pickerEnsureDealRowsSyncSchema($db) {
    $ensureColumn = function($tableName, $columnName, $columnSql) use ($db) {
        $stmt = $db->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
        $stmt->execute(array($columnName));
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec("ALTER TABLE `{$tableName}` ADD COLUMN {$columnSql}");
        }
    };

    $ensureColumn('b24_sale_requests', 'deal_rows_sync_status', "`deal_rows_sync_status` varchar(20) NOT NULL DEFAULT 'pending'");
    $ensureColumn('b24_sale_requests', 'deal_rows_sync_stage', "`deal_rows_sync_stage` varchar(64) DEFAULT NULL");
    $ensureColumn('b24_sale_requests', 'deal_rows_sync_error', "`deal_rows_sync_error` text");
    $ensureColumn('b24_sale_requests', 'deal_rows_sync_payload', "`deal_rows_sync_payload` longtext");
    $ensureColumn('b24_sale_requests', 'deal_rows_sync_last_response', "`deal_rows_sync_last_response` longtext");
    $ensureColumn('b24_sale_requests', 'deal_rows_sync_last_hash', "`deal_rows_sync_last_hash` varchar(64) DEFAULT NULL");
    $ensureColumn('b24_sale_requests', 'deal_rows_sync_attempts', "`deal_rows_sync_attempts` int NOT NULL DEFAULT 0");
    $ensureColumn('b24_sale_requests', 'deal_rows_sync_attempted_at', "`deal_rows_sync_attempted_at` datetime DEFAULT NULL");
    $ensureColumn('b24_sale_requests', 'deal_rows_synced_at', "`deal_rows_synced_at` datetime DEFAULT NULL");
    $ensureColumn('b24_sale_requests', 'deal_rows_verified_at', "`deal_rows_verified_at` datetime DEFAULT NULL");
}

function pickerCallBitrixWithRetry($method, $payload, $maxAttempts, $sleepMs) {
    $attempt = 0;
    $lastResp = null;
    while ($attempt < $maxAttempts) {
        $attempt++;
        $lastResp = sendToBitrix($method, $payload);
        if (is_array($lastResp) && !isset($lastResp['error'])) {
            return array('ok' => true, 'response' => $lastResp, 'attempts' => $attempt);
        }
        if ($attempt < $maxAttempts) {
            usleep(max(0, intval($sleepMs)) * 1000);
        }
    }
    return array('ok' => false, 'response' => $lastResp, 'attempts' => $attempt);
}

function pickerNormalizeDealRows($rows) {
    $normalized = array();
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
            $normalized[$productId] = array('qty' => 0.0, 'price' => $price, 'total' => 0.0);
        }
        $normalized[$productId]['qty'] += $qty;
        $normalized[$productId]['price'] = $price;
        $normalized[$productId]['total'] += $total;
    }
    ksort($normalized);
    return $normalized;
}

function pickerBuildDealRowsPayloadForRequest($db, $requestId) {
    ensureB24SaleLinesFinanceColumns($db);
    $stmt = $db->prepare("
        SELECT b24_product_id, product_name, quantity_m, price_per_unit, list_price_per_unit
        FROM b24_sale_lines
        WHERE request_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute(array($requestId));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $payloadRows = array();
    foreach ($rows as $row) {
        $productId = intval(isset($row['b24_product_id']) ? $row['b24_product_id'] : 0);
        $qty = floatval(isset($row['quantity_m']) ? $row['quantity_m'] : 0);
        $price = b24SaleLineListPriceForBitrixSync($row);
        if ($productId <= 0 || $qty <= 0) {
            continue;
        }
        $payloadRows[] = array(
            'PRODUCT_ID' => $productId,
            'PRODUCT_NAME' => (string)(isset($row['product_name']) ? $row['product_name'] : ''),
            'PRICE' => round($price, 2),
            'QUANTITY' => round($qty, 2)
        );
    }
    return $payloadRows;
}

function pickerVerifyDealRows($expectedRows, $actualRows) {
    $expected = pickerNormalizeDealRows($expectedRows);
    $actual = pickerNormalizeDealRows($actualRows);
    if (count($expected) !== count($actual)) {
        return array('ok' => false, 'reason' => 'row_count_mismatch', 'expected' => $expected, 'actual' => $actual);
    }
    foreach ($expected as $productId => $exp) {
        if (!isset($actual[$productId])) {
            return array('ok' => false, 'reason' => 'missing_product', 'product_id' => $productId);
        }
        $act = $actual[$productId];
        if (abs($exp['qty'] - $act['qty']) > 0.01 || abs($exp['price'] - $act['price']) > 0.01 || abs($exp['total'] - $act['total']) > 0.05) {
            return array('ok' => false, 'reason' => 'value_mismatch', 'product_id' => $productId);
        }
    }
    return array('ok' => true);
}

function pickerSyncDealRowsForRequest($db, $requestId, $force) {
    pickerEnsureDealRowsSyncSchema($db);

    $reqStmt = $db->prepare("
        SELECT id, b24_deal_id, deal_rows_sync_status, deal_rows_sync_last_hash
        FROM b24_sale_requests
        WHERE id = ?
        LIMIT 1
    ");
    $reqStmt->execute(array($requestId));
    $request = $reqStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        return array('ok' => false, 'stage' => 'request.load', 'error' => 'request_not_found');
    }
    $dealId = intval($request['b24_deal_id']);
    if ($dealId <= 0) {
        return array('ok' => false, 'stage' => 'request.load', 'error' => 'invalid_deal_id');
    }

    $rows = pickerBuildDealRowsPayloadForRequest($db, $requestId);
    if (empty($rows)) {
        return array('ok' => false, 'stage' => 'payload.build', 'error' => 'empty_rows');
    }
    $payloadHash = hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE));
    if (!$force
        && (string)$request['deal_rows_sync_status'] === 'sent'
        && !empty($request['deal_rows_sync_last_hash'])
        && hash_equals((string)$request['deal_rows_sync_last_hash'], $payloadHash)
    ) {
        return array('ok' => true, 'stage' => 'idempotent.skip', 'idempotent' => true, 'b24_deal_id' => $dealId);
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
    ")->execute(array(json_encode($rows, JSON_UNESCAPED_UNICODE), $payloadHash, $requestId));

    $setPayload = array('id' => $dealId, 'rows' => $rows);
    $setResult = pickerCallBitrixWithRetry('crm.deal.productrows.set', $setPayload, 3, 450);
    if (!$setResult['ok']) {
        $response = $setResult['response'];
        $db->prepare("
            UPDATE b24_sale_requests
            SET deal_rows_sync_status='failed', deal_rows_sync_stage='deal.productrows.set', deal_rows_sync_error=?, deal_rows_sync_last_response=?, updated_at=NOW()
            WHERE id = ?
        ")->execute(array(
            is_array($response)
                ? (string)(isset($response['error_description']) ? $response['error_description'] : (isset($response['error']) ? $response['error'] : 'set_failed'))
                : 'set_failed',
            json_encode($response, JSON_UNESCAPED_UNICODE),
            $requestId
        ));
        return array('ok' => false, 'stage' => 'deal.productrows.set', 'response' => $response, 'b24_deal_id' => $dealId);
    }

    $getResult = pickerCallBitrixWithRetry('crm.deal.productrows.get', array('id' => $dealId), 3, 450);
    if (!$getResult['ok']) {
        $response = $getResult['response'];
        $db->prepare("
            UPDATE b24_sale_requests
            SET deal_rows_sync_status='failed', deal_rows_sync_stage='deal.productrows.get', deal_rows_sync_error=?, deal_rows_sync_last_response=?, deal_rows_synced_at = NOW(), updated_at=NOW()
            WHERE id = ?
        ")->execute(array(
            is_array($response)
                ? (string)(isset($response['error_description']) ? $response['error_description'] : (isset($response['error']) ? $response['error'] : 'get_failed'))
                : 'get_failed',
            json_encode($response, JSON_UNESCAPED_UNICODE),
            $requestId
        ));
        return array('ok' => false, 'stage' => 'deal.productrows.get', 'response' => $response, 'b24_deal_id' => $dealId);
    }

    $actualRows = array();
    if (is_array($getResult['response']) && isset($getResult['response']['result']) && is_array($getResult['response']['result'])) {
        $actualRows = $getResult['response']['result'];
    }
    $verify = pickerVerifyDealRows($rows, $actualRows);
    if (empty($verify['ok'])) {
        $db->prepare("
            UPDATE b24_sale_requests
            SET deal_rows_sync_status='failed', deal_rows_sync_stage='deal.productrows.verify', deal_rows_sync_error=?, deal_rows_sync_last_response=?, deal_rows_synced_at = NOW(), updated_at=NOW()
            WHERE id = ?
        ")->execute(array(
            'verify_failed:' . (isset($verify['reason']) ? $verify['reason'] : 'unknown'),
            json_encode(array('verify' => $verify, 'get_response' => $getResult['response']), JSON_UNESCAPED_UNICODE),
            $requestId
        ));
        return array('ok' => false, 'stage' => 'deal.productrows.verify', 'verify' => $verify, 'b24_deal_id' => $dealId);
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
    ")->execute(array(
        json_encode(array(
            'set' => $setResult['response'],
            'get' => $getResult['response'],
            'verify' => $verify
        ), JSON_UNESCAPED_UNICODE),
        $requestId
    ));

    return array('ok' => true, 'stage' => 'done', 'b24_deal_id' => $dealId);
}
