<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(30);

require __DIR__ . '/../../db.php';
require __DIR__ . '/send.php';

$db = getDB();
$cfg = require __DIR__ . '/config.php';

// By default this endpoint pushes available meters to Bitrix product field.
$field = isset($_GET['field']) ? $_GET['field'] : $cfg['product_available_field'];
$method = isset($_GET['method']) ? $_GET['method'] : $cfg['product_update_method'];
$push = isset($_GET['push']) ? intval($_GET['push']) : 1;
$offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
$limit = isset($_GET['limit']) ? max(1, min(200, intval($_GET['limit']))) : 50;

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
    'items' => [],
    'partial' => false,
    'next_offset' => null,
    'processed' => 0
];

foreach ($rows as $r) {
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

    $result['items'][] = $item;
    $result['processed']++;
}

$nextOffset = $offset + $result['processed'];
if ($nextOffset < $totalCount) {
    $result['partial'] = true;
    $result['next_offset'] = $nextOffset;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);

