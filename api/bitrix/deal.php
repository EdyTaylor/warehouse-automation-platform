<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../db.php';
$db = getDB();

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["error" => "Нет данных"]);
    exit;
}

$deal_id = intval($data['deal_id'] ?? 0);
$deal_name = $data['deal_name'] ?? '';
$responsible = $data['responsible'] ?? '';
$products = $data['products'] ?? [];

if (!$deal_id || empty($products)) {
    echo json_encode(["error" => "Неверные данные"]);
    exit;
}

// 1) legacy table for compatibility
$db->prepare("
    INSERT INTO deals (b24_deal_id, name, status, created_at)
    VALUES (?, ?, 'new', NOW())
    ON DUPLICATE KEY UPDATE name=VALUES(name), status='new'
")->execute([$deal_id, $deal_name]);

// 2) manual queue: upsert request + replace lines
$db->beginTransaction();
try {
    $db->prepare("
        INSERT INTO b24_sale_requests (b24_deal_id, deal_name, responsible, status, created_at, updated_at)
        VALUES (?, ?, ?, 'new', NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            deal_name = VALUES(deal_name),
            responsible = VALUES(responsible),
            status = IF(status='completed', status, 'new'),
            updated_at = NOW()
    ")->execute([$deal_id, $deal_name, $responsible]);

    $stmt = $db->prepare("SELECT id FROM b24_sale_requests WHERE b24_deal_id = ?");
    $stmt->execute([$deal_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    $requestId = intval($request['id']);

    // Do not overwrite fulfilled lines. Remove cuts for editable lines first.
    $lineIdsStmt = $db->prepare("SELECT id FROM b24_sale_lines WHERE request_id = ? AND status != 'completed'");
    $lineIdsStmt->execute([$requestId]);
    $lineIds = $lineIdsStmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($lineIds)) {
        $placeholders = implode(',', array_fill(0, count($lineIds), '?'));
        $delCuts = $db->prepare("DELETE FROM b24_sale_line_cuts WHERE line_id IN ($placeholders)");
        $delCuts->execute($lineIds);
    }
    $db->prepare("DELETE FROM b24_sale_lines WHERE request_id = ? AND status != 'completed'")->execute([$requestId]);

    $ins = $db->prepare("
        INSERT INTO b24_sale_lines
        (request_id, b24_product_id, product_id, product_name, quantity_m, price_per_unit, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'new', NOW())
    ");

    foreach ($products as $p) {
        $b24_id = intval($p['id'] ?? 0);
        $name = $p['name'] ?? 'Без названия';
        $qty = floatval($p['quantity'] ?? 0);
        $price = floatval($p['price'] ?? 0);

        if ($qty <= 0) {
            continue;
        }

        $stmt = $db->prepare("SELECT id FROM products WHERE b24_product_id = ?");
        $stmt->execute([$b24_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $db->prepare("
                INSERT INTO products (name, roll_length, price_per_meter, b24_product_id)
                VALUES (?, 30, 0, ?)
            ")->execute([$name, $b24_id]);
            $productId = intval($db->lastInsertId());
        } else {
            $productId = intval($product['id']);
        }

        $ins->execute([$requestId, $b24_id, $productId, $name, $qty, $price]);
    }

    $db->commit();
    echo json_encode([
        "status" => "queued",
        "deal_id" => $deal_id,
        "request_id" => $requestId
    ]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(["error" => $e->getMessage()]);
}