<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require '../../db.php';
require __DIR__ .  '/../../functions/rolls.php'; // 🔥 ВАЖНО

$db = getDB();

// Получаем JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Проверка
if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'No JSON']);
    exit;
}

// 🔥 ДАННЫЕ
$product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
$quantity   = isset($data['quantity']) ? floatval($data['quantity']) : 0;
$price      = isset($data['price']) ? floatval($data['price']) : 0;
$type       = isset($data['type']) ? $data['type'] : 'sale';

$deal_id  = isset($data['deal_id']) ? intval($data['deal_id']) : null;
$deal_url = isset($data['deal_url']) ? $data['deal_url'] : null;
$responsible = isset($data['responsible']) ? $data['responsible'] : null;

// 🔥 ВАЛИДАЦИЯ
if (!$product_id || !$quantity) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

try {
    
// 🔥 ПРОВЕРКА СКЛАДА (безопасная)
$stmt = $db->prepare("
    SELECT SUM(current_length) as total_length 
    FROM rolls 
    WHERE product_id = ? AND reserved = 0 AND current_length > 0
");
$stmt->execute([$product_id]);

$stock = $stmt->fetch(PDO::FETCH_ASSOC);

$totalAvailable = 0;

if ($stock && isset($stock['total_length'])) {
    $totalAvailable = floatval($stock['total_length']);
}

// ❌ ЕСЛИ НЕ ХВАТАЕТ
if ($totalAvailable < $quantity) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Недостаточно товара',
        'available' => $totalAvailable
    ]);
    exit;
}



    // 🔥 2. СПИСАНИЕ СО СКЛАДА
    $cuts = [];

    if ($type == 'sale' || $type == 'meter') {
        $cuts = allocateMeters($db, $product_id, $quantity);
    }

    // 🔥 3. СУММА
    $total = $quantity * $price;

    // 🔥 4. ЗАПИСЬ В SALES
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
        'message' => 'Sale recorded',
        'cuts' => $cuts
    ]);

} catch (Exception $e) {

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}