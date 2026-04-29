<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/api/bitrix/send.php';
require_once __DIR__ . '/functions/pricing.php';
require_once __DIR__ . '/functions/app_settings.php';

function hasColumn($db, $table, $column) {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute(array($column));
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

function ensureColumnExists($db, $table, $column, $columnSql) {
    if (!hasColumn($db, $table, $column)) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN {$columnSql}");
    }
}

function hasTable($db, $table) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute(array($table));
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function logProductPriceHistory($db, $productId, $oldValues, $newValues) {
    try {
        if (!hasTable($db, 'product_price_history')) {
            return array('ok' => false, 'message' => 'Таблица product_price_history не найдена');
        }

        $stmt = $db->prepare("
            INSERT INTO product_price_history
                (product_id, old_price_per_meter, new_price_per_meter, old_purchase_price, new_purchase_price, old_delivery_price, new_delivery_price, change_source)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'products.php')
        ");
        $stmt->execute(array(
            intval($productId),
            isset($oldValues['price_per_meter']) ? $oldValues['price_per_meter'] : null,
            isset($newValues['price_per_meter']) ? $newValues['price_per_meter'] : null,
            isset($oldValues['purchase_price']) ? $oldValues['purchase_price'] : null,
            isset($newValues['purchase_price']) ? $newValues['purchase_price'] : null,
            isset($oldValues['delivery_price']) ? $oldValues['delivery_price'] : null,
            isset($newValues['delivery_price']) ? $newValues['delivery_price'] : null
        ));
        return array('ok' => true, 'message' => 'История обновлена');
    } catch (Exception $e) {
        error_log('products.php: failed to write product_price_history for product #' . intval($productId) . ': ' . $e->getMessage());
        return array('ok' => false, 'message' => 'Не удалось сохранить историю цен');
    }
}

function getBrandFromProductName($name) {
    $name = trim((string)$name);
    if ($name === '') {
        return 'Без бренда';
    }
    $parts = preg_split('/\s+/', $name);
    $first = isset($parts[0]) ? trim($parts[0]) : '';
    $first = preg_replace('/[^a-zA-Zа-яА-Я0-9_-]/u', '', $first);
    if ($first === '') {
        return 'Без бренда';
    }
    return $first;
}

function normalizeNumber($value) {
    $value = str_replace(' ', '', $value);
    $value = str_replace(',', '.', $value);
    return $value === '' ? 0 : $value;
}

function calculateMeterPriceFromRoll($rollLength, $deliveryPrice, $fallbackMeterPrice) {
    $rollLength = floatval(normalizeNumber($rollLength));
    $deliveryPrice = floatval(normalizeNumber($deliveryPrice));
    $fallbackMeterPrice = floatval(normalizeNumber($fallbackMeterPrice));
    if ($rollLength > 0 && $deliveryPrice > 0) {
        return $deliveryPrice / $rollLength;
    }
    return $fallbackMeterPrice;
}

function normalizeDecimalInput($value) {
    $value = trim((string)$value);
    $value = str_replace("\xc2\xa0", '', $value);
    $value = str_replace(' ', '', $value);
    $value = str_replace(',', '.', $value);
    return $value;
}

function parseDecimalField($value) {
    $normalized = normalizeDecimalInput($value);
    if ($normalized === '') {
        return array(
            'is_empty' => true,
            'is_valid' => true,
            'value' => 0.0
        );
    }
    if (!is_numeric($normalized)) {
        return array(
            'is_empty' => false,
            'is_valid' => false,
            'value' => 0.0
        );
    }
    return array(
        'is_empty' => false,
        'is_valid' => true,
        'value' => floatval($normalized)
    );
}

function validatePricingPayload($postData) {
    $labels = array(
        'roll_length' => 'Метраж рулона',
        'price_per_meter' => 'Цена за метр',
        'purchase_price' => 'Себестоимость',
        'delivery_price' => 'С доставкой за рулон',
        'price_1_4' => 'Цена 1-4',
        'price_5_9' => 'Цена 5-9',
        'price_10_19' => 'Цена 10-19',
        'price_20_plus' => 'Цена 20+'
    );
    $errors = array();
    $warnings = array();
    $values = array();

    foreach ($labels as $field => $label) {
        $raw = isset($postData[$field]) ? $postData[$field] : '';
        $parsed = parseDecimalField($raw);
        if (!$parsed['is_valid']) {
            $errors[] = $label . ': некорректное число.';
            continue;
        }
        if ($parsed['value'] < 0) {
            $errors[] = $label . ': отрицательные значения запрещены.';
        }
        if ($parsed['value'] > 100000000) {
            $errors[] = $label . ': значение выглядит аномально большим.';
        }
        $values[$field] = $parsed['value'];
    }

    $tierFields = array('price_1_4', 'price_5_9', 'price_10_19', 'price_20_plus');
    foreach ($tierFields as $tierField) {
        $raw = isset($postData[$tierField]) ? $postData[$tierField] : '';
        if (normalizeDecimalInput($raw) === '') {
            $warnings[] = 'Поле ' . $labels[$tierField] . ' пустое: будет применен fallback.';
        }
    }

    for ($i = 1; $i < count($tierFields); $i++) {
        $prevField = $tierFields[$i - 1];
        $currField = $tierFields[$i];
        $prev = isset($values[$prevField]) ? floatval($values[$prevField]) : 0.0;
        $curr = isset($values[$currField]) ? floatval($values[$currField]) : 0.0;
        if ($prev > 0 && $curr > 0) {
            if ($curr > ($prev * 1.8) || $curr < ($prev * 0.5)) {
                $warnings[] = 'Нетипичный скачок: ' . $labels[$prevField] . ' (' . $prev . ') -> ' . $labels[$currField] . ' (' . $curr . ').';
            }
        }
    }

    $suggestions = getTierAutofillSuggestions($postData);
    foreach ($suggestions as $tierKey => $suggestedPrice) {
        $warnings[] = 'Подсказка: заполнить ' . $labels[$tierKey] . ' значением ' . round($suggestedPrice, 2) . ' по цепочке fallback.';
    }

    return array(
        'errors' => $errors,
        'warnings' => $warnings,
        'values' => $values,
        'suggestions' => $suggestions
    );
}

function buildPricePreviewRows($priceSource) {
    $previewQty = array(3, 7, 12, 25);
    $rows = array();
    foreach ($previewQty as $qty) {
        $rows[] = explainTierPriceResolution($priceSource, $qty);
    }
    return $rows;
}

ensureColumnExists($db, 'products', 'delivery_price', '`delivery_price` decimal(14,2) NOT NULL DEFAULT 0');
$formErrors = array();
$formWarnings = array();
$tierAutofillSuggestions = array();
$previewRows = array();
ensureColumnExists($db, 'products', 'sync_status', "`sync_status` varchar(20) NOT NULL DEFAULT 'pending'");
ensureColumnExists($db, 'products', 'last_error', '`last_error` text NULL');
ensureColumnExists($db, 'products', 'last_attempt_at', '`last_attempt_at` datetime NULL');
$db->exec("UPDATE products SET sync_status = 'pending' WHERE sync_status IS NULL OR sync_status = ''");

function getB24SyncBatchSize($db) {
    $size = intval(getAppSetting($db, 'b24_sync_batch_size', 20));
    if ($size <= 0) {
        $size = 20;
    }
    if ($size > 200) {
        $size = 200;
    }
    return $size;
}

function getB24SyncDelayMs($db) {
    $delay = intval(getAppSetting($db, 'b24_sync_batch_delay_ms', 150));
    if ($delay < 0) {
        $delay = 0;
    }
    if ($delay > 5000) {
        $delay = 5000;
    }
    return $delay;
}

function updateProductSyncState($db, $productId, $status, $error, $attemptAt) {
    $stmt = $db->prepare("
        UPDATE products
        SET sync_status = ?, last_error = ?, last_attempt_at = ?
        WHERE id = ?
    ");
    $stmt->execute(array($status, $error, $attemptAt, intval($productId)));
}

function markProductSyncPending($db, $productId) {
    $stmt = $db->prepare("
        UPDATE products
        SET sync_status = 'pending', last_error = NULL
        WHERE id = ?
    ");
    $stmt->execute(array(intval($productId)));
}

function runB24SyncForProductIds($db, $productIds) {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)$productIds), function($v) {
        return $v > 0;
    })));

    if (empty($ids)) {
        return array('ok' => 0, 'err' => 0, 'total' => 0);
    }

    $batchSize = getB24SyncBatchSize($db);
    $delayMs = getB24SyncDelayMs($db);
    $ok = 0;
    $err = 0;
    $chunks = array_chunk($ids, $batchSize);
    foreach ($chunks as $chunkIndex => $chunk) {
        foreach ($chunk as $productId) {
            $res = syncProductPriceToB24($db, $productId);
            if ($res['ok']) {
                $ok++;
            } else {
                $err++;
            }
        }
        if ($delayMs > 0 && $chunkIndex < count($chunks) - 1) {
            usleep($delayMs * 1000);
        }
    }

    return array('ok' => $ok, 'err' => $err, 'total' => count($ids));
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
    $attemptAt = date('Y-m-d H:i:s');
    if (empty($product['b24_product_id'])) {
        updateProductSyncState($db, $productId, 'error', 'Нет b24_product_id', $attemptAt);
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
        updateProductSyncState($db, $productId, 'sent', null, $attemptAt);
        return array('ok' => true, 'message' => 'Обновлено в Б24');
    }
    $err = 'Ошибка обновления в Б24';
    if (is_array($resp) && isset($resp['error_description'])) {
        $err = $resp['error_description'];
    } elseif (is_array($resp) && isset($resp['error'])) {
        $err = $resp['error'];
    }
    updateProductSyncState($db, $productId, 'error', $err, $attemptAt);
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
$editProductHistory = array();
if (isset($_GET['edit_id'])) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute(array(intval($_GET['edit_id'])));
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($editProduct && hasTable($db, 'product_price_history')) {
        $historyStmt = $db->prepare("
            SELECT *
            FROM product_price_history
            WHERE product_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 10
        ");
        $historyStmt->execute(array(intval($_GET['edit_id'])));
        $editProductHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'save';

    if ($action === 'sync_to_b24') {
        $rows = $db->query("SELECT id FROM products WHERE b24_product_id IS NOT NULL AND b24_product_id <> 0")->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_map(function($r) { return intval($r['id']); }, $rows);
        $stats = runB24SyncForProductIds($db, $ids);
        header("Location: products.php?sync_msg=" . urlencode("Синк в Б24: обновлено {$stats['ok']}, ошибок {$stats['err']}, всего {$stats['total']}"));
        exit;
    }

    if ($action === 'sync_one') {
        $productId = intval(isset($_POST['product_id']) ? $_POST['product_id'] : 0);
        $res = syncProductPriceToB24($db, $productId);
        $msg = $res['ok'] ? "Синк товара #{$productId}: успешно" : ("Синк товара #{$productId}: " . $res['message']);
        header("Location: products.php?sync_msg=" . urlencode($msg));
        exit;
    }

    if ($action === 'sync_selected') {
        $ids = isset($_POST['selected_ids']) && is_array($_POST['selected_ids']) ? $_POST['selected_ids'] : array();
        $stats = runB24SyncForProductIds($db, $ids);
        header("Location: products.php?sync_msg=" . urlencode("Синк выбранных: обновлено {$stats['ok']}, ошибок {$stats['err']}, всего {$stats['total']}"));
        exit;
    }

    if ($action === 'retry_sync_errors') {
        $rows = $db->query("
            SELECT id
            FROM products
            WHERE sync_status = 'error'
              AND b24_product_id IS NOT NULL
              AND b24_product_id <> 0
        ")->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_map(function($r) { return intval($r['id']); }, $rows);
        $stats = runB24SyncForProductIds($db, $ids);
        header("Location: products.php?sync_msg=" . urlencode("Retry ошибок: обновлено {$stats['ok']}, ошибок {$stats['err']}, всего {$stats['total']}"));
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

    $validation = validatePricingPayload($_POST);
    $formErrors = $validation['errors'];
    $formWarnings = $validation['warnings'];
    $tierAutofillSuggestions = $validation['suggestions'];
    $previewRows = buildPricePreviewRows(array(
        'price_1_4' => isset($validation['values']['price_1_4']) ? $validation['values']['price_1_4'] : 0,
        'price_5_9' => isset($validation['values']['price_5_9']) ? $validation['values']['price_5_9'] : 0,
        'price_10_19' => isset($validation['values']['price_10_19']) ? $validation['values']['price_10_19'] : 0,
        'price_20_plus' => isset($validation['values']['price_20_plus']) ? $validation['values']['price_20_plus'] : 0,
        'price_per_meter' => isset($validation['values']['price_per_meter']) ? $validation['values']['price_per_meter'] : 0,
        'roll_length' => isset($validation['values']['roll_length']) ? $validation['values']['roll_length'] : 0
    ));

    if (!empty($formErrors)) {
        $editProduct = $_POST;
    } elseif (!empty($_POST['id'])) {
        $productId = intval($_POST['id']);
        $oldPriceValues = null;
        $oldPriceStmt = $db->prepare("
            SELECT price_per_meter, purchase_price, delivery_price
            FROM products
            WHERE id = ?
            LIMIT 1
        ");
        $oldPriceStmt->execute(array($productId));
        $oldPriceValues = $oldPriceStmt->fetch(PDO::FETCH_ASSOC);
        $calculatedMeterPrice = calculateMeterPriceFromRoll(
            isset($validation['values']['roll_length']) ? $validation['values']['roll_length'] : 0,
            isset($validation['values']['delivery_price']) ? $validation['values']['delivery_price'] : 0,
            isset($validation['values']['price_per_meter']) ? $validation['values']['price_per_meter'] : 0
        );
        $newPriceValues = array(
            'price_per_meter' => $calculatedMeterPrice,
            'purchase_price' => normalizeNumber($_POST['purchase_price']),
            'delivery_price' => normalizeNumber($_POST['delivery_price'])
        );
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
            isset($validation['values']['roll_length']) ? $validation['values']['roll_length'] : 0,
            $calculatedMeterPrice,
            isset($validation['values']['purchase_price']) ? $validation['values']['purchase_price'] : 0,
            isset($validation['values']['delivery_price']) ? $validation['values']['delivery_price'] : 0,
            isset($validation['values']['price_1_4']) ? $validation['values']['price_1_4'] : 0,
            isset($validation['values']['price_5_9']) ? $validation['values']['price_5_9'] : 0,
            isset($validation['values']['price_10_19']) ? $validation['values']['price_10_19'] : 0,
            isset($validation['values']['price_20_plus']) ? $validation['values']['price_20_plus'] : 0,
            $_POST['id']
        ));
        $historyResult = logProductPriceHistory(
            $db,
            $productId,
            $oldPriceValues ? $oldPriceValues : array(),
            $newPriceValues
        );
        markProductSyncPending($db, $_POST['id']);
        // Safe auto-sync: try to update B24, but don't break local save.
        $syncResult = syncProductPriceToB24($db, $_POST['id']);
        $syncTail = $syncResult['ok'] ? ' | Б24: ок' : (' | Б24: ' . $syncResult['message']);
        $historyTail = $historyResult['ok'] ? '' : (' | История: ' . $historyResult['message']);
        header("Location: products.php?sync_msg=" . urlencode("Товар обновлен" . $syncTail . $historyTail));
        exit;
    } elseif ($action === 'save') {
        $calculatedMeterPrice = calculateMeterPriceFromRoll(
            isset($validation['values']['roll_length']) ? $validation['values']['roll_length'] : 0,
            isset($validation['values']['delivery_price']) ? $validation['values']['delivery_price'] : 0,
            isset($validation['values']['price_per_meter']) ? $validation['values']['price_per_meter'] : 0
        );
        $newPriceValues = array(
            'price_per_meter' => $calculatedMeterPrice,
            'purchase_price' => normalizeNumber($_POST['purchase_price']),
            'delivery_price' => normalizeNumber($_POST['delivery_price'])
        );
        $stmt = $db->prepare("
            INSERT INTO products
            (name, roll_length, price_per_meter, purchase_price, delivery_price, price_1_4, price_5_9, price_10_19, price_20_plus)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute(array(
            $_POST['name'],
            isset($validation['values']['roll_length']) ? $validation['values']['roll_length'] : 0,
            $calculatedMeterPrice,
            isset($validation['values']['purchase_price']) ? $validation['values']['purchase_price'] : 0,
            isset($validation['values']['delivery_price']) ? $validation['values']['delivery_price'] : 0,
            isset($validation['values']['price_1_4']) ? $validation['values']['price_1_4'] : 0,
            isset($validation['values']['price_5_9']) ? $validation['values']['price_5_9'] : 0,
            isset($validation['values']['price_10_19']) ? $validation['values']['price_10_19'] : 0,
            isset($validation['values']['price_20_plus']) ? $validation['values']['price_20_plus'] : 0
        ));

        $newProductId = intval($db->lastInsertId());
        $historyResult = logProductPriceHistory(
            $db,
            $newProductId,
            array(),
            $newPriceValues
        );
        $historyTail = $historyResult['ok'] ? '' : (' | История: ' . $historyResult['message']);
        header("Location: products.php?sync_msg=" . urlencode("Товар сохранен локально" . $historyTail));
        exit;
    }
}

$hasCatalogId = hasColumn($db, 'products', 'catalog_id');
$products = $db->query("SELECT * FROM products ORDER BY " . ($hasCatalogId ? "catalog_id ASC, " : "") . "id DESC")->fetchAll(PDO::FETCH_ASSOC);
$syncMsg = isset($_GET['sync_msg']) ? $_GET['sync_msg'] : '';
$b24Config = require __DIR__ . '/api/bitrix/config.php';
$catalogLabels = array();
if (isset($b24Config['catalog_labels']) && is_array($b24Config['catalog_labels'])) {
    $catalogLabels = $b24Config['catalog_labels'];
}
if (empty($previewRows)) {
    $previewRows = buildPricePreviewRows(array(
        'price_1_4' => isset($editProduct['price_1_4']) ? $editProduct['price_1_4'] : 0,
        'price_5_9' => isset($editProduct['price_5_9']) ? $editProduct['price_5_9'] : 0,
        'price_10_19' => isset($editProduct['price_10_19']) ? $editProduct['price_10_19'] : 0,
        'price_20_plus' => isset($editProduct['price_20_plus']) ? $editProduct['price_20_plus'] : 0,
        'price_per_meter' => isset($editProduct['price_per_meter']) ? $editProduct['price_per_meter'] : 0,
        'roll_length' => isset($editProduct['roll_length']) ? $editProduct['roll_length'] : 0
    ));
}
$page_title = 'Товары';
require 'includes/header.php';
?>

<main class="container">
<h2>Товары (совместимый режим)</h2>
<?php if ($syncMsg): ?>
    <p style="color:green;"><?php echo htmlspecialchars($syncMsg); ?></p>
<?php endif; ?>
<?php if (!empty($formErrors)): ?>
    <div style="border:1px solid #d33; background:#fff6f6; padding:8px; margin-bottom:10px;">
        <b>Ошибки валидации:</b>
        <ul>
            <?php foreach ($formErrors as $errorText): ?>
                <li><?php echo htmlspecialchars($errorText); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if (!empty($formWarnings)): ?>
    <div style="border:1px solid #d8a700; background:#fffbea; padding:8px; margin-bottom:10px;">
        <b>Предупреждения:</b>
        <ul>
            <?php foreach ($formWarnings as $warningText): ?>
                <li><?php echo htmlspecialchars($warningText); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<p>Каталогизация возвращена по локальному `catalog_id` без рискованных внешних вызовов на открытии страницы.</p>

<form method="POST" style="margin-bottom:12px;">
    <input type="hidden" name="action" value="sync_to_b24">
    <button type="submit">Отправить цены в Б24</button>
</form>
<form id="bulk-sync-form" method="POST" style="margin-bottom:12px;">
    <button type="submit" name="action" value="sync_selected">Синк выбранных</button>
    <button type="submit" name="action" value="retry_sync_errors">Retry ошибок</button>
</form>

<form method="POST">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?php echo isset($editProduct['id']) ? $editProduct['id'] : ''; ?>">

    <input name="name" placeholder="Название" value="<?php echo isset($editProduct['name']) ? htmlspecialchars($editProduct['name']) : ''; ?>" required><br><br>
    <input name="roll_length" placeholder="Метраж рулона" value="<?php echo isset($editProduct['roll_length']) ? $editProduct['roll_length'] : ''; ?>" required><br><br>

    <input name="price_per_meter" placeholder="Цена за метр" value="<?php echo isset($editProduct['price_per_meter']) ? $editProduct['price_per_meter'] : ''; ?>"><br>
    <input name="purchase_price" placeholder="Себестоимость (KGS)" value="<?php echo isset($editProduct['purchase_price']) ? $editProduct['purchase_price'] : ''; ?>"><br>
    <input name="delivery_price" placeholder="С доставкой за рулон (KGS)" value="<?php echo isset($editProduct['delivery_price']) ? $editProduct['delivery_price'] : ''; ?>"><br><br>

    <b>Цены:</b><br>
    <input name="price_1_4" placeholder="1-4" value="<?php echo isset($editProduct['price_1_4']) ? $editProduct['price_1_4'] : ''; ?>"><br>
    <input name="price_5_9" placeholder="5-9" value="<?php echo isset($editProduct['price_5_9']) ? $editProduct['price_5_9'] : ''; ?>"><br>
    <input name="price_10_19" placeholder="10-19" value="<?php echo isset($editProduct['price_10_19']) ? $editProduct['price_10_19'] : ''; ?>"><br>
    <input name="price_20_plus" placeholder="20+" value="<?php echo isset($editProduct['price_20_plus']) ? $editProduct['price_20_plus'] : ''; ?>"><br><br>
    <?php if (!empty($tierAutofillSuggestions)): ?>
        <small>
            Подсказка автозаполнения:
            <?php foreach ($tierAutofillSuggestions as $tierKey => $tierValue): ?>
                <?php echo htmlspecialchars($tierKey); ?> → <?php echo htmlspecialchars(round($tierValue, 2)); ?>&nbsp;
            <?php endforeach; ?>
        </small><br><br>
    <?php endif; ?>

    <button><?php echo $editProduct ? 'Обновить' : 'Сохранить'; ?></button>
</form>

<h3>Превью итоговой цены</h3>
<table border="1" style="margin-bottom:16px;">
    <tr>
        <th>Рулонов</th>
        <th>Итоговая цена</th>
        <th>Целевой tier</th>
        <th>Источник цены</th>
        <th>Режим</th>
    </tr>
    <?php foreach ($previewRows as $preview): ?>
    <tr>
        <td><?php echo intval($preview['qty']); ?></td>
        <td><?php echo round(floatval($preview['price']), 2); ?></td>
        <td><?php echo htmlspecialchars($preview['targetTier']); ?></td>
        <td><?php echo htmlspecialchars($preview['sourceTier']); ?></td>
        <td><?php echo !empty($preview['fallbackUsed']) ? 'fallback' : 'tier'; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php if ($editProduct): ?>
<h3>История цен (последние 10)</h3>
<?php if (empty($editProductHistory)): ?>
    <p>Записей пока нет.</p>
<?php else: ?>
    <table border="1" style="margin-bottom:12px;">
        <tr>
            <th>Когда</th>
            <th>Старая цена/м</th>
            <th>Новая цена/м</th>
            <th>Старая себестоимость</th>
            <th>Новая себестоимость</th>
            <th>Старая с доставкой</th>
            <th>Новая с доставкой</th>
        </tr>
        <?php foreach ($editProductHistory as $h): ?>
        <tr>
            <td><?php echo htmlspecialchars($h['created_at']); ?></td>
            <td><?php echo htmlspecialchars((string)$h['old_price_per_meter']); ?></td>
            <td><?php echo htmlspecialchars((string)$h['new_price_per_meter']); ?></td>
            <td><?php echo htmlspecialchars((string)$h['old_purchase_price']); ?></td>
            <td><?php echo htmlspecialchars((string)$h['new_purchase_price']); ?></td>
            <td><?php echo htmlspecialchars((string)$h['old_delivery_price']); ?></td>
            <td><?php echo htmlspecialchars((string)$h['new_delivery_price']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
<?php endif; ?>

<h3>Список</h3>
<?php if ($hasCatalogId): ?>
<?php
$groups = array();
foreach ($products as $p) {
    $cid = isset($p['catalog_id']) ? intval($p['catalog_id']) : 0;
    $brand = getBrandFromProductName(isset($p['name']) ? $p['name'] : '');
    if (!isset($groups[$cid])) {
        $groups[$cid] = array();
    }
    if (!isset($groups[$cid][$brand])) {
        $groups[$cid][$brand] = array();
    }
    $groups[$cid][$brand][] = $p;
}
?>
<?php foreach ($groups as $catalogId => $brands): ?>
<details open style="margin-bottom:12px;">
<?php
$catalogCount = 0;
foreach ($brands as $brandItems) {
    $catalogCount += count($brandItems);
}
$catalogLabel = $catalogId > 0
    ? (isset($catalogLabels[$catalogId]) ? $catalogLabels[$catalogId] : ("Каталог #".$catalogId))
    : "Без каталога";
?>
<summary><strong><?php echo htmlspecialchars($catalogLabel); ?></strong> — <?php echo $catalogCount; ?> тов.</summary>

<?php foreach ($brands as $brand => $brandProducts): ?>
<details style="margin:10px 0 0 20px;" open>
    <summary><strong><?php echo htmlspecialchars($brand); ?></strong> — <?php echo count($brandProducts); ?> тов.</summary>
    <table border="1" style="margin-top:8px;">
    <tr>
    <th>ID</th>
    <th>✓</th>
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
    <th>Действие</th>
    <th>Последняя ошибка</th>
    <th>Попытка</th>
    <th>Sync</th>
    <th>Каталог</th>
    <th>Переместить</th>
    <th>✏️</th>
    <th>❌</th>
    </tr>

    <?php foreach ($brandProducts as $p): ?>
    <tr>
    <td><?php echo $p['id']; ?></td>
    <td>
        <input type="checkbox" form="bulk-sync-form" name="selected_ids[]" value="<?php echo $p['id']; ?>">
    </td>
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
    <td><?php echo isset($p['sync_status']) ? htmlspecialchars($p['sync_status']) : 'pending'; ?></td>
    <td><?php echo !empty($p['last_error']) ? htmlspecialchars($p['last_error']) : '—'; ?></td>
    <td><?php echo !empty($p['last_attempt_at']) ? htmlspecialchars($p['last_attempt_at']) : '—'; ?></td>
    <td>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="sync_one">
            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
            <button type="submit">↻</button>
        </form>
    </td>
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
</details>
<?php endforeach; ?>
<?php else: ?>
<table border="1">
<tr>
<th>ID</th>
<th>✓</th>
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
<th>Действие</th>
<th>Последняя ошибка</th>
<th>Попытка</th>
<th>Sync</th>
<th>✏️</th>
<th>❌</th>
</tr>
<?php foreach ($products as $p): ?>
<tr>
<td><?php echo $p['id']; ?></td>
<td>
    <input type="checkbox" form="bulk-sync-form" name="selected_ids[]" value="<?php echo $p['id']; ?>">
</td>
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
<td><?php echo isset($p['sync_status']) ? htmlspecialchars($p['sync_status']) : 'pending'; ?></td>
<td><?php echo !empty($p['last_error']) ? htmlspecialchars($p['last_error']) : '—'; ?></td>
<td><?php echo !empty($p['last_attempt_at']) ? htmlspecialchars($p['last_attempt_at']) : '—'; ?></td>
<td>
    <form method="POST" style="display:inline;">
        <input type="hidden" name="action" value="sync_one">
        <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
        <button type="submit">↻</button>
    </form>
</td>
<td><a href="?edit_id=<?php echo $p['id']; ?>">✏️</a></td>
<td><a href="?delete_id=<?php echo $p['id']; ?>">❌</a></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</main>

<?php require 'includes/footer.php'; ?>