<?php
// Минимальная тестовая страница
?>
<!DOCTYPE html>
<html>
<head>
    <title>Тест - Склад пленок</title>
    <meta charset="utf-8">
</head>
<body>
    <h1>🏭 Склад пленок - Тестовая страница</h1>
    
    <p><strong>Статус:</strong> ✅ PHP работает</p>
    
    <h2>📋 Доступные страницы:</h2>
    <ul>
        <li><a href="dashboard.php">🏠 Главная панель</a></li>
        <li><a href="warehouse.php">🏪 Склад (простой)</a></li>
        <li><a href="products.php">📦 Товары</a></li>
        <li><a href="sell.php">💰 Продажи</a></li>
        <li><a href="b24_sales.php">🔄 Битрикс24</a></li>
    </ul>
    
    <h2>🔧 API тесты:</h2>
    <ul>
        <li><a href="api/bitrix/sync_stock.php?push=1" target="_blank">📤 Синхронизировать остатки</a></li>
        <li><a href="api/sync_prices.php?action=to_b24" target="_blank">💰 Синхронизировать цены</a></li>
    </ul>
    
    <?php
    // Проверка подключения к БД
    try {
        require 'db.php';
        $db = getDB();
        $count = $db->query("SELECT COUNT(*) as count FROM products")->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p><strong>База данных:</strong> ✅ Подключена (товаров: $count)</p>";
    } catch (Exception $e) {
        echo "<p><strong>База данных:</strong> ❌ Ошибка: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
    
    <hr>
    <p><small>Если вы видите эту страницу, значит PHP работает. Проверьте другие ссылки выше.</small></p>
</body>
</html>
