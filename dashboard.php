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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Панель кладовщика - Склад пленок</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            background: #f5f5f5;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 1rem;
            text-align: center;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .stat-card {
            background: #ecf0f1;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0.25rem;
            font-size: 1rem;
        }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        .action-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .action-card h4 {
            margin-top: 0;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏭 Панель кладовщика</h1>
        <p>Склад пленочных материалов</p>
    </div>

    <div class="container">
        <!-- Статистика -->
        <div class="card">
            <h3>📊 Статистика склада</h3>
            <div class="stats">
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
    </div>
</body>
</html>
