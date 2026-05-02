<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(45);

require __DIR__ . '/../../db.php';
require __DIR__ . '/send.php';
require_once __DIR__ . '/../../functions/app_settings.php';
require_once __DIR__ . '/../../functions/integration_sync_control.php';

$db = getDB();
integrationAbortJsonIfAllSyncPaused($db);
$cfg = require __DIR__ . '/config.php';

require_once __DIR__ . '/../../functions/b24_sync_conflicts.php';
ensureB24SyncConflictsSchema($db);

$chunk = isset($_GET['chunk']) ? max(5, min(100, intval($_GET['chunk']))) : 30;
$runStock = !isset($_GET['stock']) || $_GET['stock'] === '1';
$runPricePush = !isset($_GET['price_push']) || $_GET['price_push'] === '1';
$runPricePullCheck = !isset($_GET['price_pull_check']) || $_GET['price_pull_check'] === '1';
$storeId = isset($_GET['store_id']) ? intval($_GET['store_id']) : intval(getAppSetting($db, 'stock_sync_store_id', '0'));
if ($storeId <= 0) {
    $storeId = intval(getAppSetting($db, 'default_store_from_id', '1'));
}
if ($storeId <= 0) {
    $storeId = 1;
}

$stockOffset = intval(getAppSetting($db, 'sync_cycle_stock_offset', '0'));
$pricePushOffset = intval(getAppSetting($db, 'sync_cycle_price_push_offset', '0'));
$pricePullStart = intval(getAppSetting($db, 'sync_cycle_price_pull_start', '0'));

$result = array(
    'ok' => true,
    'chunk' => $chunk,
    'store_id' => $storeId,
    'stock' => null,
    'price_push' => null,
    'price_pull_check' => null
);

if ($runStock) {
    $totalRow = $db->query("
        SELECT COUNT(*) as cnt
        FROM products
        WHERE b24_product_id IS NOT NULL AND b24_product_id > 0
    ")->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow ? intval($totalRow['cnt']) : 0;
    $rows = $db->query("
        SELECT
            p.id,
            p.b24_product_id,
            COALESCE(SUM(CASE WHEN r.reserved = 0 AND r.current_length > 0 AND r.status NOT IN ('sold','waste','written_off') THEN r.current_length ELSE 0 END), 0) as free_meters
        FROM products p
        LEFT JOIN rolls r ON r.product_id = p.id
        WHERE p.b24_product_id IS NOT NULL AND p.b24_product_id > 0
        GROUP BY p.id, p.b24_product_id
        ORDER BY p.id ASC
        LIMIT " . intval($chunk) . " OFFSET " . intval($stockOffset)
    )->fetchAll(PDO::FETCH_ASSOC);
    $processed = 0;
    $errors = 0;
    foreach ($rows as $row) {
        $processed++;
        $b24Id = intval($row['b24_product_id']);
        $free = round(floatval($row['free_meters']), 2);
        $spList = sendToBitrix('catalog.storeproduct.list', array(
            'filter' => array('productId' => $b24Id, 'storeId' => $storeId),
            'select' => array('id', 'amount')
        ));
        $existingId = 0;
        if (is_array($spList) && !isset($spList['error']) && isset($spList['result'])) {
            $r = $spList['result'];
            if (isset($r['items']) && is_array($r['items'])) {
                $r = $r['items'];
            }
            if (!empty($r[0]['id'])) {
                $existingId = intval($r[0]['id']);
            }
        }
        if ($existingId > 0) {
            $resp = sendToBitrix('catalog.storeproduct.update', array('id' => $existingId, 'fields' => array('amount' => $free)));
        } else {
            $resp = sendToBitrix('catalog.storeproduct.add', array('fields' => array('productId' => $b24Id, 'storeId' => $storeId, 'amount' => $free)));
        }
        if (!is_array($resp) || isset($resp['error'])) {
            $errors++;
        }
    }
    $next = $stockOffset + $processed;
    if ($next >= $total) {
        $next = 0;
    }
    setAppSetting($db, 'sync_cycle_stock_offset', (string)$next);
    $result['stock'] = array('processed' => $processed, 'errors' => $errors, 'offset_before' => $stockOffset, 'offset_after' => $next, 'total' => $total);
}

if ($runPricePush) {
    $totalRow = $db->query("
        SELECT COUNT(*) as cnt
        FROM products
        WHERE b24_product_id IS NOT NULL AND b24_product_id > 0 AND price_per_meter > 0
    ")->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow ? intval($totalRow['cnt']) : 0;
    $rows = $db->query("
        SELECT id, b24_product_id, price_per_meter
        FROM products
        WHERE b24_product_id IS NOT NULL AND b24_product_id > 0 AND price_per_meter > 0
        ORDER BY id ASC
        LIMIT " . intval($chunk) . " OFFSET " . intval($pricePushOffset)
    )->fetchAll(PDO::FETCH_ASSOC);
    $processed = 0;
    $errors = 0;
    foreach ($rows as $row) {
        $processed++;
        $price = round(floatval($row['price_per_meter']), 2);
        $resp = sendToBitrix('crm.product.update', array(
            'id' => intval($row['b24_product_id']),
            'fields' => array('PRICE' => $price, 'CURRENCY_ID' => 'KGS')
        ));
        if (!is_array($resp) || isset($resp['error'])) {
            $resp = sendToBitrix('catalog.product.update', array(
                'id' => intval($row['b24_product_id']),
                'fields' => array('price' => $price, 'currencyId' => 'KGS')
            ));
        }
        if (!is_array($resp) || isset($resp['error'])) {
            $errors++;
        }
    }
    $next = $pricePushOffset + $processed;
    if ($next >= $total) {
        $next = 0;
    }
    setAppSetting($db, 'sync_cycle_price_push_offset', (string)$next);
    $result['price_push'] = array('processed' => $processed, 'errors' => $errors, 'offset_before' => $pricePushOffset, 'offset_after' => $next, 'total' => $total);
}

if ($runPricePullCheck) {
    $payload = array('start' => $pricePullStart);
    if (isset($cfg['sync_catalog_ids']) && is_array($cfg['sync_catalog_ids']) && !empty($cfg['sync_catalog_ids'])) {
        $payload['filter'] = array('@CATALOG_ID' => array_values(array_map('intval', $cfg['sync_catalog_ids'])));
    }
    $resp = sendToBitrix(isset($cfg['product_list_method']) ? $cfg['product_list_method'] : 'crm.product.list', $payload);
    $checked = 0;
    $conflicts = 0;
    $next = 0;
    if (is_array($resp) && !isset($resp['error']) && isset($resp['result']) && is_array($resp['result'])) {
        foreach ($resp['result'] as $item) {
            $b24Id = intval(isset($item['ID']) ? $item['ID'] : 0);
            if ($b24Id <= 0) {
                continue;
            }
            $checked++;
            $stmt = $db->prepare("
                SELECT
                    p.id,
                    p.price_per_meter,
                    COALESCE(SUM(CASE WHEN r.reserved = 0 AND r.current_length > 0 AND r.status NOT IN ('sold','waste','written_off') THEN r.current_length ELSE 0 END), 0) as free_meters
                FROM products p
                LEFT JOIN rolls r ON r.product_id = p.id
                WHERE p.b24_product_id = ?
                GROUP BY p.id, p.price_per_meter
                LIMIT 1
            ");
            $stmt->execute(array($b24Id));
            $local = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$local) {
                continue;
            }
            $b24Price = floatval(isset($item['PRICE']) ? $item['PRICE'] : 0);
            $localPrice = floatval(isset($local['price_per_meter']) ? $local['price_per_meter'] : 0);
            if (abs($localPrice - $b24Price) > 0.01) {
                $conflicts++;
                b24UpsertSyncConflict($db, 'price_mismatch', $b24Id, intval($local['id']), $localPrice, $b24Price, 'Разница цены между приложением и Б24');
            }
            $fieldName = isset($cfg['product_available_field']) ? $cfg['product_available_field'] : 'UF_CRM_STOCK_M';
            $b24Stock = floatval(isset($item[$fieldName]) ? $item[$fieldName] : 0);
            $localStock = floatval(isset($local['free_meters']) ? $local['free_meters'] : 0);
            if (abs($localStock - $b24Stock) > 0.01) {
                $conflicts++;
                b24UpsertSyncConflict($db, 'stock_field_mismatch', $b24Id, intval($local['id']), $localStock, $b24Stock, 'Разница остатка между приложением и Б24 (поле товара)');
            }
        }
        $next = isset($resp['next']) ? intval($resp['next']) : 0;
    }
    setAppSetting($db, 'sync_cycle_price_pull_start', (string)$next);
    $result['price_pull_check'] = array(
        'checked' => $checked,
        'conflicts' => $conflicts,
        'start_before' => $pricePullStart,
        'start_after' => $next
    );
}

setAppSetting($db, 'sync_cycle_last_run_json', json_encode($result, JSON_UNESCAPED_UNICODE));
echo json_encode($result, JSON_UNESCAPED_UNICODE);

