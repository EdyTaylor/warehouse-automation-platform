<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(30);

require '../db.php';
require_once 'bitrix/send.php';
require_once __DIR__ . '/../functions/integration_sync_control.php';

$db = getDB();
integrationAbortJsonIfAllSyncPaused($db);
$cfg = require 'bitrix/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'to_app'; // to_app | to_b24
$offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
$limit = isset($_GET['limit']) ? max(1, min(200, intval($_GET['limit']))) : 40;

function getAllowedCatalogIds($cfg) {
    if (!isset($cfg['sync_catalog_ids']) || !is_array($cfg['sync_catalog_ids'])) {
        return [];
    }
    $ids = [];
    foreach ($cfg['sync_catalog_ids'] as $id) {
        $id = intval($id);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return $ids;
}

if ($action === 'to_b24') {
    // Синхронизация цен из приложения в Б24
    $totalRow = $db->query("
        SELECT COUNT(*) as cnt
        FROM products 
        WHERE b24_product_id IS NOT NULL 
          AND b24_product_id > 0
          AND price_per_meter > 0
    ")->fetch(PDO::FETCH_ASSOC);
    $totalCount = $totalRow ? intval($totalRow['cnt']) : 0;

    $stmt = $db->query("
        SELECT id, name, price_per_meter, b24_product_id
        FROM products 
        WHERE b24_product_id IS NOT NULL 
          AND b24_product_id > 0
          AND price_per_meter > 0
        ORDER BY id ASC
        LIMIT " . intval($limit) . " OFFSET " . intval($offset)
    ");
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    $updatedByCatalogMethod = 0;
    $errors = [];
    $skipped = [];
    
    foreach ($products as $product) {
        $localPrice = floatval($product['price_per_meter']);
        if ($localPrice <= 0) {
            $skipped[] = [
                'product_id' => intval($product['id']),
                'name' => $product['name'],
                'reason' => 'price_per_meter <= 0'
            ];
            continue;
        }

        $payload = [
            'id' => intval($product['b24_product_id']),
            'fields' => [
                'PRICE' => $localPrice,
                'CURRENCY_ID' => 'KGS'
            ]
        ];
        
        $resp = sendToBitrix('crm.product.update', $payload);
        
        if (isset($resp['error'])) {
            // Fallback for portals where price is managed by catalog product API.
            $fallbackResp = sendToBitrix('catalog.product.update', [
                'id' => intval($product['b24_product_id']),
                'fields' => [
                    'price' => $localPrice,
                    'currencyId' => 'KGS'
                ]
            ]);
            if (is_array($fallbackResp) && !isset($fallbackResp['error'])) {
                $updatedByCatalogMethod++;
                $updated++;
            } else {
                $errors[] = [
                    'product_id' => intval($product['id']),
                    'name' => $product['name'],
                    'b24_product_id' => intval($product['b24_product_id']),
                    'error' => isset($resp['error_description']) ? $resp['error_description'] : (isset($resp['error']) ? $resp['error'] : 'crm.product.update failed'),
                    'fallback_error' => is_array($fallbackResp)
                        ? (isset($fallbackResp['error_description']) ? $fallbackResp['error_description'] : (isset($fallbackResp['error']) ? $fallbackResp['error'] : 'catalog.product.update failed'))
                        : 'catalog.product.update failed'
                ];
            }
        } else {
            $updated++;
        }
    }
    
    $nextOffset = $offset + count($products);
    echo json_encode([
        'status' => 'ok',
        'action' => 'to_b24',
        'processed' => count($products),
        'total_count' => $totalCount,
        'offset' => $offset,
        'limit' => $limit,
        'partial' => $nextOffset < $totalCount,
        'next_offset' => $nextOffset < $totalCount ? $nextOffset : null,
        'updated' => $updated,
        'updated_via_catalog_product_update' => $updatedByCatalogMethod,
        'errors_count' => count($errors),
        'errors' => $errors,
        'skipped_count' => count($skipped),
        'skipped' => $skipped
    ], JSON_UNESCAPED_UNICODE);
    
} elseif ($action === 'to_app') {
    // Синхронизация цен из Б24 в приложение
    $method = isset($cfg['product_list_method']) ? $cfg['product_list_method'] : 'crm.product.list';
    $start = intval(isset($_GET['start']) ? $_GET['start'] : $offset);
    
    $payload = ['start' => $start];
    $allowedCatalogIds = getAllowedCatalogIds($cfg);
    if (!empty($allowedCatalogIds)) {
        $payload['filter'] = ['@CATALOG_ID' => $allowedCatalogIds];
    }
    $resp = sendToBitrix($method, $payload);
    
    if (!is_array($resp) || isset($resp['error'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Bitrix API error',
            'bitrix' => $resp
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $items = isset($resp['result']) && is_array($resp['result']) ? $resp['result'] : [];
    $skipped = 0;
    $updated = 0;
    
    foreach ($items as $item) {
        $b24Id = intval(isset($item['ID']) ? $item['ID'] : 0);
        $price = floatval(isset($item['PRICE']) ? $item['PRICE'] : 0);
        
        if ($b24Id <= 0 || $price <= 0) {
            $skipped++;
            continue;
        }
        
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
        'offset' => $offset,
        'limit' => $limit,
        'updated' => $updated,
        'skipped' => $skipped,
        'partial' => isset($resp['next']),
        'next_offset' => isset($resp['next']) ? intval($resp['next']) : null
    ], JSON_UNESCAPED_UNICODE);
    
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid action. Use: to_app or to_b24'
    ], JSON_UNESCAPED_UNICODE);
}
?>
