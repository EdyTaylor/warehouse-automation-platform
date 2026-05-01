<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
require_once __DIR__ . '/functions/app_settings.php';
require_once __DIR__ . '/functions/webhook_log_schema.php';
require_once __DIR__ . '/functions/integration_workflow_gates.php';
require_once __DIR__ . '/functions/integration_bitrix_funnels.php';
require_once __DIR__ . '/functions/integration_sync_control.php';
$db = getDB();
webhookLogEnsureSchema($db);

$bitrixCfg = require __DIR__ . '/api/bitrix/config.php';

$webhookLimit = isset($_GET['limit']) ? max(10, min(500, intval($_GET['limit']))) : 80;
$page_title = 'Центр интеграции';
require 'includes/header.php';

$webhookRows = array();
$movementErrors = array();
$movementPending = array();
$syncConflicts = array();
$cycleLastRun = '';
$successMsg = '';
$errorMsg = '';

$funnelSnap = integrationLoadFunnelsSnapshotDecoded($db);
$reserveGateMerged = integrationMergedReserveGate($db, $bitrixCfg);
$realGateMerged = integrationMergedRealizationGate($db, $bitrixCfg);
$reserveStageMap = integrationStagesSelectedMapFromRules(
    isset($reserveGateMerged['rules']) && is_array($reserveGateMerged['rules']) ? $reserveGateMerged['rules'] : array()
);
$realStageMap = integrationStagesSelectedMapFromRules(
    isset($realGateMerged['rules']) && is_array($realGateMerged['rules']) ? $realGateMerged['rules'] : array()
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];

    if ($action === 'save_sync_master_switch') {
        try {
            $on = isset($_POST['integration_all_sync_paused']) ? '1' : '0';
            setAppSetting($db, integrationAllSyncPausedSettingsKey(), $on);
            $successMsg = ($on === '1')
                ? 'Синхронизация отключена: вебхуки не обрабатываются, cron-скрипты и запись в Б24 заблокированы. Можно безопасно чистить локальную БД.'
                : 'Синхронизация снова включена.';
        } catch (Exception $e) {
            $errorMsg = 'Не удалось сохранить переключатель: ' . $e->getMessage();
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
            $errorMsg = 'Сначала задайте и сохраните секрет JSON-прихода в блоке «Склады и синк» ниже.';
        } elseif ($secretIn !== $expectedRec) {
            $errorMsg = 'Неверный секрет прихода.';
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
                    $paramsRec = array(
                        'doc_number' => isset($dataRec['doc_number']) ? $dataRec['doc_number'] : '',
                        'supplier' => isset($dataRec['supplier']) ? $dataRec['supplier'] : '',
                        'comment_text' => isset($dataRec['comment_text']) ? $dataRec['comment_text'] : '',
                        'receipt_currency' => isset($dataRec['receipt_currency']) ? $dataRec['receipt_currency'] : 'USD',
                        'min_full' => isset($dataRec['min_full']) ? $dataRec['min_full'] : 0.5,
                        'lines' => isset($dataRec['lines']) && is_array($dataRec['lines']) ? $dataRec['lines'] : array(),
                        'local_only' => (!empty($_POST['receipt_local_only']) || !empty($dataRec['local_only'])),
                    );
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
?>

<main class="container">
    <h2 id="sec-top">Центр интеграции</h2>

    <?php if ($successMsg !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <?php if ($integrationSyncPaused): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Пауза синхронизации включена.</strong>
            Исходящие вебхуки получают ответ без изменения складских данных в приложении.
            Изменения в Битрикс24 из этого приложения (остатки, цены, приходные документы, комментарии к сделкам) не отправляются.
            Импорт товаров, цикл автосинка и ручные кнопки синка из этого раздела возвращают JSON с <code>integration_sync_paused</code>.
        </div>
    <?php endif; ?>

    <details class="card integration-section" id="sec-sync-master" open>
        <summary class="integration-section-summary">Пауза всей синхронизации с Битрикс24</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Включите перед массовой очисткой таблиц (товары, рулоны, заявки B24, движения). После работы обязательно выключите,
                иначе склад и портал перестанут обмениваться данными. Обновление справочника воронок (только чтение из Б24) при паузе по-прежнему работает.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="save_sync_master_switch">
                <label style="display:flex;gap:10px;align-items:flex-start;max-width:42rem;cursor:pointer;">
                    <input type="checkbox" name="integration_all_sync_paused" value="1" <?= $integrationSyncPaused ? 'checked' : '' ?> style="margin-top:4px;">
                    <span><strong>Отключить синхронизацию</strong> — вебхуки Б24: ответ <code>integration_sync_paused</code> <em>без</em> записи в <code>webhook_log</code>; cron/импорт и исходящие <code>sendToBitrix</code> блокируются. Добавление рулонов через склад, дашборд, <code>add_stock</code> и «принятие остатка из Б24» по конфликту — тоже заблокированы. Исключение: приход с режимом <strong>только локально</strong> (<code>local_only</code>) и JSON/API/форма складских операций с соответствующей галочкой.</span>
                </label>
                <p style="margin-top:12px;">
                    <button class="btn btn-warning" type="submit">Сохранить переключатель</button>
                </p>
            </form>
        </div>
    </details>

    <details class="card integration-section" id="sec-receipt-json" open>
        <summary class="integration-section-summary">Приход из JSON (без Postman)</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Формат как у <code>api/create_receipt_json.php</code>. По умолчанию локальный документ и синхронизация с Битрикс24.
                Для большого прихода включите ниже опцию «только локально» — один документ в приложении без вызовов Б24 (меньше вероятность 504 и дробления из‑за повторов после таймаута).
                В JSON можно добавить ключ <code>&quot;local_only&quot;: true</code>; галочка тоже задаёт режим локально только.
                Если включена <strong>пауза синхронизации</strong>, запись в Б24 может быть заблокирована — без «только локально» локальная часть может выполниться или откатиться в зависимости от ошибки Б24.
            </p>
            <?php if ($stockReceiptSecretStored === ''): ?>
                <div class="alert alert-warning">Секрет прихода ещё не задан — сначала сохраните его в блоке «Склады и синк».</div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="run_stock_receipt_json">
                <div class="form-group">
                    <label>Файл JSON</label>
                    <input class="input" type="file" name="receipt_json_file" accept=".json,application/json">
                </div>
                <div class="form-group">
                    <label>Или вставьте JSON целиком</label>
                    <textarea class="input" name="receipt_json_paste" rows="10" style="width:100%;max-width:100%;font-family:monospace;font-size:12px;" placeholder="{ &quot;doc_number&quot;: &quot;…&quot;, &quot;lines&quot;: [ … ] }"></textarea>
                </div>
                <div class="form-group">
                    <label>Секрет (<code>stock_receipt_api_secret</code>)</label>
                    <input class="input" type="password" name="receipt_run_secret" autocomplete="off" style="max-width:28rem;"
                        <?= $stockReceiptSecretStored !== '' ? 'required' : '' ?>>
                </div>
                <div class="form-group">
                    <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;">
                        <input type="checkbox" name="receipt_local_only" value="1" style="margin-top:4px;">
                        <span><strong>Только локально</strong> — не звать Битрикс24 при этом приходе (<code>local_only</code>).</span>
                    </label>
                </div>
                <button class="btn btn-primary" type="submit" <?= $stockReceiptSecretStored === '' ? 'disabled' : '' ?>>Запустить приход</button>
            </form>
            <p class="text-muted" style="margin-top:10px;font-size:0.9rem;">
                Ограничение размера файла задаётся в PHP (<code>upload_max_filesize</code> / <code>post_max_size</code> на сервере). Большие приходы удобнее грузить через тот же JSON по API после настройки HTTPS.
            </p>
        </div>
    </details>

    <div class="card">
        <h3 style="margin-top:0;">Разделы</h3>
        <p class="text-muted">Перейти к нужному блоку. Ниже секции можно сворачивать, чтобы убрать лишнее с экрана.</p>
        <nav class="integration-nav" aria-label="Разделы интеграции">
            <a href="#sec-sync-master">Пауза синхронизации</a>
            <span class="text-muted">·</span>
            <a href="#sec-receipt-json">Приход из JSON</a>
            <span class="text-muted">·</span>
            <a href="#sec-quick">Быстрые действия</a>
            <span class="text-muted">·</span>
            <a href="#sec-tech">Техраздел Б24</a>
            <span class="text-muted">·</span>
            <a href="#sec-funnels">Воронки и стадии</a>
            <span class="text-muted">·</span>
            <a href="#sec-workflow">Резерв и реализация</a>
            <span class="text-muted">·</span>
            <a href="#sec-settings">Склады и синк</a>
            <span class="text-muted">·</span>
            <a href="#sec-autosync">Автосинк</a>
            <span class="text-muted">·</span>
            <a href="#sec-webhooks">Вебхуки</a>
            <span class="text-muted">·</span>
            <a href="#sec-mov-errors">Ошибки Б24</a>
            <span class="text-muted">·</span>
            <a href="#sec-pending">Ожидают отправки</a>
            <span class="text-muted">·</span>
            <a href="#sec-conflicts">Расхождения</a>
        </nav>
        <button type="button" class="btn btn-light btn-sm js-theme-toggle">Переключить тему</button>
    </div>

    <details class="card integration-section" id="sec-quick" open>
        <summary class="integration-section-summary">Быстрые действия</summary>
        <div class="integration-section-body">
            <p class="text-muted">Кнопки запускают синк через модальное окно, без открытия новых вкладок.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a class="btn btn-primary b24-sync-link" href="api/bitrix/import_products.php">Импортировать товары из Б24</a>
                <a class="btn btn-primary b24-sync-link" href="api/bitrix/sync_stock.php?push=1">Синхронизировать остатки</a>
                <a class="btn btn-secondary b24-sync-link" href="api/sync_prices.php?action=to_b24">Синхронизировать цены</a>
                <a class="btn btn-secondary b24-sync-link" href="api/bitrix/sync_cycle.php?chunk=<?= (int)$integrationSettings['sync_cycle_chunk'] ?>">Запустить 1 цикл автосинка</a>
            </div>
        </div>
    </details>

    <details class="card integration-section" id="sec-tech">
        <summary class="integration-section-summary">Технический раздел Б24</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Сервисные операции интеграции: ручной резерв, синк product rows, диагностика. Для ежедневной работы —
                <strong>Место кладовщика</strong>.
            </p>
            <a class="btn btn-light" href="b24_sales.php">Открыть тех.раздел Б24</a>
        </div>
    </details>

    <details class="card integration-section" id="sec-funnels" open>
        <summary class="integration-section-summary">Воронки и стадии из Битрикс24</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Актуальные названия воронок (CATEGORY_ID), идентификаторы стадий (<code>STATUS_ID</code> в сделке —
                <code>STAGE_ID</code>) и подписи. Данные кэшируются в БД; обновите после изменений в Б24.
            </p>
            <form method="POST" style="margin-bottom:12px;">
                <input type="hidden" name="action" value="refresh_b24_funnels">
                <button class="btn btn-primary" type="submit">Обновить справочник из Б24</button>
            </form>
            <?php if ($funnelFetchedAt !== ''): ?>
                <p><strong>Последнее обновление:</strong> <?= htmlspecialchars($funnelFetchedAt) ?>
                <?php if ($funnelSnap !== null && !empty($funnelSnap['category_source'])): ?>
                    <span class="text-muted">(источник воронок: <?= htmlspecialchars((string)$funnelSnap['category_source']) ?>)</span>
                <?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if (count($funnelCats) === 0): ?>
                <div class="alert alert-warning">Справочник пуст. Нажмите «Обновить справочник из Б24» (нужен рабочий вебхук в <code>api/bitrix/config.php</code>).</div>
            <?php else: ?>
                <?php foreach ($funnelCats as $fcat): ?>
                    <?php
                    if (!is_array($fcat)) {
                        continue;
                    }
                    $fcid = isset($fcat['id']) ? (int)$fcat['id'] : 0;
                    $fname = isset($fcat['name']) ? (string)$fcat['name'] : ('Воронка #' . $fcid);
                    $fentity = isset($fcat['entity_id']) ? (string)$fcat['entity_id'] : '';
                    $fstages = isset($fcat['stages']) && is_array($fcat['stages']) ? $fcat['stages'] : array();
                    ?>
                    <details style="margin:10px 0;border:1px solid var(--border-color);border-radius:6px;padding:4px 10px;">
                        <summary style="cursor:pointer;font-weight:600;">
                            <?= htmlspecialchars($fname) ?> — <span class="text-muted">CATEGORY_ID = <?= (int)$fcid ?></span>
                            <?php if ($fentity !== ''): ?>
                                · <code><?= htmlspecialchars($fentity) ?></code>
                            <?php endif; ?>
                        </summary>
                        <?php if (count($fstages) === 0): ?>
                            <p class="text-muted">Стадий не получено.</p>
                        <?php else: ?>
                            <table class="integration-funnel-stage-table">
                                <tr>
                                    <th>Название</th>
                                    <th>STATUS_ID</th>
                                    <th>Семантика</th>
                                </tr>
                                <?php foreach ($fstages as $st): ?>
                                    <?php if (!is_array($st)) { continue; } ?>
                                    <tr>
                                        <td><?= htmlspecialchars(isset($st['NAME']) ? (string)$st['NAME'] : '') ?></td>
                                        <td><code><?= htmlspecialchars(isset($st['STATUS_ID']) ? (string)$st['STATUS_ID'] : '') ?></code></td>
                                        <td><?= htmlspecialchars(isset($st['SEMANTICS']) ? (string)$st['SEMANTICS'] : '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>

    <details class="card integration-section" id="sec-workflow" open>
        <summary class="integration-section-summary">Резерв (очередь кладовщика) и реализация (списание)</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                <strong>Резерв</strong> — когда вебхук ставит сделку в очередь <code>b24_sale_requests</code> (см. <code>api/webhook.php</code>).
                <strong>Реализация</strong> — когда сделка считается оплаченной/завершённой: статус заявки <code>completed</code>,
                проводки <code>sale_meter</code> «Сделка оплачена в Б24». Если включить фильтр реализации, используются только отмеченные стадии;
                если выключить — сохраняется прежняя логика (семантика успеха и известные <code>WON</code> / <code>FINAL_INVOICE</code> и т.д.).
            </p>
            <?php if ($storedReserveRaw === ''): ?>
                <p class="text-muted">Переопределения резерва в БД ещё не сохранялись — действуют правила из <code>api/bitrix/config.php</code> (<code>warehouse_queue</code>).</p>
            <?php endif; ?>
            <?php if ($storedRealRaw === ''): ?>
                <p class="text-muted">Переопределения реализации в БД ещё не сохранялись — действует блок <code>warehouse_realization</code> из config или эвристика по умолчанию.</p>
            <?php endif; ?>
            <?php if (count($funnelCats) === 0): ?>
                <div class="alert alert-warning">Сначала обновите справочник воронок выше — иначе нет списка стадий для галочек.</div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="save_workflow_gates">
                <p style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;">
                    <label style="display:inline-flex;gap:8px;align-items:center;">
                        <input type="checkbox" name="reserve_filter_enabled" value="1" <?= !empty($reserveGateMerged['filter_enabled']) ? 'checked' : '' ?>>
                        Ограничить <strong>резерв</strong> выбранными стадиями
                    </label>
                    <label style="display:inline-flex;gap:8px;align-items:center;">
                        <input type="checkbox" name="realization_filter_enabled" value="1" <?= !empty($realGateMerged['filter_enabled']) ? 'checked' : '' ?>>
                        Задавать <strong>реализацию</strong> только выбранными стадиями
                    </label>
                </p>
                <?php foreach ($funnelCats as $fcat): ?>
                    <?php
                    if (!is_array($fcat)) {
                        continue;
                    }
                    $fcid = isset($fcat['id']) ? (int)$fcat['id'] : 0;
                    $fname = isset($fcat['name']) ? (string)$fcat['name'] : ('Воронка #' . $fcid);
                    $fstages = isset($fcat['stages']) && is_array($fcat['stages']) ? $fcat['stages'] : array();
                    ?>
                    <fieldset style="border:1px solid var(--border-color);border-radius:8px;padding:10px;margin:12px 0;">
                        <legend><strong><?= htmlspecialchars($fname) ?></strong> <span class="text-muted">(<?= (int)$fcid ?>)</span></legend>
                        <?php if (count($fstages) === 0): ?>
                            <p class="text-muted">Нет стадий в кэше.</p>
                        <?php else: ?>
                            <table class="integration-funnel-stage-table">
                                <tr>
                                    <th>Стадия</th>
                                    <th>STATUS_ID</th>
                                    <th>Резерв</th>
                                    <th>Реализация</th>
                                </tr>
                                <?php foreach ($fstages as $st): ?>
                                    <?php
                                    if (!is_array($st)) {
                                        continue;
                                    }
                                    $sid = isset($st['STATUS_ID']) ? (string)$st['STATUS_ID'] : '';
                                    if ($sid === '') {
                                        continue;
                                    }
                                    $sname = isset($st['NAME']) ? (string)$st['NAME'] : '';
                                    $rOn = isset($reserveStageMap[$fcid][$sid]);
                                    $zOn = isset($realStageMap[$fcid][$sid]);
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sname) ?></td>
                                        <td><code><?= htmlspecialchars($sid) ?></code></td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="reserve_stages[<?= (int)$fcid ?>][]" value="<?= htmlspecialchars($sid) ?>" <?= $rOn ? 'checked' : '' ?>>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="realization_stages[<?= (int)$fcid ?>][]" value="<?= htmlspecialchars($sid) ?>" <?= $zOn ? 'checked' : '' ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </fieldset>
                <?php endforeach; ?>
                <button class="btn btn-success" type="submit">Сохранить правила</button>
            </form>
        </div>
    </details>

    <details class="card integration-section" id="sec-settings" open>
        <summary class="integration-section-summary">Настройки интеграции (склады, задержки, курс)</summary>
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

    <details class="card integration-section" id="sec-autosync">
        <summary class="integration-section-summary">Автосинхронизация и контроль расхождений</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Рекомендуется запускать <code>api/bitrix/sync_cycle.php?chunk=<?= (int)$integrationSettings['sync_cycle_chunk'] ?></code> по cron каждые 2-5 минут.
                Цикл постепенно отправляет остатки/цены в Б24 и периодически проверяет изменения в Б24 на расхождения.
            </p>
            <?php if ($cycleLastRun !== ''): ?>
                <p><strong>Последний результат цикла:</strong></p>
                <pre style="white-space:pre-wrap;"><?= htmlspecialchars($cycleLastRun) ?></pre>
            <?php else: ?>
                <p class="text-muted">Цикл еще не запускался.</p>
            <?php endif; ?>
        </div>
    </details>

    <details class="card integration-section" id="sec-webhooks">
        <summary class="integration-section-summary">Вебхук-события Битрикс24</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Каждая строка — один POST от исходящего вебхука Б24 на <code>api/webhook.php</code>.
                Повторная доставка того же события помечается как <strong>duplicate_delivery_skipped</strong> (видно здесь же).
                Размер: <code>?limit=120</code> в адресной строке (до 500).
                Колонка <strong>Товар B24</strong>: для <code>ONCRMPRODUCT*</code> — из тела вебхука; для <code>ONCRMDEAL*</code> может подставиться
                первый каталожный <code>PRODUCT_ID</code>, если строки успешно загружены по REST после события. Итог обработки очереди/ошибки — в <strong>Итог обработки</strong>.
            </p>
            <?php if (empty($webhookRows)): ?>
                <div class="alert alert-warning">
                    Записей пока нет. Проверьте:<br>
                    • URL вебхука в Битрикс24 точно <code>http(s)://ваш-хост/api/webhook.php</code> и включены события <code>ONCRMDEALADD</code> / <code>ONCRMDEALUPDATE</code>.<br>
                    • JSON-диагностика БД без Битрикс: откройте <a href="api/webhook_ping.php" target="_blank" rel="noopener"><code>api/webhook_ping.php</code></a> — должен быть <code>webhook_log_rows</code> и при необходимости тестовая строка: <code>api/webhook_ping.php?write=1&amp;k=CHANGE_ME_FRIENDCRM_DIAG</code> (ключ задаётся в <code>api/webhook_ping.php</code>).<br>
                    • Если ping показывает строки, а после сделки их нет — до сайта из облака Б24 не добираются запросы (URL, HTTPS, блокировки).
                </div>
            <?php endif; ?>
            <div class="webhook-log-table-wrap" style="max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;box-sizing:border-box;">
            <table class="table webhook-log-table" style="margin-bottom:0;">
                <tr>
                    <th>ID</th>
                    <th>Событие</th>
                    <th>Итог обработки</th>
                    <th>Сделка</th>
                    <th>Товар B24</th>
                    <th>Payload</th>
                    <th>Время</th>
                </tr>
                <?php foreach ($webhookRows as $row): ?>
                    <?php
                    $wid = (int)$row['id'];
                    $snippet = '';
                    $rawPrev = isset($row['data_preview']) ? trim((string)$row['data_preview']) : '';
                    if ($rawPrev !== '') {
                        $decoded = json_decode($rawPrev, true);
                        if (function_exists('json_last_error') && json_last_error() === JSON_ERROR_NONE) {
                            $snippet = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                        if ($snippet === '') {
                            $snippet = $rawPrev;
                        }
                        if (strlen($snippet) > 2000) {
                            $snippet = substr($snippet, 0, 2000) . '…';
                        }
                    }
                    ?>
                    <tr class="webhook-event-row">
                        <td><?= $wid ?></td>
                        <td><code><?= htmlspecialchars((string)$row['event']) ?></code></td>
                        <td>
                            <?php
                            $hoc = (string)$row['handler_outcome'];
                            $hdl = isset($row['handler_detail']) ? trim((string)$row['handler_detail']) : '';
                            if ($hoc !== '') {
                                echo '<code>' . htmlspecialchars($hoc) . '</code>';
                                if ($hdl !== '') {
                                    echo '<details style="margin-top:6px;max-width:100%;"><summary style="cursor:pointer;font-size:12px;color:var(--text-muted,#6c757d);">Детали ошибки / пояснение</summary>'
                                        . '<div style="margin-top:6px;max-width:100%;overflow-x:auto;"><pre style="margin:0;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;font-size:11px;padding:8px;background:var(--card-background,#f1f3f5);border-radius:4px;border:1px solid rgba(127,127,127,0.25);">'
                                        . htmlspecialchars($hdl)
                                        . '</pre></div></details>';
                                }
                            } else {
                                echo '<span class="text-muted">—</span>';
                            }
                            ?>
                        </td>
                        <td><?= isset($row['entity_deal_id']) && intval($row['entity_deal_id']) > 0 ? intval($row['entity_deal_id']) : '—' ?></td>
                        <td><?= isset($row['entity_product_id']) && intval($row['entity_product_id']) > 0 ? intval($row['entity_product_id']) : '—' ?></td>
                        <td><?= isset($row['data_chars']) ? intval($row['data_chars']) . ' симв.' : '—' ?></td>
                        <td><?= htmlspecialchars((string)$row['created_at']) ?></td>
                    </tr>
                    <?php if ($snippet !== ''): ?>
                    <tr class="webhook-json-row">
                        <td colspan="7" style="max-width:100%;vertical-align:top;">
                            <details>
                                <summary style="cursor:pointer;">Показать тело события (до 1500 симв.; форматирование JSON)</summary>
                                <div style="max-width:100%;margin-top:8px;overflow-x:auto;overflow-y:hidden;box-sizing:border-box;">
                                    <pre style="margin:0;white-space:pre-wrap;overflow-wrap:anywhere;word-wrap:break-word;word-break:break-word;font-size:12px;padding:10px;background:var(--card-background,#f8f9fa);border-radius:6px;border:1px solid rgba(127,127,127,0.2);"><?= htmlspecialchars($snippet) ?></pre>
                                </div>
                            </details>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
            </div>
        </div>
    </details>

    <details class="card integration-section" id="sec-mov-errors">
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

    <details class="card integration-section" id="sec-pending">
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

    <details class="card integration-section" id="sec-conflicts">
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
</main>

<?php require 'includes/footer.php'; ?>
