<?php
// Версия с совместимым синтаксисом PHP
ini_set('display_errors', 1);
error_reporting(E_ALL);

$db_connected = false;
$rolls_data = array();
$products_data = array();

try {
    require 'db.php';
    $db = getDB();
    $db_connected = true;
    
    // Простой запрос товаров без LIMIT
    $stmt = $db->query("SELECT id, name FROM products ORDER BY name");
    if ($stmt) {
        $products_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Простой запрос рулонов
    $stmt = $db->query("SELECT id, product_id, current_length, status FROM rolls ORDER BY id DESC");
    if ($stmt) {
        $rolls_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Склад - Совместимая версия</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { background: #3498db; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; display: inline-block; margin: 0.25rem; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-danger { background: #e74c3c; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.25rem; font-weight: bold; }
        .form-group input, .form-group select { padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; width: 100%; max-width: 300px; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .error { color: red; background: #ffeaea; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .success { color: green; background: #e8f5e8; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏭 Склад пленок</h1>
        
        <?php if (isset($db_error)): ?>
            <div class="error">
                <strong>Ошибка базы данных:</strong> <?php echo htmlspecialchars($db_error); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>📦 Добавить рулон</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Товар:</label>
                    <select name="product_id" required>
                        <option value="">Выберите товар</option>
                        <?php 
                        if ($db_connected && !empty($products_data)) {
                            foreach ($products_data as $product) {
                                echo '<option value="' . $product['id'] . '">' . htmlspecialchars($product['name']) . '</option>';
                            }
                        } else {
                            echo '<option value="">Нет товаров в базе</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Количество:</label>
                    <input type="number" name="quantity" min="1" value="1" required>
                </div>
                <button type="submit" class="btn btn-success">➕ Добавить</button>
            </form>
        </div>

        <div class="card">
            <h2>📋 Текущие рулоны</h2>
            <?php 
            if ($db_connected && !empty($rolls_data)) {
                echo '<table>';
                echo '<thead><tr><th>ID</th><th>Товар ID</th><th>Остаток (м)</th><th>Статус</th></tr></thead>';
                echo '<tbody>';
                foreach ($rolls_data as $roll) {
                    echo '<tr>';
                    echo '<td>' . $roll['id'] . '</td>';
                    echo '<td>' . $roll['product_id'] . '</td>';
                    echo '<td><strong>' . $roll['current_length'] . '</strong></td>';
                    
                    $status_colors = array(
                        'active' => 'green',
                        'sold' => 'red', 
                        'cut' => 'orange',
                        'scrap' => 'blue'
                    );
                    $color = isset($status_colors[$roll['status']]) ? $status_colors[$roll['status']] : 'gray';
                    
                    echo '<td><span style="color: ' . $color . '; font-weight: bold;">' . strtoupper($roll['status']) . '</span></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>📦 Нет рулонов на складе</p>';
            }
            ?>
        </div>

        <div class="card">
            <h2>🔊 Статус системы</h2>
            <p><strong>PHP:</strong> ✅ Работает</p>
            <p><strong>Страница:</strong> ✅ Загружена</p>
            <p><strong>База данных:</strong> <?php echo $db_connected ? '✅ Подключена' : '❌ Ошибка подключения'; ?></p>
            <?php if ($db_connected): ?>
                <p><strong>Товаров в БД:</strong> <?php echo count($products_data); ?></p>
                <p><strong>Рулонов в БД:</strong> <?php echo count($rolls_data); ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>🔗 Навигация</h2>
            <a href="index.php" class="btn">🏠 Главная</a>
            <a href="dashboard.php" class="btn">📊 Панель</a>
            <a href="products.php" class="btn">📦 Товары</a>
            <a href="sell.php" class="btn">💰 Продажи</a>
        </div>
    </div>
</body>
</html>
