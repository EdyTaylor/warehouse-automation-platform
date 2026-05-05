<?php
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
require_once __DIR__ . '/functions/app_settings.php';
require_once __DIR__ . '/functions/webhook_log_schema.php';
require_once __DIR__ . '/functions/bitrix_outgoing_log.php';
require_once __DIR__ . '/functions/integration_workflow_gates.php';
require_once __DIR__ . '/functions/integration_bitrix_funnels.php';
require_once __DIR__ . '/functions/integration_sync_control.php';
require_once __DIR__ . '/functions/stock_emergency_kill.php';
require_once __DIR__ . '/functions/prg_flash.php';
$db = getDB();
webhookLogEnsureSchema($db);
bitrixOutgoingLogEnsureSchema($db);

$bitrixCfg = require __DIR__ . '/api/bitrix/config.php';

$friendcrm_sync_mode = isset($friendcrm_sync_mode) ? $friendcrm_sync_mode : 'settings';

$webhookLimit = isset($_GET['limit']) ? max(10, min(500, intval($_GET['limit']))) : 80;
$outMethodFilter = isset($_GET['out_method']) ? trim((string)$_GET['out_method']) : '';
$outStatusFilter = isset($_GET['out_status']) ? trim((string)$_GET['out_status']) : '';

$webhookRows = array();
$outgoingRows = array();
$outgoingMethods = array();
$movementErrors = array();
$movementPending = array();
$syncConflicts = array();
$cycleLastRun = '';
$successMsg = '';
$errorMsg = '';

$bulkReceiptUiDefault = isset($_GET['bulk']) && (string)$_GET['bulk'] === '1';

$funnelSnap = integrationLoadFunnelsSnapshotDecoded($db);
$reserveGateMerged = integrationMergedReserveGate($db, $bitrixCfg);
$realGateMerged = integrationMergedRealizationGate($db, $bitrixCfg);
$reserveStageMap = integrationStagesSelectedMapFromRules(
    isset($reserveGateMerged['rules']) && is_array($reserveGateMerged['rules']) ? $reserveGateMerged['rules'] : array()
);
$realStageMap = integrationStagesSelectedMapFromRules(
    isset($realGateMerged['rules']) && is_array($realGateMerged['rules']) ? $realGateMerged['rules'] : array()
);

$retryB24DeveloperToken = '';
$b24ResyncDeveloperDocs = array();
if ($friendcrm_sync_mode === 'developers') {
    require_once __DIR__ . '/includes/stock_operations_core.php';
    ensureStockOperationTables($db);
    $retryB24DeveloperToken = ensureFormToken('retry_b24_sync');
    try {
        $b24ResyncDeveloperDocs = $db->query("
            SELECT id, operation_type, doc_number, supplier, total_amount, status, created_at, b24_document_id, b24_sync_status
            FROM stock_operation_docs
            WHERE operation_type IN ('receipt', 'writeoff')
              AND b24_sync_status = 'sent'
              AND COALESCE(b24_document_id, 0) > 0
            ORDER BY id DESC
            LIMIT 50
        ")->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($b24ResyncDeveloperDocs)) {
            $b24ResyncDeveloperDocs = array();
        }
    } catch (Exception $e) {
        $b24ResyncDeveloperDocs = array();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];

    if ($action === 'save_sync_master_switch') {
        try {
            $on = isset($_POST['integration_all_sync_paused']) ? '1' : '0';
            $allowLocalRec = isset($_POST['integration_allow_local_receipt_during_pause']) ? '1' : '0';
            setAppSetting($db, integrationAllSyncPausedSettingsKey(), $on);
            setAppSetting($db, integrationAllowLocalReceiptDuringPauseSettingsKey(), $allowLocalRec);
            integrationBumpStockAbortEpoch($db);
            $successMsg = ($on === '1')
                ? 'Синхронизация отключена: вебхуки не обрабатываются, cron-скрипты и запись в Б24 заблокированы. Новые рулоны — только если отмечено разрешение локального прихода при паузе.'
                : 'Синхронизация снова включена.';
            $successMsg .= ' Длинный приход при необходимости откатывается (обновлён счётчик прерывания).';
        } catch (Exception $e) {
            $errorMsg = 'Не удалось сохранить переключатель: ' . $e->getMessage();
        }
    }

    if ($action === 'interrupt_running_receipt') {
        try {
            integrationBumpStockAbortEpoch($db);
            $successMsg = 'Сигнал прерывания отправлен: выполняющийся приход должен откатиться в течение нескольких секунд, если он ещё добавлял рулоны.';
        } catch (Exception $e) {
            $errorMsg = 'Не удалось отправить прерывание: ' . $e->getMessage();
        }
    }

    if ($action === 'save_emergency_roll_block') {
        try {
            require_once __DIR__ . '/functions/stock_emergency_kill.php';
            $block = isset($_POST['db_emergency_block_roll_creates']) ? '1' : '0';
            setAppSetting($db, stockEmergencyRollCreationDbKey(), $block);
            integrationBumpStockAbortEpoch($db);
            $successMsg = ($block === '1')
                ? 'Включён запрет создания новых рулонов через базу (не нужен FTP-файл).'
                : 'Запрет создания рулонов через базу снят.';
        } catch (Exception $e) {
            $errorMsg = 'Не удалось сохранить аварийный переключатель: ' . $e->getMessage();
        }
    }

    if ($action === 'refresh_b24_funnels') {
        try {
            $snap = integrationBuildDealFunnelsSnapshotFromBitrix();
            integrationSaveFunnelsSnapshot($db, $snap);
            $funnelSnap = $snap;
            if (!empty($snap['errors']) && is_array($snap['errors'])) {
                $errorMsg = 'Справочник сохранён, но при запросах к Б24 были ошибки: ' . htmlspecialchars(implode(' | ', $snap['errors']));
            } else {
                $successMsg = 'Воронки и стадии обновлены из Битрикс24.';
            }
        } catch (Exception $e) {
            $errorMsg = 'Не удалось обновить справочник: ' . $e->getMessage();
        }
    }

    if ($action === 'save_workflow_gates') {
        try {
            $reserveGate = integrationBuildGateFromPost(
                isset($_POST['reserve_filter_enabled']),
                isset($_POST['reserve_stages']) && is_array($_POST['reserve_stages']) ? $_POST['reserve_stages'] : array()
            );
            $realGate = integrationBuildGateFromPost(
                isset($_POST['realization_filter_enabled']),
                isset($_POST['realization_stages']) && is_array($_POST['realization_stages']) ? $_POST['realization_stages'] : array()
            );
            setAppSetting($db, integrationWarehouseReserveGateSettingsKey(), json_encode($reserveGate, JSON_UNESCAPED_UNICODE));
            setAppSetting($db, integrationWarehouseRealizationGateSettingsKey(), json_encode($realGate, JSON_UNESCAPED_UNICODE));
            $reserveGateMerged = integrationMergedReserveGate($db, $bitrixCfg);
            $realGateMerged = integrationMergedRealizationGate($db, $bitrixCfg);
            $reserveStageMap = integrationStagesSelectedMapFromRules(
                isset($reserveGateMerged['rules']) && is_array($reserveGateMerged['rules']) ? $reserveGateMerged['rules'] : array()
            );
            $realStageMap = integrationStagesSelectedMapFromRules(
                isset($realGateMerged['rules']) && is_array($realGateMerged['rules']) ? $realGateMerged['rules'] : array()
            );
            $successMsg = 'Правила резерва и реализации сохранены. Вебхук читает их из БД поверх базовых настроек в api/bitrix/config.php.';
        } catch (Exception $e) {
            $errorMsg = 'Не удалось сохранить правила: ' . $e->getMessage();
        }
    }

    if ($action === 'save_integration_settings') {
        try {
            $storeFrom = max(1, intval(isset($_POST['default_store_from_id']) ? $_POST['default_store_from_id'] : 1));
            $storeTo = max(1, intval(isset($_POST['default_store_to_id']) ? $_POST['default_store_to_id'] : 1));
            $responsibleId = max(1, intval(isset($_POST['default_responsible_id']) ? $_POST['default_responsible_id'] : 1));
            $currency = strtoupper(trim(isset($_POST['default_currency']) ? $_POST['default_currency'] : 'KGS'));
            $batchLimit = max(10, min(500, intval(isset($_POST['sync_batch_limit']) ? $_POST['sync_batch_limit'] : 100)));
            $docDelayMs = max(0, min(5000, intval(isset($_POST['b24_doc_delay_ms']) ? $_POST['b24_doc_delay_ms'] : 700)));
            $conductChecks = max(1, min(20, intval(isset($_POST['b24_conduct_check_attempts']) ? $_POST['b24_conduct_check_attempts'] : 5)));
            $usdToKgsRate = floatval(isset($_POST['usd_to_kgs_rate']) ? $_POST['usd_to_kgs_rate'] : 90);
            $stockSyncStoreId = max(1, intval(isset($_POST['stock_sync_store_id']) ? $_POST['stock_sync_store_id'] : 1));
            $syncCycleChunk = max(5, min(100, intval(isset($_POST['sync_cycle_chunk']) ? $_POST['sync_cycle_chunk'] : 30)));
            if ($usdToKgsRate <= 0) {
                $usdToKgsRate = 90;
            }
            if ($currency === '') {
                $currency = 'KGS';
            }
            setAppSetting($db, 'default_store_from_id', (string)$storeFrom);
            setAppSetting($db, 'default_store_to_id', (string)$storeTo);
            setAppSetting($db, 'default_responsible_id', (string)$responsibleId);
            setAppSetting($db, 'default_currency', $currency);
            setAppSetting($db, 'sync_batch_limit', (string)$batchLimit);
            setAppSetting($db, 'b24_doc_delay_ms', (string)$docDelayMs);
            setAppSetting($db, 'b24_conduct_check_attempts', (string)$conductChecks);
            setAppSetting($db, 'usd_to_kgs_rate', (string)$usdToKgsRate);
            setAppSetting($db, 'stock_sync_store_id', (string)$stockSyncStoreId);
            setAppSetting($db, 'sync_cycle_chunk', (string)$syncCycleChunk);
            $receiptSecretNew = isset($_POST['stock_receipt_api_secret']) ? trim((string)$_POST['stock_receipt_api_secret']) : '';
            if ($receiptSecretNew !== '') {
                setAppSetting($db, 'stock_receipt_api_secret', $receiptSecretNew);
            }
            $successMsg = 'Настройки интеграции сохранены.';
        } catch (Exception $e) {
            $errorMsg = 'Не удалось сохранить настройки: ' . $e->getMessage();
        }
    }

    if ($action === 'run_stock_receipt_json') {
        require_once __DIR__ . '/includes/stock_operations_core.php';
        @ini_set('max_execution_time', '0');
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        ensureStockOperationTables($db);
        $expectedRec = trim((string)getAppSetting($db, 'stock_receipt_api_secret', ''));
        $secretIn = isset($_POST['receipt_run_secret']) ? trim((string)$_POST['receipt_run_secret']) : '';
        if ($expectedRec === '') {
            $errorMsg = 'Сначала задайте и сохраните секрет JSON-прихода на странице «Разработчикам» (вкладка </> в шапке) в форме «Склады, курс…».';
        } elseif ($secretIn !== $expectedRec) {
            $errorMsg = 'Неверный секрет прихода.';
        } else {
            require_once __DIR__ . '/functions/stock_emergency_kill.php';
            $guiEm = stockEmergencyRollCreationStoppedMessage($db);
            if ($guiEm !== '') {
                $errorMsg = $guiEm;
            } else {
            $jsonRaw = '';
            if (isset($_FILES['receipt_json_file']) && isset($_FILES['receipt_json_file']['tmp_name'])
                && isset($_FILES['receipt_json_file']['error']) && (int)$_FILES['receipt_json_file']['error'] === UPLOAD_ERR_OK
                && is_uploaded_file($_FILES['receipt_json_file']['tmp_name'])) {
                $jsonRaw = (string)file_get_contents($_FILES['receipt_json_file']['tmp_name']);
            }
            if (trim($jsonRaw) === '' && isset($_POST['receipt_json_paste'])) {
                $jsonRaw = (string)$_POST['receipt_json_paste'];
            }
            $jsonRaw = trim($jsonRaw);
            if ($jsonRaw === '') {
                $errorMsg = 'Выберите файл .json или вставьте JSON в текстовое поле.';
            } else {
                $dataRec = json_decode($jsonRaw, true);
                if (!is_array($dataRec)) {
                    $jem = '';
                    if (function_exists('json_last_error_msg')) {
                        $jem = json_last_error_msg();
                    }
                    $errorMsg = 'Некорректный JSON' . ($jem !== '' ? (': ' . $jem) : '') . '.';
                } else {
                    $linesPerChunkForm = isset($_POST['receipt_lines_per_chunk']) ? intval($_POST['receipt_lines_per_chunk']) : 0;
                    $maxRollUnitsForm = isset($_POST['receipt_max_roll_units']) ? intval($_POST['receipt_max_roll_units']) : 0;
                    if (isset($dataRec['lines_per_chunk'])) {
                        $linesPerChunkForm = intval($dataRec['lines_per_chunk']);
                    }
                    if (isset($dataRec['max_roll_units_per_chunk'])) {
                        $maxRollUnitsForm = intval($dataRec['max_roll_units_per_chunk']);
                    }
                    $chunkOptGui = stockOperationsReceiptNormalizeChunkOptions($linesPerChunkForm, $maxRollUnitsForm);

                    $dnRec = isset($dataRec['doc_number']) ? trim((string)$dataRec['doc_number']) : '';
                    if ($dnRec === '' && !$chunkOptGui['active']) {
                        $dataRec['doc_number'] = 'AUTOGUI-' . substr(hash('sha256', $jsonRaw), 0, 40);
                    } elseif ($dnRec === '' && $chunkOptGui['active']) {
                        $dataRec['doc_number'] = '';
                    }
                    $paramsRec = array(
                        'doc_number' => isset($dataRec['doc_number']) ? $dataRec['doc_number'] : '',
                        'supplier' => isset($dataRec['supplier']) ? $dataRec['supplier'] : '',
                        'comment_text' => isset($dataRec['comment_text']) ? $dataRec['comment_text'] : '',
                        'receipt_currency' => isset($dataRec['receipt_currency']) ? $dataRec['receipt_currency'] : 'USD',
                        'min_full' => isset($dataRec['min_full']) ? $dataRec['min_full'] : 0.5,
                        'lines' => isset($dataRec['lines']) && is_array($dataRec['lines']) ? $dataRec['lines'] : array(),
                        'local_only' => (!empty($_POST['receipt_local_only']) || !empty($dataRec['local_only'])),
                    );

                    if ($chunkOptGui['active']) {
                        $templateBulk = array(
                            'doc_number' => $paramsRec['doc_number'],
                            'supplier' => $paramsRec['supplier'],
                            'comment_text' => $paramsRec['comment_text'],
                            'receipt_currency' => $paramsRec['receipt_currency'],
                            'min_full' => $paramsRec['min_full'],
                            'local_only' => $paramsRec['local_only'],
                        );
                        $wrapRec = stockOperationsRunChunkedReceiptFromPayload(
                            $db,
                            $templateBulk,
                            $paramsRec['lines'],
                            $linesPerChunkForm,
                            $maxRollUnitsForm,
                            $jsonRaw
                        );
                        if (!empty($wrapRec['ok'])) {
                            $idList = isset($wrapRec['doc_ids']) && is_array($wrapRec['doc_ids'])
                                ? array_map('intval', $wrapRec['doc_ids']) : array();
                            $successMsg = 'Приход из JSON выполнен частями: '
                                . 'документов ' . intval($wrapRec['chunks_total'])
                                . ' (локальные id: ' . (empty($idList) ? '—' : '#' . implode(', #', $idList)) . '). ';
                            $successMsg .= 'Строк партии до ' . intval($chunkOptGui['lines_per_chunk'])
                                . ', рулонов в партии до ' . intval($chunkOptGui['max_roll_units']) . '. ';
                            $tailMsg = '';
                            foreach ($wrapRec['results'] as $ri => $oner) {
                                if (!empty($oner['duplicate_receipt_skip'])) {
                                    $tailMsg .= 'Часть ' . ($ri + 1) . ': пропуск дубликата. ';
                                    continue;
                                }
                                $stOne = isset($oner['sync_status']) ? trim((string)$oner['sync_status']) : '';
                                $b24One = isset($oner['b24_document_id']) ? (string)$oner['b24_document_id'] : '';
                                if ($b24One !== '' && $stOne !== '') {
                                    $tailMsg .= 'Часть ' . ($ri + 1) . ': Б24=' . $b24One . ' (' . $stOne . '). ';
                                }
                            }
                            $successMsg .= trim($tailMsg);
                        } else {
                            $errorMsg = trim(isset($wrapRec['error_message']) ? $wrapRec['error_message'] : 'ошибка чанкового прихода');
                            if ($errorMsg === '') {
                                $errorMsg = 'Приход не выполнен (чанки).';
                            }
                        }
                    } else {
                        $resultRec = stockOperationsProcessCreateReceiptPayload($db, $paramsRec);
                        if (!empty($resultRec['ok'])) {
                            $parts = array();
                            $parts[] = 'локальный документ #' . (isset($resultRec['doc_id']) ? (int)$resultRec['doc_id'] : 0);
                            if (isset($resultRec['b24_document_id']) && $resultRec['b24_document_id'] !== null && (string)$resultRec['b24_document_id'] !== '') {
                                $parts[] = 'Б24 документ: ' . $resultRec['b24_document_id'];
                            }
                            if (isset($resultRec['sync_status']) && trim((string)$resultRec['sync_status']) !== '') {
                                $parts[] = 'синхронизация Б24: ' . $resultRec['sync_status'];
                            }
                            $smExtra = trim(isset($resultRec['success_message']) ? $resultRec['success_message'] : '');
                            $successMsg = 'Приход из JSON выполнен (' . implode(', ', $parts) . ')'
                                . ($smExtra !== '' ? '. ' . $smExtra : '') . '.';
                        } else {
                            $errorMsg = 'Приход не выполнен: ' . trim(isset($resultRec['error_message']) ? $resultRec['error_message'] : 'ошибка');
                        }
                    }
                }
            }
            }
        }
    }

    if ($action === 'clear_outgoing_log') {
        try {
            $db->exec("DELETE FROM bitrix_outgoing_log");
            $successMsg = 'Лог исходящих вызовов очищен.';
        } catch (Exception $e) {
            $errorMsg = 'Не удалось очистить лог исходящих: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $prgQm = array();
    if ($bulkReceiptUiDefault) {
        $prgQm['bulk'] = '1';
    }
    if (isset($_GET['limit'])) {
        $lm = intval($_GET['limit']);
        if ($lm > 0) {
            $prgQm['limit'] = (string)$lm;
        }
    }
    if ($outMethodFilter !== '') {
        $prgQm['out_method'] = $outMethodFilter;
    }
    if ($outStatusFilter !== '') {
        $prgQm['out_status'] = $outStatusFilter;
    }
    prgFlashCommitAndRedirect303WithQuery(
        'sync_monitor.php',
        $prgQm,
        array(
            'success' => $successMsg,
            'error' => $errorMsg,
        )
    );
}

$__syFlash = prgFlashConsume();
if (!empty($__syFlash['error'])) {
    $errorMsg = $__syFlash['error'];
    $successMsg = '';
} elseif (!empty($__syFlash['success'])) {
    $successMsg = $__syFlash['success'];
}

$stockEmergencyStopMsg = stockEmergencyRollCreationStoppedMessage($db);

$stockReceiptSecretStored = trim((string)getAppSetting($db, 'stock_receipt_api_secret', ''));

$integrationSettings = array(
    'stock_receipt_secret_set' => ($stockReceiptSecretStored !== ''),
    'default_store_from_id' => getAppSetting($db, 'default_store_from_id', '1'),
    'default_store_to_id' => getAppSetting($db, 'default_store_to_id', '1'),
    'default_responsible_id' => getAppSetting($db, 'default_responsible_id', '1'),
    'default_currency' => getAppSetting($db, 'default_currency', 'KGS'),
    'sync_batch_limit' => getAppSetting($db, 'sync_batch_limit', '100'),
    'b24_doc_delay_ms' => getAppSetting($db, 'b24_doc_delay_ms', '700'),
    'b24_conduct_check_attempts' => getAppSetting($db, 'b24_conduct_check_attempts', '5'),
    'usd_to_kgs_rate' => getAppSetting($db, 'usd_to_kgs_rate', '90'),
    'stock_sync_store_id' => getAppSetting($db, 'stock_sync_store_id', '1'),
    'sync_cycle_chunk' => getAppSetting($db, 'sync_cycle_chunk', '30')
);

$cycleLastRun = (string)getAppSetting($db, 'sync_cycle_last_run_json', '');

$funnelCats = ($funnelSnap !== null && isset($funnelSnap['categories']) && is_array($funnelSnap['categories']))
    ? $funnelSnap['categories']
    : array();
$funnelFetchedAt = ($funnelSnap !== null && isset($funnelSnap['fetched_at'])) ? (string)$funnelSnap['fetched_at'] : '';

try {
    $wk = $db->query('
        SELECT id, event,
               COALESCE(handler_outcome, \'\') AS handler_outcome,
               COALESCE(handler_detail, \'\') AS handler_detail,
               entity_deal_id, entity_product_id,
               CHAR_LENGTH(data) AS data_chars,
               LEFT(data, 1500) AS data_preview,
               created_at
        FROM webhook_log
        ORDER BY id DESC
        LIMIT ' . (int)$webhookLimit . '
    ');
    $webhookRows = $wk->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $webhookRows = array();
}

try {
    $mw = array();
    $mp = array();
    if ($outMethodFilter !== '') {
        $mw[] = "method = ?";
        $mp[] = $outMethodFilter;
    }
    if ($outStatusFilter !== '') {
        $mw[] = "status = ?";
        $mp[] = $outStatusFilter;
    }
    $outSql = '
        SELECT id, method, endpoint, status, error_code,
               CHAR_LENGTH(request_payload) AS request_chars,
               LEFT(request_payload, 1200) AS request_preview,
               CHAR_LENGTH(response_payload) AS response_chars,
               LEFT(response_payload, 1200) AS response_preview,
               created_at
        FROM bitrix_outgoing_log
    ';
    if (!empty($mw)) {
        $outSql .= ' WHERE ' . implode(' AND ', $mw);
    }
    $outSql .= ' ORDER BY id DESC LIMIT ' . intval($webhookLimit);
    $outStmt = $db->prepare($outSql);
    $outStmt->execute($mp);
    $outgoingRows = $outStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $outgoingRows = array();
}

try {
    $outgoingMethods = $db->query("
        SELECT DISTINCT method
        FROM bitrix_outgoing_log
        WHERE method IS NOT NULL AND method != ''
        ORDER BY method ASC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_COLUMN);
    if (!is_array($outgoingMethods)) {
        $outgoingMethods = array();
    }
} catch (Exception $e) {
    $outgoingMethods = array();
}

try {
    $movementErrors = $db->query("
        SELECT id, product_id, movement_type, deal_id, bitrix_status, bitrix_response, created_at
        FROM stock_movements
        WHERE bitrix_status = 'error'
        ORDER BY id DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
    $movementPending = $db->query("
        SELECT id, product_id, movement_type, deal_id, bitrix_status, created_at
        FROM stock_movements
        WHERE bitrix_status = 'pending'
        ORDER BY id DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $movementErrors = array();
    $movementPending = array();
}

try {
    $syncConflicts = $db->query("
        SELECT id, conflict_type, b24_product_id, local_product_id, local_value, b24_value, details, created_at
        FROM b24_sync_conflicts
        WHERE status = 'new'
        ORDER BY id DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $syncConflicts = array();
}

$storedReserveRaw = getAppSetting($db, integrationWarehouseReserveGateSettingsKey(), '');
$storedRealRaw = getAppSetting($db, integrationWarehouseRealizationGateSettingsKey(), '');
$integrationSyncPaused = integrationAllSyncPaused($db);
$integrationAllowLocalReceiptDuringPause = integrationAllowsLocalReceiptDuringPause($db);
$integrationStockAbortEpoch = integrationGetStockAbortEpoch($db);
$dbEmergencyRollBlockOn = (trim((string)getAppSetting($db, stockEmergencyRollCreationDbKey(), '0')) === '1');

$page_title = ($friendcrm_sync_mode === 'developers') ? 'Разработчикам' : 'Настройки';
require 'includes/header.php';
?>

<main class="container integration-main-stack">
<?php if ($friendcrm_sync_mode === 'developers'): ?>

    <h2 id="sec-top">Разработчикам</h2>
    <p class="text-muted integration-flow-subtitle">Пауза Битрикс24, приход из JSON, справочники, вебхуки, секрет API. <a href="sync_monitor.php">← Назад к настройкам</a></p>

    <?php if ($successMsg !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <?php if ($stockEmergencyStopMsg !== ''): ?>
        <div class="alert alert-danger" role="alert" style="border-left:4px solid #c00;">
            <strong>Аварийная остановка складских приходов активна.</strong>
            <?= htmlspecialchars($stockEmergencyStopMsg) ?>
            <div class="text-muted" style="margin-top:8px;font-size:0.92rem;">
                Файловый стоп: удалите <code>STOCK_CREATES_OFF</code> / <code>STOCK_CREATES_OFF.txt</code> в корне сайта (рядом с <code>index.php</code>).
                Если включён запрет через БД — отключите галочку в блоке паузы ниже или выполните <code>UPDATE app_settings SET value=&apos;0&apos; WHERE `key`=&apos;emergency_block_roll_creates&apos;;</code> в phpMyAdmin.
            </div>
        </div>
    <?php endif; ?>

    <?php if ($integrationSyncPaused): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Пауза синхронизации включена.</strong>
            Исходящие вебхуки получают ответ без изменения складских данных в приложении.
            Изменения в Битрикс24 из этого приложения (остатки, цены, приходные документы, комментарии к сделкам) не отправляются.
            Импорт товаров, цикл автосинка и ручные кнопки синка из этого раздела возвращают JSON с <code>integration_sync_paused</code>.
            Новые рулоны только при активной галочке «Разрешить локальный приход при паузе» и режиме <code>local_only</code>.
        </div>
    <?php endif; ?>

    <div class="card" style="padding:14px 16px;margin-bottom:1rem;">
        <p class="text-muted" style="margin:0;font-size:0.92rem;">Секрет и curl для <code>api/create_receipt_json.php</code> — в форме ниже. Массовый приход Llumar в браузере: <a href="sync_monitor_developers.php?bulk=1#sec-receipt-json"><code>?bulk=1</code></a>.</p>
    </div>

    <details class="card integration-section integration-flow-settings" id="sec-settings-dev" open>
        <summary class="integration-section-summary">Склады, курс, лимиты и секрет JSON-прихода</summary>
        <div class="integration-section-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_integration_settings">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Склад списания (storeFrom)</label>
                        <input class="input" type="number" min="1" name="default_store_from_id" value="<?= (int)$integrationSettings['default_store_from_id'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Склад прихода (storeTo)</label>
                        <input class="input" type="number" min="1" name="default_store_to_id" value="<?= (int)$integrationSettings['default_store_to_id'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Ответственный (responsibleId)</label>
                        <input class="input" type="number" min="1" name="default_responsible_id" value="<?= (int)$integrationSettings['default_responsible_id'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Валюта</label>
                        <input class="input" type="text" maxlength="3" name="default_currency" value="<?= htmlspecialchars((string)$integrationSettings['default_currency']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Скорость синка (batch limit)</label>
                        <input class="input" type="number" min="10" max="500" name="sync_batch_limit" value="<?= (int)$integrationSettings['sync_batch_limit'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Задержка перед проведением документа Б24 (мс)</label>
                        <input class="input" type="number" min="0" max="5000" name="b24_doc_delay_ms" value="<?= (int)$integrationSettings['b24_doc_delay_ms'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Проверок статуса проведения (attempts)</label>
                        <input class="input" type="number" min="1" max="20" name="b24_conduct_check_attempts" value="<?= (int)$integrationSettings['b24_conduct_check_attempts'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Курс USD → KGS (для прихода)</label>
                        <input class="input" type="number" min="0.01" step="0.01" name="usd_to_kgs_rate" value="<?= htmlspecialchars((string)$integrationSettings['usd_to_kgs_rate']) ?>">
                    </div>
                    <div class="form-group">
                        <label>ID склада Б24 для синка остатков</label>
                        <input class="input" type="number" min="1" name="stock_sync_store_id" value="<?= (int)$integrationSettings['stock_sync_store_id'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Размер шага автосинка (5-100)</label>
                        <input class="input" type="number" min="5" max="100" name="sync_cycle_chunk" value="<?= (int)$integrationSettings['sync_cycle_chunk'] ?>">
                    </div>
                    <div class="form-group warehouse-filter-item-wide">
                        <label>Секрет JSON-прихода (<code>stock_receipt_api_secret</code>)</label>
                        <input class="input" type="password" name="stock_receipt_api_secret" value="" autocomplete="new-password"
                            placeholder="<?= !empty($integrationSettings['stock_receipt_secret_set']) ? 'Оставьте пустым, чтобы не менять текущий ключ' : 'Задайте длинную случайную строку и сохраните' ?>">
                        <p class="text-muted" style="margin-top:6px;margin-bottom:0;">
                            Нужен для <code>api/create_receipt_json.php</code>: один <strong>POST</strong> с телом JSON прихода → запись в приложение + документ в Б24.
                            <?php if (!empty($integrationSettings['stock_receipt_secret_set'])): ?>
                                Ключ уже задан; новое значение перезапишет его.
                            <?php else: ?>
                                Пока не задан — API прихода отвечает 503.
                            <?php endif; ?>
                        </p>
                        <pre style="margin-top:10px;white-space:pre-wrap;font-size:12px;padding:10px;background:var(--background-color,#f5f6fa);border-radius:6px;border:1px solid var(--border-color);">curl -X POST "<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'ваш-хост')) ?>/api/create_receipt_json.php" \
  -H "Content-Type: application/json; charset=utf-8" \
  -H "X-Stock-Receipt-Secret: ВАШ_СЕКРЕТ_ИЗ_ПОЛЯ_ВЫШЕ" \
  --data-binary @example/new/bulk_receipt_from_llumar.generated.json</pre>
                    </div>
                </div>
                <button class="btn btn-success" type="submit">Сохранить настройки</button>
            </form>
        </div>
    </details>

    <?php require __DIR__ . '/includes/sync_monitor_developer_sections.php'; ?>

<?php else: ?>

    <h2 id="sec-top">Настройки</h2>
    <p class="text-muted integration-flow-subtitle">Битрикс24, параметры складов и обмен данными.</p>

    <?php if ($successMsg !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <?php if ($stockEmergencyStopMsg !== ''): ?>
        <div class="alert alert-danger" role="alert" style="border-left:4px solid #c00;">
            <strong>Аварийная остановка складских приходов активна.</strong>
            <?= htmlspecialchars($stockEmergencyStopMsg) ?>
            <div class="text-muted" style="margin-top:8px;font-size:0.92rem;">
                Файловый стоп: удалите <code>STOCK_CREATES_OFF</code> / <code>STOCK_CREATES_OFF.txt</code> в корне сайта (рядом с <code>index.php</code>).
                Если включён запрет через БД — отключите галочку на вкладке <a href="sync_monitor_developers.php">«Разработчикам»</a> (блок паузы) или выполните <code>UPDATE app_settings SET value=&apos;0&apos; WHERE `key`=&apos;emergency_block_roll_creates&apos;;</code> в phpMyAdmin.
            </div>
        </div>
    <?php endif; ?>

    <?php if ($integrationSyncPaused): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Пауза синхронизации включена.</strong>
            Исходящие вебхуки получают ответ без изменения складских данных в приложении.
            Изменения в Битрикс24 из этого приложения (остатки, цены, приходные документы, комментарии к сделкам) не отправляются.
            Импорт товаров, цикл автосинка и ручные кнопки синка из этого раздела возвращают JSON с <code>integration_sync_paused</code>.
            Новые рулоны только при активной галочке «Разрешить локальный приход при паузе» и режиме <code>local_only</code>.
        </div>
    <?php endif; ?>

    <p class="integration-flow-hint card" style="padding:12px 16px;margin-bottom:1rem;margin-top:0;">
        Технические инструменты (пауза синхронизации, приход JSON, вебхуки, секрет API) — на отдельной вкладке
        <a href="sync_monitor_developers.php"><strong class="nav-dev-icon-copy">&lt;/&gt; Разработчикам</strong></a> в шапке.
    </p>

    <details class="card integration-section integration-flow-quick" id="sec-quick" open>
        <summary class="integration-section-summary">Быстрые действия</summary>
        <div class="integration-section-body">
            <p class="text-muted">Кнопки запускают синк через модальное окно, без открытия новых вкладок.
                Массовый приход из JSON — на вкладке <a href="sync_monitor_developers.php">Разработчикам</a>,
                например <a href="sync_monitor_developers.php?bulk=1#sec-receipt-json"><code>…developers.php?bulk=1</code></a>.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a class="btn btn-primary b24-sync-link" href="api/bitrix/import_products.php">Импортировать товары из Б24</a>
                <a class="btn btn-primary b24-sync-link" href="api/bitrix/sync_stock.php?push=1">Синхронизировать остатки</a>
                <a class="btn btn-secondary b24-sync-link" href="api/sync_prices.php?action=to_b24">Синхронизировать цены</a>
                <a class="btn btn-secondary b24-sync-link" href="api/bitrix/sync_cycle.php?chunk=<?= (int)$integrationSettings['sync_cycle_chunk'] ?>">Запустить 1 цикл автосинка</a>
            </div>
        </div>
    </details>

    <div class="integration-flow-section-nav card">
        <h3 style="margin-top:0;">На странице</h3>
        <p class="text-muted">Переход по якорям. Расширенные блоки — <a href="sync_monitor_developers.php">Разработчикам</a>.</p>
        <nav class="integration-nav" aria-label="Разделы страницы настроек">
            <a href="#sec-quick">Быстрые действия</a>
            <span class="text-muted">·</span>
            <a href="#sec-settings">Склады, курс и лимиты</a>
            <span class="text-muted">·</span>
            <a href="#sec-mov-errors">Ошибки Б24</a>
            <span class="text-muted">·</span>
            <a href="#sec-pending">Ожидают отправки</a>
            <span class="text-muted">·</span>
            <a href="#sec-conflicts">Расхождения</a>
        </nav>
    </div>

    <details class="card integration-section integration-flow-settings" id="sec-settings" open>
        <summary class="integration-section-summary">Склады, курс и параметры синхронизации</summary>
        <div class="integration-section-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_integration_settings">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Склад списания (storeFrom)</label>
                        <input class="input" type="number" min="1" name="default_store_from_id" value="<?= (int)$integrationSettings['default_store_from_id'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Склад прихода (storeTo)</label>
                        <input class="input" type="number" min="1" name="default_store_to_id" value="<?= (int)$integrationSettings['default_store_to_id'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Ответственный (responsibleId)</label>
                        <input class="input" type="number" min="1" name="default_responsible_id" value="<?= (int)$integrationSettings['default_responsible_id'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Валюта</label>
                        <input class="input" type="text" maxlength="3" name="default_currency" value="<?= htmlspecialchars((string)$integrationSettings['default_currency']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Скорость синка (batch limit)</label>
                        <input class="input" type="number" min="10" max="500" name="sync_batch_limit" value="<?= (int)$integrationSettings['sync_batch_limit'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Задержка перед проведением документа Б24 (мс)</label>
                        <input class="input" type="number" min="0" max="5000" name="b24_doc_delay_ms" value="<?= (int)$integrationSettings['b24_doc_delay_ms'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Проверок статуса проведения (attempts)</label>
                        <input class="input" type="number" min="1" max="20" name="b24_conduct_check_attempts" value="<?= (int)$integrationSettings['b24_conduct_check_attempts'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Курс USD → KGS (для прихода)</label>
                        <input class="input" type="number" min="0.01" step="0.01" name="usd_to_kgs_rate" value="<?= htmlspecialchars((string)$integrationSettings['usd_to_kgs_rate']) ?>">
                    </div>
                    <div class="form-group">
                        <label>ID склада Б24 для синка остатков</label>
                        <input class="input" type="number" min="1" name="stock_sync_store_id" value="<?= (int)$integrationSettings['stock_sync_store_id'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Размер шага автосинка (5-100)</label>
                        <input class="input" type="number" min="5" max="100" name="sync_cycle_chunk" value="<?= (int)$integrationSettings['sync_cycle_chunk'] ?>">
                    </div>
                </div>
                <button class="btn btn-success" type="submit">Сохранить настройки</button>
            </form>
        </div>
    </details>

    <details class="card integration-section integration-flow-monitor" id="sec-mov-errors" open>
        <summary class="integration-section-summary">Ошибки отправки в Б24</summary>
        <div class="integration-section-body">
        <table class="table">
            <tr><th>ID</th><th>Product</th><th>Тип</th><th>Deal</th><th>Статус</th><th>Ответ</th><th>Время</th></tr>
            <?php foreach ($movementErrors as $row): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= (int)$row['product_id'] ?></td>
                    <td><?= htmlspecialchars($row['movement_type']) ?></td>
                    <td><?= (int)$row['deal_id'] ?></td>
                    <td><?= htmlspecialchars($row['bitrix_status']) ?></td>
                    <td><pre style="white-space:pre-wrap;max-width:420px;"><?= htmlspecialchars((string)$row['bitrix_response']) ?></pre></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    </details>

    <details class="card integration-section integration-flow-monitor" id="sec-pending" open>
        <summary class="integration-section-summary">Ожидают отправки</summary>
        <div class="integration-section-body">
        <table class="table">
            <tr><th>ID</th><th>Product</th><th>Тип</th><th>Deal</th><th>Статус</th><th>Время</th></tr>
            <?php foreach ($movementPending as $row): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= (int)$row['product_id'] ?></td>
                    <td><?= htmlspecialchars($row['movement_type']) ?></td>
                    <td><?= (int)$row['deal_id'] ?></td>
                    <td><?= htmlspecialchars($row['bitrix_status']) ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    </details>

    <details class="card integration-section integration-flow-monitor" id="sec-conflicts" open>
        <summary class="integration-section-summary">Найденные расхождения с Б24</summary>
        <div class="integration-section-body">
            <p class="text-muted">Если здесь есть строки, нужно проверить товар и понять, где фактическая истина (приложение или Б24).</p>
            <table class="table">
                <tr><th>ID</th><th>Тип</th><th>B24 товар</th><th>Локальный товар</th><th>Локально</th><th>В Б24</th><th>Комментарий</th><th>Время</th></tr>
                <?php foreach ($syncConflicts as $row): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars((string)$row['conflict_type']) ?></td>
                        <td><?= (int)$row['b24_product_id'] ?></td>
                        <td><?= (int)$row['local_product_id'] ?></td>
                        <td><?= htmlspecialchars((string)$row['local_value']) ?></td>
                        <td><?= htmlspecialchars((string)$row['b24_value']) ?></td>
                        <td><?= htmlspecialchars((string)$row['details']) ?></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </details>
<?php endif; ?>
</main>


<?php
$friendcrm_footer_append_html = ($friendcrm_sync_mode === 'developers')
    ? '<script src="assets/sync_monitor_receipt_chunks.js?v=1"></script>'
    : '';
require 'includes/footer.php';
