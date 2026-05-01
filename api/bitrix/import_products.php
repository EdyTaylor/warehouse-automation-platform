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
$maxPages = isset($_GET['pages']) ? intval($_GET['pages']) : 20;
if ($maxPages <= 0) {
    $maxPages = 20;
}

$ins = $db->prepare("
    INSERT INTO products
    (name, roll_length, price_per_meter, b24_product_id)
    VALUES (?, 30, 0, ?)
");
$upd = $db->prepare("
    UPDATE products
    SET name = ?
    WHERE b24_product_id = ?
");
$sel = $db->prepare("SELECT id FROM products WHERE b24_product_id = ?");

$created = 0;
$updated = 0;
$seen = 0;
$currentStart = $start;
$pages = 0;

while ($pages < $maxPages) {
    $payload = ['start' => $currentStart];
    if (isset($cfg['sync_catalog_ids']) && is_array($cfg['sync_catalog_ids'])) {
        $allowedCatalogIds = [];
        foreach ($cfg['sync_catalog_ids'] as $catalogId) {
            $catalogId = intval($catalogId);
            if ($catalogId > 0) {
                $allowedCatalogIds[] = $catalogId;
            }
        }
        if (!empty($allowedCatalogIds)) {
            $payload['filter'] = ['@CATALOG_ID' => $allowedCatalogIds];
        }
    }
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

    foreach ($items as $item) {
        $b24Id = isset($item['ID']) ? intval($item['ID']) : 0;
        $name = isset($item['NAME']) ? $item['NAME'] : '';
        if ($b24Id <= 0) {
            continue;
        }

        $seen++;
        $sel->execute([$b24Id]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $upd->execute([$name, $b24Id]);
            $updated++;
        } else {
            $ins->execute([$name, $b24Id]);
            $created++;
        }
    }

    $pages++;

    if (isset($resp['next'])) {
        $currentStart = intval($resp['next']);
    } else {
        break;
    }
}

echo json_encode([
    'status' => 'ok',
    'method' => $method,
    'pages_processed' => $pages,
    'seen' => $seen,
    'created' => $created,
    'updated' => $updated,
    'next' => isset($resp['next']) ? $resp['next'] : null
], JSON_UNESCAPED_UNICODE);

