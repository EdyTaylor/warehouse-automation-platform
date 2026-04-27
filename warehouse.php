<?php
// Полная функциональность с безопасным синтаксисом
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();

// Обработка форм
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_roll'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $min = floatval($_POST['min_full']);
        
        $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute(array($product_id));
        $product = $stmt->fetch();
        
        if ($product) {
            for ($i = 0; $i < $quantity; $i++) {
                $stmt = $db->prepare("
                    INSERT INTO rolls 
                    (product_id, original_length, current_length, min_full_length, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $stmt->execute(array(
                    $product_id,
                    $product['roll_length'],
                    $product['roll_length'],
                    $min
                ));
            }
            $success_msg = "✅ Добавлено рулонов: $quantity";
        }
    }
    
    if (isset($_POST['delete_roll'])) {
        $roll_id = intval($_POST['delete_roll']);
        $stmt = $db->prepare("DELETE FROM rolls WHERE id=?");
        $stmt->execute(array($roll_id));
        $success_msg = "🗑️ Рулон удален";
    }
}

// Получаем полные данные с проверкой
try {
    $rolls = $db->query("
        SELECT 
            r.id,
            r.product_id,
            r.original_length,
            r.current_length,
            r.min_full_length,
            r.status,
            r.price_per_meter,
            r.price_1_4,
            r.price_5_9,
            r.price_10_19,
            r.price_20_plus,
            p.name as product_name,
            p.roll_length as product_roll_length
        FROM rolls r
        LEFT JOIN products p ON r.product_id = p.id
        ORDER BY r.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Если JOIN не работает, получаем данные отдельно
    $rolls = $db->query("SELECT * FROM rolls ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rolls as &$roll) {
        $roll['product_name'] = 'Товар #' . $roll['product_id'];
        $roll['product_roll_length'] = $roll['original_length'];
    }
}

$products = $db->query("SELECT * FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Управление складом</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { background: #3498db; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; display: inline-block; margin: 0.25rem; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.25rem; font-weight: bold; }
        .form-group input, .form-group select { padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; width: 100%; max-width: 300px; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .nav { margin-bottom: 2rem; }
        .nav a { margin-right: 1rem; }
        .success { color: green; background: #e8f5e8; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .status-active { color: green; font-weight: bold; }
        .status-sold { color: red; font-weight: bold; }
        .status-cut { color: orange; font-weight: bold; }
        .status-scrap { color: blue; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏭 Управление складом</h1>
        
        <div class="nav">
            <a href="dashboard.php" class="btn">🏠 Главная</a>
            <a href="warehouse.php" class="btn">🏪 Склад</a>
            <a href="products.php" class="btn">📦 Товары</a>
            <a href="sell.php" class="btn">💰 Продажи</a>
            <a href="b24_sales.php" class="btn">🔄 Б24</a>
        </div>

        <?php if (isset($success_msg)): ?>
            <div class="success"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <!-- Добавление рулонов -->
        <div class="card">
            <h2>📦 Добавить рулоны</h2>
            <form method="POST">
                <input type="hidden" name="add_roll" value="1">
                <div class="form-group">
                    <label>Товар:</label>
                    <select name="product_id" required>
                        <option value="">Выберите товар</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>">
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo !empty($p['roll_length']) ? $p['roll_length'] : '30'; ?>м)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Мин. остаток (м):</label>
                    <input type="number" name="min_full" step="0.1" value="0.5" required>
                </div>
                <div class="form-group">
                    <label>Количество:</label>
                    <input type="number" name="quantity" min="1" value="1" required>
                </div>
                <button type="submit" class="btn btn-success">➕ Добавить</button>
            </form>
        </div>

        <!-- Складские остатки -->
        <div class="card">
            <h2>📋 Складские остатки</h2>
            <?php if (count($rolls) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Товар</th>
                        <th>Длина</th>
                        <th>Остаток</th>
                        <th>Цена/м</th>
                        <th>1-4</th>
                        <th>5-9</th>
                        <th>10-19</th>
                        <th>20+</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rolls as $r): ?>
                    <tr>
                        <td><?php echo $r['id']; ?></td>
                        <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                        <td><?php echo !empty($r['original_length']) ? $r['original_length'] . ' м' : '-'; ?></td>
                        <td><strong><?php echo !empty($r['current_length']) ? $r['current_length'] . ' м' : '-'; ?></strong></td>
                        <td><?php echo !empty($r['price_per_meter']) && $r['price_per_meter'] > 0 ? number_format($r['price_per_meter'], 0) . ' ₽' : '-'; ?></td>
                        <td><?php echo !empty($r['price_1_4']) && $r['price_1_4'] > 0 ? $r['price_1_4'] : '-'; ?></td>
                        <td><?php echo !empty($r['price_5_9']) && $r['price_5_9'] > 0 ? $r['price_5_9'] : '-'; ?></td>
                        <td><?php echo !empty($r['price_10_19']) && $r['price_10_19'] > 0 ? $r['price_10_19'] : '-'; ?></td>
                        <td><?php echo !empty($r['price_20_plus']) && $r['price_20_plus'] > 0 ? $r['price_20_plus'] : '-'; ?></td>
                        <td>
                            <?php
                            $statusClass = 'status-active';
                            $statusText = $r['status'];
                            switch ($r['status']) {
                                case 'active': $statusClass = 'status-active'; $statusText = 'Активный'; break;
                                case 'sold': $statusClass = 'status-sold'; $statusText = 'Продан'; break;
                                case 'cut': $statusClass = 'status-cut'; $statusText = 'В резке'; break;
                                case 'scrap': $statusClass = 'status-scrap'; $statusText = 'Обрезок'; break;
                                case 'written_off': $statusClass = 'status-sold'; $statusText = 'Списан'; break;
                                case 'waste': $statusClass = 'status-sold'; $statusText = 'Отход'; break;
                            }
                            ?>
                            <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_roll" value="<?php echo $r['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" 
                                        onclick="return confirm('Удалить рулон #<?php echo $r['id']; ?>?')">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>📦 Нет рулонов на складе</p>
            <?php endif; ?>
        </div>

        <!-- Статистика -->
        <div class="card">
            <h2>📊 Статистика</h2>
            <?php
            $stats = $db->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                    COUNT(CASE WHEN status = 'sold' THEN 1 END) as sold,
                    SUM(current_length) as total_meters
                FROM rolls
            ")->fetch(PDO::FETCH_ASSOC);
            ?>
            <p><strong>Всего рулонов:</strong> <?php echo $stats['total']; ?></p>
            <p><strong>Активных:</strong> <?php echo $stats['active']; ?></p>
            <p><strong>Продано:</strong> <?php echo $stats['sold']; ?></p>
            <p><strong>Всего метров:</strong> <?php echo number_format($stats['total_meters'], 1); ?></p>
        </div>
    </div>
</body>
</html>
