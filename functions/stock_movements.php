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

function ensureProductSyncedWithBitrix($db, $productId) {
    $productId = intval($productId);
    if ($productId <= 0) {
        return 0;
    }

    $stmt = $db->prepare("SELECT id, name, b24_product_id, purchase_price FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        return 0;
    }

    $existingB24Id = intval(isset($product['b24_product_id']) ? $product['b24_product_id'] : 0);
    if ($existingB24Id > 0) {
        return $existingB24Id;
    }

    $name = trim(isset($product['name']) ? (string)$product['name'] : '');
    if ($name === '') {
        return 0;
    }

    $fields = ['NAME' => $name];
    $price = floatval(isset($product['purchase_price']) ? $product['purchase_price'] : 0);
    if ($price > 0) {
        $fields['PRICE'] = $price;
    }

    $resp = sendToBitrix('crm.product.add', ['fields' => $fields]);
    if (!is_array($resp) || isset($resp['error']) || !isset($resp['result'])) {
        return 0;
    }

    $newB24Id = intval($resp['result']);
    if ($newB24Id <= 0) {
        return 0;
    }

    $db->prepare("UPDATE products SET b24_product_id = ? WHERE id = ?")
        ->execute([$newB24Id, $productId]);

    return $newB24Id;
}

function syncProductAvailableToBitrix($db, $productId) {
    $config = getBitrixStockConfig();
    $stmt = $db->prepare("SELECT b24_product_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        return ['status' => 'skip', 'message' => 'No b24_product_id'];
    }

    $b24ProductId = intval(isset($product['b24_product_id']) ? $product['b24_product_id'] : 0);
    if ($b24ProductId <= 0) {
        $b24ProductId = ensureProductSyncedWithBitrix($db, $productId);
    }
    if ($b24ProductId <= 0) {
        return ['status' => 'skip', 'message' => 'No b24_product_id'];
    }

    $freeMeters = getFreeMetersByProduct($db, $productId);
    $method = $config['product_update_method'];
    $field = $config['product_available_field'];

    $payload = [
        'id' => $b24ProductId,
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
