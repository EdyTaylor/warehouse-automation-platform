<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../db.php';
$db = getDB();

// Получаем JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Проверка
if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'No JSON']);
    exit;
}

// 🔥 ДАННЫЕ ИЗ БИТРИКСА
$b24_product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
$quantity   = isset($data['quantity']) ? floatval($data['quantity']) : 0;
$price      = isset($data['price']) ? floatval($data['price']) : 0;
$type       = isset($data['type']) ? $data['type'] : 'sale';

$deal_id  = isset($data['deal_id']) ? intval($data['deal_id']) : null;
$deal_url = isset($data['deal_url']) ? $data['deal_url'] : null;

$responsible = isset($data['responsible']) ? $data['responsible'] : null;

// 🔥 ВАЛИДАЦИЯ
if (!$b24_product_id || !$quantity) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

// 🔥 МАППИНГ ИЗ B24 ID -> ЛОКАЛЬНЫЙ products.id
$stmt = $db->prepare("SELECT id FROM products WHERE b24_product_id = ?");
$stmt->execute([$b24_product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['status' => 'error', 'message' => 'Product not mapped by b24_product_id']);
    exit;
}

$product_id = intval($product['id']);

// 🔥 СЧИТАЕМ СУММУ
$total = $quantity * $price;

// 🔥 ЗАПИСЬ В SALES
$stmt = $db->prepare("
    INSERT INTO sales 
    (product_id, type, quantity, price_per_unit, total, deal_id, deal_url, responsible, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->execute([
    $product_id,
    $type,
    $quantity,
    $price,
    $total,
    $deal_id,
    $deal_url,
    $responsible
]);

echo json_encode([
    'status' => 'success',
    'message' => 'Sale recorded'
]);