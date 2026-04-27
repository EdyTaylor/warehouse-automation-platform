<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require 'menu.php';
require_once __DIR__ . '/functions/stock_movements.php';


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
?>


<h2>Добавить рулоны</h2>

<form method="POST">
    <select name="product_id" required>
        <option value="">Выбери товар</option>
        <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>">
                <?= $p['name'] ?> (<?= $p['roll_length'] ?>м)
            </option>
        <?php endforeach; ?>
    </select>

    <input name="min_full" placeholder="Мин остаток" required>
    <input name="quantity" placeholder="Количество" required>

    <button type="submit">Добавить</button>
</form>


<h2>Продажа рулонов</h2>

<form method="POST">
    <select name="sell_product_id" required>
        <option value="">Выбери товар</option>
        <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>">
                <?= $p['name'] ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input name="sell_qty" type="number" placeholder="Количество рулонов" required>

    <button name="sell_rolls">Продать</button>
</form>


<h2>Продажа в метрах</h2>

<form method="POST">
    <select name="meter_product_id" required>
        <option value="">Выбери товар</option>
        <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>">
                <?= $p['name'] ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input name="meters" type="number" step="0.1" placeholder="Сколько метров" required>

    <button name="sell_meters">Продать</button>
</form>


<h2>Списание</h2>

<form method="POST">
    <input type="hidden" name="action" value="writeoff">

    <select name="writeoff_roll_id" required>
        <option value="">Выбери рулон</option>
        <?php foreach ($rolls as $r): ?>
            <option value="<?= $r['roll_id'] ?>">
                #<?= $r['roll_id'] ?> | <?= $r['name'] ?> (остаток: <?= $r['current_length'] ?>м)
            </option>
        <?php endforeach; ?>
    </select>

    <input name="writeoff_meters" type="number" step="0.1" placeholder="Сколько списать" required>

    <button type="submit">Списать</button>
</form>


<h2>Склад</h2>

<table border="1">
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
<th>Удалить</th>
</tr>

<?php foreach ($rolls as $r): ?>
<tr>
<td><?= $r['id'] ?></td>
<td><?= $r['name'] ?></td>
<td><?= $r['original_length'] ?></td>
<td><?= $r['current_length'] ?></td>
<td><?= $r['price_per_meter'] ?></td>
<td><?= $r['price_1_4'] ?></td>
<td><?= $r['price_5_9'] ?></td>
<td><?= $r['price_10_19'] ?></td>
<td><?= $r['price_20_plus'] ?></td>
<td><?= $r['status'] ?></td>

<td>
<a href="?delete_id=<?= $r['id'] ?>" onclick="return confirm('Удалить?')">
❌
</a>
</td>

</tr>
<?php endforeach; ?>
</table>