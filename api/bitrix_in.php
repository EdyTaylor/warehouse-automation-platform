<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';
$db = getDB();

// Получаем JSON
$data = json_decode(file_get_contents('php://input'), true);

// Данные из Bitrix
$b24_product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
$name = isset($data['name']) ? $data['name'] : 'Без названия';
$quantity = isset($data['quantity']) ? floatval($data['quantity']) : 0;
$purchase_price = isset($data['purchase_price']) ? floatval($data['purchase_price']) : 0;
$sale_price = isset($data['sale_price']) ? floatval($data['sale_price']) : 0;
$deal_id = isset($data['deal_id']) ? intval($data['deal_id']) : null;
$deal_name = isset($data['deal_name']) ? $data['deal_name'] : '';
$responsible = isset($data['responsible']) ? $data['responsible'] : '';

if ($b24_product_id <= 0 || $quantity <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid payload"]);
    exit;
}

// 1. Ищем товар только по b24_product_id
$stmt = $db->prepare("SELECT id FROM products WHERE b24_product_id = ?");
$stmt->execute([$b24_product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    // Добавляем товар если нет
    $stmt = $db->prepare("
        INSERT INTO products (name, purchase_price, price_1_4, b24_product_id, roll_length, price_per_meter)
        VALUES (?, ?, ?, ?, 30, 0)
    ");
    $stmt->execute([$name, $purchase_price, $sale_price, $b24_product_id]);
    $product_id = intval($db->lastInsertId());
} else {
    $product_id = intval($product['id']);
}

// 2. Списание со склада (продажа)
$stmt = $db->prepare("
    INSERT INTO sales (product_id, type, quantity, price_per_unit, total, deal_id)
    VALUES (?, 'roll', ?, ?, ?, ?)
");

$total = $quantity * $sale_price;

$stmt->execute([$product_id, $quantity, $sale_price, $total, $deal_id]);

echo json_encode(["status" => "ok"]);