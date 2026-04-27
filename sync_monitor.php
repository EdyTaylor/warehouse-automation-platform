<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
$page_title = 'Монитор синхронизации';
require 'includes/header.php';

$webhookRows = [];
$movementErrors = [];
$movementPending = [];

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
    <h2>Монитор синхронизации</h2>

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
