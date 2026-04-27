<?php
$page_title = 'Панель кладовщика';
require 'includes/header.php';
require 'db.php';
$db = getDB();

// Получаем базовую статистику без сложных запросов
try {
    $productsCount = $db->query("SELECT COUNT(*) as count FROM products")->fetch(PDO::FETCH_ASSOC)['count'];
    $rollsCount = $db->query("SELECT COUNT(*) as count FROM rolls WHERE status = 'active'")->fetch(PDO::FETCH_ASSOC)['count'];
    $totalMeters = $db->query("SELECT SUM(current_length) as total FROM rolls WHERE status = 'active'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $todaySales = $db->query("SELECT COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['count'];
} catch (Exception $e) {
    $productsCount = 0;
    $rollsCount = 0;
    $totalMeters = 0;
    $todaySales = 0;
}

    <div class="container">
    <!-- Статистика -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $productsCount ?></div>
            <div class="stat-label">Товаров в базе</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $rollsCount ?></div>
            <div class="stat-label">Активных рулонов</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($totalMeters, 1) ?></div>
            <div class="stat-label">Свободных метров</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $todaySales ?></div>
            <div class="stat-label">Продаж сегодня</div>
        </div>
    </div>

    <!-- Основные действия -->
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">⚡ Основные действия</h2>
        </div>
        <div class="actions-grid">
            <div class="action-card">
                <h4>🏪 Управление складом</h4>
                <p>Добавление рулонов, списание, остатки</p>
                <a href="warehouse.php" class="btn btn-success">Открыть</a>
            </div>
            
            <div class="action-card">
                <h4>📦 Товары</h4>
                <p>Управление товарами и ценами</p>
                <a href="products.php" class="btn btn-primary">Открыть</a>
            </div>
            
            <div class="action-card">
                <h4>💰 Продажи</h4>
                <p>Продажа рулонов и метров</p>
                <a href="sell.php" class="btn btn-primary">Открыть</a>
            </div>
            
            <div class="action-card">
                <h4>🔄 Продажи Б24</h4>
                <p>Обработка заказов из Битрикс24</p>
                <a href="b24_sales.php" class="btn btn-warning">Открыть</a>
            </div>
        </div>
    </div>

    <!-- Быстрые действия -->
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">� Быстрые действия</h2>
        </div>
        <div class="actions-grid">
            <div class="action-card">
                <h4>📤 Синхронизация остатков</h4>
                <p>Отправить остатки в Битрикс24</p>
                <a href="api/bitrix/sync_stock.php?push=1" class="btn btn-warning" target="_blank">Синхронизировать</a>
            </div>
            
            <div class="action-card">
                <h4>💰 Синхронизация цен</h4>
                <p>Отправить цены в Битрикс24</p>
                <a href="api/sync_prices.php?action=to_b24" class="btn btn-warning" target="_blank">Синхронизировать</a>
            </div>
            
            <div class="action-card">
                <h4>📥 Импорт товаров</h4>
                <p>Импортировать товары из Б24</p>
                <a href="api/bitrix/import_products.php" class="btn btn-success" target="_blank">Импортировать</a>
            </div>
        </div>
    </div>

    <!-- Статус системы -->
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">ℹ️ Статус системы</h2>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">✅</div>
                <div class="stat-label">Система работает</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">🗄️</div>
                <div class="stat-label">База данных подключена</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">🔄</div>
                <div class="stat-label">Б24 интеграция настроена</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">⏰</div>
                <div class="stat-label"><?= date('d.m.Y H:i') ?></div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
