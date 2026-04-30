<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require __DIR__ . '/send.php';
require_once __DIR__ . '/../../functions/stock_movements.php';

$db = getDB();

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 1000;
if ($limit <= 0) {
    $limit = 1000;
}
if ($limit > 5000) {
    $limit = 5000;
}

$onlyWithoutB24 = true;
if (isset($_GET['all']) && (string)$_GET['all'] === '1') {
    $onlyWithoutB24 = false;
}

$sql = "
    SELECT id, name, b24_product_id
    FROM products
";
if ($onlyWithoutB24) {
    $sql .= " WHERE b24_product_id IS NULL OR b24_product_id = 0";
}
$sql .= " ORDER BY id ASC LIMIT " . intval($limit);

$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$processed = 0;
$created = 0;
$alreadyLinked = 0;
$errors = array();
$resultRows = array();

foreach ($rows as $row) {
    $processed++;
    $productId = intval(isset($row['id']) ? $row['id'] : 0);
    $name = isset($row['name']) ? (string)$row['name'] : '';
    $oldB24Id = intval(isset($row['b24_product_id']) ? $row['b24_product_id'] : 0);

    if ($productId <= 0 || trim($name) === '') {
        $errors[] = array(
            'product_id' => $productId,
            'name' => $name,
            'error' => 'Некорректная строка товара'
        );
        continue;
    }

    if ($oldB24Id > 0) {
        $alreadyLinked++;
        $resultRows[] = array(
            'product_id' => $productId,
            'name' => $name,
            'b24_product_id' => $oldB24Id,
            'status' => 'already_linked'
        );
        continue;
    }

    $newB24Id = ensureProductSyncedWithBitrix($db, $productId);
    if ($newB24Id > 0) {
        $created++;
        $resultRows[] = array(
            'product_id' => $productId,
            'name' => $name,
            'b24_product_id' => $newB24Id,
            'status' => 'created_in_b24'
        );
        continue;
    }

    $errors[] = array(
        'product_id' => $productId,
        'name' => $name,
        'error' => 'Не удалось создать/привязать товар в Б24'
    );
}

// Optional: push actual prices for all linked products after backfill.
$priceSync = null;
if (isset($_GET['sync_prices']) && (string)$_GET['sync_prices'] === '1') {
    $priceUpdated = 0;
    $priceErrors = 0;
    $priceRows = $db->query("
        SELECT id, name, b24_product_id, price_per_meter
        FROM products
        WHERE b24_product_id IS NOT NULL
          AND b24_product_id > 0
          AND price_per_meter > 0
        ORDER BY id ASC
        LIMIT " . intval($limit))->fetchAll(PDO::FETCH_ASSOC);

    foreach ($priceRows as $pr) {
        $resp = sendToBitrix('crm.product.update', array(
            'id' => intval($pr['b24_product_id']),
            'fields' => array(
                'PRICE' => floatval($pr['price_per_meter'])
            )
        ));
        if (is_array($resp) && !isset($resp['error'])) {
            $priceUpdated++;
        } else {
            $priceErrors++;
        }
    }

    $priceSync = array(
        'processed' => count($priceRows),
        'updated' => $priceUpdated,
        'errors' => $priceErrors
    );
}

echo json_encode(array(
    'ok' => true,
    'processed' => $processed,
    'created_in_b24' => $created,
    'already_linked' => $alreadyLinked,
    'errors_count' => count($errors),
    'errors' => $errors,
    'result_rows' => $resultRows,
    'price_sync' => $priceSync
), JSON_UNESCAPED_UNICODE);

