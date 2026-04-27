<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/functions/stock_movements.php';

// Получаем новые заказы из Б24
$newOrders = $db->query("
    SELECT r.*, COUNT(l.id) as lines_count
    FROM b24_sale_requests r
    LEFT JOIN b24_sale_lines l ON l.request_id = r.id
    WHERE r.status = 'new'
    GROUP BY r.id
    ORDER BY r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Получаем товары с остатками
$products = $db->query("
    SELECT 
        p.id, p.name, p.price_per_meter,
        COALESCE(SUM(CASE 
            WHEN r.reserved = 0 
            AND r.current_length > 0 
            AND r.status NOT IN ('sold','waste','written_off') 
            THEN r.current_length 
            ELSE 0 
        END), 0) as free_meters,
        COUNT(CASE WHEN r.status = 'active' AND r.current_length > 0 THEN 1 END) as active_rolls
    FROM products p
    LEFT JOIN rolls r ON r.product_id = p.id
    GROUP BY p.id, p.name, p.price_per_meter
    ORDER BY p.name
")->fetchAll(PDO::FETCH_ASSOC);

// Получаем последние движения
$recentMovements = $db->query("
    SELECT sm.*, p.name as product_name
    FROM stock_movements sm
    LEFT JOIN products p ON p.id = sm.product_id
    ORDER BY sm.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
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
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }
        .order-card {
            border: 2px solid #e74c3c;
            border-radius: 8px;
            padding: 1rem;
            background: #fff;
        }
        .order-card.processing {
            border-color: #f39c12;
        }
        .order-card.completed {
            border-color: #27ae60;
        }
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0.25rem;
        }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
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
        .stock-table {
            width: 100%;
            border-collapse: collapse;
        }
        .stock-table th, .stock-table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .stock-table th {
            background: #ecf0f1;
            font-weight: bold;
        }
        .low-stock {
            color: #e74c3c;
            font-weight: bold;
        }
        .normal-stock {
            color: #27ae60;
        }
        .movement-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        .movement-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            color: white;
        }
        .movement-sale { background: #e74c3c; }
        .movement-receipt { background: #27ae60; }
        .movement-writeoff { background: #f39c12; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏭 Панель кладовщика</h1>
        <p>Склад пленочных материалов</p>
    </div>

    <div class="container">
        <!-- Статистика -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= count($newOrders) ?></div>
                <div>Новых заказов</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($products, fn($p) => $p['free_meters'] < 30)) ?></div>
                <div>Товаров с низким остатком</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= array_sum(array_column($products, 'free_meters')) ?></div>
                <div>Свободных метров</div>
            </div>
        </div>

        <!-- Новые заказы -->
        <div class="card">
            <h3>📦 Новые заказы из Битрикс24</h3>
            <?php if (empty($newOrders)): ?>
                <p>Нет новых заказов</p>
            <?php else: ?>
                <div class="orders-grid">
                    <?php foreach ($newOrders as $order): ?>
                        <div class="order-card">
                            <h4>Заказ #<?= $order['b24_deal_id'] ?></h4>
                            <p><strong><?= htmlspecialchars($order['deal_name']) ?></strong></p>
                            <p>Ответственный: <?= htmlspecialchars($order['responsible']) ?></p>
                            <p>Позиций: <?= $order['lines_count'] ?></p>
                            <p>Создан: <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
                            <a href="process_order.php?id=<?= $order['id'] ?>" class="btn btn-warning">Обработать</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Остатки на складе -->
        <div class="card">
            <h3>📊 Остатки на складе</h3>
            <table class="stock-table">
                <tr>
                    <th>Товар</th>
                    <th>Свободно, м</th>
                    <th>Цена/м</th>
                    <th>Рулонов</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= htmlspecialchars($product['name']) ?></td>
                        <td class="<?= $product['free_meters'] < 30 ? 'low-stock' : 'normal-stock' ?>">
                            <?= number_format($product['free_meters'], 1) ?> м
                        </td>
                        <td><?= number_format($product['price_per_meter'], 0) ?> ₽</td>
                        <td><?= $product['active_rolls'] ?></td>
                        <td>
                            <?= $product['free_meters'] < 30 ? '⚠️ Низкий' : '✅ Норма' ?>
                        </td>
                        <td>
                            <a href="add_stock.php?product_id=<?= $product['id'] ?>" class="btn btn-success">+</a>
                            <a href="sell.php" class="btn">Продажа</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Последние движения -->
        <div class="card">
            <h3>📋 Последние движения</h3>
            <?php if (empty($recentMovements)): ?>
                <p>Нет движений</p>
            <?php else: ?>
                <?php foreach ($recentMovements as $movement): ?>
                    <div class="movement-item">
                        <span class="movement-type movement-<?= $movement['movement_type'] ?>">
                            <?= $movement['movement_type'] ?>
                        </span>
                        <strong><?= htmlspecialchars($movement['product_name']) ?></strong>
                        <?= $movement['quantity_m'] > 0 ? $movement['quantity_m'] . ' м' : '' ?>
                        <?= $movement['quantity_rolls'] > 0 ? $movement['quantity_rolls'] . ' рул.' : '' ?>
                        <small><?= date('d.m.Y H:i', strtotime($movement['created_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Быстрые действия -->
        <div class="card">
            <h3>⚡ Быстрые действия</h3>
            <a href="warehouse.php" class="btn">Управление складом</a>
            <a href="products.php" class="btn">Товары</a>
            <a href="b24_sales.php" class="btn">Продажи Б24</a>
            <a href="api/bitrix/sync_stock.php?push=1" class="btn btn-warning" target="_blank">Синхронизировать остатки в Б24</a>
        </div>
    </div>
</body>
</html>
