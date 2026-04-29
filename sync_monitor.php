<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
require_once __DIR__ . '/functions/app_settings.php';
$db = getDB();
$page_title = 'Центр интеграции';
require 'includes/header.php';

$webhookRows = [];
$movementErrors = [];
$movementPending = [];
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
        if ($currency === '') {
            $currency = 'KGS';
        }
        setAppSetting($db, 'default_store_from_id', (string)$storeFrom);
        setAppSetting($db, 'default_store_to_id', (string)$storeTo);
        setAppSetting($db, 'default_responsible_id', (string)$responsibleId);
        setAppSetting($db, 'default_currency', $currency);
        setAppSetting($db, 'sync_batch_limit', (string)$batchLimit);
        setAppSetting($db, 'b24_doc_delay_ms', (string)$docDelayMs);
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
    'b24_doc_delay_ms' => getAppSetting($db, 'b24_doc_delay_ms', '700')
);

try {
    $webhookRows = $db->query("
        SELECT id, event, created_at
        FROM webhook_log
        ORDER BY id DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
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
        </div>
    </div>

    <div class="card">
        <h3>Настройки интеграции</h3>
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
            </div>
            <button class="btn btn-success" type="submit">Сохранить настройки</button>
        </form>
    </div>

    <div class="card">
        <h3>Последние webhook события</h3>
        <table class="table">
            <tr><th>ID</th><th>Событие</th><th>Время</th></tr>
            <?php foreach ($webhookRows as $row): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= htmlspecialchars($row['event']) ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
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
</main>

<?php require 'includes/footer.php'; ?>
