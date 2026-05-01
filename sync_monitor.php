<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
require_once __DIR__ . '/functions/app_settings.php';
require_once __DIR__ . '/functions/webhook_log_schema.php';
$db = getDB();
webhookLogEnsureSchema($db);

$webhookLimit = isset($_GET['limit']) ? max(10, min(500, intval($_GET['limit']))) : 80;
$page_title = 'Центр интеграции';
require 'includes/header.php';

$webhookRows = [];
$movementErrors = [];
$movementPending = [];
$syncConflicts = [];
$cycleLastRun = '';
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_integration_settings') {
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
        $successMsg = 'Настройки интеграции сохранены.';
    } catch (Exception $e) {
        $errorMsg = 'Не удалось сохранить настройки: ' . $e->getMessage();
    }
}

$integrationSettings = array(
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

try {
    $wk = $db->query('
        SELECT id, event,
               COALESCE(handler_outcome, \'\') AS handler_outcome,
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
    $webhookRows = [];
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
    $movementErrors = [];
    $movementPending = [];
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
    $syncConflicts = [];
}
?>

<main class="container">
    <h2>⚙️ Центр интеграции</h2>

    <?php if ($successMsg !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>Быстрые действия</h3>
        <p class="text-muted">Кнопки запускают синк через модальное окно, без открытия новых вкладок.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a class="btn btn-primary b24-sync-link" href="api/bitrix/import_products.php">📦 Импортировать товары из Б24</a>
            <a class="btn btn-primary b24-sync-link" href="api/bitrix/sync_stock.php?push=1">🏪 Синхронизировать остатки</a>
            <a class="btn btn-secondary b24-sync-link" href="api/sync_prices.php?action=to_b24">💰 Синхронизировать цены</a>
            <a class="btn btn-secondary b24-sync-link" href="api/bitrix/sync_cycle.php?chunk=<?= (int)$integrationSettings['sync_cycle_chunk'] ?>">🔁 Запустить 1 цикл автосинка</a>
        </div>
    </div>

    <div class="card">
        <h3>Технический раздел Б24</h3>
        <p class="text-muted">
            Этот раздел нужен для сервисных операций интеграции: ручной резерв/подтверждение строк сделки,
            повтор синка product rows и диагностика ошибок Б24. Для ежедневной работы кладовщика используйте
            вкладку <strong>Место кладовщика</strong>.
        </p>
        <a class="btn btn-light" href="b24_sales.php">Открыть тех.раздел Б24</a>
    </div>

    <div class="card">
        <h3>Настройки интеграции</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            <button type="button" class="btn btn-light btn-sm js-theme-toggle">🌓 Переключить тему</button>
        </div>
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

    <div class="card">
        <h3>Автосинхронизация и контроль расхождений</h3>
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

    <div class="card">
        <h3>Вебхук-события Битрикс24</h3>
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
                        <?= htmlspecialchars((string)$row['handler_outcome']) !== ''
                            ? '<code>' . htmlspecialchars((string)$row['handler_outcome']) . '</code>'
                            : '<span class="text-muted">—</span>'
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
                                <pre style="margin:0;white-space:pre-wrap;overflow-wrap:anywhere;word-wrap:break-word;word-break:break-word;font-size:12px;padding:10px;background:var(--bs-body-bg,#f8f9fa);border-radius:6px;border:1px solid rgba(127,127,127,0.2);"><?= htmlspecialchars($snippet) ?></pre>
                            </div>
                        </details>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
        </div>
    </div>

    <div class="card">
        <h3>Ошибки отправки в Б24</h3>
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

    <div class="card">
        <h3>Ожидают отправки</h3>
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

    <div class="card">
        <h3>Найденные расхождения с Б24</h3>
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
                    <td><?= htmlspecialchars((string)$row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</main>

<?php require 'includes/footer.php'; ?>
