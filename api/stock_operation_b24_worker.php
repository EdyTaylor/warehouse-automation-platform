<?php
/**
 * Фоновый довод прихода/списания в Битрикс24 (создание документа, строки, проведение).
 * Вызывается из stockOperationsDispatchB24WarehouseWorker() через curl без ожидания в основном HTTP — обход 504 nginx.
 *
 * Безопасность: app_settings stock_operation_b24_worker_secret + query secret=.
 */
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
ensureStockOperationTables($db);

$docId = intval(isset($_GET['doc_id']) ? $_GET['doc_id'] : (isset($_REQUEST['doc_id']) ? $_REQUEST['doc_id'] : 0));
$secret = isset($_GET['secret']) ? trim((string)$_GET['secret']) : '';
$expected = trim((string)getAppSetting($db, 'stock_operation_b24_worker_secret', ''));

if ($docId <= 0 || $expected === '' || !hash_equals((string)$expected, (string)$secret)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Forbidden'), JSON_UNESCAPED_UNICODE);
    exit;
}

$docStmt = $db->prepare("
    SELECT id, operation_type, doc_number, comment_text, supplier, b24_sync_status, b24_document_id, b24_sync_response
    FROM stock_operation_docs
    WHERE id = ?
    LIMIT 1
");
$docStmt->execute(array($docId));
$doc = $docStmt->fetch(PDO::FETCH_ASSOC);
if (!$doc || !is_array($doc)) {
    http_response_code(404);
    echo json_encode(array('ok' => false, 'error' => 'Документ не найден.'), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array((string)$doc['operation_type'], array('receipt', 'writeoff'), true)) {
    echo json_encode(array('ok' => false, 'error' => 'Тип документа не поддерживается.'), JSON_UNESCAPED_UNICODE);
    exit;
}

if (intval(isset($doc['b24_document_id']) ? $doc['b24_document_id'] : 0) > 0 && (string)$doc['b24_sync_status'] === 'sent') {
    echo json_encode(array('ok' => true, 'skipped' => 'already_sent'), JSON_UNESCAPED_UNICODE);
    exit;
}

$b24WorkerLock = 'b24_stock_worker_doc_' . $docId;
$lockWait = intval(getAppSetting($db, 'stock_b24_worker_mysql_lock_wait', '900'));
if ($lockWait < 10) {
    $lockWait = 10;
}
if ($lockWait > 3200) {
    $lockWait = 3200;
}
$stLock = $db->prepare('SELECT GET_LOCK(?, ?)');
$stLock->execute(array($b24WorkerLock, $lockWait));
$rLock = $stLock->fetch(PDO::FETCH_NUM);
if ($rLock === false || intval($rLock[0]) !== 1) {
    echo json_encode(array(
        'ok' => false,
        'skipped' => 'lock_busy',
        'hint' => 'Другой процесс уже синкает этот документ.',
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $linesStmt = $db->prepare("
        SELECT product_id, qty_rolls, quantity_m, roll_length, price_per_roll, delivery_price_per_roll, line_total
        FROM stock_operation_lines
        WHERE doc_id = ?
        ORDER BY id ASC
    ");
    $linesStmt->execute(array($docId));
    $lineRows = $linesStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($lineRows)) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'Нет строк документа.'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Повторно прочитать статус после ожидания блокировки
    $docFreshStmt = $db->prepare("
        SELECT id, operation_type, doc_number, comment_text, supplier, b24_sync_status, b24_document_id, b24_sync_response
        FROM stock_operation_docs WHERE id = ? LIMIT 1
    ");
    $docFreshStmt->execute(array($docId));
    $docFresh = $docFreshStmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($docFresh)) {
        $doc = $docFresh;
    }
    if (intval(isset($doc['b24_document_id']) ? $doc['b24_document_id'] : 0) > 0 && (string)$doc['b24_sync_status'] === 'sent') {
        echo json_encode(array('ok' => true, 'skipped' => 'already_sent_after_lock'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $retryStrategy = trim((string)getAppSetting($db, 'stock_b24_worker_retry_strategy', 'portal_by_number_only'));
    if ($retryStrategy !== 'full') {
        $retryStrategy = 'portal_by_number_only';
    }

    try {
        $syncResult = stockOperationsExecuteB24SyncWithLines($db, $doc, $lineRows, $retryStrategy);
        $syncStatus = resolveB24SyncStatus($syncResult);
        $db->prepare("UPDATE stock_operation_docs SET b24_document_id = ?, b24_sync_status = ?, b24_sync_response = ? WHERE id = ?")
            ->execute(array(
                isset($syncResult['b24_document_id']) ? intval($syncResult['b24_document_id']) : null,
                $syncStatus,
                json_encode($syncResult, JSON_UNESCAPED_UNICODE),
                $docId
            ));

        if (((string)$doc['operation_type']) === 'receipt' && ($syncStatus === 'sent' || $syncStatus === 'partial')) {
            stockOperationsPushReceiptProductsNamePriceToB24Catalog($db, $lineRows);
        }

        if (((string)$doc['operation_type']) === 'receipt' && $syncStatus === 'sent') {
            stockOperationsSyncReceiptCatalogTotalsToBitrix($db, $lineRows);
        }

        echo json_encode(array(
            'ok' => ($syncStatus === 'sent'),
            'sync_status' => $syncStatus,
            'b24_document_id' => isset($syncResult['b24_document_id']) ? intval($syncResult['b24_document_id']) : null,
        ), JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        $payload = array(
            'ok' => false,
            'stage' => 'worker_exception',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        );
        try {
            $db->prepare("UPDATE stock_operation_docs SET b24_sync_status = 'error', b24_sync_response = ? WHERE id = ?")
                ->execute(array(json_encode($payload, JSON_UNESCAPED_UNICODE), intval($docId)));
        } catch (Exception $e2) {
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
} finally {
    stockReceiptMysqlReleaseLock($db, $b24WorkerLock);
}
