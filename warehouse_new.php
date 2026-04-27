<?php
$page_title = 'Управление складом';
require 'includes/header.php';
require 'db.php';
$db = getDB();
require_once __DIR__ . '/functions/stock_movements.php';

// 🔥 УДАЛЕНИЕ
if (isset($_GET['delete_id'])) {
    $stmt = $db->prepare("DELETE FROM rolls WHERE id = ?");
    $stmt->execute([intval($_GET['delete_id'])]);
    header("Location: warehouse.php?success=Рулон удален");
    exit;
}

// 🔥 ТОВАРЫ
$products = $db->query("SELECT * FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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
        header("Location: warehouse.php?success=Добавлено рулонов: $quantity");
        exit;
    }
}

// 🔥 СПИСАНИЕ
if (isset($_POST['action']) && $_POST['action'] === 'writeoff') {
    $roll_id = intval($_POST['writeoff_roll_id']);
    $meters = floatval($_POST['writeoff_meters']);

    $stmt = $db->prepare("SELECT * FROM rolls WHERE id=?");
    $stmt->execute([$roll_id]);
    $roll = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($roll) {
        if ($meters > $roll['current_length']) {
            header("Location: warehouse.php?error=Нельзя списать больше чем есть");
            exit;
        }

        $new_length = $roll['current_length'] - $meters;
        $new_status = ($new_length <= 0) ? 'written_off' : 'cut';
        if ($new_length <= 0) $new_length = 0;

        $stmt = $db->prepare("UPDATE rolls SET current_length=?, status=? WHERE id=?");
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

        header("Location: warehouse.php?success=Списано: $meters м");
        exit;
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
        header("Location: warehouse.php?error=Недостаточно целых рулонов");
        exit;
    }

    $price = getPrice($product, $qty);
    $total = $price * $qty;

    for ($i = 0; $i < $qty; $i++) {
        $stmt = $db->prepare("UPDATE rolls SET status='sold', current_length=0 WHERE id=?");
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

    header("Location: warehouse.php?success=Продано рулонов: $qty | $total");
    exit;
}

// 🔥 ПРОДАЖА МЕТРОВ
if (isset($_POST['sell_meters'])) {
    require_once __DIR__ . '/functions/rolls.php';

    $product_id = intval($_POST['meter_product_id']);
    $meters = floatval($_POST['meters']);

    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    try {
        $cuts = allocateMeters($db, $product_id, $meters);
        $price = $product['price_per_meter'];
        $total = $price * $meters;

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

        header("Location: warehouse.php?success=Продано $meters м | $total");
        exit;
    } catch (Exception $e) {
        header("Location: warehouse.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// 🔥 СКЛАД
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
?>

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
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">📦 Добавить рулоны</h2>
        </div>
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
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">💰 Продажа рулонов</h2>
        </div>
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
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">📏 Продажа в метрах</h2>
        </div>
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
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">🗑️ Списание</h2>
        </div>
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
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">📋 Складские остатки</h2>
            <div>
                <span class="badge badge-success"><?= $stats['active_rolls'] ?> активных</span>
                <span class="badge badge-warning"><?= $stats['cut_rolls'] ?> в резке</span>
                <span class="badge badge-danger"><?= $stats['sold_rolls'] ?> продано</span>
            </div>
        </div>
        <div class="table-responsive">
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
                                    <div class="progress" style="width: 100px; height: 6px;">
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
                                $statusClass = 'badge-secondary';
                                $statusText = $r['status'];
                                switch ($r['status']) {
                                    case 'active': $statusClass = 'badge-success'; $statusText = 'Активный'; break;
                                    case 'sold': $statusClass = 'badge-danger'; $statusText = 'Продан'; break;
                                    case 'cut': $statusClass = 'badge-warning'; $statusText = 'В резке'; break;
                                    case 'scrap': $statusClass = 'badge-info'; $statusText = 'Обрезок'; break;
                                    case 'written_off': $statusClass = 'badge-danger'; $statusText = 'Списан'; break;
                                    case 'waste': $statusClass = 'badge-danger'; $statusText = 'Отход'; break;
                                }
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="add_stock.php?product_id=<?= $r['product_id'] ?>" class="btn btn-sm btn-success" title="Добавить такой же">➕</a>
                                    <a href="?delete_id=<?= $r['id'] ?>" 
                                       class="btn btn-sm btn-danger" 
                                       title="Удалить рулон"
                                       onclick="return confirm('Удалить рулон #<?= $r['id'] ?>?')">🗑️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
