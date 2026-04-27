<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/functions/stock_movements.php';

$data = json_decode(file_get_contents("php://input"), true);

$product_id = intval($data['product_id'] ?? 0);
$meters = floatval($data['meters'] ?? 0);
$deal_id = intval($data['deal_id'] ?? 0);

if (!$product_id || !$meters || !$deal_id) {
    echo json_encode(["error" => "Нет данных"]);
    exit;
}


// 🔥 берем доступные рулоны (НЕ зарезервированные)
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

$remaining = $meters;
$reserved_rolls = [];

foreach ($rolls as $roll) {

    if ($remaining <= 0) break;

    $take = min($roll['current_length'], $remaining);

    // помечаем как резерв
    $db->prepare("
        UPDATE rolls 
        SET reserved=1, deal_id=?, reserved_length=?
        WHERE id=?
    ")->execute([$deal_id, $take, $roll['id']]);

    logAndSyncMovement($db, [
        'product_id' => $product_id,
        'roll_id' => intval($roll['id']),
        'movement_type' => 'reserve',
        'quantity_m' => $take,
        'quantity_rolls' => 0,
        'deal_id' => $deal_id,
        'comment' => 'Резерв по сделке'
    ]);

    $reserved_rolls[] = [
        "roll_id" => $roll['id'],
        "reserved" => $take
    ];

    $remaining -= $take;
}


if ($remaining > 0) {
    echo json_encode([
        "error" => "Не хватает товара",
        "missing" => $remaining
    ]);
    exit;
}


// записываем резерв как "не продажу"
$db->prepare("
    INSERT INTO sales 
    (product_id, type, quantity, reserved, deal_id)
    VALUES (?, 'reserve', ?, 1, ?)
")->execute([$product_id, $meters, $deal_id]);


echo json_encode([
    "status" => "reserved",
    "deal_id" => $deal_id,
    "rolls" => $reserved_rolls
]);