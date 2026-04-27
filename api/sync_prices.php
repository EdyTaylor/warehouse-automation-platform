<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require '../db.php';
require_once 'bitrix/send.php';

$db = getDB();
$cfg = require 'bitrix/config.php';

$action = $_GET['action'] ?? 'to_app'; // to_app | to_b24

if ($action === 'to_b24') {
    // Синхронизация цен из приложения в Б24
    $stmt = $db->query("
        SELECT id, name, price_per_meter, b24_product_id 
        FROM products 
        WHERE b24_product_id IS NOT NULL 
        AND b24_product_id > 0
        AND price_per_meter > 0
    ");
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    $errors = [];
    
    foreach ($products as $product) {
        $payload = [
            'id' => intval($product['b24_product_id']),
            'fields' => [
                'PRICE' => floatval($product['price_per_meter'])
            ]
        ];
        
        $resp = sendToBitrix('crm.product.update', $payload);
        
        if (isset($resp['error'])) {
            $errors[] = [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'error' => $resp['error']
            ];
        } else {
            $updated++;
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'action' => 'to_b24',
        'processed' => count($products),
        'updated' => $updated,
        'errors' => $errors
    ], JSON_UNESCAPED_UNICODE);
    
} elseif ($action === 'to_app') {
    // Синхронизация цен из Б24 в приложение
    $method = $cfg['product_list_method'] ?? 'crm.product.list';
    $start = intval($_GET['start'] ?? 0);
    
    $payload = ['start' => $start];
    $resp = sendToBitrix($method, $payload);
    
    if (!is_array($resp) || isset($resp['error'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Bitrix API error',
            'bitrix' => $resp
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $items = $resp['result'] ?? [];
    $updated = 0;
    
    foreach ($items as $item) {
        $b24Id = intval($item['ID'] ?? 0);
        $price = floatval($item['PRICE'] ?? 0);
        
        if ($b24Id <= 0 || $price <= 0) continue;
        
        $stmt = $db->prepare("
            UPDATE products 
            SET price_per_meter = ? 
            WHERE b24_product_id = ?
        ");
        $result = $stmt->execute([$price, $b24Id]);
        
        if ($result && $stmt->rowCount() > 0) {
            $updated++;
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'action' => 'to_app',
        'processed' => count($items),
        'updated' => $updated,
        'next' => $resp['next'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid action. Use: to_app or to_b24'
    ], JSON_UNESCAPED_UNICODE);
}
?>
