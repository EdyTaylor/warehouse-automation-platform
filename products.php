<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();

function normalizeNumber($value) {
    $value = str_replace(' ', '', $value);
    $value = str_replace(',', '.', $value);
    return $value === '' ? 0 : $value;
}

// УДАЛЕНИЕ
if (isset($_GET['delete_id'])) {
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([intval($_GET['delete_id'])]);
    header("Location: products.php");
    exit;
}

// РЕДАКТИРОВАНИЕ
$editProduct = null;

if (isset($_GET['edit_id'])) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([intval($_GET['edit_id'])]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
}

// СОХРАНЕНИЕ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($_POST['id'])) {

        $stmt = $db->prepare("
            UPDATE products SET
                name = ?,
                roll_length = ?,
                price_per_meter = ?,
                purchase_price = ?,
                delivery_price = ?,
                price_1_4 = ?,
                price_5_9 = ?,
                price_10_19 = ?,
                price_20_plus = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $_POST['name'],
            $_POST['roll_length'],
            normalizeNumber($_POST['price_per_meter']),
            normalizeNumber($_POST['purchase_price']),
            normalizeNumber($_POST['delivery_price']),
            normalizeNumber($_POST['price_1_4']),
            normalizeNumber($_POST['price_5_9']),
            normalizeNumber($_POST['price_10_19']),
            normalizeNumber($_POST['price_20_plus']),
            $_POST['id']
        ]);

    } else {

        $stmt = $db->prepare("
            INSERT INTO products 
            (name, roll_length, price_per_meter, purchase_price, delivery_price,
             price_1_4, price_5_9, price_10_19, price_20_plus)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['name'],
            $_POST['roll_length'],
            normalizeNumber($_POST['price_per_meter']),
            normalizeNumber($_POST['purchase_price']),
            normalizeNumber($_POST['delivery_price']),
            normalizeNumber($_POST['price_1_4']),
            normalizeNumber($_POST['price_5_9']),
            normalizeNumber($_POST['price_10_19']),
            normalizeNumber($_POST['price_20_plus'])
        ]);
    }

    header("Location: products.php");
    exit;
}

// СПИСОК
$products = $db->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

require 'menu.php';
?>

<h2>Товары</h2>

<form method="POST">
    <input type="hidden" name="id" value="<?php echo isset($editProduct['id']) ? $editProduct['id'] : ''; ?>">

    <input name="name" placeholder="Название" value="<?php echo isset($editProduct['name']) ? htmlspecialchars($editProduct['name']) : ''; ?>" required><br><br>

    <input name="roll_length" placeholder="Метраж рулона" value="<?php echo isset($editProduct['roll_length']) ? $editProduct['roll_length'] : ''; ?>" required><br><br>

    <input name="price_per_meter" placeholder="Цена за метр" value="<?php echo isset($editProduct['price_per_meter']) ? $editProduct['price_per_meter'] : ''; ?>"><br>
    <input name="purchase_price" placeholder="Себестоимость" value="<?php echo isset($editProduct['purchase_price']) ? $editProduct['purchase_price'] : ''; ?>"><br>
    <input name="delivery_price" placeholder="С доставкой" value="<?php echo isset($editProduct['delivery_price']) ? $editProduct['delivery_price'] : ''; ?>"><br><br>

    <b>Цены:</b><br>

    <input name="price_1_4" placeholder="1-4" value="<?php echo isset($editProduct['price_1_4']) ? $editProduct['price_1_4'] : ''; ?>"><br>
    <input name="price_5_9" placeholder="5-9" value="<?php echo isset($editProduct['price_5_9']) ? $editProduct['price_5_9'] : ''; ?>"><br>
    <input name="price_10_19" placeholder="10-19" value="<?php echo isset($editProduct['price_10_19']) ? $editProduct['price_10_19'] : ''; ?>"><br>
    <input name="price_20_plus" placeholder="20+" value="<?php echo isset($editProduct['price_20_plus']) ? $editProduct['price_20_plus'] : ''; ?>"><br><br>

    <button><?php echo $editProduct ? 'Обновить' : 'Сохранить'; ?></button>
</form>

<h3>Список</h3>

<table border="1">
<tr>
<th>ID</th>
<th>Название</th>
<th>Метраж</th>
<th>Цена/м</th>
<th>Себестоимость</th>
<th>С доставкой</th>
<th>1-4</th>
<th>5-9</th>
<th>10-19</th>
<th>20+</th>
<th>B24 ID</th>
<th>✏️</th>
<th>❌</th>
</tr>

<?php foreach ($products as $p) { ?>
<tr>
<td><?php echo $p['id']; ?></td>
<td><?php echo htmlspecialchars($p['name']); ?></td>
<td><?php echo $p['roll_length']; ?></td>
<td><?php echo $p['price_per_meter']; ?></td>
<td><?php echo $p['purchase_price']; ?></td>
<td><?php echo $p['delivery_price']; ?></td>
<td><?php echo $p['price_1_4']; ?></td>
<td><?php echo $p['price_5_9']; ?></td>
<td><?php echo $p['price_10_19']; ?></td>
<td><?php echo $p['price_20_plus']; ?></td>
<td><?php echo $p['b24_product_id']; ?></td>

<td><a href="?edit_id=<?php echo $p['id']; ?>">✏️</a></td>
<td><a href="?delete_id=<?php echo $p['id']; ?>">❌</a></td>
</tr>
<?php } ?>
</table>