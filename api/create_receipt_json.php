<?php
/**
 * JSON API: один приход (в т.ч. разовый большой) → локальный склад + складской документ в Б24.
 *
 * Строки массового прихода LLumar и др.: нужны b24_product_id (ID в каталоге Б24), qty_rolls, roll_length, цены за рулон.
 * Поле product_name рекомендуется для LLumar (генератор снова пишет имя матча из прайса): оно имеет приоритет над локальным name при приходе
 * и обновляет строку каталога. Без product_name имя возьмётся из БД или из Битрикс (crm.product.get).
 * Если b24_product_id задан, строка всегда резолвится по нему — локальный product_id
 * в JSON при наличии ошибки игнорируется. product_id без b24 — необязателен (подбор по имени или новая карточка).
 *
 * Безопасность: ключ app_settings stock_receipt_api_secret (задаётся в sync_monitor.php), заголовок
 * X-Stock-Receipt-Secret или для отладки тот же ключ в query ?secret=
 *
 * Для больших приходов: по умолчанию при ≥ строк (app_settings stock_receipt_b24_worker_min_lines, по умолчанию 2) синк Б24
 * уходит в фоновый запрос api/stock_operation_b24_worker.php (ответ HTTP не ждёт проведения — нет 504 у nginx).
 * Либо добавьте в JSON: "local_only": true — без документа в Б24 вообще.
 * Чанковый режим (несколько документов подряд, короче один HTTP-ответ): "lines_per_chunk": 28 (или 25–80),
 * опционально "max_roll_units_per_chunk": 350 (не более суммы qty_rolls в партии); 0 там = брать безопасный дефолт.
 * При чанках doc_number пустой → стабильные ACHK-…-C1ofN по хешу тела; заданный doc_number → суффиксы -C1ofN (до 64 символов).
 * Без чанков и без doc_number подставится AUTOAPI-<sha256 RAW body>. Повтор того же POST — идемпотентность по номеру.
 *
 * Важно: только POST с телом JSON. Обычный GET из адресной строки не выполнит приход (будет 405).
 *
 * Пример curl (Linux / Git Bash):
 *   curl -X POST "https://ваш-сайт/api/create_receipt_json.php" \
 *     -H "Content-Type: application/json; charset=utf-8" \
 *     -H "X-Stock-Receipt-Secret: ВАШ_КЛЮЧ" \
 *     --data-binary @example/new/bulk_receipt_from_llumar.generated.json
 *
 * PowerShell:
 *   $u = "https://ваш-сайт/api/create_receipt_json.php"
 *   $k = "ВАШ_КЛЮЧ"
 *   $body = Get-Content -Raw -Encoding UTF8 "example\new\bulk_receipt_from_llumar.generated.json"
 *   Invoke-RestMethod -Uri $u -Method Post -Body $body -ContentType "application/json; charset=utf-8" -Headers @{ "X-Stock-Receipt-Secret" = $k }
 */

ini_set('display_errors', 0);
@ini_set('max_execution_time', '0');
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/functions/stock_movements.php';
require_once dirname(__DIR__) . '/api/bitrix/send.php';
require_once dirname(__DIR__) . '/functions/app_settings.php';
require_once dirname(__DIR__) . '/includes/stock_operations_core.php';

$db = getDB();

require_once dirname(__DIR__) . '/functions/stock_emergency_kill.php';
$emergencyKillCreates = stockEmergencyRollCreationStoppedMessage($db);
if ($emergencyKillCreates !== '') {
    http_response_code(503);
    echo json_encode(array(
        'ok' => false,
        'emergency_blocked' => true,
        'error_message' => $emergencyKillCreates,
    ));
    exit;
}
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

$lpc = isset($data['lines_per_chunk']) ? intval($data['lines_per_chunk']) : 0;
$mruChunk = isset($data['max_roll_units_per_chunk']) ? intval($data['max_roll_units_per_chunk']) : 0;
$wantChunkPreview = stockOperationsReceiptNormalizeChunkOptions($lpc, $mruChunk);

$dnIn = isset($data['doc_number']) ? trim((string)$data['doc_number']) : '';
if ($dnIn === '' && !$wantChunkPreview['active']) {
    $data['doc_number'] = 'AUTOAPI-' . substr(hash('sha256', $raw), 0, 40);
} elseif ($dnIn === '' && $wantChunkPreview['active']) {
    $data['doc_number'] = '';
}

$params = array(
    'doc_number' => isset($data['doc_number']) ? $data['doc_number'] : '',
    'supplier' => isset($data['supplier']) ? $data['supplier'] : '',
    'comment_text' => isset($data['comment_text']) ? $data['comment_text'] : '',
    'receipt_currency' => isset($data['receipt_currency']) ? $data['receipt_currency'] : 'USD',
    'min_full' => isset($data['min_full']) ? $data['min_full'] : 0.5,
    'lines' => isset($data['lines']) && is_array($data['lines']) ? $data['lines'] : array(),
    'local_only' => !empty($data['local_only']),
);

$response = array();

if ($wantChunkPreview['active']) {
    $templateChunk = array(
        'doc_number' => $params['doc_number'],
        'supplier' => $params['supplier'],
        'comment_text' => $params['comment_text'],
        'receipt_currency' => $params['receipt_currency'],
        'min_full' => $params['min_full'],
        'local_only' => $params['local_only'],
    );
    $wrap = stockOperationsRunChunkedReceiptFromPayload(
        $db,
        $templateChunk,
        $params['lines'],
        $lpc,
        $mruChunk,
        $raw
    );
    $response['ok'] = !empty($wrap['ok']);
    $response['chunked'] = true;
    $response['chunks_total'] = isset($wrap['chunks_total']) ? (int)$wrap['chunks_total'] : 0;
    $response['chunks_completed'] = isset($wrap['chunks_completed']) ? (int)$wrap['chunks_completed'] : 0;
    $response['doc_ids'] = isset($wrap['doc_ids']) && is_array($wrap['doc_ids']) ? $wrap['doc_ids'] : array();
    $response['duplicate_receipt_skips'] = isset($wrap['duplicate_receipt_skips']) ? (int)$wrap['duplicate_receipt_skips'] : 0;
    $response['chunk_results'] = isset($wrap['results']) ? $wrap['results'] : array();
    $response['error_message'] = isset($wrap['error_message']) ? (string)$wrap['error_message'] : '';
    $lastIdx = count($response['chunk_results']) - 1;
    $lastUsd = ($lastIdx >= 0 && isset($response['chunk_results'][$lastIdx]['usd_to_kgs_rate']))
        ? $response['chunk_results'][$lastIdx]['usd_to_kgs_rate'] : null;
    $response['usd_to_kgs_rate'] = $lastUsd;
    $response['success_message'] = $response['ok']
        ? ('Приход выполнен частями: ' . $response['chunks_completed'] . ' документ(ов); id: ' . implode(', ', array_map('intval', $response['doc_ids'])) . '.')
        : '';
    $response['doc_id'] = !empty($response['doc_ids']) ? intval($response['doc_ids'][0]) : null;
    $response['duplicate_receipt_skip'] = ($response['duplicate_receipt_skips'] > 0
        && (int)$response['duplicate_receipt_skips'] === (int)$response['chunks_completed']
        && (int)$response['chunks_completed'] > 0);
} else {
    $result = stockOperationsProcessCreateReceiptPayload($db, $params);

    $response = array(
        'ok' => !empty($result['ok']),
        'chunked' => false,
        'doc_id' => isset($result['doc_id']) ? $result['doc_id'] : null,
        'b24_document_id' => isset($result['b24_document_id']) ? $result['b24_document_id'] : null,
        'sync_status' => isset($result['sync_status']) ? $result['sync_status'] : null,
        'duplicate_receipt_skip' => !empty($result['duplicate_receipt_skip']),
        'usd_to_kgs_rate' => isset($result['usd_to_kgs_rate']) ? $result['usd_to_kgs_rate'] : null,
        'total_amount_kgs' => isset($result['total_amount_kgs']) ? $result['total_amount_kgs'] : null,
        'success_message' => isset($result['success_message']) ? $result['success_message'] : '',
        'error_message' => isset($result['error_message']) ? $result['error_message'] : '',
        'b24_background_queued' => !empty($result['b24_background_queued']),
    );

    if (!$response['ok'] && isset($result['sync_result'])) {
        $response['sync_result'] = $result['sync_result'];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
