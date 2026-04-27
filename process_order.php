<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/functions/rolls.php';
require_once __DIR__ . '/functions/stock_movements.php';

$requestId = intval($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Неверный ID заказа');
}

// Получаем информацию о заказе
$stmt = $db->prepare("
    SELECT * FROM b24_sale_requests WHERE id = ?
");
$stmt->execute([$requestId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('Заказ не найден');
}

// Получаем позиции заказа
$stmt = $db->prepare("
    SELECT l.*, 
           p.name as product_name,
           p.price_per_meter as current_price,
           COALESCE(SUM(CASE 
               WHEN r.reserved = 0 
               AND r.current_length > 0 
               AND r.status NOT IN ('sold','waste','written_off') 
               THEN r.current_length 
               ELSE 0 
           END), 0) as available_meters
    FROM b24_sale_lines l
    LEFT JOIN products p ON p.id = l.product_id
    LEFT JOIN rolls r ON r.product_id = p.id
    WHERE l.request_id = ?
    GROUP BY l.id
");
$stmt->execute([$requestId]);
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'process') {
        $db->beginTransaction();
        
        try {
            foreach ($_POST['lines'] as $lineId => $lineData) {
                $lineId = intval($lineId);
                $quantity = floatval($lineData['quantity'] ?? 0);
                $price = floatval($lineData['price'] ?? 0);
                
                if ($quantity <= 0) continue;
                
                // Получаем информацию о позиции
                $stmt = $db->prepare("SELECT * FROM b24_sale_lines WHERE id = ?");
                $stmt->execute([$lineId]);
                $line = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$line) continue;
                
                // Списываем со склада
                $cuts = allocateMeters($db, $line['product_id'], $quantity);
                
                // Записываем продажу
                $total = $quantity * $price;
                $stmt = $db->prepare("
                    INSERT INTO sales 
                    (product_id, type, quantity, price_per_unit, total, deal_id, deal_url, responsible, created_at)
                    VALUES (?, 'meter', ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $line['product_id'],
                    $quantity,
                    $price,
                    $total,
                    $order['b24_deal_id'],
                    "https://llumar.bitrix24.kz/crm/deal/details/{$order['b24_deal_id']}/",
                    $order['responsible']
                ]);
                
                // Логируем движение
                logAndSyncMovement($db, [
                    'product_id' => $line['product_id'],
                    'movement_type' => 'sale_meter',
                    'quantity_m' => $quantity,
                    'quantity_rolls' => 0,
                    'price_per_unit' => $price,
                    'total' => $total,
                    'deal_id' => $order['b24_deal_id'],
                    'comment' => "Продажа по заказу #{$order['b24_deal_id']}"
                ]);
                
                // Обновляем статус позиции
                $stmt = $db->prepare("
                    UPDATE b24_sale_lines 
                    SET status = 'completed', quantity_m = ?, price_per_unit = ?
                    WHERE id = ?
                ");
                $stmt->execute([$quantity, $price, $lineId]);
                
                // Сохраняем информацию о раскрое
                foreach ($cuts as $cut) {
                    $stmt = $db->prepare("
                        INSERT INTO b24_sale_line_cuts 
                        (line_id, roll_id, meters_used, remaining_meters)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$lineId, $cut['roll_id'], $cut['used'], $cut['remaining']]);
                }
            }
            
            // Обновляем статус заказа
            $stmt = $db->prepare("
                UPDATE b24_sale_requests 
                SET status = 'completed', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$requestId]);
            
            $db->commit();
            
            header("Location: dashboard.php?success=order_processed");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Ошибка: " . $e->getMessage();
        }
    }
}

// Получаем доступные рулоны для каждой позиции
$availableRolls = [];
foreach ($lines as $line) {
    $stmt = $db->prepare("
        SELECT * FROM rolls 
        WHERE product_id = ? 
        AND reserved = 0 
        AND current_length > 0 
        AND status NOT IN ('sold','waste','written_off')
        ORDER BY 
            CASE WHEN status = 'scrap' THEN 0 ELSE 1 END,
            current_length ASC
    ");
    $stmt->execute([$line['product_id']]);
    $availableRolls[$line['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Обработка заказа #<?= $order['b24_deal_id'] ?></title>
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
            max-width: 1000px;
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
        .order-info {
            background: #ecf0f1;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .line-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .line-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .stock-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        .stock-item {
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
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
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
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
        .success {
            color: #27ae60;
            background: #d4edda;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .rolls-suggestion {
            background: #e8f4f8;
            padding: 0.5rem;
            border-radius: 4px;
            margin: 0.5rem 0;
            font-size: 0.85rem;
        }
        .price-display {
            font-weight: bold;
            color: #2c3e50;
        }
        .total-display {
            font-size: 1.2rem;
            font-weight: bold;
            color: #27ae60;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📦 Обработка заказа</h1>
        <a href="dashboard.php" class="btn">← Назад к панели</a>
    </div>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="order-info">
                <h3>Заказ #<?= $order['b24_deal_id'] ?></h3>
                <p><strong>Название:</strong> <?= htmlspecialchars($order['deal_name']) ?></p>
                <p><strong>Ответственный:</strong> <?= htmlspecialchars($order['responsible']) ?></p>
                <p><strong>Создан:</strong> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="process">
            
            <?php foreach ($lines as $line): ?>
                <div class="line-card">
                    <div class="line-header">
                        <div>
                            <h4><?= htmlspecialchars($line['product_name']) ?></h4>
                            <p>Требуется: <?= $line['quantity_m'] ?> м</p>
                        </div>
                        <div class="price-display">
                            Цена: <?= number_format($line['price_per_unit'], 0) ?> ₽/м
                        </div>
                    </div>

                    <div class="stock-info">
                        <div class="stock-item">
                            <strong>Доступно:</strong><br>
                            <?= number_format($line['available_meters'], 1) ?> м
                        </div>
                        <div class="stock-item">
                            <strong>Статус:</strong><br>
                            <?= $line['available_meters'] >= $line['quantity_m'] 
                                ? '<span style="color: #27ae60;">✅ Достаточно</span>' 
                                : '<span style="color: #e74c3c;">❌ Недостаточно</span>' ?>
                        </div>
                    </div>

                    <?php if (!empty($availableRolls[$line['id'])): ?>
                        <div class="rolls-suggestion">
                            <strong>Рекомендуемый раскрой:</strong><br>
                            <?php 
                            $remaining = $line['quantity_m'];
                            foreach ($availableRolls[$line['id']] as $roll) {
                                if ($remaining <= 0) break;
                                $take = min($roll['current_length'], $remaining);
                                echo "Рулон #{$roll['id']}: {$take} м (остаток: " . ($roll['current_length'] - $take) . " м)<br>";
                                $remaining -= $take;
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Количество для списания (м):</label>
                        <input type="number" 
                               name="lines[<?= $line['id'] ?>][quantity]" 
                               value="<?= $line['quantity_m'] ?>" 
                               step="0.1" 
                               min="0" 
                               max="<?= $line['available_meters'] ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label>Цена за метр (₽):</label>
                        <input type="number" 
                               name="lines[<?= $line['id'] ?>][price]" 
                               value="<?= $line['price_per_unit'] ?>" 
                               step="1" 
                               min="0" 
                               required>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="card">
                <div style="text-align: center;">
                    <button type="submit" class="btn btn-success" style="font-size: 1.1rem; padding: 0.75rem 2rem;">
                        ✅ Обработать заказ
                    </button>
                    <a href="dashboard.php" class="btn btn-danger">Отмена</a>
                </div>
            </div>
        </form>
    </div>
</body>
</html>
