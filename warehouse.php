<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();

// Простая версия без сложных зависимостей
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
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.25rem; font-weight: bold; }
        .form-group input, .form-group select { padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; width: 200px; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .nav { margin-bottom: 2rem; }
        .nav a { margin-right: 1rem; }
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

        <?php
        // Обработка форм
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['add_roll'])) {
                $product_id = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity']);
                $min = floatval($_POST['min_full']);
                
                $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                if ($product) {
                    for ($i = 0; $i < $quantity; $i++) {
                        $stmt = $db->prepare("
                            INSERT INTO rolls 
                            (product_id, original_length, current_length, min_full_length, status)
                            VALUES (?, ?, ?, ?, 'active')
                        ");
                        $stmt->execute([
                            $product_id,
                            $product['roll_length'],
                            $product['roll_length'],
                            $min
                        ]);
                    }
                    echo "<div class='card'><p style='color:green;'>✅ Добавлено рулонов: $quantity</p></div>";
                }
            }
            
            if (isset($_POST['delete_roll'])) {
                $roll_id = intval($_POST['delete_roll']);
                $stmt = $db->prepare("DELETE FROM rolls WHERE id=?");
                $stmt->execute([$roll_id]);
                echo "<div class='card'><p style='color:orange;'>🗑️ Рулон удален</p></div>";
            }
        }
        ?>

        <!-- Добавление рулонов -->
        <div class="card">
            <h2>📦 Добавить рулоны</h2>
            <form method="POST">
                <input type="hidden" name="add_roll" value="1">
                <div class="form-group">
                    <label>Товар:</label>
                    <select name="product_id" required>
                        <option value="">Выберите товар</option>
                        <?php
                        $products = $db->query("SELECT * FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($products as $p):
                        ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['roll_length'] ?>м)</option>
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
            <?php
            $rolls = $db->query("
                SELECT r.*, p.name as product_name
                FROM rolls r
                LEFT JOIN products p ON r.product_id = p.id
                ORDER BY r.id DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rolls) > 0):
            ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Товар</th>
                        <th>Длина</th>
                        <th>Остаток</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rolls as $roll): ?>
                    <tr>
                        <td><?= $roll['id'] ?></td>
                        <td><?= htmlspecialchars($roll['product_name']) ?></td>
                        <td><?= $roll['original_length'] ?> м</td>
                        <td><strong><?= $roll['current_length'] ?> м</strong></td>
                        <td>
                            <?php
                            $status_colors = [
                                'active' => 'green',
                                'sold' => 'red', 
                                'cut' => 'orange',
                                'scrap' => 'blue'
                            ];
                            $color = $status_colors[$roll['status']] ?? 'gray';
                            ?>
                            <span style="color: <?= $color ?>; font-weight: bold;">
                                <?= strtoupper($roll['status']) ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_roll" value="<?= $roll['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" 
                                        onclick="return confirm('Удалить рулон #<?= $roll['id'] ?>?')">🗑️</button>
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
            <p><strong>Всего рулонов:</strong> <?= $stats['total'] ?></p>
            <p><strong>Активных:</strong> <?= $stats['active'] ?></p>
            <p><strong>Продано:</strong> <?= $stats['sold'] ?></p>
            <p><strong>Всего метров:</strong> <?= number_format($stats['total_meters'], 1) ?></p>
        </div>
    </div>
</body>
</html>
