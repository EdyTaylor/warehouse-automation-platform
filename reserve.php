<?php
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'error' => 'LEGACY_ENDPOINT_DISABLED',
    'message' => 'Резерв выполняется только через b24_sales.php (ручная очередь кладовщика).'
]);