<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require '../db.php';
$db = getDB();

// Получаем данные от Битрикс24
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['error' => 'No data received']);
    exit;
}

$event = $data['event'] ?? '';
$auth = $data['auth'] ?? [];

// Логируем все входящие вебхуки для отладки
$stmt = $db->prepare("
    INSERT INTO webhook_log (event, data, created_at)
    VALUES (?, ?, NOW())
");
$stmt->execute([$event, json_encode($data, JSON_UNESCAPED_UNICODE)]);

switch ($event) {
    case 'ONCRMDEALADD':
        // Новый сделка в Б24
        handleNewDeal($db, $data);
        break;
        
    case 'ONCRMDEALUPDATE':
        // Обновление сделки
        handleDealUpdate($db, $data);
        break;
        
    case 'ONCRMPRODUCTADD':
        // Новый товар
        handleNewProduct($db, $data);
        break;
        
    case 'ONCRMPRODUCTUPDATE':
        // Обновление товара
        handleProductUpdate($db, $data);
        break;
        
    default:
        echo json_encode(['status' => 'unknown_event', 'event' => $event]);
        exit;
}

function handleNewDeal($db, $data) {
    $deal = $data['data'] ?? [];
    
    if (empty($deal)) {
        echo json_encode(['error' => 'No deal data']);
        exit;
    }
    
    $dealId = intval($deal['ID'] ?? 0);
    $dealName = $deal['TITLE'] ?? '';
    $responsibleId = $deal['ASSIGNED_BY_ID'] ?? '';
    
    if ($dealId <= 0) {
        echo json_encode(['error' => 'Invalid deal ID']);
        exit;
    }
    
    // Получаем информацию о ответственном
    $responsible = getUserName($db, $responsibleId);
    
    // Получаем товары в сделке
    $products = getDealProducts($db, $dealId);
    
    if (!empty($products)) {
        // Создаем заказ в системе
        require_once __DIR__ . '/../api/bitrix/deal.php';
        
        $dealData = [
            'deal_id' => $dealId,
            'deal_name' => $dealName,
            'responsible' => $responsible,
            'products' => $products
        ];
        
        // Имитируем POST запрос для deal.php
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = json_decode(json_encode($dealData), true);
        
        // Вызываем обработчик сделки
        ob_start();
        include __DIR__ . '/../api/bitrix/deal.php';
        $response = ob_get_clean();
        
        echo json_encode(['status' => 'deal_processed', 'deal_id' => $dealId]);
    } else {
        echo json_encode(['status' => 'no_products', 'deal_id' => $dealId]);
    }
}

function handleDealUpdate($db, $data) {
    $deal = $data['data'] ?? [];
    $dealId = intval($deal['ID'] ?? 0);
    
    if ($dealId <= 0) {
        echo json_encode(['error' => 'Invalid deal ID']);
        exit;
    }
    
    // Получаем актуальные товары и обновляем заказ
    $products = getDealProducts($db, $dealId);
    
    if (!empty($products)) {
        // Обновляем существующий заказ
        $stmt = $db->prepare("
            UPDATE b24_sale_requests 
            SET status = 'new', updated_at = NOW()
            WHERE b24_deal_id = ?
        ");
        $stmt->execute([$dealId]);
        
        echo json_encode(['status' => 'deal_updated', 'deal_id' => $dealId]);
    } else {
        echo json_encode(['status' => 'no_products', 'deal_id' => $dealId]);
    }
}

function handleNewProduct($db, $data) {
    $product = $data['data'] ?? [];
    
    if (empty($product)) {
        echo json_encode(['error' => 'No product data']);
        exit;
    }
    
    $productId = intval($product['ID'] ?? 0);
    $productName = $product['NAME'] ?? '';
    $productPrice = floatval($product['PRICE'] ?? 0);
    
    if ($productId <= 0) {
        echo json_encode(['error' => 'Invalid product ID']);
        exit;
    }
    
    // Добавляем товар в локальную БД
    $stmt = $db->prepare("
        INSERT INTO products (name, roll_length, price_per_meter, b24_product_id)
        VALUES (?, 30, ?, ?)
        ON DUPLICATE KEY UPDATE 
            name = VALUES(name),
            price_per_meter = VALUES(price_per_meter)
    ");
    $stmt->execute([$productName, $productPrice, $productId]);
    
    echo json_encode(['status' => 'product_added', 'product_id' => $productId]);
}

function handleProductUpdate($db, $data) {
    $product = $data['data'] ?? [];
    
    if (empty($product)) {
        echo json_encode(['error' => 'No product data']);
        exit;
    }
    
    $productId = intval($product['ID'] ?? 0);
    $productName = $product['NAME'] ?? '';
    $productPrice = floatval($product['PRICE'] ?? 0);
    
    if ($productId <= 0) {
        echo json_encode(['error' => 'Invalid product ID']);
        exit;
    }
    
    // Обновляем товар в локальной БД
    $stmt = $db->prepare("
        UPDATE products 
        SET name = ?, price_per_meter = ?
        WHERE b24_product_id = ?
    ");
    $stmt->execute([$productName, $productPrice, $productId]);
    
    echo json_encode(['status' => 'product_updated', 'product_id' => $productId]);
}

function getUserName($db, $userId) {
    // Здесь можно добавить получение имени пользователя из Б24
    // Пока возвращаем ID
    return "User {$userId}";
}

function getDealProducts($db, $dealId) {
    require_once __DIR__ . '/bitrix/send.php';
    
    $cfg = require __DIR__ . '/bitrix/config.php';
    $method = $cfg['method_urls']['crm.deal.productrows.get'] ?? null;
    
    if (!$method) {
        return [];
    }
    
    $payload = ['id' => $dealId];
    $resp = sendToBitrix('crm.deal.productrows.get', $payload);
    
    if (!is_array($resp) || isset($resp['error'])) {
        return [];
    }
    
    $products = [];
    foreach (($resp['result'] ?? []) as $item) {
        $productId = intval($item['PRODUCT_ID'] ?? 0);
        $quantity = floatval($item['QUANTITY'] ?? 0);
        $price = floatval($item['PRICE'] ?? 0);
        $name = $item['PRODUCT_NAME'] ?? '';
        
        if ($productId > 0 && $quantity > 0) {
            $products[] = [
                'id' => $productId,
                'name' => $name,
                'quantity' => $quantity,
                'price' => $price
            ];
        }
    }
    
    return $products;
}

echo json_encode(['status' => 'processed']);
?>
