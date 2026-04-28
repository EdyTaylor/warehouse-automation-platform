<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/functions/stock_movements.php';

$productId = intval($_GET['product_id'] ?? 0);

// Получаем информацию о товаре
$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die('Товар не найден');
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = intval($_POST['quantity'] ?? 0);
    $minFull = floatval($_POST['min_full'] ?? 0);
    $purchasePrice = floatval($_POST['purchase_price'] ?? 0);
    
    if ($quantity > 0) {
        $db->beginTransaction();
        
        try {
            for ($i = 0; $i < $quantity; $i++) {
                // Добавляем рулон
                $stmt = $db->prepare("
                    INSERT INTO rolls 
                    (product_id, original_length, current_length, min_full_length, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $productId,
                    $product['roll_length'],
                    $product['roll_length'],
                    $minFull
                ]);
                
                $rollId = intval($db->lastInsertId());
                
                // Логируем движение
                logAndSyncMovement($db, [
                    'product_id' => $productId,
                    'roll_id' => $rollId,
                    'movement_type' => 'receipt',
                    'quantity_m' => floatval($product['roll_length']),
                    'quantity_rolls' => 1,
                    'price_per_unit' => $purchasePrice,
                    'total' => $purchasePrice * floatval($product['roll_length']),
                    'comment' => 'Добавление рулонов через add_stock.php'
                ]);
            }
            
            // Обновляем цену закупки если указана
            if ($purchasePrice > 0) {
                $stmt = $db->prepare("UPDATE products SET purchase_price = ? WHERE id = ?");
                $stmt->execute([$purchasePrice, $productId]);
            }
            
            $db->commit();
            
            header("Location: dashboard.php?success=stock_added");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Ошибка: " . $e->getMessage();
        }
    } else {
        $error = "Укажите количество рулонов";
    }
}

// Получаем текущие остатки
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_rolls,
        SUM(current_length) as total_meters,
        COUNT(CASE WHEN status = 'active' AND current_length = original_length THEN 1 END) as full_rolls
    FROM rolls 
    WHERE product_id = ?
");
$stmt->execute([$productId]);
$stock = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Добавление <?= htmlspecialchars($product['name']) ?></title>
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
            max-width: 600px;
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
        .product-info {
            background: #ecf0f1;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .stock-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }
        .stock-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .stock-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
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
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .error {
            color: #e74c3c;
            background: #ffeaea;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .warning {
            color: #f39c12;
            background: #fff3cd;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .price-display {
            font-size: 1.2rem;
            font-weight: bold;
            color: #27ae60;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📦 Добавление на склад</h1>
        <a href="dashboard.php" class="btn">← Назад</a>
    </div>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="product-info">
                <h2><?= htmlspecialchars($product['name']) ?></h2>
                <p>Длина рулона: <?= $product['roll_length'] ?> м</p>
                <p>Текущая цена: <?= number_format($product['price_per_meter'], 0) ?> KGS/м</p>
            </div>

            <div class="stock-info">
                <div class="stock-item">
                    <div class="stock-number"><?= $stock['total_rolls'] ?></div>
                    <div>Всего рулонов</div>
                </div>
                <div class="stock-item">
                    <div class="stock-number"><?= number_format($stock['total_meters'], 1) ?></div>
                    <div>Всего метров</div>
                </div>
                <div class="stock-item">
                    <div class="stock-number"><?= $stock['full_rolls'] ?></div>
                    <div>Целых рулонов</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Добавить рулоны</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Количество рулонов:</label>
                    <input type="number" name="quantity" value="1" min="1" max="50" required>
                </div>

                <div class="form-group">
                    <label>Минимальный остаток (м):</label>
                    <input type="number" name="min_full" value="0.5" step="0.1" min="0" required>
                </div>

                <div class="form-group">
                    <label>Цена закупки за рулон (KGS):</label>
                    <input type="number" name="purchase_price" value="<?= $product['purchase_price'] ?>" step="0.01" min="0">
                    <small>Оставьте 0 если не хотите изменять</small>
                </div>

                <div class="price-display">
                    Итого к добавлению: <span id="totalMeters">30</span> м
                </div>

                <div style="margin-top: 1.5rem; text-align: center;">
                    <button type="submit" class="btn btn-success" style="font-size: 1.1rem; padding: 0.75rem 2rem;">
                        ✅ Добавить на склад
                    </button>
                    <a href="dashboard.php" class="btn btn-danger">Отмена</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelector('input[name="quantity"]').addEventListener('input', function() {
            const quantity = parseInt(this.value) || 0;
            const rollLength = <?= $product['roll_length'] ?>;
            document.getElementById('totalMeters').textContent = quantity * rollLength;
        });
    </script>
</body>
</html>
