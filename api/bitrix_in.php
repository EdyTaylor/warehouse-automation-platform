<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db.php';
$db = getDB();

// Получаем JSON
$data = json_decode(file_get_contents('php://input'), true);

// Данные из Bitrix
$product_id = $data['product_id'];
$name = $data['name'];
$quantity = $data['quantity'];
$purchase_price = $data['purchase_price'];
$sale_price = $data['sale_price'];
$deal_id = $data['deal_id'];
$deal_name = $data['deal_name'];
$responsible = $data['responsible'];

// 1. Проверяем есть ли товар
$stmt = $db->prepare("SELECT id FROM products WHERE id = ?");
$stmt->execute([$product_id]);

if (!$stmt->fetch()) {
    // Добавляем товар если нет
    $stmt = $db->prepare("
        INSERT INTO products (id, name, purchase_price, price_1_4)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$product_id, $name, $purchase_price, $sale_price]);
}

// 2. Списание со склада (продажа)
$stmt = $db->prepare("
    INSERT INTO sales (product_id, type, quantity, price_per_unit, total, deal_id)
    VALUES (?, 'roll', ?, ?, ?, ?)
");

$total = $quantity * $sale_price;

$stmt->execute([$product_id, $quantity, $sale_price, $total, $deal_id]);

echo json_encode(["status" => "ok"]);