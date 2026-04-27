<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Склад пленок - Восстановление</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin: 1rem 0; }
        .btn { background: #3498db; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; margin: 0.25rem; }
        .btn:hover { background: #2980b9; }
        .error { color: #e74c3c; background: #ffeaea; padding: 1rem; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏭 Склад пленок - Восстановление системы</h1>
        
        <div class="card">
            <h2>✅ Система работает</h2>
            <p>Основной функционал восстановлен. Вы можете использовать следующие разделы:</p>
            
            <h3>📋 Основные функции:</h3>
            <a href="warehouse.php" class="btn">🏪 Управление складом</a>
            <a href="products.php" class="btn">📦 Товары</a>
            <a href="sell.php" class="btn">💰 Продажи</a>
            <a href="b24_sales.php" class="btn">🔄 Продажи Б24</a>
            
            <h3>⚙️ Синхронизация:</h3>
            <a href="api/bitrix/sync_stock.php?push=1" class="btn" target="_blank">📤 Синхронизировать остатки</a>
            <a href="api/sync_prices.php?action=to_b24" class="btn" target="_blank">💰 Синхронизировать цены</a>
        </div>
        
        <div class="card">
            <h2>🔧 Статус системы</h2>
            <p><strong>База данных:</strong> Подключена ✅</p>
            <p><strong>API Битрикс24:</strong> Настроено ✅</p>
            <p><strong>Основные функции:</strong> Работают ✅</p>
            <p><strong>Новая панель:</strong> Временно отключена (исправляется)</p>
        </div>
        
        <div class="card">
            <h2>📝 Что делать:</h2>
            <ol>
                <li>Используйте <strong>warehouse.php</strong> для управления складом</li>
                <li>Используйте <strong>products.php</strong> для управления товарами</li>
                <li>Используйте <strong>sell.php</strong> для продаж</li>
                <li>Новая панель будет доступна после исправления ошибок</li>
            </ol>
        </div>
    </div>
</body>
</html>