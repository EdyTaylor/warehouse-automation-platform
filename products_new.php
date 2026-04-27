<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/api/bitrix/send.php';

// Параметры фильтрации
$categoryId = intval($_GET['category_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$priceFilter = $_GET['price_filter'] ?? 'all';
$stockFilter = $_GET['stock_filter'] ?? 'all';

// Получаем категории из Б24
$categories = [];
try {
    $cfg = require __DIR__ . '/api/bitrix/config.php';
    $resp = file_get_contents($cfg['webhook'] . 'crm.catalog.list');
    $data = json_decode($resp, true);
    if (isset($data['result'])) {
        $categories = $data['result'];
    }
} catch (Exception $e) {
    // Если не удалось получить категории, продолжаем без них
}

// Строим запрос для товаров
$whereConditions = [];
$params = [];

if ($categoryId > 0) {
    $whereConditions[] = "catalog_id = ?";
    $params[] = $categoryId;
}

if (!empty($search)) {
    $whereConditions[] = "name LIKE ?";
    $params[] = "%$search%";
}

// Фильтры по цене и остаткам
switch ($priceFilter) {
    case 'with_price':
        $whereConditions[] = "price_per_meter > 0";
        break;
    case 'without_price':
        $whereConditions[] = "(price_per_meter IS NULL OR price_per_meter = 0)";
        break;
}

switch ($stockFilter) {
    case 'in_stock':
        $whereConditions[] = "free_meters > 0";
        break;
    case 'out_of_stock':
        $whereConditions[] = "free_meters = 0";
        break;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Получаем товары с остатками
$sql = "
    SELECT 
        p.*,
        COALESCE(SUM(CASE 
            WHEN r.reserved = 0 
            AND r.current_length > 0 
            AND r.status NOT IN ('sold','waste','written_off') 
            THEN r.current_length 
            ELSE 0 
        END), 0) as free_meters,
        COUNT(CASE WHEN r.status = 'active' AND r.current_length > 0 THEN 1 END) as active_rolls,
        COUNT(CASE WHEN r.status = 'scrap' AND r.current_length > 0 THEN 1 END) as scrap_rolls
    FROM products p
    LEFT JOIN rolls r ON r.product_id = p.id
    $whereClause
    GROUP BY p.id, p.name, p.price_per_meter, p.b24_product_id, p.catalog_id, p.description
    ORDER BY p.name
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Синхронизация с Б24
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'sync_from_b24') {
        // Импорт/обновление товаров из Б24
        $importUrl = __DIR__ . '/api/bitrix/import_products_advanced.php';
        $params = ['sync_prices' => 1];
        if ($categoryId > 0) $params['category_id'] = $categoryId;
        if (!empty($search)) $params['search'] = $search;
        
        $queryString = http_build_query($params);
        $resp = file_get_contents($importUrl . '?' . $queryString);
        $result = json_decode($resp, true);
        
        $syncMessage = '';
        if ($result['status'] === 'ok') {
            $syncMessage = "Синхронизировано: {$result['updated']} обновлено, {$result['created']} создано";
            if ($result['price_updates'] > 0) {
                $syncMessage .= ", {$result['price_updates']} цен обновлено";
            }
        } else {
            $syncMessage = "Ошибка синхронизации: " . ($result['message'] ?? 'Неизвестная ошибка');
        }
        
        // Обновляем страницу после синхронизации
        header("Location: products_new.php?message=" . urlencode($syncMessage) . "&category_id=$categoryId&search=" . urlencode($search));
        exit;
    }
    
    if ($action === 'sync_prices_to_b24') {
        // Синхронизация цен в Б24
        $syncUrl = __DIR__ . '/api/sync_prices.php?action=to_b24';
        $resp = file_get_contents($syncUrl);
        $result = json_decode($resp, true);
        
        $syncMessage = '';
        if ($result['status'] === 'ok') {
            $syncMessage = "Цены синхронизированы в Б24: {$result['updated']} обновлено";
        } else {
            $syncMessage = "Ошибка синхронизации цен: " . ($result['message'] ?? 'Неизвестная ошибка');
        }
        
        header("Location: products_new.php?message=" . urlencode($syncMessage));
        exit;
    }
}

// Сообщение для пользователя
$message = $_GET['message'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Товары - Склад пленок</title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 0.25rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .form-group input, .form-group select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
            font-size: 0.9rem;
        }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
        }
        .products-table th, .products-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .products-table th {
            background: #ecf0f1;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        .products-table tr:hover {
            background: #f8f9fa;
        }
        .stock-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
            color: white;
        }
        .stock-good { background: #27ae60; }
        .stock-low { background: #f39c12; }
        .stock-out { background: #e74c3c; }
        .price-display {
            font-weight: bold;
            color: #2c3e50;
        }
        .no-price {
            color: #e74c3c;
            font-style: italic;
        }
        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #ecf0f1;
            border-radius: 4px;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .table-responsive {
            overflow-x: auto;
            max-height: 70vh;
        }
        .product-name {
            max-width: 200px;
        }
        .b24-link {
            color: #3498db;
            text-decoration: none;
        }
        .b24-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📦 Управление товарами</h1>
        <a href="dashboard.php" class="btn">← На главную</a>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'Ошибка') === false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Фильтры -->
        <div class="filters">
            <h3>🔍 Фильтры и поиск</h3>
            <form method="GET" class="filters-grid">
                <div class="form-group">
                    <label>Категория:</label>
                    <select name="category_id">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['ID'] ?>" <?= $categoryId == $cat['ID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Поиск по названию:</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Введите название...">
                </div>

                <div class="form-group">
                    <label>Фильтр по ценам:</label>
                    <select name="price_filter">
                        <option value="all" <?= $priceFilter === 'all' ? 'selected' : '' ?>>Все товары</option>
                        <option value="with_price" <?= $priceFilter === 'with_price' ? 'selected' : '' ?>>С ценой</option>
                        <option value="without_price" <?= $priceFilter === 'without_price' ? 'selected' : '' ?>>Без цены</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Фильтр по остаткам:</label>
                    <select name="stock_filter">
                        <option value="all" <?= $stockFilter === 'all' ? 'selected' : '' ?>>Все товары</option>
                        <option value="in_stock" <?= $stockFilter === 'in_stock' ? 'selected' : '' ?>>В наличии</option>
                        <option value="out_of_stock" <?= $stockFilter === 'out_of_stock' ? 'selected' : '' ?>>Нет в наличии</option>
                    </select>
                </div>
            </form>

            <div class="actions">
                <button type="submit" form="filter-form" class="btn">🔍 Применить фильтры</button>
                <a href="products_new.php" class="btn">🔄 Сбросить</a>
            </div>
        </form>

            <div class="actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="sync_from_b24">
                    <button type="submit" class="btn btn-success">📥 Синхронизировать из Б24</button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="sync_prices_to_b24">
                    <button type="submit" class="btn btn-warning">💰 Отправить цены в Б24</button>
                </form>
            </div>
        </div>

        <!-- Статистика -->
        <div class="card">
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number"><?= count($products) ?></div>
                    <div>Товаров</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= count(array_filter($products, fn($p) => $p['free_meters'] > 0)) ?></div>
                    <div>В наличии</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= count(array_filter($products, fn($p) => $p['price_per_meter'] > 0)) ?></div>
                    <div>С ценой</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= array_sum(array_column($products, 'free_meters')) ?></div>
                    <div>Всего метров</div>
                </div>
            </div>
        </div>

        <!-- Таблица товаров -->
        <div class="card">
            <h3>📋 Список товаров (<?= count($products) ?>)</h3>
            <div class="table-responsive">
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Категория</th>
                            <th>Цена/м</th>
                            <th>В наличии</th>
                            <th>Рулонов</th>
                            <th>Обрезков</th>
                            <th>Б24</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= $product['id'] ?></td>
                                <td class="product-name">
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                    <?php if ($product['description']): ?>
                                        <br><small><?= htmlspecialchars(substr($product['description'], 0, 50)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $categoryName = 'Без категории';
                                    foreach ($categories as $cat) {
                                        if ($cat['ID'] == $product['catalog_id']) {
                                            $categoryName = $cat['NAME'];
                                            break;
                                        }
                                    }
                                    echo htmlspecialchars($categoryName);
                                    ?>
                                </td>
                                <td class="price-display">
                                    <?= $product['price_per_meter'] > 0 
                                        ? number_format($product['price_per_meter'], 0) . ' ₽' 
                                        : '<span class="no-price">Нет цены</span>' ?>
                                </td>
                                <td>
                                    <?php 
                                    $meters = floatval($product['free_meters']);
                                    if ($meters > 30) {
                                        $badge = 'stock-good';
                                        $text = '✅ ' . number_format($meters, 1) . ' м';
                                    } elseif ($meters > 0) {
                                        $badge = 'stock-low';
                                        $text = '⚠️ ' . number_format($meters, 1) . ' м';
                                    } else {
                                        $badge = 'stock-out';
                                        $text = '❌ 0 м';
                                    }
                                    ?>
                                    <span class="stock-badge <?= $badge ?>"><?= $text ?></span>
                                </td>
                                <td><?= $product['active_rolls'] ?></td>
                                <td><?= $product['scrap_rolls'] ?></td>
                                <td>
                                    <?php if ($product['b24_product_id']): ?>
                                        <a href="https://llumar.bitrix24.kz/catalog/product/<?= $product['b24_product_id'] ?>/" 
                                           target="_blank" class="b24-link">
                                            #<?= $product['b24_product_id'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #e74c3c;">Не привязан</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="add_stock.php?product_id=<?= $product['id'] ?>" class="btn btn-success">+</a>
                                    <a href="sell.php" class="btn">Продажа</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Автоматическая отправка формы при изменении фильтров
        document.querySelectorAll('.filters select, .filters input[type="text"]').forEach(element => {
            element.addEventListener('change', function() {
                if (this.type === 'text') {
                    // Для текстового поля ждем 1 секунду после прекращения ввода
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        this.closest('form').submit();
                    }, 1000);
                } else {
                    // Для селектов отправляем сразу
                    this.closest('form').submit();
                }
            });
        });
    </script>
</body>
</html>
