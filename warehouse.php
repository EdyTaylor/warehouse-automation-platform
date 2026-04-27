<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require 'menu.php';
require_once __DIR__ . '/functions/stock_movements.php';

// Современный CSS стиль
$modern_css = "
<style>
* { box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0; 
    background: linear-gradient(135deg, #f5f6fa 0%, #e9ecef 100%);
    color: #2c3e50;
    line-height: 1.6;
}
.header {
    background: linear-gradient(135deg, #2c3e50, #34495e);
    color: white;
    padding: 1.5rem 0;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}
.header-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header h1 {
    font-size: 2rem;
    font-weight: 600;
    margin: 0;
}
.header p {
    opacity: 0.9;
    margin: 0;
    font-size: 1.1rem;
}
.nav {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.nav-link {
    color: white;
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    font-weight: 500;
}
.nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-1px);
}
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem 2rem;
}
.card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.07);
    border: 1px solid #e1e8ed;
    transition: all 0.3s ease;
}
.card:hover {
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.card-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 1.5rem 0;
    padding-bottom: 1rem;
    border-bottom: 2px solid #ecf0f1;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
}
.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #3498db;
    margin-bottom: 0.5rem;
}
.stat-label {
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    font-size: 0.875rem;
    letter-spacing: 0.5px;
}
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.form-group {
    display: flex;
    flex-direction: column;
}
.form-label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #495057;
}
.form-control {
    padding: 0.75rem 1rem;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}
.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}
.btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    min-width: 120px;
}
.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
.btn-success {
    background: linear-gradient(135deg, #27ae60, #229954);
    color: white;
}
.btn-success:hover {
    background: linear-gradient(135deg, #229954, #1e8e49);
}
.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}
.btn-primary:hover {
    background: linear-gradient(135deg, #2980b9, #21618c);
}
.btn-warning {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
}
.btn-warning:hover {
    background: linear-gradient(135deg, #e67e22, #d35400);
}
.btn-danger {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
}
.btn-danger:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
}
.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}
.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.badge-success { background: #27ae60; color: white; }
.badge-warning { background: #f39c12; color: white; }
.badge-danger { background: #e74c3c; color: white; }
.badge-info { background: #3498db; color: white; }
.table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.table th,
.table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}
.table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}
.table tr:hover {
    background: #f8f9fa;
}
.progress {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
    margin: 0.5rem 0;
}
.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #27ae60, #3498db);
    transition: width 0.3s ease;
}
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border-left: 4px solid;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border-left-color: #27ae60;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-left-color: #e74c3c;
}
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}
</style>
";

// 🔥 УДАЛЕНИЕ
if (isset($_GET['delete_id'])) {
    $stmt = $db->prepare("DELETE FROM rolls WHERE id = ?");
    $stmt->execute([intval($_GET['delete_id'])]);
    header("Location: warehouse.php");
    exit;
}


// 🔥 ТОВАРЫ
$products = $db->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);


// 🔥 ФУНКЦИЯ ЦЕНЫ
function getPrice($row, $qty) {

    if ($qty <= 4 && $row['price_1_4'] > 0) return $row['price_1_4'];
    if ($qty <= 9 && $row['price_5_9'] > 0) return $row['price_5_9'];
    if ($qty <= 19 && $row['price_10_19'] > 0) return $row['price_10_19'];
    if ($row['price_20_plus'] > 0) return $row['price_20_plus'];

    return 0;
}


// 🔥 ДОБАВЛЕНИЕ РУЛОНОВ
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && !isset($_POST['sell_rolls']) 
    && !isset($_POST['sell_meters']) 
    && (!isset($_POST['action']) || $_POST['action'] !== 'writeoff')
) {

    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $min = floatval($_POST['min_full']);

    $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

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

        logAndSyncMovement($db, [
            'product_id' => $product_id,
            'roll_id' => intval($db->lastInsertId()),
            'movement_type' => 'receipt',
            'quantity_m' => floatval($product['roll_length']),
            'quantity_rolls' => 1,
            'price_per_unit' => isset($product['purchase_price']) ? floatval($product['purchase_price']) : 0,
            'total' => isset($product['purchase_price']) ? floatval($product['purchase_price']) : 0,
            'comment' => 'Оприходование в приложении'
        ]);
    }

    echo "<p style='color:green;'>Добавлено: $quantity</p>";
}


// 🔥 СПИСАНИЕ
if (isset($_POST['action']) && $_POST['action'] === 'writeoff') {

    $roll_id = intval($_POST['writeoff_roll_id']);
    $meters = floatval($_POST['writeoff_meters']);

    $stmt = $db->prepare("SELECT * FROM rolls WHERE id=?");
    $stmt->execute([$roll_id]);
    $roll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$roll) {
        echo "<p style='color:red;'>Рулон не найден (ID: $roll_id)</p>";
    } else {

        if ($meters > $roll['current_length']) {
            echo "<p style='color:red;'>Нельзя списать больше чем есть</p>";
        } else {

            $new_length = $roll['current_length'] - $meters;

            if ($new_length <= 0) {
                $new_status = 'written_off';
                $new_length = 0;
            } else {
                $new_status = 'cut';
            }

            $stmt = $db->prepare("
                UPDATE rolls 
                SET current_length=?, status=? 
                WHERE id=?
            ");
            $stmt->execute([$new_length, $new_status, $roll_id]);

            $stmt = $db->prepare("
                INSERT INTO sales 
                (product_id, type, quantity, price_per_unit, total, deal_id, deal_url)
                VALUES (?, 'writeoff', ?, 0, 0, NULL, NULL)
            ");
            $stmt->execute([$roll['product_id'], $meters]);

            logAndSyncMovement($db, [
                'product_id' => intval($roll['product_id']),
                'roll_id' => $roll_id,
                'movement_type' => 'writeoff',
                'quantity_m' => $meters,
                'quantity_rolls' => 0,
                'price_per_unit' => 0,
                'total' => 0,
                'comment' => 'Ручное списание в warehouse.php'
            ]);

            echo "<p style='color:orange;'>Списано: $meters м</p>";
        }
    }
}


// 🔥 ПРОДАЖА РУЛОНОВ
if (isset($_POST['sell_rolls'])) {

    $product_id = intval($_POST['sell_product_id']);
    $qty = intval($_POST['sell_qty']);

    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT * FROM rolls 
        WHERE product_id = ? 
        AND status = 'active'
        AND current_length = original_length
        ORDER BY id ASC
    ");
    $stmt->execute([$product_id]);
    $rollsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rollsList) < $qty) {
        echo "<p style='color:red;'>Недостаточно целых рулонов</p>";
    } else {

        $price = getPrice($product, $qty);
        $total = $price * $qty;

        for ($i = 0; $i < $qty; $i++) {
            $stmt = $db->prepare("
                UPDATE rolls 
                SET status='sold', current_length=0 
                WHERE id=?
            ");
            $stmt->execute([$rollsList[$i]['id']]);
        }

        $stmt = $db->prepare("
            INSERT INTO sales (product_id, type, quantity, price_per_unit, total)
            VALUES (?, 'roll', ?, ?, ?)
        ");
        $stmt->execute([$product_id, $qty, $price, $total]);

        logAndSyncMovement($db, [
            'product_id' => $product_id,
            'movement_type' => 'sale_roll',
            'quantity_m' => 0,
            'quantity_rolls' => $qty,
            'price_per_unit' => $price,
            'total' => $total,
            'comment' => 'Продажа рулонов'
        ]);

        echo "<p style='color:green;'>Продано рулонов: $qty | $total</p>";
    }
}


// 🔥 ПРОДАЖА МЕТРОВ (НОВАЯ ЛОГИКА)
if (isset($_POST['sell_meters'])) {

    require_once __DIR__ . '/functions/rolls.php';

    $product_id = intval($_POST['meter_product_id']);
    $meters = floatval($_POST['meters']);

    // получаем товар
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    try {

        // 🔥 ГЛАВНОЕ — раскрой
        $cuts = allocateMeters($db, $product_id, $meters);

        // 💰 расчет
        $price = $product['price_per_meter'];
        $total = $price * $meters;

        // 💾 запись продажи
        $stmt = $db->prepare("
            INSERT INTO sales (product_id, type, quantity, price_per_unit, total)
            VALUES (?, 'meter', ?, ?, ?)
        ");
        $stmt->execute([$product_id, $meters, $price, $total]);

        logAndSyncMovement($db, [
            'product_id' => $product_id,
            'movement_type' => 'sale_meter',
            'quantity_m' => $meters,
            'quantity_rolls' => 0,
            'price_per_unit' => $price,
            'total' => $total,
            'comment' => 'Продажа в метрах'
        ]);

        echo "<p style='color:green;'>Продано $meters м | $total</p>";

    } catch (Exception $e) {
        echo "<p style='color:red;'>" . $e->getMessage() . "</p>";
    }
}


// 🔥 СКЛАД (ФИКС roll_id)
$rolls = $db->query("
    SELECT 
        rolls.id AS roll_id,
        rolls.*,
        products.*
    FROM rolls
    LEFT JOIN products ON rolls.product_id = products.id
    ORDER BY rolls.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Статистика
$stats = [
    'total_rolls' => count($rolls),
    'active_rolls' => count(array_filter($rolls, fn($r) => $r['status'] === 'active')),
    'sold_rolls' => count(array_filter($rolls, fn($r) => $r['status'] === 'sold')),
    'cut_rolls' => count(array_filter($rolls, fn($r) => $r['status'] === 'cut')),
    'total_meters' => array_sum(array_column($rolls, 'current_length')),
    'active_meters' => array_sum(array_filter($rolls, fn($r) => $r['status'] === 'active'), ARRAY_FILTER_USE_KEY)
];

echo $modern_css;
?>

<header class="header">
    <div class="header-content">
        <div>
            <h1>🏭 Управление складом</h1>
            <p>Добавление рулонов, списание, остатки</p>
        </div>
        <nav class="nav">
            <a href="dashboard.php" class="nav-link">🏠 Главная</a>
            <a href="warehouse.php" class="nav-link">🏪 Склад</a>
            <a href="products.php" class="nav-link">📦 Товары</a>
            <a href="sell.php" class="nav-link">💰 Продажи</a>
            <a href="b24_sales.php" class="nav-link">🔄 Б24</a>
        </nav>
    </div>
</header>

<div class="container">
    <!-- Статистика -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['total_rolls'] ?></div>
            <div class="stat-label">Всего рулонов</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['active_rolls'] ?></div>
            <div class="stat-label">Активных</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['total_meters'], 1) ?></div>
            <div class="stat-label">Всего метров</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['active_meters'], 1) ?></div>
            <div class="stat-label">Свободных метров</div>
        </div>
    </div>

    <!-- Добавление рулонов -->
    <div class="card">
        <h2 class="card-title">📦 Добавить рулоны</h2>
        <form method="POST" class="form-row">
            <div class="form-group">
                <label class="form-label">Товар</label>
                <select name="product_id" class="form-control" required>
                    <option value="">Выберите товар</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['name']) ?> (<?= $p['roll_length'] ?>м)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Мин. остаток (м)</label>
                <input type="number" name="min_full" class="form-control" step="0.1" required>
            </div>
            <div class="form-group">
                <label class="form-label">Количество</label>
                <input type="number" name="quantity" class="form-control" min="1" required>
            </div>
            <div class="form-group d-flex align-items-end">
                <button type="submit" class="btn btn-success">➕ Добавить</button>
            </div>
        </form>
    </div>

    <!-- Продажа рулонов -->
    <div class="card">
        <h2 class="card-title">💰 Продажа рулонов</h2>
        <form method="POST" class="form-row">
            <div class="form-group">
                <label class="form-label">Товар</label>
                <select name="sell_product_id" class="form-control" required>
                    <option value="">Выберите товар</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Количество рулонов</label>
                <input type="number" name="sell_qty" class="form-control" min="1" required>
            </div>
            <div class="form-group d-flex align-items-end">
                <button type="submit" name="sell_rolls" class="btn btn-primary">💵 Продать</button>
            </div>
        </form>
    </div>

    <!-- Продажа в метрах -->
    <div class="card">
        <h2 class="card-title">📏 Продажа в метрах</h2>
        <form method="POST" class="form-row">
            <div class="form-group">
                <label class="form-label">Товар</label>
                <select name="meter_product_id" class="form-control" required>
                    <option value="">Выберите товар</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Метров</label>
                <input type="number" name="meters" class="form-control" step="0.1" min="0.1" required>
            </div>
            <div class="form-group d-flex align-items-end">
                <button type="submit" name="sell_meters" class="btn btn-primary">💵 Продать</button>
            </div>
        </form>
    </div>

    <!-- Списание -->
    <div class="card">
        <h2 class="card-title">🗑️ Списание</h2>
        <form method="POST">
            <input type="hidden" name="action" value="writeoff">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Рулон</label>
                    <select name="writeoff_roll_id" class="form-control" required>
                        <option value="">Выберите рулон</option>
                        <?php foreach ($rolls as $r): ?>
                            <option value="<?= $r['roll_id'] ?>">
                                #<?= $r['roll_id'] ?> | <?= htmlspecialchars($r['name']) ?> (остаток: <?= $r['current_length'] ?>м)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Метров к списанию</label>
                    <input type="number" name="writeoff_meters" class="form-control" step="0.1" min="0.1" required>
                </div>
                <div class="form-group d-flex align-items-end">
                    <button type="submit" class="btn btn-warning">🗑️ Списать</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Склад -->
    <div class="card">
        <h2 class="card-title">📋 Складские остатки</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Товар</th>
                    <th>Длина</th>
                    <th>Остаток</th>
                    <th>Цена/м</th>
                    <th>Цены за рулон</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rolls as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= $r['original_length'] ?> м</td>
                        <td>
                            <strong><?= $r['current_length'] ?> м</strong>
                            <?php if ($r['current_length'] < $r['original_length']): ?>
                                <div class="progress" style="width: 100px;">
                                    <div class="progress-bar" style="width: <?= ($r['current_length'] / $r['original_length']) * 100 ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($r['price_per_meter'], 0) ?> ₽</td>
                        <td>
                            <small>
                                1-4: <?= $r['price_1_4'] ?: '-' ?><br>
                                5-9: <?= $r['price_5_9'] ?: '-' ?><br>
                                10-19: <?= $r['price_10_19'] ?: '-' ?><br>
                                20+: <?= $r['price_20_plus'] ?: '-' ?>
                            </small>
                        </td>
                        <td>
                            <?php
                            $badgeClass = 'badge-secondary';
                            $statusText = $r['status'];
                            switch ($r['status']) {
                                case 'active': $badgeClass = 'badge-success'; $statusText = 'Активный'; break;
                                case 'sold': $badgeClass = 'badge-danger'; $statusText = 'Продан'; break;
                                case 'cut': $badgeClass = 'badge-warning'; $statusText = 'В резке'; break;
                                case 'scrap': $badgeClass = 'badge-info'; $statusText = 'Обрезок'; break;
                                case 'written_off': $badgeClass = 'badge-danger'; $statusText = 'Списан'; break;
                                case 'waste': $badgeClass = 'badge-danger'; $statusText = 'Отход'; break;
                            }
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= $statusText ?></span>
                        </td>
                        <td>
                            <a href="add_stock.php?product_id=<?= $r['product_id'] ?>" class="btn btn-sm btn-success" title="Добавить такой же">➕</a>
                            <a href="?delete_id=<?= $r['id'] ?>" 
                               class="btn btn-sm btn-danger" 
                               title="Удалить рулон"
                               onclick="return confirm('Удалить рулон #<?= $r['id'] ?>?')">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>