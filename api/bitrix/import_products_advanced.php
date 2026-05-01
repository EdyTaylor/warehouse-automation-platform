<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require __DIR__ . '/send.php';
require_once __DIR__ . '/../../functions/integration_sync_control.php';

$db = getDB();
integrationAbortJsonIfAllSyncPaused($db);
$cfg = require __DIR__ . '/config.php';

$method = isset($cfg['product_list_method']) ? $cfg['product_list_method'] : 'crm.product.list';
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$syncPrices = isset($_GET['sync_prices']) ? intval($_GET['sync_prices']) : 0;

// Построение фильтра для Б24
$filter = [];
if ($categoryId > 0) {
    $filter['CATALOG_ID'] = $categoryId;
}
if (!empty($search)) {
    $filter['?NAME'] = $search;
}

$payload = [
    'start' => $start,
    'filter' => $filter
];

$resp = sendToBitrix($method, $payload);

if (!is_array($resp)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Bitrix response is not JSON',
        'method' => $method
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($resp['error'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Bitrix method failed',
        'method' => $method,
        'bitrix' => $resp,
        'hint' => 'Add crm.product.list incoming webhook URL into api/bitrix/config.php -> method_urls'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = [];
if (isset($resp['result']) && is_array($resp['result'])) {
    $items = $resp['result'];
}

$ins = $db->prepare("
    INSERT INTO products
    (name, roll_length, price_per_meter, b24_product_id, catalog_id, description)
    VALUES (?, 30, ?, ?, ?, ?)
");
$upd = $db->prepare("
    UPDATE products
    SET name = ?, price_per_meter = ?, catalog_id = ?, description = ?
    WHERE b24_product_id = ?
");
$sel = $db->prepare("SELECT id FROM products WHERE b24_product_id = ?");

$created = 0;
$updated = 0;
$seen = 0;
$priceUpdates = 0;

foreach ($items as $item) {
    $b24Id = isset($item['ID']) ? intval($item['ID']) : 0;
    $name = isset($item['NAME']) ? $item['NAME'] : '';
    $price = isset($item['PRICE']) ? floatval($item['PRICE']) : 0;
    $description = isset($item['DESCRIPTION']) ? $item['DESCRIPTION'] : '';
    $catalogId = isset($item['CATALOG_ID']) ? intval($item['CATALOG_ID']) : 0;
    
    if ($b24Id <= 0) {
        continue;
    }

    $seen++;
    $sel->execute([$b24Id]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        // Обновляем существующий товар
        $currentPrice = 0;
        if ($syncPrices) {
            // Получаем текущую цену для сравнения
            $stmt = $db->prepare("SELECT price_per_meter FROM products WHERE b24_product_id = ?");
            $stmt->execute([$b24Id]);
            $currentProduct = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentPrice = $currentProduct ? floatval($currentProduct['price_per_meter']) : 0;
        }
        
        $upd->execute([$name, $price, $catalogId, $description, $b24Id]);
        $updated++;
        
        if ($syncPrices && abs($price - $currentPrice) > 0.01) {
            $priceUpdates++;
        }
    } else {
        // Создаем новый товар
        $ins->execute([$name, $price, $b24Id, $catalogId, $description]);
        $created++;
    }
}

echo json_encode([
    'status' => 'ok',
    'method' => $method,
    'filter' => $filter,
    'processed' => count($items),
    'seen' => $seen,
    'created' => $created,
    'updated' => $updated,
    'price_updates' => $priceUpdates,
    'next' => isset($resp['next']) ? $resp['next'] : null,
    'sync_prices' => $syncPrices
], JSON_UNESCAPED_UNICODE);
?>
