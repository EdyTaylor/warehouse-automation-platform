<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/send.php';

$cfg = require __DIR__ . '/config.php';

// Метод для получения каталогов товаров
$method = 'crm.catalog.list';

$payload = [];
$resp = sendToBitrix($method, $payload);

if (!is_array($resp)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Bitrix response is not JSON'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($resp['error'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to get catalogs',
        'bitrix' => $resp
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$catalogs = [];
if (isset($resp['result']) && is_array($resp['result'])) {
    $catalogs = $resp['result'];
}

echo json_encode([
    'status' => 'ok',
    'catalogs' => $catalogs
], JSON_UNESCAPED_UNICODE);
?>
