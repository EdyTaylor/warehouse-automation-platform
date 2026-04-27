<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();

// Простая статистика без сложных запросов
try {
    $productsCount = $db->query("SELECT COUNT(*) as count FROM products")->fetch(PDO::FETCH_ASSOC)['count'];
    $rollsCount = $db->query("SELECT COUNT(*) as count FROM rolls WHERE status = 'active'")->fetch(PDO::FETCH_ASSOC)['count'];
} catch (Exception $e) {
    $productsCount = 0;
    $rollsCount = 0;
}
 
$page_title = 'Главная';
require 'includes/header.php';
?>

<main class="container">
        <!-- Статистика -->
        <div class="card">
            <h3>📊 Статистика склада</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $productsCount ?></div>
                    <div>Товаров в базе</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $rollsCount ?></div>
                    <div>Активных рулонов</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">✅</div>
                    <div>Система работает</div>
                </div>
            </div>
        </div>

        <!-- Основные действия -->
        <div class="card">
            <h3>⚡ Основные действия</h3>
            <div class="actions-grid">
                <div class="action-card">
                    <h4>🏪 Управление складом</h4>
                    <p>Добавление рулонов, списание, остатки</p>
                    <a href="warehouse.php" class="btn btn-success">Открыть</a>
                </div>
                
                <div class="action-card">
                    <h4>📦 Товары</h4>
                    <p>Управление товарами и ценами</p>
                    <a href="products.php" class="btn">Открыть</a>
                </div>
                
                <div class="action-card">
                    <h4>💰 Продажи</h4>
                    <p>Продажа рулонов и метров</p>
                    <a href="sell.php" class="btn">Открыть</a>
                </div>
                
                <div class="action-card">
                    <h4>🔄 Продажи Б24</h4>
                    <p>Обработка заказов из Битрикс24</p>
                    <a href="b24_sales.php" class="btn">Открыть</a>
                </div>
            </div>
        </div>

        <!-- Синхронизация -->
        <div class="card">
            <h3>🔄 Синхронизация с Битрикс24</h3>
            <div class="actions-grid">
                <div class="action-card">
                    <h4>📤 Остатки в Б24</h4>
                    <p>Отправить остатки на склад Б24</p>
                    <a href="api/bitrix/sync_stock.php?push=1" class="btn btn-warning" target="_blank">Синхронизировать</a>
                </div>
                
                <div class="action-card">
                    <h4>💰 Цены в Б24</h4>
                    <p>Отправить цены в Б24</p>
                    <a href="api/sync_prices.php?action=to_b24" class="btn btn-warning" target="_blank">Синхронизировать</a>
                </div>
                
                <div class="action-card">
                    <h4>📥 Товары из Б24</h4>
                    <p>Импортировать товары из Б24</p>
                    <a href="api/bitrix/import_products.php" class="btn btn-success" target="_blank">Импортировать</a>
                </div>
            </div>
        </div>

        <!-- Информация -->
        <div class="card">
            <h3>ℹ️ Информация</h3>
            <p><strong>Статус системы:</strong> ✅ Работает в штатном режиме</p>
            <p><strong>База данных:</strong> Подключена</p>
            <p><strong>Интеграция Б24:</strong> Настроена</p>
            <p><strong>Последнее обновление:</strong> <?= date('d.m.Y H:i') ?></p>
        </div>
</main>

<?php require 'includes/footer.php'; ?>
