<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/api/bitrix/send.php';

function hasColumn($db, $table, $column) {
    $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function getCatalogSyncFilter($cfg) {
    if (!isset($cfg['sync_catalog_ids'])) {
        return [];
    }
    if (!is_array($cfg['sync_catalog_ids'])) {
        return [];
    }
    $ids = [];
    foreach ($cfg['sync_catalog_ids'] as $id) {
        $id = intval($id);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return $ids;
}

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
    $action = isset($_POST['action']) ? $_POST['action'] : 'save';

    if ($action === 'sync_from_crm') {
        $cfg = require __DIR__ . '/api/bitrix/config.php';
        $allowedCatalogIds = getCatalogSyncFilter($cfg);
        $supportsCatalogId = hasColumn($db, 'products', 'catalog_id');

        if ($supportsCatalogId) {
            $ins = $db->prepare("
                INSERT INTO products
                (name, roll_length, price_per_meter, b24_product_id, catalog_id)
                VALUES (?, 30, 0, ?, ?)
            ");
            $upd = $db->prepare("
                UPDATE products
                SET name = ?, catalog_id = ?
                WHERE b24_product_id = ?
            ");
        } else {
            $ins = $db->prepare("
                INSERT INTO products
                (name, roll_length, price_per_meter, b24_product_id)
                VALUES (?, 30, 0, ?)
            ");
            $upd = $db->prepare("
                UPDATE products
                SET name = ?
                WHERE b24_product_id = ?
            ");
        }
        $sel = $db->prepare("SELECT id FROM products WHERE b24_product_id = ?");

        $created = 0;
        $updated = 0;
        $start = 0;
        $guard = 0;

        while ($guard < 50) {
            $payload = ['start' => $start];
            if (!empty($allowedCatalogIds)) {
                // Exclude "services/app" catalogs by syncing only explicitly allowed catalogs.
                $payload['filter'] = ['@CATALOG_ID' => $allowedCatalogIds];
            }

            $resp = sendToBitrix('crm.product.list', $payload);
            if (!is_array($resp) || isset($resp['error'])) {
                $msg = isset($resp['error_description']) ? $resp['error_description'] : 'Ошибка вызова crm.product.list';
                header("Location: products.php?sync_msg=" . urlencode("Ошибка CRM: " . $msg));
                exit;
            }

            $items = isset($resp['result']) && is_array($resp['result']) ? $resp['result'] : [];
            foreach ($items as $item) {
                $b24Id = isset($item['ID']) ? intval($item['ID']) : 0;
                $name = isset($item['NAME']) ? $item['NAME'] : '';
                $catalogId = isset($item['CATALOG_ID']) ? intval($item['CATALOG_ID']) : null;
                if ($b24Id <= 0) {
                    continue;
                }

                $sel->execute([$b24Id]);
                $exists = $sel->fetch(PDO::FETCH_ASSOC);
                if ($exists) {
                    if ($supportsCatalogId) {
                        $upd->execute([$name, $catalogId, $b24Id]);
                    } else {
                        $upd->execute([$name, $b24Id]);
                    }
                    $updated++;
                } else {
                    if ($supportsCatalogId) {
                        $ins->execute([$name, $b24Id, $catalogId]);
                    } else {
                        $ins->execute([$name, $b24Id]);
                    }
                    $created++;
                }
            }

            if (!isset($resp['next'])) {
                break;
            }
            $start = intval($resp['next']);
            $guard++;
        }

        $filterMsg = !empty($allowedCatalogIds) ? " (каталоги: " . implode(',', $allowedCatalogIds) . ")" : "";
        header("Location: products.php?sync_msg=" . urlencode("Из CRM: создано {$created}, обновлено {$updated}{$filterMsg}"));
        exit;
    }

    if ($action === 'sync_to_crm') {
        $rows = $db->query("
            SELECT id, name, b24_product_id, price_per_meter
            FROM products
            WHERE b24_product_id IS NOT NULL AND b24_product_id <> 0
        ")->fetchAll(PDO::FETCH_ASSOC);

        $sent = 0;
        $errors = 0;
        foreach ($rows as $row) {
            $fields = [
                'NAME' => $row['name']
            ];
            if (floatval($row['price_per_meter']) > 0) {
                $fields['PRICE'] = floatval($row['price_per_meter']);
            }

            $resp = sendToBitrix('crm.product.update', [
                'id' => intval($row['b24_product_id']),
                'fields' => $fields
            ]);

            if (is_array($resp) && !isset($resp['error'])) {
                $sent++;
            } else {
                $errors++;
            }
        }

        header("Location: products.php?sync_msg=" . urlencode("В CRM: отправлено {$sent}, ошибок {$errors}"));
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
$hasCatalogId = hasColumn($db, 'products', 'catalog_id');
$products = $db->query("SELECT * FROM products ORDER BY " . ($hasCatalogId ? "catalog_id ASC, " : "") . "id DESC")->fetchAll(PDO::FETCH_ASSOC);
$syncMsg = isset($_GET['sync_msg']) ? $_GET['sync_msg'] : '';

$page_title = 'Товары';
require 'includes/header.php';
?>

<main class="container">
<h2>Товары</h2>

<?php if ($syncMsg): ?>
    <p style="color:green;"><?php echo htmlspecialchars($syncMsg); ?></p>
<?php endif; ?>

<form method="POST" style="margin-bottom: 12px;">
    <input type="hidden" name="action" value="sync_from_crm">
    <button type="submit">Обновить из CRM</button>
</form>

<form method="POST" style="margin-bottom: 16px;">
    <input type="hidden" name="action" value="sync_to_crm">
    <button type="submit">Отправить в CRM</button>
</form>

<form method="POST">
    <input type="hidden" name="action" value="save">
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
<?php if ($hasCatalogId): ?>
<th>Каталог B24</th>
<?php endif; ?>
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
<?php if ($hasCatalogId): ?>
<td><?php echo isset($p['catalog_id']) ? $p['catalog_id'] : ''; ?></td>
<?php endif; ?>

<td><a href="?edit_id=<?php echo $p['id']; ?>">✏️</a></td>
<td><a href="?delete_id=<?php echo $p['id']; ?>">❌</a></td>
</tr>
<?php } ?>
</table>
</main>

<?php require 'includes/footer.php'; ?>