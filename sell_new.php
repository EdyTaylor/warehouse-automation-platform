<?php
$page_title = 'Продажи';
require 'includes/header.php';
require 'db.php';
$db = getDB();

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
        header("Location: sell.php?error=Недостаточно целых рулонов");
        exit;
    }

    $price = getPrice($product, $qty);
    $total = $price * $qty;

    for ($i = 0; $i < $qty; $i++) {
        $db->prepare("
            UPDATE rolls 
            SET status='sold', current_length=0 
            WHERE id=?
        ")->execute([$rollsList[$i]['id']]);
    }

    $db->prepare("
        INSERT INTO sales (product_id, type, quantity, price_per_unit, total)
        VALUES (?, 'roll', ?, ?, ?)
    ")->execute([$product_id, $qty, $price, $total]);

    header("Location: sell.php?success=Продано рулонов: $qty | $total");
    exit;
}

// 🔥 ПРОДАЖА МЕТРОВ
if (isset($_POST['sell_meters'])) {
    $product_id = intval($_POST['meter_product_id']);
    $meters = floatval($_POST['meters']);

    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT * FROM rolls 
        WHERE product_id = ? 
        AND status != 'sold'
        AND current_length > 0
        ORDER BY 
            CASE WHEN status='cut' THEN 0 ELSE 1 END,
            current_length ASC
    ");
    $stmt->execute([$product_id]);
    $rolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $remaining = $meters;

    foreach ($rolls as $roll) {
        if ($remaining <= 0) break;

        $take = min($roll['current_length'], $remaining);
        $new_length = $roll['current_length'] - $take;
        $status = ($new_length <= 0) ? 'sold' : 'cut';

        $db->prepare("
            UPDATE rolls SET current_length=?, status=?
            WHERE id=?
        ")->execute([$new_length, $status, $roll['id']]);

        $remaining -= $take;
    }

    if ($remaining > 0) {
        header("Location: sell.php?error=Не хватает метров");
        exit;
    } else {
        $price = $product['price_per_meter'];
        $total = $price * $meters;

        $db->prepare("
            INSERT INTO sales (product_id, type, quantity, price_per_unit, total)
            VALUES (?, 'meter', ?, ?, ?)
        ")->execute([$product_id, $meters, $price, $total]);

        header("Location: sell.php?success=Продано $meters м | $total");
        exit;
    }
}

// 🔥 СКЛАД
$rolls = $db->query("
    SELECT 
        rolls.id as roll_id,
        rolls.*,
        products.name
    FROM rolls
    LEFT JOIN products ON rolls.product_id = products.id
    ORDER BY rolls.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Статистика продаж
$stats = $db->query("
    SELECT 
        COUNT(*) as total_sales,
        SUM(CASE WHEN type = 'roll' THEN quantity ELSE 0 END) as total_rolls,
        SUM(CASE WHEN type = 'meter' THEN quantity ELSE 0 END) as total_meters,
        SUM(total) as total_revenue
    FROM sales
    WHERE DATE(created_at) = CURDATE()
")->fetch(PDO::FETCH_ASSOC);

// Доступные рулоны для продажи
$availableRolls = array_filter($rolls, fn($r) => $r['status'] === 'active' && $r['current_length'] > 0);
$cutRolls = array_filter($rolls, fn($r) => $r['status'] === 'cut' && $r['current_length'] > 0);
?>

<div class="container">
    <!-- Статистика продаж -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['total_sales'] ?></div>
            <div class="stat-label">Продаж сегодня</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['total_rolls'] ?></div>
            <div class="stat-label">Рулонов продано</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['total_meters'], 1) ?></div>
            <div class="stat-label">Метров продано</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['total_revenue'], 0) ?> ₽</div>
            <div class="stat-label">Выручка сегодня</div>
        </div>
    </div>

    <!-- Продажа рулонов -->
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">📦 Продажа целых рулонов</h2>
            <div class="text-muted">
                <small>Доступно: <?= count(array_filter($availableRolls, fn($r) => $r['current_length'] == $r['original_length'])) ?> рулонов</small>
            </div>
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
            <div class="form-group">
                <label class="form-label">Цена за рулон</label>
                <input type="number" id="roll_price" class="form-control" readonly placeholder="Рассчитается автоматически">
            </div>
            <div class="form-group">
                <label class="form-label">Итого</label>
                <input type="number" id="roll_total" class="form-control" readonly placeholder="Рассчитается автоматически">
            </div>
            <div class="form-group d-flex align-items-end">
                <button type="submit" name="sell_rolls" class="btn btn-success btn-lg">💵 Продать рулоны</button>
            </div>
        </form>
    </div>

    <!-- Продажа в метрах -->
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">📏 Продажа в метрах</h2>
            <div class="text-muted">
                <small>Доступно: <?= count($availableRolls) + count($cutRolls) ?> рулонов</small>
            </div>
        </div>
        <form method="POST" class="form-row">
            <div class="form-group">
                <label class="form-label">Товар</label>
                <select name="meter_product_id" class="form-control" required>
                    <option value="">Выберите товар</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $p['price_per_meter'] ?>">
                            <?= htmlspecialchars($p['name']) ?> (<?= $p['price_per_meter'] ?> ₽/м)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Метров</label>
                <input type="number" name="meters" class="form-control" step="0.1" min="0.1" required id="meters_input">
            </div>
            <div class="form-group">
                <label class="form-label">Цена за метр</label>
                <input type="number" id="meter_price" class="form-control" readonly placeholder="Из товара">
            </div>
            <div class="form-group">
                <label class="form-label">Итого</label>
                <input type="number" id="meter_total" class="form-control" readonly placeholder="Рассчитается автоматически">
            </div>
            <div class="form-group d-flex align-items-end">
                <button type="submit" name="sell_meters" class="btn btn-primary btn-lg">💵 Продать метры</button>
            </div>
        </form>
    </div>

    <!-- Доступные рулоны -->
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">📋 Доступные рулоны</h2>
            <div>
                <span class="badge badge-success"><?= count($availableRolls) ?> целых</span>
                <span class="badge badge-warning"><?= count($cutRolls) ?> в резке</span>
            </div>
        </div>
        
        <?php if (!empty($availableRolls)): ?>
            <h4 class="mt-3 mb-2">✅ Целые рулоны</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Товар</th>
                            <th>Длина</th>
                            <th>Цена/м</th>
                            <th>Цена рулона</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availableRolls as $r): ?>
                            <?php if ($r['current_length'] == $r['original_length']): ?>
                                <tr>
                                    <td><?= $r['roll_id'] ?></td>
                                    <td><?= htmlspecialchars($r['name']) ?></td>
                                    <td><?= $r['current_length'] ?> м</td>
                                    <td><?= number_format($r['price_per_meter'], 0) ?> ₽</td>
                                    <td><?= number_format($r['price_per_meter'] * $r['current_length'], 0) ?> ₽</td>
                                    <td>
                                        <button class="btn btn-sm btn-success" onclick="quickSellRoll(<?= $r['product_id'] ?>, 1, <?= $r['price_per_meter'] * $r['current_length'] ?>)">
                                            🛒 Быстрая продажа
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($cutRolls)): ?>
            <h4 class="mt-4 mb-2">✂️ Рулоны в резке</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Товар</th>
                            <th>Остаток</th>
                            <th>Цена/м</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cutRolls as $r): ?>
                            <tr>
                                <td><?= $r['roll_id'] ?></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td>
                                    <strong><?= $r['current_length'] ?> м</strong>
                                    <div class="progress" style="width: 100px; height: 6px;">
                                        <div class="progress-bar" style="width: <?= ($r['current_length'] / $r['original_length']) * 100 ?>%"></div>
                                    </div>
                                </td>
                                <td><?= number_format($r['price_per_meter'], 0) ?> ₽</td>
                                <td><span class="badge badge-warning">В резке</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (empty($availableRolls) && empty($cutRolls)): ?>
            <div class="alert alert-warning text-center">
                ⚠️ Нет доступных рулонов для продажи. Добавьте рулоны на склад.
            </div>
        <?php endif; ?>
    </div>

    <!-- Последние продажи -->
    <div class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">📈 Последние продажи</h2>
        </div>
        <?php
        $recentSales = $db->query("
            SELECT s.*, p.name
            FROM sales s
            LEFT JOIN products p ON p.id = s.product_id
            ORDER BY s.created_at DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <?php if (!empty($recentSales)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Товар</th>
                            <th>Тип</th>
                            <th>Количество</th>
                            <th>Цена</th>
                            <th>Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><?= date('d.m.Y H:i', strtotime($sale['created_at'])) ?></td>
                                <td><?= htmlspecialchars($sale['name']) ?></td>
                                <td>
                                    <?php
                                    $typeClass = $sale['type'] === 'roll' ? 'badge-success' : 'badge-info';
                                    $typeText = $sale['type'] === 'roll' ? 'Рулон' : 'Метры';
                                    ?>
                                    <span class="badge <?= $typeClass ?>"><?= $typeText ?></span>
                                </td>
                                <td><?= $sale['quantity'] ?></td>
                                <td><?= number_format($sale['price_per_unit'], 0) ?> ₽</td>
                                <td><strong><?= number_format($sale['total'], 0) ?> ₽</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                📊 Пока нет продаж
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Автоматический расчет цен
document.addEventListener('DOMContentLoaded', function() {
    // Для продажи рулонов
    const rollProductSelect = document.querySelector('select[name="sell_product_id"]');
    const rollQtyInput = document.querySelector('input[name="sell_qty"]');
    const rollPriceInput = document.getElementById('roll_price');
    const rollTotalInput = document.getElementById('roll_total');

    function calculateRollPrice() {
        const productId = rollProductSelect.value;
        const qty = parseInt(rollQtyInput.value) || 0;
        
        if (productId && qty > 0) {
            // Получаем цены товара
            const prices = {
                '1-4': <?= json_encode(array_column($products, 'price_1_4', 'id')) ?>[productId] || 0,
                '5-9': <?= json_encode(array_column($products, 'price_5_9', 'id')) ?>[productId] || 0,
                '10-19': <?= json_encode(array_column($products, 'price_10_19', 'id')) ?>[productId] || 0,
                '20+': <?= json_encode(array_column($products, 'price_20_plus', 'id')) ?>[productId] || 0
            };
            
            let price = 0;
            if (qty <= 4 && prices['1-4'] > 0) price = prices['1-4'];
            else if (qty <= 9 && prices['5-9'] > 0) price = prices['5-9'];
            else if (qty <= 19 && prices['10-19'] > 0) price = prices['10-19'];
            else if (prices['20+'] > 0) price = prices['20+'];
            
            rollPriceInput.value = price || 0;
            rollTotalInput.value = price * qty;
        } else {
            rollPriceInput.value = '';
            rollTotalInput.value = '';
        }
    }

    rollProductSelect.addEventListener('change', calculateRollPrice);
    rollQtyInput.addEventListener('input', calculateRollPrice);

    // Для продажи метров
    const meterProductSelect = document.querySelector('select[name="meter_product_id"]');
    const metersInput = document.getElementById('meters_input');
    const meterPriceInput = document.getElementById('meter_price');
    const meterTotalInput = document.getElementById('meter_total');

    function calculateMeterPrice() {
        const selectedOption = meterProductSelect.options[meterProductSelect.selectedIndex];
        const price = parseFloat(selectedOption?.dataset?.price) || 0;
        const meters = parseFloat(metersInput.value) || 0;
        
        meterPriceInput.value = price || 0;
        meterTotalInput.value = price * meters;
    }

    meterProductSelect.addEventListener('change', calculateMeterPrice);
    metersInput.addEventListener('input', calculateMeterPrice);
});

// Быстрая продажа рулона
function quickSellRoll(productId, qty, price) {
    if (confirm(`Продать 1 рулон за ${price} ₽?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="sell_product_id" value="${productId}">
            <input type="hidden" name="sell_qty" value="${qty}">
            <input type="hidden" name="sell_rolls" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require 'includes/footer.php'; ?>
