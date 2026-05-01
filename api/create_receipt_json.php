<?php
/**
 * JSON API: один приход (в т.ч. разовый большой) → локальный склад + складской документ в Б24.
 *
 * Строки без привязки к прайсу LLumar: укажите b24_product_id (ID товара в каталоге Б24), qty_rolls,
 * roll_length, цены за рулон; product_id можно не задавать — локальная запись products создастся/найдётся сама.
 *
 * Безопасность: ключ app_settings stock_receipt_api_secret, заголовок X-Stock-Receipt-Secret (или ?secret= для отладки).
 *
 *   curl -X POST "https://ваш-сайт/api/create_receipt_json.php" \
 *     -H "Content-Type: application/json; charset=utf-8" \
 *     -H "X-Stock-Receipt-Secret: ВАШ_КЛЮЧ" \
 *     -d @example/new/bulk_receipt_once_b24.example.json
 */

ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/functions/stock_movements.php';
require_once dirname(__DIR__) . '/api/bitrix/send.php';
require_once dirname(__DIR__) . '/functions/app_settings.php';
require_once dirname(__DIR__) . '/includes/stock_operations_core.php';

$db = getDB();
ensureStockOperationTables($db);

$expected = trim((string)getAppSetting($db, 'stock_receipt_api_secret', ''));
$hdr = isset($_SERVER['HTTP_X_STOCK_RECEIPT_SECRET']) ? trim((string)$_SERVER['HTTP_X_STOCK_RECEIPT_SECRET']) : '';
$q = isset($_GET['secret']) ? trim((string)$_GET['secret']) : '';

if ($expected === '') {
    http_response_code(503);
    echo json_encode(array(
        'ok' => false,
        'error' => 'В настройках приложения не задан stock_receipt_api_secret (app_settings или sync_monitor при наличии).'
    ));
    exit;
}

if ($hdr !== $expected && $q !== $expected) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Forbidden'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'error' => 'Use POST with JSON body'));
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => 'Invalid JSON'));
    exit;
}

$params = array(
    'doc_number' => isset($data['doc_number']) ? $data['doc_number'] : '',
    'supplier' => isset($data['supplier']) ? $data['supplier'] : '',
    'comment_text' => isset($data['comment_text']) ? $data['comment_text'] : '',
    'receipt_currency' => isset($data['receipt_currency']) ? $data['receipt_currency'] : 'USD',
    'min_full' => isset($data['min_full']) ? $data['min_full'] : 0.5,
    'lines' => isset($data['lines']) && is_array($data['lines']) ? $data['lines'] : array(),
);

$result = stockOperationsProcessCreateReceiptPayload($db, $params);

$response = array(
    'ok' => !empty($result['ok']),
    'doc_id' => isset($result['doc_id']) ? $result['doc_id'] : null,
    'b24_document_id' => isset($result['b24_document_id']) ? $result['b24_document_id'] : null,
    'sync_status' => isset($result['sync_status']) ? $result['sync_status'] : null,
    'usd_to_kgs_rate' => isset($result['usd_to_kgs_rate']) ? $result['usd_to_kgs_rate'] : null,
    'total_amount_kgs' => isset($result['total_amount_kgs']) ? $result['total_amount_kgs'] : null,
    'success_message' => isset($result['success_message']) ? $result['success_message'] : '',
    'error_message' => isset($result['error_message']) ? $result['error_message'] : '',
);

if (!$response['ok'] && isset($result['sync_result'])) {
    $response['sync_result'] = $result['sync_result'];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
