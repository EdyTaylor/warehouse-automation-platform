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
$maxSeconds = isset($_GET['max_seconds']) ? max(5, intval($_GET['max_seconds'])) : 20;

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
")->fetchAll(PDO::FETCH_ASSOC);

$result = [
    'status' => 'ok',
    'count' => count($rows),
    'push' => $push ? true : false,
    'field' => $field,
    'method' => $method,
    'items' => [],
    'partial' => false,
    'processed' => 0
];

$startedAt = microtime(true);
foreach ($rows as $r) {
    if ((microtime(true) - $startedAt) >= $maxSeconds) {
        $result['partial'] = true;
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

    $result['items'][] = $item;
    $result['processed']++;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);

