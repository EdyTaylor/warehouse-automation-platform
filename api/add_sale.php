<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../db.php';
$db = getDB();

$data = json_decode(file_get_contents("php://input"), true);

// 🔥 данные
$product_id = $data['product_id'] ?? 0;
$quantity   = $data['quantity'] ?? 0;
$price      = $data['price'] ?? 0;
$type       = $data['type'] ?? 'sale';
$deal_id    = $data['deal_id'] ?? null;
$deal_url   = $data['deal_url'] ?? null;

// 🔥 расчет
$total = $quantity * $price;

// 🔥 запись
$stmt = $db->prepare("
    INSERT INTO sales 
    (product_id, type, quantity, price_per_unit, total, created_at, deal_id, deal_url, reserved)
    VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 0)
");

$stmt->execute([
    $product_id,
    $type,
    $quantity,
    $price,
    $total,
    $deal_id,
    $deal_url
]);

echo json_encode([
    "status" => "ok",
    "inserted_id" => $db->lastInsertId()
]);