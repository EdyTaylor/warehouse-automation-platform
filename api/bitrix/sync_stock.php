<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(45);

require __DIR__ . '/../../db.php';
require __DIR__ . '/send.php';
require_once __DIR__ . '/../../functions/app_settings.php';

$db = getDB();
$cfg = require __DIR__ . '/config.php';

// By default this endpoint pushes available meters to Bitrix product field.
$field = isset($_GET['field']) ? $_GET['field'] : $cfg['product_available_field'];
$method = isset($_GET['method']) ? $_GET['method'] : $cfg['product_update_method'];
$push = isset($_GET['push']) ? intval($_GET['push']) : 1;
$pushStore = isset($_GET['push_store']) ? intval($_GET['push_store']) : 1;
$compare = isset($_GET['compare']) ? intval($_GET['compare']) : 0;
$storeId = isset($_GET['store_id']) ? intval($_GET['store_id']) : intval(getAppSetting($db, 'stock_sync_store_id', '0'));
if ($storeId <= 0) {
    $storeId = intval(getAppSetting($db, 'default_store_from_id', '1'));
}
if ($storeId <= 0) {
    $storeId = intval(getAppSetting($db, 'default_store_to_id', '1'));
}
if ($storeId <= 0) {
    $storeId = 1;
}
$offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
$defaultLimit = intval(getAppSetting($db, 'sync_batch_limit', '100'));
if ($defaultLimit <= 0) {
    $defaultLimit = 100;
}
$limit = isset($_GET['limit']) ? max(1, min(200, intval($_GET['limit']))) : max(1, min(200, $defaultLimit));
if ($push) {
    // Push mode is heavy (1 Bitrix call per product), keep batch small to avoid 504.
    $limit = min($limit, 35);
}
$startedAt = microtime(true);
$timeBudgetSec = 20.0;

$totalRow = $db->query("
    SELECT COUNT(*) as cnt
    FROM products p
    WHERE p.b24_product_id IS NOT NULL AND p.b24_product_id <> 0
")->fetch(PDO::FETCH_ASSOC);
$totalCount = $totalRow ? intval($totalRow['cnt']) : 0;

$rows = $db->query("
    SELECT
        p.id as product_id,
        p.name,
        p.b24_product_id,
        COALESCE(SUM(CASE WHEN r.reserved = 0 AND r.current_length > 0 AND r.status NOT IN ('sold','waste','written_off') THEN r.current_length ELSE 0 END), 0) as free_meters
    FROM products p
    LEFT JOIN rolls r ON r.product_id = p.id
    WHERE p.b24_product_id IS NOT NULL AND p.b24_product_id <> 0
    GROUP BY p.id, p.name, p.b24_product_id
    ORDER BY p.id ASC
    LIMIT {$limit} OFFSET {$offset}
")->fetchAll(PDO::FETCH_ASSOC);

$result = [
    'status' => 'ok',
    'count' => count($rows),
    'total_count' => $totalCount,
    'offset' => $offset,
    'limit' => $limit,
    'push' => $push ? true : false,
    'field' => $field,
    'method' => $method,
    'push_store' => $pushStore ? true : false,
    'compare' => $compare ? true : false,
    'store_id' => $storeId,
    'items' => [],
    'partial' => false,
    'next_offset' => null,
    'processed' => 0,
    'mismatch_count' => 0
];

foreach ($rows as $r) {
    if ((microtime(true) - $startedAt) >= $timeBudgetSec) {
        break;
    }
    $free = round(floatval($r['free_meters']), 2);

    $item = [
        'product_id' => intval($r['product_id']),
        'b24_product_id' => intval($r['b24_product_id']),
        'name' => $r['name'],
        'free_meters' => $free
    ];

    if ($push && $field && $method) {
        // We pass the computed stock into a custom field.
        // For crm.product.update format typically:
        // { "id": <b24_product_id>, "fields": { "<field>": <value> } }
        $payload = [
            'id' => intval($r['b24_product_id']),
            'fields' => [
                $field => $free
            ]
        ];

        $resp = sendToBitrix($method, $payload);
        $item['bitrix_status'] = (is_array($resp) && !isset($resp['error'])) ? 'ok' : 'error';
        if (is_array($resp) && isset($resp['error'])) {
            $item['bitrix_error'] = isset($resp['error_description']) ? $resp['error_description'] : $resp['error'];
        }
    }

    if ($push && $pushStore && $storeId > 0) {
        $b24ProductId = intval($r['b24_product_id']);
        $existingStoreProductId = 0;

        $listResp = sendToBitrix('catalog.storeproduct.list', [
            'filter' => [
                'productId' => $b24ProductId,
                'storeId' => $storeId
            ],
            'select' => ['id', 'amount', 'productId', 'storeId']
        ]);
        if (is_array($listResp) && !isset($listResp['error']) && isset($listResp['result']) && is_array($listResp['result'])) {
            $rowsStore = $listResp['result'];
            if (isset($rowsStore['items']) && is_array($rowsStore['items'])) {
                $rowsStore = $rowsStore['items'];
            }
            if (!empty($rowsStore[0]) && is_array($rowsStore[0])) {
                $existingStoreProductId = intval(isset($rowsStore[0]['id']) ? $rowsStore[0]['id'] : 0);
            }
        }

        if ($existingStoreProductId > 0) {
            $storeResp = sendToBitrix('catalog.storeproduct.update', [
                'id' => $existingStoreProductId,
                'fields' => [
                    'amount' => $free
                ]
            ]);
        } else {
            $storeResp = sendToBitrix('catalog.storeproduct.add', [
                'fields' => [
                    'productId' => $b24ProductId,
                    'storeId' => $storeId,
                    'amount' => $free
                ]
            ]);
        }

        $item['bitrix_store_status'] = (is_array($storeResp) && !isset($storeResp['error'])) ? 'ok' : 'error';
        if (is_array($storeResp) && isset($storeResp['error'])) {
            $item['bitrix_store_error'] = isset($storeResp['error_description']) ? $storeResp['error_description'] : $storeResp['error'];
        }
    }

    if ($compare && $storeId > 0) {
        $b24ProductId = intval($r['b24_product_id']);
        $storeListResp = sendToBitrix('catalog.storeproduct.list', [
            'filter' => [
                'productId' => $b24ProductId,
                'storeId' => $storeId
            ],
            'select' => ['id', 'amount', 'productId', 'storeId']
        ]);
        $b24StoreAmount = null;
        if (is_array($storeListResp) && !isset($storeListResp['error']) && isset($storeListResp['result']) && is_array($storeListResp['result'])) {
            $rowsStore = $storeListResp['result'];
            if (isset($rowsStore['items']) && is_array($rowsStore['items'])) {
                $rowsStore = $rowsStore['items'];
            }
            if (!empty($rowsStore[0]) && is_array($rowsStore[0])) {
                $b24StoreAmount = floatval(isset($rowsStore[0]['amount']) ? $rowsStore[0]['amount'] : 0);
            }
        }

        if ($b24StoreAmount === null) {
            $item['compare_status'] = 'no_b24_store_row';
        } else {
            $delta = round($free - $b24StoreAmount, 2);
            $item['b24_store_amount'] = $b24StoreAmount;
            $item['compare_delta'] = $delta;
            $isMismatch = abs($delta) > 0.01;
            $item['compare_status'] = $isMismatch ? 'mismatch' : 'match';
            if ($isMismatch) {
                $result['mismatch_count']++;
            }
        }
    }

    $result['items'][] = $item;
    $result['processed']++;
}

$nextOffset = $offset + $result['processed'];
if ($nextOffset < $totalCount) {
    $result['partial'] = true;
    $result['next_offset'] = $nextOffset;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);

