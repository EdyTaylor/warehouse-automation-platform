<?php

require_once __DIR__ . '/../api/bitrix/send.php';

function getBitrixStockConfig() {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../api/bitrix/config.php';
    }
    return $config;
}

function getFreeMetersByProduct($db, $productId) {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN reserved = 0
                  AND current_length > 0
                  AND status NOT IN ('sold','waste','written_off')
                THEN current_length
                ELSE 0
            END
        ), 0) as free_meters
        FROM rolls
        WHERE product_id = ?
    ");
    $stmt->execute([$productId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? round(floatval($row['free_meters']), 2) : 0;
}

function syncProductAvailableToBitrix($db, $productId) {
    $config = getBitrixStockConfig();
    $stmt = $db->prepare("SELECT b24_product_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product || empty($product['b24_product_id'])) {
        return ['status' => 'skip', 'message' => 'No b24_product_id'];
    }

    $freeMeters = getFreeMetersByProduct($db, $productId);
    $method = $config['product_update_method'];
    $field = $config['product_available_field'];

    $payload = [
        'id' => intval($product['b24_product_id']),
        'fields' => [
            $field => $freeMeters
        ]
    ];

    return sendToBitrix($method, $payload);
}

function logStockMovement($db, $data) {
    $stmt = $db->prepare("
        INSERT INTO stock_movements
        (product_id, roll_id, movement_type, quantity_m, quantity_rolls, price_per_unit, total, deal_id, comment, bitrix_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");

    $stmt->execute([
        isset($data['product_id']) ? intval($data['product_id']) : 0,
        isset($data['roll_id']) ? intval($data['roll_id']) : null,
        isset($data['movement_type']) ? $data['movement_type'] : 'adjustment',
        isset($data['quantity_m']) ? floatval($data['quantity_m']) : 0,
        isset($data['quantity_rolls']) ? intval($data['quantity_rolls']) : 0,
        isset($data['price_per_unit']) ? floatval($data['price_per_unit']) : null,
        isset($data['total']) ? floatval($data['total']) : null,
        isset($data['deal_id']) ? intval($data['deal_id']) : null,
        isset($data['comment']) ? $data['comment'] : null
    ]);

    return intval($db->lastInsertId());
}

function syncMovementToBitrix($db, $movementId) {
    $config = getBitrixStockConfig();
    $stmt = $db->prepare("SELECT * FROM stock_movements WHERE id = ?");
    $stmt->execute([$movementId]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$m) {
        return ['status' => 'error', 'message' => 'Movement not found'];
    }

    if (empty($m['deal_id'])) {
        $db->prepare("UPDATE stock_movements SET bitrix_status='sent', bitrix_response=? WHERE id=?")
            ->execute([json_encode(['status' => 'skip', 'message' => 'No deal_id']), $movementId]);
        return ['status' => 'skip', 'message' => 'No deal_id'];
    }

    $comment = '[Склад] ' . $m['movement_type']
        . '; метры=' . $m['quantity_m']
        . '; рулоны=' . $m['quantity_rolls']
        . '; product_id=' . $m['product_id']
        . ($m['comment'] ? '; ' . $m['comment'] : '');

    $payload = [
        'fields' => [
            'ENTITY_ID' => intval($m['deal_id']),
            'ENTITY_TYPE' => 'deal',
            'COMMENT' => $comment
        ]
    ];

    $resp = sendToBitrix($config['movement_timeline_method'], $payload);
    $isError = is_array($resp) && isset($resp['error']);

    $db->prepare("UPDATE stock_movements SET bitrix_status=?, bitrix_response=? WHERE id=?")
        ->execute([$isError ? 'error' : 'sent', json_encode($resp, JSON_UNESCAPED_UNICODE), $movementId]);

    return $resp;
}

function logAndSyncMovement($db, $movementData) {
    $movementId = logStockMovement($db, $movementData);
    $movementSync = syncMovementToBitrix($db, $movementId);
    $stockSync = syncProductAvailableToBitrix($db, intval($movementData['product_id']));

    return [
        'movement_id' => $movementId,
        'movement_sync' => $movementSync,
        'stock_sync' => $stockSync
    ];
}
