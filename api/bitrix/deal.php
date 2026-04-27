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

// 🔥 сохраняем сделку
$db->prepare("
    INSERT INTO deals (b24_deal_id, name, status, created_at)
    VALUES (?, ?, 'reserve', NOW())
    ON DUPLICATE KEY UPDATE name=VALUES(name)
")->execute([$deal_id, $deal_name]);

$result = [];

foreach ($products as $p) {

    $b24_id = intval($p['id']);
    $name = $p['name'];
    $qty = floatval($p['quantity']);

    // 🔍 ищем товар
    $stmt = $db->prepare("SELECT * FROM products WHERE b24_product_id = ?");
    $stmt->execute([$b24_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    // ➕ если нет — создаем
    if (!$product) {
        $db->prepare("
            INSERT INTO products (name, roll_length, price_per_meter, b24_product_id)
            VALUES (?, 30, 0, ?)
        ")->execute([$name, $b24_id]);

        $product_id = $db->lastInsertId();
    } else {
        $product_id = $product['id'];
    }

    // 🔥 РЕЗЕРВ (твоя логика адаптирована)
    $stmt = $db->prepare("
        SELECT * FROM rolls
        WHERE product_id=?
        AND status != 'sold'
        AND reserved = 0
        AND current_length > 0
        ORDER BY 
            CASE WHEN status='cut' THEN 0 ELSE 1 END,
            current_length ASC
    ");
    $stmt->execute([$product_id]);

    $rolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $remaining = $qty;
    $reserved_rolls = [];

    foreach ($rolls as $roll) {

        if ($remaining <= 0) break;

        $take = min($roll['current_length'], $remaining);

        $db->prepare("
            UPDATE rolls 
            SET reserved=1, deal_id=?
            WHERE id=?
        ")->execute([$deal_id, $roll['id']]);

        $reserved_rolls[] = [
            "roll_id" => $roll['id'],
            "reserved" => $take
        ];

        $remaining -= $take;
    }

    if ($remaining > 0) {
        echo json_encode([
            "error" => "Не хватает товара",
            "product" => $name,
            "missing" => $remaining
        ]);
        exit;
    }

    // 🟡 записываем резерв
    $db->prepare("
        INSERT INTO sales 
        (product_id, type, quantity, reserved, deal_id)
        VALUES (?, 'reserve', ?, 1, ?)
    ")->execute([$product_id, $qty, $deal_id]);

    $result[] = [
        "product" => $name,
        "reserved" => $qty
    ];
}

echo json_encode([
    "status" => "reserved",
    "deal_id" => $deal_id,
    "result" => $result
]);