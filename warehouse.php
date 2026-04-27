<?php
// Максимально простая версия без сложных запросов
?>
<!DOCTYPE html>
<html>
<head>
    <title>Склад - Простая версия</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🏭 Склад пленок</h1>
        
        <div class="card">
            <h2>📦 Добавить рулон</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Товар:</label>
                    <select name="product_id" required>
                        <option value="">Выберите товар</option>
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
            <p>📦 Информация о рулонах будет здесь</p>
            <p><small>Для полной функциональности нужно настроить подключение к базе данных</small></p>
        </div>

        <div class="card">
            <h2>🔊 Статус системы</h2>
            <p><strong>PHP:</strong> ✅ Работает</p>
            <p><strong>Страница:</strong> ✅ Загружена</p>
            <p><strong>База данных:</strong> 🔄 Проверка...</p>
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
