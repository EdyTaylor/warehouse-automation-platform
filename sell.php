<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require 'menu.php';


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

        echo "<p style='color:green;'>Продано рулонов: $qty | $total</p>";
    }
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
        echo "<p style='color:red;'>Не хватает метров</p>";
    } else {

        $price = $product['price_per_meter'];
        $total = $price * $meters;

        $db->prepare("
            INSERT INTO sales (product_id, type, quantity, price_per_unit, total)
            VALUES (?, 'meter', ?, ?, ?)
        ")->execute([$product_id, $meters, $price, $total]);

        echo "<p style='color:green;'>Продано $meters м | $total</p>";
    }
}


// 🔥 СКЛАД (ФИКС С roll_id)
$rolls = $db->query("
    SELECT 
        rolls.id as roll_id,
        rolls.*,
        products.name
    FROM rolls
    LEFT JOIN products ON rolls.product_id = products.id
    ORDER BY rolls.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Списание</h2>

<form method="POST">
    <input type="hidden" name="action" value="writeoff">

    <select name="writeoff_roll_id" required>
        <option value="">Выбери рулон</option>
        <?php foreach ($rolls as $r): ?>
            <option value="<?= $r['roll_id'] ?>">
                #<?= $r['roll_id'] ?> | <?= $r['name'] ?> (<?= $r['current_length'] ?>м)
            </option>
        <?php endforeach; ?>
    </select>

    <input name="writeoff_meters" type="number" step="0.1" required>

    <button type="submit">Списать</button>
</form>