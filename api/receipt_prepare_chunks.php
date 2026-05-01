<?php
/**
 * Планирует массив тел для POST api/create_receipt_json.php без чанк-ключей линковки.
 * Тот же секрет, что у create_receipt_json. POST, тело — полный JSON прихода
 * с lines_per_chunk / max_roll_units_per_chunk (>0 включают разбиение на сервере).
 */
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/functions/app_settings.php';
require_once dirname(__DIR__) . '/includes/stock_operations_core.php';

$db = getDB();

$expected = trim((string)getAppSetting($db, 'stock_receipt_api_secret', ''));
$hdr = isset($_SERVER['HTTP_X_STOCK_RECEIPT_SECRET']) ? trim((string)$_SERVER['HTTP_X_STOCK_RECEIPT_SECRET']) : '';
$q = isset($_GET['secret']) ? trim((string)$_GET['secret']) : '';

if ($expected === '') {
    http_response_code(503);
    echo json_encode(array('ok' => false, 'error' => 'stock_receipt_api_secret не задан'));
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

$lpc = isset($data['lines_per_chunk']) ? intval($data['lines_per_chunk']) : 0;
$mruChunk = isset($data['max_roll_units_per_chunk']) ? intval($data['max_roll_units_per_chunk']) : 0;
$norm = stockOperationsReceiptNormalizeChunkOptions($lpc, $mruChunk);
if (!$norm['active']) {
    http_response_code(400);
    echo json_encode(array(
        'ok' => false,
        'error' => 'Укажите lines_per_chunk > 0 (как в форме или в JSON).',
    ));
    exit;
}

$lines = isset($data['lines']) && is_array($data['lines']) ? $data['lines'] : array();
$segments = stockOperationsReceiptFlattenLineSegments($lines, $norm['max_roll_piece']);
$chunks = stockOperationsReceiptPackSegmentsIntoChunks(
    $segments,
    $norm['lines_per_chunk'],
    $norm['max_roll_units']
);

if (empty($chunks)) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => 'Нет строк прихода (qty_rolls / lines).'));
    exit;
}

$baseDn = isset($data['doc_number']) ? trim((string)$data['doc_number']) : '';
$seed = trim((string)$raw);
$chunkTotal = count($chunks);
$payloads = array();

foreach ($chunks as $ci => $chunkLines) {
    $docNum = stockReceiptDocNumberForChunk($baseDn, intval($ci), $chunkTotal, $seed !== '' ? $seed : $baseDn . '|' . $chunkTotal);

    $commentBase = isset($data['comment_text']) ? trim((string)$data['comment_text']) : '';
    $partNote = '[Часть ' . ($ci + 1) . '/' . $chunkTotal . ' (браузер), ' . gmdate('Y-m-d\\TH:i:s\\Z') . '] ';
    $commentMerged = trim($commentBase !== '' ? ($commentBase . ' ' . $partNote) : $partNote);

    $payloads[] = array(
        'doc_number' => $docNum,
        'supplier' => isset($data['supplier']) ? $data['supplier'] : '',
        'comment_text' => $commentMerged,
        'receipt_currency' => isset($data['receipt_currency']) ? $data['receipt_currency'] : 'USD',
        'min_full' => isset($data['min_full']) ? $data['min_full'] : 0.5,
        'lines' => $chunkLines,
        'local_only' => !empty($data['local_only']),
    );
}

echo json_encode(array(
    'ok' => true,
    'chunks_total' => count($payloads),
    'lines_per_chunk' => $norm['lines_per_chunk'],
    'max_roll_units' => $norm['max_roll_units'],
    'payloads' => $payloads,
), JSON_UNESCAPED_UNICODE);
exit;
