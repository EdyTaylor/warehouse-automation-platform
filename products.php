<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/api/bitrix/send.php';

function hasColumn($db, $table, $column) {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute(array($column));
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

function normalizeNumber($value) {
    $value = str_replace(' ', '', $value);
    $value = str_replace(',', '.', $value);
    return $value === '' ? 0 : $value;
}

function syncProductPriceToB24($db, $productId) {
    $stmt = $db->prepare("
        SELECT id, name, b24_product_id, price_per_meter
        FROM products
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute(array(intval($productId)));
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        return array('ok' => false, 'message' => 'Товар не найден');
    }
    if (empty($product['b24_product_id'])) {
        return array('ok' => false, 'message' => 'Нет b24_product_id');
    }

    $fields = array('NAME' => $product['name']);
    if (floatval($product['price_per_meter']) > 0) {
        $fields['PRICE'] = floatval($product['price_per_meter']);
    }

    $resp = sendToBitrix('crm.product.update', array(
        'id' => intval($product['b24_product_id']),
        'fields' => $fields
    ));

    if (is_array($resp) && !isset($resp['error'])) {
        return array('ok' => true, 'message' => 'Обновлено в Б24');
    }
    $err = 'Ошибка обновления в Б24';
    if (is_array($resp) && isset($resp['error_description'])) {
        $err = $resp['error_description'];
    } elseif (is_array($resp) && isset($resp['error'])) {
        $err = $resp['error'];
    }
    return array('ok' => false, 'message' => $err);
}

// DELETE
if (isset($_GET['delete_id'])) {
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute(array(intval($_GET['delete_id'])));
    header("Location: products.php");
    exit;
}

// EDIT
$editProduct = null;
if (isset($_GET['edit_id'])) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute(array(intval($_GET['edit_id'])));
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'save';

    if ($action === 'sync_to_b24') {
        $rows = $db->query("SELECT id FROM products WHERE b24_product_id IS NOT NULL AND b24_product_id <> 0")->fetchAll(PDO::FETCH_ASSOC);
        $ok = 0;
        $err = 0;
        foreach ($rows as $r) {
            $res = syncProductPriceToB24($db, $r['id']);
            if ($res['ok']) {
                $ok++;
            } else {
                $err++;
            }
        }
        header("Location: products.php?sync_msg=" . urlencode("Синк в Б24: обновлено {$ok}, ошибок {$err}"));
        exit;
    }

    if ($action === 'move_group') {
        $productId = intval(isset($_POST['product_id']) ? $_POST['product_id'] : 0);
        $targetCatalogId = intval(isset($_POST['target_catalog_id']) ? $_POST['target_catalog_id'] : 0);
        if ($productId <= 0 || $targetCatalogId <= 0 || !hasColumn($db, 'products', 'catalog_id')) {
            header("Location: products.php?sync_msg=" . urlencode("Некорректные данные для перемещения"));
            exit;
        }

        $stmt = $db->prepare("UPDATE products SET catalog_id = ? WHERE id = ?");
        $stmt->execute(array($targetCatalogId, $productId));

        $stmt = $db->prepare("SELECT b24_product_id FROM products WHERE id = ?");
        $stmt->execute(array($productId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && intval($row['b24_product_id']) > 0) {
            $resp = sendToBitrix('crm.product.update', array(
                'id' => intval($row['b24_product_id']),
                'fields' => array('CATALOG_ID' => $targetCatalogId)
            ));
            if (is_array($resp) && isset($resp['error'])) {
                header("Location: products.php?sync_msg=" . urlencode("Группа локально изменена, ошибка Б24: " . (isset($resp['error_description']) ? $resp['error_description'] : $resp['error'])));
                exit;
            }
        }

        header("Location: products.php?sync_msg=" . urlencode("Товар перемещен в каталог #{$targetCatalogId}"));
        exit;
    }

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

        $stmt->execute(array(
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
        ));
        // Safe auto-sync: try to update B24, but don't break local save.
        $syncResult = syncProductPriceToB24($db, $_POST['id']);
        $syncTail = $syncResult['ok'] ? ' | Б24: ок' : (' | Б24: ' . $syncResult['message']);
        header("Location: products.php?sync_msg=" . urlencode("Товар обновлен" . $syncTail));
        exit;
    } else {
        $stmt = $db->prepare("
            INSERT INTO products
            (name, roll_length, price_per_meter, purchase_price, delivery_price, price_1_4, price_5_9, price_10_19, price_20_plus)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute(array(
            $_POST['name'],
            $_POST['roll_length'],
            normalizeNumber($_POST['price_per_meter']),
            normalizeNumber($_POST['purchase_price']),
            normalizeNumber($_POST['delivery_price']),
            normalizeNumber($_POST['price_1_4']),
            normalizeNumber($_POST['price_5_9']),
            normalizeNumber($_POST['price_10_19']),
            normalizeNumber($_POST['price_20_plus'])
        ));
        header("Location: products.php?sync_msg=" . urlencode("Товар сохранен локально"));
        exit;
    }
}

$hasCatalogId = hasColumn($db, 'products', 'catalog_id');
$products = $db->query("SELECT * FROM products ORDER BY " . ($hasCatalogId ? "catalog_id ASC, " : "") . "id DESC")->fetchAll(PDO::FETCH_ASSOC);
$syncMsg = isset($_GET['sync_msg']) ? $_GET['sync_msg'] : '';
$page_title = 'Товары';
require 'includes/header.php';
?>

<main class="container">
<h2>Товары (совместимый режим)</h2>
<?php if ($syncMsg): ?>
    <p style="color:green;"><?php echo htmlspecialchars($syncMsg); ?></p>
<?php endif; ?>
<p>Каталогизация возвращена по локальному `catalog_id` без рискованных внешних вызовов на открытии страницы.</p>

<form method="POST" style="margin-bottom:12px;">
    <input type="hidden" name="action" value="sync_to_b24">
    <button type="submit">Отправить цены в Б24</button>
</form>

<form method="POST">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?php echo isset($editProduct['id']) ? $editProduct['id'] : ''; ?>">

    <input name="name" placeholder="Название" value="<?php echo isset($editProduct['name']) ? htmlspecialchars($editProduct['name']) : ''; ?>" required><br><br>
    <input name="roll_length" placeholder="Метраж рулона" value="<?php echo isset($editProduct['roll_length']) ? $editProduct['roll_length'] : ''; ?>" required><br><br>

    <input name="price_per_meter" placeholder="Цена за метр" value="<?php echo isset($editProduct['price_per_meter']) ? $editProduct['price_per_meter'] : ''; ?>"><br>
    <input name="purchase_price" placeholder="Себестоимость (KGS)" value="<?php echo isset($editProduct['purchase_price']) ? $editProduct['purchase_price'] : ''; ?>"><br>
    <input name="delivery_price" placeholder="С доставкой" value="<?php echo isset($editProduct['delivery_price']) ? $editProduct['delivery_price'] : ''; ?>"><br><br>

    <b>Цены:</b><br>
    <input name="price_1_4" placeholder="1-4" value="<?php echo isset($editProduct['price_1_4']) ? $editProduct['price_1_4'] : ''; ?>"><br>
    <input name="price_5_9" placeholder="5-9" value="<?php echo isset($editProduct['price_5_9']) ? $editProduct['price_5_9'] : ''; ?>"><br>
    <input name="price_10_19" placeholder="10-19" value="<?php echo isset($editProduct['price_10_19']) ? $editProduct['price_10_19'] : ''; ?>"><br>
    <input name="price_20_plus" placeholder="20+" value="<?php echo isset($editProduct['price_20_plus']) ? $editProduct['price_20_plus'] : ''; ?>"><br><br>

    <button><?php echo $editProduct ? 'Обновить' : 'Сохранить'; ?></button>
</form>

<h3>Список</h3>
<?php if ($hasCatalogId): ?>
<?php
$groups = array();
foreach ($products as $p) {
    $cid = isset($p['catalog_id']) ? intval($p['catalog_id']) : 0;
    if (!isset($groups[$cid])) {
        $groups[$cid] = array();
    }
    $groups[$cid][] = $p;
}
?>
<?php foreach ($groups as $catalogId => $groupProducts): ?>
<details open style="margin-bottom:12px;">
<summary><strong><?php echo $catalogId > 0 ? ("Каталог #".$catalogId) : "Без каталога"; ?></strong> — <?php echo count($groupProducts); ?> тов.</summary>
<table border="1" style="margin-top:8px;">
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
<th>Каталог</th>
<th>Переместить</th>
<th>✏️</th>
<th>❌</th>
</tr>

<?php foreach ($groupProducts as $p): ?>
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
<td><?php echo isset($p['b24_product_id']) ? $p['b24_product_id'] : ''; ?></td>
<td><?php echo isset($p['catalog_id']) ? intval($p['catalog_id']) : ''; ?></td>
<td>
    <form method="POST" style="display:inline;">
        <input type="hidden" name="action" value="move_group">
        <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
        <input type="number" name="target_catalog_id" min="1" style="width:80px;" placeholder="ID" required>
        <button type="submit">↔</button>
    </form>
</td>
<td><a href="?edit_id=<?php echo $p['id']; ?>">✏️</a></td>
<td><a href="?delete_id=<?php echo $p['id']; ?>">❌</a></td>
</tr>
<?php endforeach; ?>
</table>
</details>
<?php endforeach; ?>
<?php else: ?>
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
<?php foreach ($products as $p): ?>
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
<td><?php echo isset($p['b24_product_id']) ? $p['b24_product_id'] : ''; ?></td>
<td><a href="?edit_id=<?php echo $p['id']; ?>">✏️</a></td>
<td><a href="?delete_id=<?php echo $p['id']; ?>">❌</a></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</main>

<?php require 'includes/footer.php'; ?>