<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
$db = getDB();
require_once __DIR__ . '/../functions/webhook_log_schema.php';

/** @var int|null Строка в webhook_log текущего запроса (для итога обработки). */
$GLOBALS['webhook_log_id'] = null;

function extractDealPayload($data) {
    return webhookLogExtractDealPayload($data);
}

function webhookLoadHandlers() {
    require_once __DIR__ . '/../functions/app_settings.php';
    require_once __DIR__ . '/../functions/integration_workflow_gates.php';
    require_once __DIR__ . '/bitrix/deal.php';
    require_once __DIR__ . '/bitrix/warehouse_gate.php';
    require_once __DIR__ . '/bitrix/send.php';
    require_once __DIR__ . '/../functions/stock_movements.php';
    require_once __DIR__ . '/../functions/deal_rows_sync_service.php';
    require_once __DIR__ . '/../functions/bitrix_deal_tier_discount_sync.php';
}

/**
 * Не перезаписываем строки сделки в Б24 после закрытия заявки / отгрузки (избегаем лишних циклов).
 *
 * @param PDO $db
 * @param int $requestId
 * @return bool
 */
function webhookRequestShouldSkipPushingProductRowsToBitrix($db, $requestId) {
    $requestId = intval($requestId);
    if ($requestId <= 0) {
        return true;
    }
    try {
        $stmt = $db->prepare("SELECT status, picker_status FROM b24_sale_requests WHERE id = ? LIMIT 1");
        $stmt->execute(array($requestId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
    if (!$row) {
        return true;
    }
    $status = isset($row['status']) ? (string)$row['status'] : '';
    $picker = isset($row['picker_status']) ? (string)$row['picker_status'] : '';
    if ($status === 'completed' || $status === 'cancelled') {
        return true;
    }
    if ($picker === 'shipped') {
        return true;
    }
    return false;
}

/**
 * После queueDealForWarehouse: отправить в Б24 строки с ценами из b24_sale_lines (как в b24_sales / picker).
 *
 * @param PDO $db
 * @param int $requestId
 * @return array ok, skipped, reason|stage|error|idempotent|b24_deal_id
 */
function webhookSyncDealProductRowsAfterQueue($db, $requestId) {
    $requestId = intval($requestId);
    if ($requestId <= 0) {
        return array('ok' => false, 'skipped' => true, 'reason' => 'invalid_request_id');
    }
    if (webhookRequestShouldSkipPushingProductRowsToBitrix($db, $requestId)) {
        return array('ok' => true, 'skipped' => true, 'reason' => 'request_closed_or_completed');
    }
    if (!function_exists('pickerSyncDealRowsForRequest')) {
        require_once __DIR__ . '/../functions/deal_rows_sync_service.php';
    }
    try {
        $result = pickerSyncDealRowsForRequest($db, $requestId, false);
        if (!empty($result['ok'])) {
            return array(
                'ok' => true,
                'skipped' => false,
                'stage' => isset($result['stage']) ? $result['stage'] : '',
                'idempotent' => !empty($result['idempotent']),
                'b24_deal_id' => isset($result['b24_deal_id']) ? $result['b24_deal_id'] : null
            );
        }
        $stage = isset($result['stage']) ? (string)$result['stage'] : 'unknown';
        $err = '';
        if (isset($result['response']) && is_array($result['response'])) {
            if (isset($result['response']['error_description'])) {
                $err = (string)$result['response']['error_description'];
            } elseif (isset($result['response']['error'])) {
                $err = (string)$result['response']['error'];
            }
        }
        if ($err === '' && isset($result['error'])) {
            $err = (string)$result['error'];
        }
        return array('ok' => false, 'skipped' => false, 'stage' => $stage, 'error' => $err);
    } catch (Exception $e) {
        return array('ok' => false, 'skipped' => false, 'stage' => 'exception', 'error' => $e->getMessage());
    }
}

/**
 * Исходящий вебхук Битрикс24 часто приходит как application/x-www-form-urlencoded:
 * event=ONCRMDEALUPDATE&data[FIELDS][ID]=...&auth[domain]=...
 * вместо JSON. Приводим к той же форме массива, что и у JSON-тела.
 */
function bitrixWebhookNormalizeFromParsedForm($parsed) {
    if (!is_array($parsed) || empty($parsed['event'])) {
        return null;
    }

    return array(
        'event' => $parsed['event'],
        'data' => (isset($parsed['data']) && is_array($parsed['data'])) ? $parsed['data'] : array(),
        'auth' => (isset($parsed['auth']) && is_array($parsed['auth'])) ? $parsed['auth'] : array(),
        'ts' => isset($parsed['ts']) ? $parsed['ts'] : null,
        'event_handler_id' => isset($parsed['event_handler_id']) ? $parsed['event_handler_id'] : null,
    );
}

function bitrixWebhookDecodeRequestBody($rawInput) {
    $data = json_decode((string)$rawInput, true);
    if (is_array($data) && isset($data['event'])) {
        return $data;
    }

    $parsed = array();

    if (!empty($_POST) && isset($_POST['event'])) {
        $parsed = $_POST;
    }

    $trimRaw = trim((string)$rawInput);
    if (empty($parsed['event']) && $trimRaw !== '') {
        parse_str((string)$rawInput, $parsed);
    }

    $normalized = bitrixWebhookNormalizeFromParsedForm($parsed);
    if ($normalized !== null) {
        return $normalized;
    }

    return null;
}

function ensureWebhookLockTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS webhook_event_lock (
            id int NOT NULL AUTO_INCREMENT,
            event_hash varchar(64) NOT NULL,
            event_name varchar(120) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_webhook_event_hash (event_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensureDynamicItemInboxTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS b24_dynamic_item_inbox (
            id int NOT NULL AUTO_INCREMENT,
            event_name varchar(80) NOT NULL,
            entity_type_id int DEFAULT NULL,
            item_id int DEFAULT NULL,
            item_payload longtext,
            product_rows_payload longtext,
            source_payload longtext,
            process_status varchar(20) NOT NULL DEFAULT 'new',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_b24_dynamic_item_lookup (entity_type_id, item_id),
            KEY idx_b24_dynamic_item_status (process_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// Получаем данные от Битрикс24. При паузе синхронизации — ответ без записи в БД (см. integration_all_sync_paused).
$input = file_get_contents('php://input');
$data = bitrixWebhookDecodeRequestBody($input);

webhookLogEnsureSchema($db);
require_once __DIR__ . '/../functions/integration_sync_control.php';

if (integrationAllSyncPaused($db)) {
    echo json_encode(array(
        'status' => 'integration_sync_paused',
        'hint' => 'Включите синхронизацию: sync_monitor_developers.php (блок паузы) или вкладка </> «Разработчикам», чтобы обрабатывать события Б24.',
    ));
    exit;
}

if (!is_array($data)) {
    $raw = mb_substr((string)$input, 0, 60000);
    $GLOBALS['webhook_log_id'] = webhookLogInsertIncoming(
        $db,
        'INVALID_JSON_OR_BODY',
        array(
            'hint' => 'neither_json_nor_form-urlencoded',
            'body_chars' => strlen((string)$input),
            'body_preview' => $raw,
        ),
        null,
        null
    );
    webhookLogFinish($db, 'json_decode_failed_or_empty_body');
    echo json_encode(array('error' => 'No data received'));
    exit;
}

$event = isset($data['event']) ? $data['event'] : '';
$auth = (isset($data['auth']) && is_array($data['auth'])) ? $data['auth'] : array();
$eventDataForHash = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : array();
/**
 * Частый кейс outbound: в data только {"FIELDS":{"ID":"..."}} без стадии — хеш данных одинаковый
 * для каждого обновления сделки, и второй переход уже не обрабатывается. Учитываем ts/event_handler_id.
 */
$saltPieces = array();
if (isset($data['ts']) && $data['ts'] !== null && $data['ts'] !== '') {
    $saltPieces[] = (string)$data['ts'];
}
if (isset($data['event_handler_id']) && $data['event_handler_id'] !== null && $data['event_handler_id'] !== '') {
    $saltPieces[] = (string)$data['event_handler_id'];
}
$eventHashSalt = implode('|', $saltPieces);
$eventHash = hash('sha256', $event . '|' . json_encode($eventDataForHash, JSON_UNESCAPED_UNICODE) . '|' . $eventHashSalt);

list($incomingDealId, $incomingProductId) = webhookLogExtractEntityIds($event, $data);
$GLOBALS['webhook_log_id'] = webhookLogInsertIncoming($db, $event === '' ? 'EMPTY_EVENT_FIELD' : $event, $data, $incomingDealId, $incomingProductId);

webhookRegisterFatalOutcomeGuard($db);

webhookLoadHandlers();

ensureWebhookLockTable($db);
$lockStmt = $db->prepare("INSERT IGNORE INTO webhook_event_lock (event_hash, event_name) VALUES (?, ?)");
$lockStmt->execute([$eventHash, $event]);
if ($lockStmt->rowCount() === 0) {
    webhookLogFinish($db, 'duplicate_delivery_skipped');
    echo json_encode(array('status' => 'duplicate_event_ignored', 'event' => $event));
    exit;
}

switch ($event) {
    case 'ONCRMDEALADD':
        // Новый сделка в Б24
        handleNewDeal($db, $data);
        break;
        
    case 'ONCRMDEALUPDATE':
        // Обновление сделки
        handleDealUpdate($db, $data);
        break;
        
    case 'ONCRMPRODUCTADD':
        // Новый товар
        handleNewProduct($db, $data);
        break;
        
    case 'ONCRMPRODUCTUPDATE':
        // Обновление товара
        handleProductUpdate($db, $data);
        break;

    case 'ONCRMDYNAMICITEMADD':
        handleDynamicItemEvent($db, $data, 'add');
        break;

    case 'ONCRMDYNAMICITEMUPDATE':
        handleDynamicItemEvent($db, $data, 'update');
        break;

    case 'ONCRMDYNAMICITEMDELETE':
        handleDynamicItemEvent($db, $data, 'delete');
        break;
        
    default:
        webhookLogFinish($db, 'unknown_event:' . substr((string)$event, 0, 110));
        echo json_encode(['status' => 'unknown_event', 'event' => $event]);
        exit;
}

function handleNewDeal($db, $data) {
    $deal = extractDealPayload($data);
    
    if (empty($deal)) {
        webhookLogFinish($db, 'deal_add_empty_payload');
        echo json_encode(['error' => 'No deal data']);
        exit;
    }
    
    $dealId = intval(isset($deal['ID']) ? $deal['ID'] : 0);
    $dealName = isset($deal['TITLE']) ? $deal['TITLE'] : '';
    $responsibleId = isset($deal['ASSIGNED_BY_ID']) ? $deal['ASSIGNED_BY_ID'] : '';
    
    if ($dealId <= 0) {
        webhookLogFinish($db, 'deal_add_invalid_id');
        echo json_encode(['error' => 'Invalid deal ID']);
        exit;
    }

    $cfg = require __DIR__ . '/bitrix/config.php';
    $gate = integrationMergedReserveGate($db, $cfg);
    $dealCtx = $deal;
    if (!empty($gate['filter_enabled'])) {
        $dealCtx = bitrixMergeDealWebhookAndCrm($deal, getDealDetails($dealId));
    }
    if (!bitrixWarehouseQueueAllowed($dealCtx, $gate)) {
        $tierDiscountNew = null;
        if (function_exists('bitrixTierDiscountSyncEnabled') && bitrixTierDiscountSyncEnabled($cfg)) {
            $productsGate = getDealProducts($db, $dealId);
            if (!empty($productsGate)) {
                $tierDiscountNew = bitrixDealTierDiscountSyncWhenQueueSkipped($db, $dealId);
            }
        }
        webhookLogFinish($db, 'skipped_warehouse_gate', $dealId, null);
        $outGate = array(
            'status' => 'skipped_warehouse_gate',
            'deal_id' => $dealId,
            'category_id' => isset($dealCtx['CATEGORY_ID']) ? $dealCtx['CATEGORY_ID'] : null,
            'stage_id' => isset($dealCtx['STAGE_ID']) ? $dealCtx['STAGE_ID'] : null,
        );
        if ($tierDiscountNew !== null) {
            $outGate['tier_discount_sync'] = $tierDiscountNew;
        }
        echo json_encode($outGate);
        exit;
    }
    
    // Получаем информацию о ответственном
    $responsible = getUserName($db, $responsibleId);
    
    // Получаем товары в сделке
    $products = getDealProducts($db, $dealId);
    $firstPidNew = (!empty($products) && isset($products[0]['id'])) ? intval($products[0]['id']) : 0;
    $logProductId = ($firstPidNew > 0 ? $firstPidNew : null);
    
    if (!empty($products)) {
        $dealData = [
            'deal_id' => $dealId,
            'deal_name' => $dealName,
            'responsible' => $responsible,
            'products' => $products
        ];
        $result = queueDealForWarehouse($db, $dealData);
        $dealRowsSync = null;
        if (!isset($result['error']) && isset($result['request_id']) && intval($result['request_id']) > 0) {
            $dealRowsSync = webhookSyncDealProductRowsAfterQueue($db, intval($result['request_id']));
        }
        if (isset($result['error'])) {
            webhookLogFinish($db, 'queue_error', $dealId, $logProductId, isset($result['error']) ? $result['error'] : null);
        } elseif ($dealRowsSync !== null && isset($dealRowsSync['ok']) && !$dealRowsSync['ok'] && empty($dealRowsSync['skipped'])) {
            $detailSync = isset($dealRowsSync['stage']) ? (string)$dealRowsSync['stage'] : '';
            if (isset($dealRowsSync['error']) && (string)$dealRowsSync['error'] !== '') {
                $detailSync = $detailSync !== '' ? $detailSync . ': ' : '';
                $detailSync .= (string)$dealRowsSync['error'];
            }
            webhookLogFinish($db, 'deal_processed_productrows_sync_failed', $dealId, $logProductId, $detailSync !== '' ? $detailSync : null);
        } else {
            webhookLogFinish($db, 'deal_processed', $dealId, $logProductId);
        }
        $outNew = isset($result['error'])
            ? array('status' => 'error', 'deal_id' => $dealId, 'error' => $result['error'])
            : array('status' => 'deal_processed', 'deal_id' => $dealId, 'request_id' => isset($result['request_id']) ? $result['request_id'] : null);
        if ($dealRowsSync !== null) {
            $outNew['deal_rows_sync'] = $dealRowsSync;
        }
        echo json_encode($outNew);
    } else {
        webhookFinishNoProducts($db, $dealId);
        echo json_encode(['status' => 'no_products', 'deal_id' => $dealId]);
    }
}

function handleDealUpdate($db, $data) {
    $deal = extractDealPayload($data);
    $dealId = intval(isset($deal['ID']) ? $deal['ID'] : 0);

    if ($dealId <= 0) {
        webhookLogFinish($db, 'deal_update_invalid_id');
        echo json_encode(['error' => 'Invalid deal ID']);
        exit;
    }

    // Получаем актульные товары и пересобираем заявку для кладовщика
    $dealData = getDealDetails($dealId);
    $products = getDealProducts($db, $dealId);
    $firstPidUpd = (!empty($products) && isset($products[0]['id'])) ? intval($products[0]['id']) : 0;
    $logProductIdDeal = ($firstPidUpd > 0 ? $firstPidUpd : null);

    $cfg = require __DIR__ . '/bitrix/config.php';
    $gate = integrationMergedReserveGate($db, $cfg);
    $dealCtx = bitrixMergeDealWebhookAndCrm($deal, $dealData);
    $realGate = integrationMergedRealizationGate($db, $cfg);

    // === ДИАГНОСТИКА ===
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/bitrix_realization_check.log';

    $cat = isset($dealData['CATEGORY_ID']) ? $dealData['CATEGORY_ID'] : 'NULL';
    $stg = isset($dealData['STAGE_ID']) ? $dealData['STAGE_ID'] : 'NULL';
    $sem = isset($dealData['SEMANTICS']) ? $dealData['SEMANTICS'] : 'NULL';
    $stageUpper = strtoupper(trim((string)$stg));
    $semanticLower = strtolower(trim((string)$sem));

    file_put_contents($logFile, date('c') . " [DEAL_#$dealId] CATEGORY_ID=$cat | STAGE_ID=$stg | SEMANTICS=$sem" . PHP_EOL, FILE_APPEND);

    $isCancelledByBitrix = ($semanticLower === 'f')
        || (strpos($stageUpper, 'LOSE') !== false)
        || (strpos($stageUpper, 'CANCEL') !== false);

    $isPaidByCurrentStage = bitrixRealizationIsPaid($dealData, $realGate);
    $realizedReqStmt = $db->prepare("
        SELECT id, status, picker_status
        FROM b24_sale_requests
        WHERE b24_deal_id = ?
        LIMIT 1
    ");
    $realizedReqStmt->execute(array($dealId));
    $realizedReq = $realizedReqStmt->fetch(PDO::FETCH_ASSOC);
    $wasRealizedInApp = is_array($realizedReq)
        && (
            (string)(isset($realizedReq['status']) ? $realizedReq['status'] : '') === 'completed'
            || (string)(isset($realizedReq['picker_status']) ? $realizedReq['picker_status'] : '') === 'shipped'
        );

    if ($isCancelledByBitrix) {
        file_put_contents($logFile, date('c') . " [DEAL_#$dealId] ↩ ОТМЕНА В Б24: запускаю cancelDealReservations()" . PHP_EOL, FILE_APPEND);
        try {
            cancelDealReservations($db, $dealId);
            webhookLogFinish($db, 'deal_cancelled_in_bitrix', $dealId, $logProductIdDeal);
            echo json_encode(array(
                'status' => 'deal_cancelled_in_bitrix',
                'deal_id' => $dealId,
                'stage_id' => $stg,
                'semantics' => $sem
            ));
            return;
        } catch (Exception $e) {
            file_put_contents($logFile, date('c') . " [DEAL_#$dealId] ✗ ОШИБКА cancelDealReservations(): " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            webhookLogFinish($db, 'deal_cancel_failed', $dealId, $logProductIdDeal, $e->getMessage());
            echo json_encode(array(
                'status' => 'error',
                'deal_id' => $dealId,
                'error' => 'cancel_failed'
            ));
            return;
        }
    }

    // Отмена реализации без закрытия сделки (перевод из «оплачено/отгружено» обратно в не-оплаченную стадию).
    if ($wasRealizedInApp && !$isPaidByCurrentStage) {
        file_put_contents($logFile, date('c') . " [DEAL_#$dealId] ↩ ОТМЕНА РЕАЛИЗАЦИИ ПО СТАДИИ: was_realized=YES, isPaidNow=FALSE; запускаю cancelDealReservations()" . PHP_EOL, FILE_APPEND);
        try {
            cancelDealReservations($db, $dealId);
            webhookLogFinish($db, 'deal_realization_reverted', $dealId, $logProductIdDeal);
            echo json_encode(array(
                'status' => 'deal_realization_reverted',
                'deal_id' => $dealId,
                'stage_id' => $stg,
                'semantics' => $sem
            ));
            return;
        } catch (Exception $e) {
            file_put_contents($logFile, date('c') . " [DEAL_#$dealId] ✗ ОШИБКА cancelDealReservations() при откате реализации: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            webhookLogFinish($db, 'deal_realization_revert_failed', $dealId, $logProductIdDeal, $e->getMessage());
            echo json_encode(array(
                'status' => 'error',
                'deal_id' => $dealId,
                'error' => 'realization_revert_failed'
            ));
            return;
        }
    }

    // Проверяем резерв
    $checkReq = $db->prepare("SELECT id, status FROM b24_sale_requests WHERE b24_deal_id = ? LIMIT 1");
    $checkReq->execute([$dealId]);
    $reqData = $checkReq->fetch(PDO::FETCH_ASSOC);
    if ($reqData) {
        $reqId = $reqData['id'];
        $checkLines = $db->prepare("SELECT COUNT(*) as cnt FROM b24_sale_lines WHERE request_id = ?");
        $checkLines->execute([$reqId]);
        $lineCnt = $checkLines->fetch(PDO::FETCH_ASSOC)['cnt'];

        $checkCuts = $db->prepare("SELECT COUNT(*) as cnt FROM b24_sale_line_cuts WHERE line_id IN (SELECT id FROM b24_sale_lines WHERE request_id = ?)");
        $checkCuts->execute([$reqId]);
        $cutCnt = $checkCuts->fetch(PDO::FETCH_ASSOC)['cnt'];

        file_put_contents($logFile, date('c') . " [DEAL_#$dealId] REQUEST_#$reqId: status={$reqData['status']} | lines=$lineCnt | cuts=$cutCnt" . PHP_EOL, FILE_APPEND);
    } else {
        file_put_contents($logFile, date('c') . " [DEAL_#$dealId] NO_REQUEST_IN_DB" . PHP_EOL, FILE_APPEND);
    }
    // === КОНЕЦ ДИАГНОСТИКИ ===

    // 🔥 ОБРАБОТКА РЕЗЕРВА (warehouse_queue)
    if (!empty($products) && bitrixWarehouseQueueAllowed($dealCtx, $gate)) {
        $result = queueDealForWarehouse($db, [
            'deal_id' => $dealId,
            'deal_name' => isset($dealData['TITLE']) ? $dealData['TITLE'] : ('Deal #' . $dealId),
            'responsible' => isset($dealData['ASSIGNED_BY_ID']) ? getUserName($db, $dealData['ASSIGNED_BY_ID']) : '',
            'products' => $products
        ]);
        $dealRowsSyncUpd = null;
        if (!isset($result['error']) && isset($result['request_id']) && intval($result['request_id']) > 0) {
            $dealRowsSyncUpd = webhookSyncDealProductRowsAfterQueue($db, intval($result['request_id']));
        }
        if (isset($result['error'])) {
            webhookLogFinish($db, 'queue_error', $dealId, $logProductIdDeal, isset($result['error']) ? $result['error'] : null);
        } elseif ($dealRowsSyncUpd !== null && isset($dealRowsSyncUpd['ok']) && !$dealRowsSyncUpd['ok'] && empty($dealRowsSyncUpd['skipped'])) {
            $detailSyncU = isset($dealRowsSyncUpd['stage']) ? (string)$dealRowsSyncUpd['stage'] : '';
            if (isset($dealRowsSyncUpd['error']) && (string)$dealRowsSyncUpd['error'] !== '') {
                $detailSyncU = $detailSyncU !== '' ? $detailSyncU . ': ' : '';
                $detailSyncU .= (string)$dealRowsSyncUpd['error'];
            }
            webhookLogFinish($db, 'deal_updated_productrows_sync_failed', $dealId, $logProductIdDeal, $detailSyncU !== '' ? $detailSyncU : null);
        } else {
            webhookLogFinish($db, 'deal_updated', $dealId, $logProductIdDeal);
        }
        $outUpd = isset($result['error'])
            ? array('status' => 'error', 'deal_id' => $dealId, 'error' => $result['error'])
            : array('status' => 'deal_updated', 'deal_id' => $dealId, 'request_id' => isset($result['request_id']) ? $result['request_id'] : null);
        if ($dealRowsSyncUpd !== null) {
            $outUpd['deal_rows_sync'] = $dealRowsSyncUpd;
        }
        echo json_encode($outUpd);
    } elseif (!empty($products)) {
        $tierDiscountSkip = null;
        if (function_exists('bitrixTierDiscountSyncEnabled') && bitrixTierDiscountSyncEnabled($cfg)) {
            $tierDiscountSkip = bitrixDealTierDiscountSyncWhenQueueSkipped($db, $dealId);
        }
        webhookLogFinish($db, 'skipped_warehouse_gate', $dealId, $logProductIdDeal);
        $outSkipGate = array(
            'status' => 'skipped_warehouse_gate',
            'deal_id' => $dealId,
            'category_id' => isset($dealCtx['CATEGORY_ID']) ? $dealCtx['CATEGORY_ID'] : null,
            'stage_id' => isset($dealCtx['STAGE_ID']) ? $dealCtx['STAGE_ID'] : null,
        );
        if ($tierDiscountSkip !== null) {
            $outSkipGate['tier_discount_sync'] = $tierDiscountSkip;
        }
        echo json_encode($outSkipGate);
    } else {
        webhookFinishNoProducts($db, $dealId);
        echo json_encode(['status' => 'no_products', 'deal_id' => $dealId]);
    }

    // 🔥 ОБРАБОТКА РЕАЛИЗАЦИИ (warehouse_realization)
    $dealDataFresh = $dealData;

    for ($i = 0; $i < 3; $i++) {
        $dealDataFresh = getDealDetails($dealId);

        $isPaid = bitrixRealizationIsPaid($dealDataFresh, $realGate);

        file_put_contents($logFile, date('c') . " [DEAL_#$dealId] CHECK_$i: bitrixRealizationIsPaid=" . ($isPaid ? 'TRUE' : 'FALSE') . " | gate_filter=" . ($realGate['filter_enabled'] ? 'ON' : 'OFF') . PHP_EOL, FILE_APPEND);

        if ($isPaid) {
            file_put_contents($logFile, date('c') . " [DEAL_#$dealId] ✓ РЕАЛИЗАЦИЯ ДЕТЕКТИРОВАНА, прерываю цикл" . PHP_EOL, FILE_APPEND);
            break;
        }

        if ($i < 2) {
            usleep(300000);
        }
    }

    // Логируем решение перед применением
    $isPaidFinal = bitrixRealizationIsPaid($dealDataFresh, $realGate);
    file_put_contents($logFile, date('c') . " [DEAL_#$dealId] FINAL_DECISION: isPaid=" . ($isPaidFinal ? 'TRUE' : 'FALSE') . " | realGate=" . json_encode($realGate) . PHP_EOL, FILE_APPEND);

    if ($isPaidFinal) {
        file_put_contents($logFile, date('c') . " [DEAL_#$dealId] → CALLING realizeWarehouseDealFromReserve()" . PHP_EOL, FILE_APPEND);
        try {
            $realRes = realizeWarehouseDealFromReserve($db, $dealId);
            file_put_contents($logFile, date('c') . " [DEAL_#$dealId] ✓ РЕАЛИЗАЦИЯ OK: " . json_encode($realRes, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        } catch (Throwable $e) {
            file_put_contents($logFile, date('c') . " [DEAL_#$dealId] ✗ РЕАЛИЗАЦИЯ EXCEPTION: " . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL, FILE_APPEND);
        }
    } else {
        file_put_contents($logFile, date('c') . " [DEAL_#$dealId] → НЕ РЕАЛИЗОВАНА (isPaid=FALSE)" . PHP_EOL, FILE_APPEND);
    }
}

function handleNewProduct($db, $data) {
    $product = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : array();
    
    if (empty($product)) {
        webhookLogFinish($db, 'product_add_empty_payload');
        echo json_encode(['error' => 'No product data']);
        exit;
    }
    
    $productId = intval(isset($product['ID']) ? $product['ID'] : 0);
    $productName = isset($product['NAME']) ? $product['NAME'] : '';
    $productPrice = floatval(isset($product['PRICE']) ? $product['PRICE'] : 0);
    
    if ($productId <= 0) {
        webhookLogFinish($db, 'product_add_invalid_id');
        echo json_encode(['error' => 'Invalid product ID']);
        exit;
    }
    
    // Добавляем товар в локальную БД
    $stmt = $db->prepare("SELECT id FROM products WHERE b24_product_id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
        $upd = $db->prepare("UPDATE products SET name = ?, price_per_meter = ? WHERE id = ?");
        $upd->execute([$productName, $productPrice, intval($exists['id'])]);
        webhookLogFinish($db, 'product_upserted_existing', null, $productId);
    } else {
        $ins = $db->prepare("
            INSERT INTO products (name, roll_length, price_per_meter, b24_product_id)
            VALUES (?, 30, ?, ?)
        ");
        $ins->execute([$productName, $productPrice, $productId]);
        webhookLogFinish($db, 'product_inserted_local', null, $productId);
    }
    
    echo json_encode(['status' => 'product_added', 'product_id' => $productId]);
}

function handleProductUpdate($db, $data) {
    $product = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : array();
    
    if (empty($product)) {
        webhookLogFinish($db, 'product_update_empty_payload');
        echo json_encode(['error' => 'No product data']);
        exit;
    }
    
    $productId = intval(isset($product['ID']) ? $product['ID'] : 0);
    $productName = isset($product['NAME']) ? $product['NAME'] : '';
    $productPrice = floatval(isset($product['PRICE']) ? $product['PRICE'] : 0);
    
    if ($productId <= 0) {
        webhookLogFinish($db, 'product_update_invalid_id');
        echo json_encode(['error' => 'Invalid product ID']);
        exit;
    }
    
    // Обновляем товар в локальной БД
    $stmt = $db->prepare("
        UPDATE products 
        SET name = ?, price_per_meter = ?
        WHERE b24_product_id = ?
    ");
    $stmt->execute([$productName, $productPrice, $productId]);
    
    webhookLogFinish($db, 'product_updated_local', null, $productId);
    echo json_encode(['status' => 'product_updated', 'product_id' => $productId]);
}

function handleDynamicItemEvent($db, $data, $action) {
    ensureDynamicItemInboxTable($db);

    $ids = extractDynamicItemIds($data);
    $entityTypeId = intval($ids['entity_type_id']);
    $itemId = intval($ids['item_id']);
    if ($entityTypeId <= 0 || $itemId <= 0) {
        webhookLogFinish($db, 'dynamic_item_missing_ids');
        echo json_encode([
            'status' => 'dynamic_item_skipped',
            'reason' => 'missing_entity_or_item_id',
            'action' => $action
        ]);
        return;
    }

    $itemPayload = null;
    $rowsPayload = null;

    if ($action !== 'delete') {
        $itemResp = sendToBitrix('crm.item.get', [
            'entityTypeId' => $entityTypeId,
            'id' => $itemId
        ]);
        $itemPayload = is_array($itemResp) ? json_encode($itemResp, JSON_UNESCAPED_UNICODE) : null;

        $rowsResp = sendToBitrix('crm.item.productrow.get', [
            'entityTypeId' => $entityTypeId,
            'id' => $itemId
        ]);
        $rowsPayload = is_array($rowsResp) ? json_encode($rowsResp, JSON_UNESCAPED_UNICODE) : null;
    }

    $ins = $db->prepare("
        INSERT INTO b24_dynamic_item_inbox
        (event_name, entity_type_id, item_id, item_payload, product_rows_payload, source_payload, process_status)
        VALUES (?, ?, ?, ?, ?, ?, 'new')
    ");
    $ins->execute([
        'ONCRMDYNAMICITEM' . strtoupper($action),
        $entityTypeId,
        $itemId,
        $itemPayload,
        $rowsPayload,
        json_encode($data, JSON_UNESCAPED_UNICODE)
    ]);

    webhookLogFinish($db, 'dynamic_item_queued_et' . $entityTypeId);
    echo json_encode([
        'status' => 'dynamic_item_queued',
        'action' => $action,
        'entity_type_id' => $entityTypeId,
        'item_id' => $itemId,
        'inbox_id' => intval($db->lastInsertId())
    ]);
}

function extractDynamicItemIds($data) {
    $entityTypeId = 0;
    $itemId = 0;
    $payload = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
    $fields = isset($payload['FIELDS']) && is_array($payload['FIELDS']) ? $payload['FIELDS'] : $payload;

    if (isset($fields['ENTITY_TYPE_ID'])) {
        $entityTypeId = intval($fields['ENTITY_TYPE_ID']);
    } elseif (isset($payload['ENTITY_TYPE_ID'])) {
        $entityTypeId = intval($payload['ENTITY_TYPE_ID']);
    }

    if (isset($fields['ID'])) {
        $itemId = intval($fields['ID']);
    } elseif (isset($fields['ITEM_ID'])) {
        $itemId = intval($fields['ITEM_ID']);
    } elseif (isset($payload['ID'])) {
        $itemId = intval($payload['ID']);
    } elseif (isset($payload['ITEM_ID'])) {
        $itemId = intval($payload['ITEM_ID']);
    }

    return [
        'entity_type_id' => $entityTypeId,
        'item_id' => $itemId
    ];
}

function getUserName($db, $userId) {
    $uid = 0;
    if (is_numeric($userId)) {
        $uid = intval($userId);
    } else {
        $rawUser = trim((string)$userId);
        if (preg_match('/(\d+)/', $rawUser, $m)) {
            $uid = intval($m[1]);
        }
    }
    if ($uid <= 0) {
        return '';
    }

    static $cache = array();
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }

    $label = 'User ' . $uid;
    $resp = sendToBitrix('user.get', array(
        'FILTER' => array('ID' => $uid)
    ));

    if (is_array($resp) && isset($resp['result']) && is_array($resp['result']) && !empty($resp['result'][0])) {
        $u = $resp['result'][0];
        $parts = array();
        if (!empty($u['NAME'])) {
            $parts[] = trim((string)$u['NAME']);
        }
        if (!empty($u['LAST_NAME'])) {
            $parts[] = trim((string)$u['LAST_NAME']);
        }
        $fullName = trim(implode(' ', $parts));
        if ($fullName !== '') {
            $label .= ' (' . $fullName . ')';
        }
    }

    $cache[$uid] = $label;
    return $label;
}

function ensureSalesFinanceColumns($db) {
    if (!function_exists('ensureColumnExists')) {
        return;
    }
    ensureColumnExists($db, 'sales', 'cost_fact', '`cost_fact` decimal(14,2) NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'sales', 'gross_profit', '`gross_profit` decimal(14,2) NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'sales', 'gross_margin_percent', '`gross_margin_percent` decimal(8,2) NOT NULL DEFAULT 0');
}

function realizeWarehouseDealFromReserve($db, $dealId) {
    ensureOrderAllocationsTable($db);
    ensureSalesFinanceColumns($db);

    $movementIds = [];
    $productIdsToSync = [];

    $db->beginTransaction();
    try {
        $requestStmt = $db->prepare("
            SELECT *
            FROM b24_sale_requests
            WHERE b24_deal_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $requestStmt->execute([$dealId]);
        $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) {
            $db->commit();
            return ['status' => 'no_request'];
        }

        $requestId = intval($request['id']);
        $linesStmt = $db->prepare("
            SELECT
                l.*,
                COALESCE((SELECT SUM(c.meters) FROM b24_sale_line_cuts c WHERE c.line_id = l.id), 0) as allocated_m
            FROM b24_sale_lines l
            WHERE l.request_id = ?
              AND l.status != 'completed'
            ORDER BY l.id ASC
            FOR UPDATE
        ");
        $linesStmt->execute([$requestId]);
        $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($lines)) {
            $db->prepare("
                UPDATE b24_sale_requests
                SET status = 'completed',
                    picker_status = 'shipped',
                    shipped_at = IFNULL(shipped_at, NOW()),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$requestId]);
            $db->commit();
            return ['status' => 'already_completed'];
        }

        foreach ($lines as $line) {
            $lineId = intval($line['id']);
            $need = floatval($line['quantity_m']);
            $allocated = floatval($line['allocated_m']);
            if ($allocated + 0.0001 < $need) {
                throw new Exception('Cannot realize deal: line #' . $lineId . ' has ' . round($allocated, 2) . 'm reserved of ' . round($need, 2) . 'm.');
            }

            $cutsStmt = $db->prepare("
                SELECT c.*
                FROM b24_sale_line_cuts c
                WHERE c.line_id = ?
                ORDER BY c.id ASC
            ");
            $cutsStmt->execute([$lineId]);
            $cuts = $cutsStmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($cuts)) {
                throw new Exception('Cannot realize deal: line #' . $lineId . ' has no selected rolls.');
            }

            $costFact = 0.0;
            foreach ($cuts as $cut) {
                $rollId = intval($cut['roll_id']);
                $take = floatval($cut['meters']);
                if ($take <= 0) {
                    continue;
                }

                $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id = ? FOR UPDATE");
                $rollStmt->execute([$rollId]);
                $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);
                if (!$roll) {
                    throw new Exception('Cannot realize deal: roll #' . $rollId . ' not found.');
                }

                $newLen = floatval($roll['current_length']) - $take;
                if ($newLen < -0.0001) {
                    throw new Exception('Cannot realize deal: roll #' . $rollId . ' has not enough meters.');
                }

                $rollCostPerMeter = floatval(isset($roll['cost_per_meter']) ? $roll['cost_per_meter'] : 0);
                if ($rollCostPerMeter > 0) {
                    $costFact += $take * $rollCostPerMeter;
                }

                $newReserved = max(0, floatval($roll['reserved_length']) - $take);
                $newLen = max(0, $newLen);
                $newStatus = $newLen <= 0.0001 ? 'sold' : 'cut';

                if ($newReserved <= 0.0001) {
                    $db->prepare("
                        UPDATE rolls
                        SET current_length = ?, status = ?, reserved = 0, deal_id = NULL, reserved_length = 0
                        WHERE id = ?
                    ")->execute([$newLen, $newStatus, $rollId]);
                } else {
                    $db->prepare("
                        UPDATE rolls
                        SET current_length = ?, status = ?, reserved_length = ?
                        WHERE id = ?
                    ")->execute([$newLen, $newStatus, $newReserved, $rollId]);
                }
            }

            $price = floatval(isset($line['price_per_unit']) ? $line['price_per_unit'] : 0);
            $qty = floatval($line['quantity_m']);
            $revenue = $qty * $price;
            $grossProfit = $revenue - $costFact;
            $grossMarginPercent = $revenue > 0 ? (($grossProfit / $revenue) * 100) : 0;
            $productId = intval($line['product_id']);

            $db->prepare("
                INSERT INTO sales (product_id, type, quantity, price_per_unit, total, deal_id, cost_fact, gross_profit, gross_margin_percent)
                VALUES (?, 'meter', ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $productId,
                $qty,
                $price,
                $revenue,
                $dealId,
                round($costFact, 2),
                round($grossProfit, 2),
                round($grossMarginPercent, 2)
            ]);

            $movementIds[] = logStockMovement($db, [
                'product_id' => $productId,
                'movement_type' => 'sale_meter',
                'quantity_m' => $qty,
                'quantity_rolls' => 0,
                'price_per_unit' => $price,
                'total' => $revenue,
                'deal_id' => $dealId,
                'comment' => 'Deal realized in B24 | margin: ' . round($grossMarginPercent, 2) . '%'
            ]);
            $productIdsToSync[$productId] = $productId;

            $db->prepare("UPDATE b24_sale_lines SET status = 'completed' WHERE id = ?")->execute([$lineId]);
        }

        $db->prepare("
            UPDATE b24_sale_requests
            SET status = 'completed',
                picker_status = 'shipped',
                shipped_at = IFNULL(shipped_at, NOW()),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$requestId]);
        $db->prepare("UPDATE deals SET status = 'closed' WHERE b24_deal_id = ?")->execute([$dealId]);

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    foreach ($movementIds as $movementId) {
        syncMovementToBitrix($db, $movementId);
    }
    foreach ($productIdsToSync as $productId) {
        syncProductAvailableToBitrix($db, $productId);
    }

    return [
        'status' => 'realized',
        'movements' => count($movementIds),
        'products_synced' => count($productIdsToSync)
    ];
}

function applyDealPaidOrReserveMark($db, $dealId, $dealData, $realizationGate = null) {
    if ($dealId <= 0 || !is_array($dealData)) {
        return;
    }

    if ($realizationGate === null) {
        $cfg = require __DIR__ . '/bitrix/config.php';
        require_once __DIR__ . '/../functions/integration_workflow_gates.php';
        $realizationGate = integrationMergedRealizationGate($db, $cfg);
    }

    try {
        $stage = strtoupper(trim((string)(isset($dealData['STAGE_ID']) ? $dealData['STAGE_ID'] : '')));
        $semantic = strtolower(trim((string)(isset($dealData['SEMANTICS']) ? $dealData['SEMANTICS'] : '')));
        $isPaid = bitrixRealizationIsPaid($dealData, $realizationGate);

        if ($isPaid) {
            realizeWarehouseDealFromReserve($db, $dealId);
        } elseif (false) {
            $db->prepare("UPDATE b24_sale_requests SET status = 'completed', updated_at = NOW() WHERE b24_deal_id = ?")
                ->execute([$dealId]);

            $lineIdsStmt = $db->prepare("
                SELECT l.id
                FROM b24_sale_lines l
                JOIN b24_sale_requests r ON r.id = l.request_id
                WHERE r.b24_deal_id = ? AND l.status != 'completed'
            ");
            $lineIdsStmt->execute([$dealId]);
            $lineIds = $lineIdsStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($lineIds)) {
                $placeholders = implode(',', array_fill(0, count($lineIds), '?'));
                $cutsStmt = $db->prepare("
                    SELECT c.line_id, c.roll_id, c.meters, l.product_id
                    FROM b24_sale_line_cuts c
                    JOIN b24_sale_lines l ON l.id = c.line_id
                    WHERE c.line_id IN ($placeholders)
                ");
                $cutsStmt->execute($lineIds);
                $cuts = $cutsStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($cuts as $cut) {
                    $dupStmt = $db->prepare("
                        SELECT id
                        FROM stock_movements
                        WHERE deal_id = ?
                          AND roll_id = ?
                          AND movement_type = 'sale_meter'
                          AND comment = 'Сделка оплачена в Б24'
                        LIMIT 1
                    ");
                    $dupStmt->execute([$dealId, intval($cut['roll_id'])]);
                    if ($dupStmt->fetch(PDO::FETCH_ASSOC)) {
                        continue;
                    }

                    logAndSyncMovement($db, [
                        'product_id' => intval($cut['product_id']),
                        'roll_id' => intval($cut['roll_id']),
                        'movement_type' => 'sale_meter',
                        'quantity_m' => floatval($cut['meters']),
                        'quantity_rolls' => 0,
                        'deal_id' => $dealId,
                        'comment' => 'Сделка оплачена в Б24'
                    ]);
                }
            }
        } else {
            $isCancelled = $semantic === 'f' || strpos($stage, 'LOSE') !== false || strpos($stage, 'CANCEL') !== false;
            if ($isCancelled) {
                cancelDealReservations($db, $dealId);
                return;
            }
            $db->prepare("UPDATE b24_sale_requests SET status = IF(status='completed','completed','in_progress'), updated_at = NOW() WHERE b24_deal_id = ?")
                ->execute([$dealId]);
        }
    } catch (Exception $e) {
        // Keep webhook resilient even when optional B24 queue tables are absent.
        return;
    }
}

/**
 * Извлекает массив строк товаров из ответа crm.deal.productrows.get (разные форматы портала).
 */
function bitrixUnwrapDealProductRows($resp) {
    if (!is_array($resp) || isset($resp['error']) || !array_key_exists('result', $resp)) {
        return null;
    }
    $r = $resp['result'];
    if (!is_array($r)) {
        return array();
    }
    if (isset($r['productRows']) && is_array($r['productRows'])) {
        return $r['productRows'];
    }
    if (isset($r[0]) && is_array($r[0])) {
        return $r;
    }
    if (isset($r['PRODUCT_ID']) || isset($r['productId']) || isset($r['PRODUCT_NAME']) || isset($r['ID'])) {
        return array($r);
    }
    if (empty($r)) {
        return array();
    }
    $vals = array_values($r);
    if (!empty($vals) && isset($vals[0]) && is_array($vals[0])
        && (isset($vals[0]['PRODUCT_ID']) || isset($vals[0]['PRODUCT_NAME']))) {
        return $vals;
    }
    return $r;
}

/** Ответ crm.item.productrow.list — универсальный API строк сделки. */
function bitrixUnwrapItemProductRowList($resp) {
    if (!is_array($resp) || isset($resp['error']) || !array_key_exists('result', $resp)) {
        return null;
    }
    $r = $resp['result'];
    if (!is_array($r)) {
        return array();
    }
    if (isset($r['productRows']) && is_array($r['productRows'])) {
        return $r['productRows'];
    }
    if (isset($r['rows']) && is_array($r['rows'])) {
        return $r['rows'];
    }
    if (isset($r[0]) && is_array($r[0])) {
        return $r;
    }
    if (empty($r)) {
        return array();
    }
    $vals = array_values($r);
    if (!empty($vals) && isset($vals[0]) && is_array($vals[0])) {
        return $vals;
    }
    return array();
}

/**
 * Сырые строки CRM → список для queueDealForWarehouse.
 * Возвращает массив с ключами products, debug.
 */
function bitrixBuildWarehouseProductsFromCrmRows(array $rows) {
    $debug = '';
    $products = array();

    foreach ($rows as $item) {
        if (!is_array($item)) {
            continue;
        }

        $productId = 0;
        if (isset($item['PRODUCT_ID']) && $item['PRODUCT_ID'] !== '' && $item['PRODUCT_ID'] !== null) {
            $productId = intval($item['PRODUCT_ID']);
        } elseif (isset($item['productId']) && $item['productId'] !== '' && $item['productId'] !== null) {
            $productId = intval($item['productId']);
        }

        $price = 0.0;
        if (isset($item['PRICE']) && $item['PRICE'] !== '' && $item['PRICE'] !== null) {
            $price = floatval(str_replace(',', '.', (string)$item['PRICE']));
        } elseif (isset($item['price']) && $item['price'] !== '' && $item['price'] !== null) {
            $price = floatval(str_replace(',', '.', (string)$item['price']));
        }

        $quantity = 0.0;
        if (isset($item['QUANTITY']) && $item['QUANTITY'] !== '' && $item['QUANTITY'] !== null) {
            $quantity = floatval(str_replace(',', '.', (string)$item['QUANTITY']));
        } elseif (isset($item['quantity']) && $item['quantity'] !== '' && $item['quantity'] !== null) {
            $quantity = floatval(str_replace(',', '.', (string)$item['quantity']));
        }
        if ($quantity <= 0 && $price > 0) {
            $quantity = 1.0;
        }

        $name = '';
        if (isset($item['PRODUCT_NAME'])) {
            $name = (string)$item['PRODUCT_NAME'];
        } elseif (isset($item['productName'])) {
            $name = (string)$item['productName'];
        }

        if ($quantity <= 0) {
            continue;
        }

        if ($productId <= 0) {
            $debug = trim($debug . ' row_without_product_id');
            continue;
        }

        $products[] = array(
            'id' => $productId,
            'name' => $name,
            'quantity' => $quantity,
            'price' => $price
        );
    }

    if (empty($products) && $debug === '') {
        $debug = 'crm_rows_empty_after_filter';
    }

    return array('products' => $products, 'debug' => $debug);
}

function getDealProducts($db, $dealId) {
    require_once __DIR__ . '/bitrix/send.php';

    $GLOBALS['webhook_debug_productrows'] = '';

    $classic = sendToBitrix('crm.deal.productrows.get', array('id' => $dealId));
    $classicRows = array();

    if (!is_array($classic)) {
        $GLOBALS['webhook_debug_productrows'] = 'productrows_resp_not_array';
    } elseif (isset($classic['error'])) {
        $GLOBALS['webhook_debug_productrows'] = isset($classic['error_description'])
            ? (string)$classic['error_description']
            : (isset($classic['error']) ? (string)$classic['error'] : 'productrows_error');
    } else {
        $rows = bitrixUnwrapDealProductRows($classic);
        if ($rows === null) {
            $GLOBALS['webhook_debug_productrows'] = 'productrows_bad_result_shape';
        } else {
            $classicRows = $rows;
        }
    }

    $products = array();
    if (!empty($classicRows)) {
        $built = bitrixBuildWarehouseProductsFromCrmRows($classicRows);
        $products = $built['products'];
        if (!empty($built['debug']) && empty($products)) {
            $GLOBALS['webhook_debug_productrows'] = trim($GLOBALS['webhook_debug_productrows'] . ' ' . $built['debug']);
        }
    }

    if (!empty($products)) {
        return $products;
    }

    $dbgClassic = isset($GLOBALS['webhook_debug_productrows']) ? trim((string)$GLOBALS['webhook_debug_productrows']) : '';

    $itemResp = sendToBitrix('crm.item.productrow.list', array(
        'filter' => array(
            '=ownerType' => 'D',
            '=ownerId' => $dealId,
        ),
    ));

    $GLOBALS['webhook_debug_productrows'] = '';

    if (!is_array($itemResp)) {
        $GLOBALS['webhook_debug_productrows'] = 'item_rows_resp_not_array';
    } elseif (isset($itemResp['error'])) {
        $GLOBALS['webhook_debug_productrows'] = isset($itemResp['error_description'])
            ? (string)$itemResp['error_description']
            : (isset($itemResp['error']) ? (string)$itemResp['error'] : 'item_rows_error');
    } else {
        $itemRows = bitrixUnwrapItemProductRowList($itemResp);
        if ($itemRows === null) {
            $GLOBALS['webhook_debug_productrows'] = 'item_rows_bad_result_shape';
        } elseif (empty($itemRows)) {
            $GLOBALS['webhook_debug_productrows'] = 'item_rows_empty_list';
        } else {
            $builtItem = bitrixBuildWarehouseProductsFromCrmRows($itemRows);
            if (!empty($builtItem['products'])) {
                return $builtItem['products'];
            }
            $GLOBALS['webhook_debug_productrows'] = trim($builtItem['debug'] . ' item_rows_unparsed');
        }
    }

    $dbgItem = isset($GLOBALS['webhook_debug_productrows']) ? trim((string)$GLOBALS['webhook_debug_productrows']) : '';
    if ($dbgClassic !== '' && $dbgItem !== '') {
        $GLOBALS['webhook_debug_productrows'] = $dbgClassic . ' | ' . $dbgItem;
    } elseif ($dbgClassic !== '') {
        $GLOBALS['webhook_debug_productrows'] = $dbgClassic;
    } elseif ($dbgItem !== '') {
        $GLOBALS['webhook_debug_productrows'] = $dbgItem;
    } else {
        $GLOBALS['webhook_debug_productrows'] = 'no_product_rows_both_methods';
    }

    return array();
}

function webhookFinishNoProducts($db, $dealId) {
    $suffix = '';
    if (!empty($GLOBALS['webhook_debug_productrows'])) {
        $s = preg_replace('/\s+/', ' ', (string)$GLOBALS['webhook_debug_productrows']);
        if (strlen($s) > 90) {
            $s = substr($s, 0, 90);
        }
        $suffix = ':' . $s;
    }
    $tag = 'no_products' . $suffix;
    if (strlen($tag) > 155) {
        $tag = substr($tag, 0, 155);
    }
    webhookLogFinish($db, $tag, $dealId, null);
}

function getDealDetails($dealId) {
    $resp = sendToBitrix('crm.deal.get', array('id' => $dealId));
    if (!is_array($resp) || isset($resp['error'])) {
        return [];
    }
    return isset($resp['result']) && is_array($resp['result']) ? $resp['result'] : array();
}
