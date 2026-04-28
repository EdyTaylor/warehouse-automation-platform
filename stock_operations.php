<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

$page_title = 'Складские операции';
require 'includes/header.php';
?>

<main class="container">
    <div class="card">
        <h2>🧾 Складские операции</h2>
        <p class="text-muted">
            Единая точка работы со складом: приход, списание, реализация и синхронизация с Б24.
        </p>
    </div>

    <div class="card">
        <h3>Основные действия</h3>
        <div class="actions-grid">
            <div class="action-card">
                <h4>📥 Приход</h4>
                <p>Операция прихода будет оформляться здесь в виде документа по списку товаров.</p>
                <a href="warehouse.php" class="btn btn-light">Открыть склад</a>
            </div>

            <div class="action-card">
                <h4>🗑️ Списание</h4>
                <p>Списание со склада по товарам и количеству с фиксацией причины операции.</p>
                <a href="warehouse.php" class="btn btn-light">Открыть склад</a>
            </div>

            <div class="action-card">
                <h4>💳 Реализация</h4>
                <p>Реализация из Б24: резерв, подтверждение и финальное списание по сделке.</p>
                <a href="b24_sales.php" class="btn">Открыть Б24 очередь</a>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>Синхронизация</h3>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="api/bitrix/sync_stock.php?push=1" class="btn btn-warning" target="_blank">📤 Синхронизировать остатки</a>
            <a href="api/sync_prices.php?action=to_b24" class="btn btn-warning" target="_blank">💰 Синхронизировать цены</a>
            <a href="api/bitrix/import_products.php" class="btn btn-success" target="_blank">📥 Импортировать товары</a>
        </div>
    </div>
</main>

<?php require 'includes/footer.php'; ?>
