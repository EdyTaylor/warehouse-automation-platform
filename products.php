<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/api/bitrix/send.php';
require_once __DIR__ . '/functions/stock_movements.php';
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

function buildProductsUrl($overrides) {
    $query = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    $qs = http_build_query($query);
    return 'products.php' . ($qs ? ('?' . $qs) : '');
}

ensureColumnExists($db, 'products', 'delivery_price', '`delivery_price` decimal(14,2) NOT NULL DEFAULT 0');
$formErrors = array();
$formWarnings = array();
$tierAutofillSuggestions = array();
$previewRows = array();
ensureColumnExists($db, 'products', 'sync_status', "`sync_status` varchar(20) NOT NULL DEFAULT 'pending'");
ensureColumnExists($db, 'products', 'last_error', '`last_error` text NULL');
ensureColumnExists($db, 'products', 'last_attempt_at', '`last_attempt_at` datetime NULL');
ensureColumnExists($db, 'products', 'last_b24_target_ids', '`last_b24_target_ids` text NULL');
ensureColumnExists($db, 'products', 'last_b24_parent_type', '`last_b24_parent_type` int NULL');
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

function updateProductB24SyncAudit($db, $productId, $targetIds, $parentType) {
    $targets = array_values(array_unique(array_filter(array_map('intval', (array)$targetIds), function($v) {
        return $v > 0;
    })));
    $targetCsv = empty($targets) ? null : implode(',', $targets);
    $stmt = $db->prepare("
        UPDATE products
        SET last_b24_target_ids = ?, last_b24_parent_type = ?
        WHERE id = ?
    ");
    $stmt->execute(array($targetCsv, intval($parentType), intval($productId)));
}

function markProductSyncPending($db, $productId) {
    $stmt = $db->prepare("
        UPDATE products
        SET sync_status = 'pending', last_error = NULL
        WHERE id = ?
    ");
    $stmt->execute(array(intval($productId)));
}

define('B24_PRICE_SYNC_REPORT_MAX_ROWS', 3000);

function truncateB24SyncDetail($text, $maxChars) {
    $text = (string)$text;
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $maxChars) {
            return mb_substr($text, 0, $maxChars, 'UTF-8') . '…';
        }
        return $text;
    }
    if (strlen($text) > $maxChars) {
        return substr($text, 0, $maxChars) . '…';
    }
    return $text;
}

/**
 * @return array{id:int,name:string,b24_id:int,detail:string}
 */
function makeB24PriceSyncListRow($productId, $name, $b24ProductId, $detail) {
    return array(
        'id' => intval($productId),
        'name' => trim((string)$name),
        'b24_id' => intval($b24ProductId),
        'detail' => truncateB24SyncDetail($detail, 450)
    );
}

function resetB24PriceSyncSessionReport() {
    $_SESSION['b24_price_sync_report'] = array(
        'updated' => array(),
        'skipped' => array(),
        'errors' => array()
    );
}

function appendB24PriceSyncSessionReport($bucket, $row) {
    if (!isset($_SESSION['b24_price_sync_report']) || !is_array($_SESSION['b24_price_sync_report'])) {
        resetB24PriceSyncSessionReport();
    }
    $key = null;
    if ($bucket === 'updated') {
        $key = 'updated';
    } elseif ($bucket === 'skipped') {
        $key = 'skipped';
    } else {
        $key = 'errors';
    }
    if (count($_SESSION['b24_price_sync_report'][$key]) >= B24_PRICE_SYNC_REPORT_MAX_ROWS) {
        return;
    }
    $_SESSION['b24_price_sync_report'][$key][] = $row;
}

function runB24SyncForProductIds($db, $productIds) {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)$productIds), function($v) {
        return $v > 0;
    })));

    if (empty($ids)) {
        return array('ok' => 0, 'err' => 0, 'skip' => 0, 'total' => 0);
    }

    resetB24PriceSyncSessionReport();

    $batchSize = getB24SyncBatchSize($db);
    $delayMs = getB24SyncDelayMs($db);
    $ok = 0;
    $err = 0;
    $skip = 0;
    $chunks = array_chunk($ids, $batchSize);
    foreach ($chunks as $chunkIndex => $chunk) {
        foreach ($chunk as $productId) {
            $res = syncProductPriceToB24($db, $productId);
            if (!empty($res['sync_report_bucket']) && isset($res['sync_report_row'])) {
                appendB24PriceSyncSessionReport($res['sync_report_bucket'], $res['sync_report_row']);
            }
            if (!empty($res['skipped'])) {
                $skip++;
            } elseif ($res['ok']) {
                $ok++;
            } else {
                $err++;
            }
        }
        if ($delayMs > 0 && $chunkIndex < count($chunks) - 1) {
            usleep($delayMs * 1000);
        }
    }

    return array('ok' => $ok, 'err' => $err, 'skip' => $skip, 'total' => count($ids));
}

function processPriceSyncChunk($db, $offset, $limit) {
    $offset = max(0, intval($offset));
    $limit = intval($limit);
    if ($limit <= 0) {
        $limit = 20;
    }
    if ($limit > 200) {
        $limit = 200;
    }

    $totalRow = $db->query("
        SELECT COUNT(*) AS cnt
        FROM products
        WHERE b24_product_id IS NOT NULL
          AND b24_product_id <> 0
    ")->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow ? intval($totalRow['cnt']) : 0;

    if ($offset >= $total) {
        return array(
            'total' => $total,
            'processed' => 0,
            'ok' => 0,
            'err' => 0,
            'skip' => 0,
            'next_offset' => $offset,
            'done' => true
        );
    }

    $rows = $db->query("
        SELECT id
        FROM products
        WHERE b24_product_id IS NOT NULL
          AND b24_product_id <> 0
        ORDER BY id ASC
        LIMIT " . intval($limit) . " OFFSET " . intval($offset))->fetchAll(PDO::FETCH_ASSOC);

    $ok = 0;
    $err = 0;
    $skip = 0;
    $errors = array();
    foreach ($rows as $row) {
        $productId = intval(isset($row['id']) ? $row['id'] : 0);
        if ($productId <= 0) {
            $err++;
            if (count($errors) < 5) {
                $errors[] = array('product_id' => 0, 'message' => 'Некорректный product_id');
            }
            appendB24PriceSyncSessionReport('errors', makeB24PriceSyncListRow(0, '', 0, 'Некорректный product_id в партии'));
            continue;
        }
        $res = syncProductPriceToB24($db, $productId);
        if (!empty($res['sync_report_bucket']) && isset($res['sync_report_row'])) {
            appendB24PriceSyncSessionReport($res['sync_report_bucket'], $res['sync_report_row']);
        }
        if (!empty($res['skipped'])) {
            $skip++;
        } elseif (isset($res['ok']) && $res['ok']) {
            $ok++;
        } else {
            $err++;
            if (count($errors) < 5) {
                $errors[] = array(
                    'product_id' => $productId,
                    'message' => isset($res['message']) ? (string)$res['message'] : 'Неизвестная ошибка'
                );
            }
        }
    }

    $processed = count($rows);
    $nextOffset = $offset + $processed;
    return array(
        'total' => $total,
        'processed' => $processed,
        'ok' => $ok,
        'err' => $err,
        'skip' => $skip,
        'errors' => $errors,
        'next_offset' => $nextOffset,
        'done' => ($nextOffset >= $total || $processed === 0)
    );
}

function fetchB24ProductSnapshot($b24ProductId) {
    $b24ProductId = intval($b24ProductId);
    if ($b24ProductId <= 0) {
        return array('ok' => false, 'error' => 'invalid_b24_product_id');
    }

    $crmResp = sendToBitrix('crm.product.get', array('id' => $b24ProductId));
    $catalogResp = sendToBitrix('catalog.product.get', array('id' => $b24ProductId));

    $crmRow = null;
    if (is_array($crmResp) && !isset($crmResp['error']) && isset($crmResp['result']) && is_array($crmResp['result'])) {
        $crmRow = $crmResp['result'];
    }
    $catalogRow = b24CatalogProductRowFromGetResponse($catalogResp);

    $price = null;
    $purchasePrice = null;
    if ($crmRow !== null && isset($crmRow['PRICE']) && $crmRow['PRICE'] !== '') {
        $price = floatval($crmRow['PRICE']);
    } elseif ($catalogRow !== null && isset($catalogRow['price']) && $catalogRow['price'] !== '') {
        $price = floatval($catalogRow['price']);
    }
    if ($crmRow !== null && isset($crmRow['PURCHASING_PRICE']) && $crmRow['PURCHASING_PRICE'] !== '') {
        $purchasePrice = floatval($crmRow['PURCHASING_PRICE']);
    } elseif ($catalogRow !== null && isset($catalogRow['purchasingPrice']) && $catalogRow['purchasingPrice'] !== '') {
        $purchasePrice = floatval($catalogRow['purchasingPrice']);
    }

    return array(
        'ok' => ($crmRow !== null || $catalogRow !== null),
        'price' => $price,
        'purchase_price' => $purchasePrice,
        'crm_raw' => $crmResp,
        'catalog_raw' => $catalogResp
    );
}

function b24CatalogPriceRowId($row) {
    return intval(isset($row['id']) ? $row['id'] : (isset($row['ID']) ? $row['ID'] : 0));
}

function b24CatalogPriceRowGroup($row) {
    return intval(isset($row['catalogGroupId']) ? $row['catalogGroupId'] : (isset($row['CATALOG_GROUP_ID']) ? $row['CATALOG_GROUP_ID'] : 0));
}

function b24CatalogPriceRowQtyFrom($row) {
    if (isset($row['quantityFrom'])) {
        return intval($row['quantityFrom']);
    }
    if (isset($row['QUANTITY_FROM'])) {
        return intval($row['QUANTITY_FROM']);
    }

    return 1;
}

function b24CatalogOfferRowsFromListResponse($resp) {
    if (!is_array($resp) || isset($resp['error']) || !isset($resp['result'])) {
        return array();
    }
    $r = $resp['result'];
    if (isset($r['offers']) && is_array($r['offers'])) {
        return array_values($r['offers']);
    }
    if (isset($r['offer']) && is_array($r['offer'])) {
        return array($r['offer']);
    }

    return array();
}

function b24CatalogProductRowFromGetResponse($resp) {
    if (!is_array($resp) || isset($resp['error']) || !isset($resp['result']) || !is_array($resp['result'])) {
        return null;
    }
    if (isset($resp['result']['product']) && is_array($resp['result']['product'])) {
        return $resp['result']['product'];
    }
    if (isset($resp['result']['PRODUCT']) && is_array($resp['result']['PRODUCT'])) {
        return $resp['result']['PRODUCT'];
    }
    if (isset($resp['result']['id']) || isset($resp['result']['ID'])) {
        return $resp['result'];
    }

    return null;
}

function b24CatalogPriceRowsFromListResponse($resp) {
    if (!is_array($resp) || isset($resp['error']) || !isset($resp['result'])) {
        return array();
    }
    if (isset($resp['result']['prices']) && is_array($resp['result']['prices'])) {
        return array_values($resp['result']['prices']);
    }
    if (isset($resp['result']['price']) && is_array($resp['result']['price'])) {
        return array($resp['result']['price']);
    }
    if (is_array($resp['result']) && isset($resp['result'][0]) && is_array($resp['result'][0])) {
        return array_values($resp['result']);
    }

    return array();
}

/**
 * Для parent-товара возвращает iblock вариаций (offers), например 19 -> 21.
 * Кэшируем на время запроса, чтобы не дергать REST каждый товар.
 */
function b24ResolveOfferIblockIdByParentIblock($parentIblockId) {
    $parentIblockId = intval($parentIblockId);
    if ($parentIblockId <= 0) {
        return 0;
    }
    static $map = null;
    if ($map === null) {
        $map = array();
        $resp = sendToBitrix('catalog.catalog.list', array(
            'select' => array('id', 'iblockId', 'productIblockId')
        ));
        if (is_array($resp) && !isset($resp['error']) && isset($resp['result']['catalogs']) && is_array($resp['result']['catalogs'])) {
            foreach ($resp['result']['catalogs'] as $cat) {
                if (!is_array($cat)) {
                    continue;
                }
                $productIblockId = intval(isset($cat['productIblockId']) ? $cat['productIblockId'] : 0);
                $offerIblockId = intval(isset($cat['iblockId']) ? $cat['iblockId'] : 0);
                if ($productIblockId > 0 && $offerIblockId > 0) {
                    $map[$productIblockId] = $offerIblockId;
                }
            }
        }
    }

    return isset($map[$parentIblockId]) ? intval($map[$parentIblockId]) : 0;
}

function b24CatalogIdByIblockId($iblockId) {
    $iblockId = intval($iblockId);
    if ($iblockId <= 0) {
        return 0;
    }
    static $map = null;
    if ($map === null) {
        $map = array();
        $resp = sendToBitrix('catalog.catalog.list', array(
            'select' => array('id', 'iblockId')
        ));
        if (is_array($resp) && !isset($resp['error']) && isset($resp['result']['catalogs']) && is_array($resp['result']['catalogs'])) {
            foreach ($resp['result']['catalogs'] as $cat) {
                if (!is_array($cat)) {
                    continue;
                }
                $cid = intval(isset($cat['id']) ? $cat['id'] : 0);
                $iid = intval(isset($cat['iblockId']) ? $cat['iblockId'] : 0);
                if ($cid > 0 && $iid > 0) {
                    $map[$iid] = $cid;
                }
            }
        }
    }

    return isset($map[$iblockId]) ? intval($map[$iblockId]) : 0;
}

/**
 * Возвращает true/false если каталог offers/не offers, null если не удалось проверить.
 */
function b24CatalogIsOffersByIblockId($iblockId) {
    $catalogId = b24CatalogIdByIblockId($iblockId);
    if ($catalogId <= 0) {
        return null;
    }
    static $cache = array();
    if (array_key_exists($catalogId, $cache)) {
        return $cache[$catalogId];
    }
    $resp = sendToBitrix('catalog.catalog.isOffers', array('id' => $catalogId));
    if (!is_array($resp) || isset($resp['error'])) {
        $cache[$catalogId] = null;
        return null;
    }
    $result = false;
    if (isset($resp['result']['isOffers'])) {
        $v = $resp['result']['isOffers'];
        $result = ($v === true || $v === 'Y' || intval($v) === 1);
    } elseif (isset($resp['result'])) {
        $v = $resp['result'];
        $result = ($v === true || $v === 'Y' || intval($v) === 1);
    }
    $cache[$catalogId] = $result;
    return $result;
}

/**
 * У товара «с вариациями» (таблица Метр/рулон в карточке Б24) цена хранится на торговом предложении (offer),
 * а не на родителе. Возвращаем ID офферов каталога; если офферов нет — одиночный простой товар = сам id.
 *
 * @return int[]
 */
function b24ResolveRetailPriceCatalogTargetIdsMeta($catalogProductId) {
    $catalogProductId = intval($catalogProductId);
    if ($catalogProductId <= 0) {
        return array(
            'targets' => array(),
            'parent_type' => 0,
            'parent_iblock_id' => 0,
            'offer_iblock_id' => 0,
            'offer_catalog_is_offers' => null,
            'expected_offers' => false
        );
    }
    $fallback = array($catalogProductId);

    $iblockId = 0;
    $parentType = 0;
    $catGet = sendToBitrix('catalog.product.get', array('id' => $catalogProductId));
    $catRow = b24CatalogProductRowFromGetResponse($catGet);
    if ($catRow !== null) {
        $iblockId = intval(isset($catRow['iblockId']) ? $catRow['iblockId'] : (isset($catRow['IBLOCK_ID']) ? $catRow['IBLOCK_ID'] : 0));
        $parentType = intval(isset($catRow['type']) ? $catRow['type'] : (isset($catRow['TYPE']) ? $catRow['TYPE'] : 0));
    }

    $offerIblockId = 0;
    $offerCatalogIsOffers = null;
    $filterAttempts = array();
    if ($iblockId > 0) {
        $offerIblockId = b24ResolveOfferIblockIdByParentIblock($iblockId);
        if ($offerIblockId > 0) {
            $offerCatalogIsOffers = b24CatalogIsOffersByIblockId($offerIblockId);
            $filterAttempts[] = array('parentId' => $catalogProductId, 'iblockId' => $offerIblockId);
        }
    }
    // На порталах Bitrix iblockId часто обязателен; но оставляем запасной вариант.
    $filterAttempts[] = array('parentId' => $catalogProductId);

    $offerRows = array();
    foreach ($filterAttempts as $filter) {
        $offerResp = sendToBitrix('catalog.product.offer.list', array(
            // Берем только гарантированно доступные поля оффера (без спорных).
            'select' => array('id', 'iblockId', 'name', 'parentId'),
            'filter' => $filter
        ));
        $offerRows = b24CatalogOfferRowsFromListResponse($offerResp);
        if (!empty($offerRows)) {
            break;
        }
    }

    $ids = array();
    foreach ($offerRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $oid = intval(isset($row['id']) ? $row['id'] : 0);
        if ($oid > 0) {
            $ids[] = $oid;
        }
    }
    $ids = array_values(array_unique($ids));

    if (!empty($ids)) {
        return array(
            'targets' => $ids,
            'parent_type' => $parentType,
            'parent_iblock_id' => $iblockId,
            'offer_iblock_id' => $offerIblockId,
            'offer_catalog_is_offers' => $offerCatalogIsOffers,
            'expected_offers' => ($parentType === 3)
        );
    }

    return array(
        'targets' => $fallback,
        'parent_type' => $parentType,
        'parent_iblock_id' => $iblockId,
        'offer_iblock_id' => $offerIblockId,
        'offer_catalog_is_offers' => $offerCatalogIsOffers,
        'expected_offers' => ($parentType === 3)
    );
}

function b24ResolveRetailPriceCatalogTargetIds($catalogProductId) {
    $meta = b24ResolveRetailPriceCatalogTargetIdsMeta($catalogProductId);
    return isset($meta['targets']) && is_array($meta['targets']) ? $meta['targets'] : array();
}

function b24RetailPriceMatchesOnAllTargets(array $targets, $retailPrice) {
    $retailPrice = floatval($retailPrice);
    foreach ($targets as $tid) {
        $tid = intval($tid);
        if ($tid <= 0) {
            return false;
        }
        $snap = fetchB24RetailPrice($tid);
        $okTier = isset($snap['ok']) && $snap['ok']
            && $snap['price'] !== null
            && abs(floatval($snap['price']) - $retailPrice) < 0.02;
        if (!$okTier) {
            return false;
        }
    }

    return true;
}

function b24CrmRetailPriceMatchesOnAnyTarget(array $targets, $retailPrice) {
    $retailPrice = floatval($retailPrice);
    foreach ($targets as $tid) {
        $tid = intval($tid);
        if ($tid <= 0) {
            continue;
        }
        $sp = fetchB24ProductSnapshot($tid);
        if ($sp['ok'] && $sp['price'] !== null && abs(floatval($sp['price']) - $retailPrice) < 0.02) {
            return true;
        }
    }

    return false;
}

/**
 * Одна строка типовой цены (без «лестницы»): предпочтение группе BASE (1), затем минимальный quantityFrom.
 */
function pickRetailCatalogPriceRow($rows) {
    if (empty($rows) || !is_array($rows)) {
        return null;
    }

    $rows = array_values($rows);
    $base = array();
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (b24CatalogPriceRowGroup($row) === 1) {
            $base[] = $row;
        }
    }

    $candidates = !empty($base) ? $base : $rows;
    usort($candidates, function ($a, $b) {
        return b24CatalogPriceRowQtyFrom($a) - b24CatalogPriceRowQtyFrom($b);
    });

    foreach ($candidates as $row) {
        if (b24CatalogPriceRowId($row) > 0) {
            return $row;
        }
    }

    return null;
}

function upsertB24RetailPrice($b24ProductId, $retailPrice, $currencyId) {
    $b24ProductId = intval($b24ProductId);
    $retailPrice = round(floatval($retailPrice), 4);
    $currencyId = strtoupper(trim((string)$currencyId));
    if ($b24ProductId <= 0 || $retailPrice <= 0 || $currencyId === '') {
        return array('ok' => false, 'error' => 'invalid_args');
    }

    $listResp = sendToBitrix('catalog.price.list', array(
        'filter' => array('productId' => $b24ProductId)
    ));

    $rows = b24CatalogPriceRowsFromListResponse($listResp);

    $pickedRow = pickRetailCatalogPriceRow($rows);
    $priceId = $pickedRow ? b24CatalogPriceRowId($pickedRow) : 0;
    $groupId = 1;
    if ($pickedRow) {
        $g = b24CatalogPriceRowGroup($pickedRow);
        if ($g > 0) {
            $groupId = $g;
        }
    }

    if ($priceId > 0) {
        $resp = sendToBitrix('catalog.price.update', array(
            'id' => $priceId,
            'fields' => array(
                'price' => $retailPrice,
                'currency' => $currencyId
            )
        ));
        $ok = is_array($resp) && !isset($resp['error']);
        return array('ok' => $ok, 'response' => $resp);
    }

    $resp = sendToBitrix('catalog.price.add', array(
        'fields' => array(
            'productId' => $b24ProductId,
            'catalogGroupId' => $groupId,
            'price' => $retailPrice,
            'currency' => $currencyId
        )
    ));
    $ok = is_array($resp) && !isset($resp['error']);
    return array('ok' => $ok, 'response' => $resp);
}

function fetchB24RetailPrice($b24ProductId) {
    $b24ProductId = intval($b24ProductId);
    if ($b24ProductId <= 0) {
        return array('ok' => false, 'price' => null, 'error' => 'invalid_b24_product_id');
    }

    $resp = sendToBitrix('catalog.price.list', array(
        'filter' => array('productId' => $b24ProductId)
    ));
    if (!is_array($resp) || isset($resp['error'])) {
        return array('ok' => false, 'price' => null, 'raw' => $resp);
    }

    $rows = b24CatalogPriceRowsFromListResponse($resp);
    if (empty($rows)) {
        return array('ok' => true, 'price' => null, 'raw' => $resp);
    }

    $selected = null;
    foreach ($rows as $row) {
        $groupId = intval(isset($row['catalogGroupId']) ? $row['catalogGroupId'] : (isset($row['CATALOG_GROUP_ID']) ? $row['CATALOG_GROUP_ID'] : 0));
        if ($groupId === 1) {
            $selected = $row;
            break;
        }
    }
    if ($selected === null) {
        $firstRow = reset($rows);
        if (is_array($firstRow)) {
            $selected = $firstRow;
        } else {
            return array('ok' => false, 'price' => null, 'raw' => $resp);
        }
    }

    $price = null;
    if (isset($selected['price']) && $selected['price'] !== '') {
        $price = floatval($selected['price']);
    } elseif (isset($selected['PRICE']) && $selected['PRICE'] !== '') {
        $price = floatval($selected['PRICE']);
    }

    return array('ok' => true, 'price' => $price, 'raw' => $resp);
}

function runCreateMissingProductsInB24($db, $limit) {
    $limit = intval($limit);
    if ($limit <= 0) {
        $limit = 200;
    }
    if ($limit > 5000) {
        $limit = 5000;
    }

    $rows = $db->query("
        SELECT id
        FROM products
        WHERE b24_product_id IS NULL OR b24_product_id = 0
        ORDER BY id ASC
        LIMIT " . $limit)->fetchAll(PDO::FETCH_ASSOC);

    $created = 0;
    $alreadyLinked = 0;
    $errors = 0;

    foreach ($rows as $row) {
        $productId = intval(isset($row['id']) ? $row['id'] : 0);
        if ($productId <= 0) {
            $errors++;
            continue;
        }
        $newB24Id = ensureProductSyncedWithBitrix($db, $productId);
        if ($newB24Id > 0) {
            $created++;
        } else {
            $errors++;
        }
    }

    return array(
        'total' => count($rows),
        'created' => $created,
        'already_linked' => $alreadyLinked,
        'errors' => $errors
    );
}

function syncProductPriceToB24($db, $productId) {
    $pid = intval($productId);
    $stmt = $db->prepare("
        SELECT id, name, b24_product_id, price_per_meter
        FROM products
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute(array($pid));
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $pName = '';
    if (is_array($product) && isset($product['name'])) {
        $pName = (string)$product['name'];
    }
    if (!$product) {
        return array(
            'ok' => false,
            'message' => 'Товар не найден',
            'sync_report_bucket' => 'errors',
            'sync_report_row' => makeB24PriceSyncListRow($pid, '', 0, 'Товар не найден в базе приложения')
        );
    }
    $attemptAt = date('Y-m-d H:i:s');
    if (empty($product['b24_product_id'])) {
        updateProductSyncState($db, $productId, 'error', 'Нет b24_product_id', $attemptAt);
        return array(
            'ok' => false,
            'message' => 'Нет b24_product_id',
            'sync_report_bucket' => 'errors',
            'sync_report_row' => makeB24PriceSyncListRow($pid, $pName, 0, 'Нет b24_product_id — сначала создайте товар в Б24')
        );
    }

    $b24Id = intval($product['b24_product_id']);
    $retailPrice = round(floatval(isset($product['price_per_meter']) ? $product['price_per_meter'] : 0), 4);
    if ($retailPrice <= 0) {
        return array(
            'ok' => false,
            'skipped' => true,
            'message' => 'Пропущено: не задана цена за метр',
            'sync_report_bucket' => 'skipped',
            'sync_report_row' => makeB24PriceSyncListRow($pid, $pName, $b24Id, 'В приложении цена за метр не задана или равна 0')
        );
    }

    $currencyId = strtoupper(trim((string)getAppSetting($db, 'default_currency', 'KGS')));
    if ($currencyId === '') {
        $currencyId = 'KGS';
    }

    // В Б24 передаём только розничную цену за метр (без закупки и без catalog.product.update — иначе «single price» / диапазоны).
    // Товары с вариациями: цены в интерфейсе идут на торговые предложения (offer), см. catalog.product.offer.list по parentId.
    $targetsMeta = b24ResolveRetailPriceCatalogTargetIdsMeta($b24Id);
    $targets = isset($targetsMeta['targets']) && is_array($targetsMeta['targets']) ? $targetsMeta['targets'] : array();
    $parentType = intval(isset($targetsMeta['parent_type']) ? $targetsMeta['parent_type'] : 0);
    $parentIblockId = intval(isset($targetsMeta['parent_iblock_id']) ? $targetsMeta['parent_iblock_id'] : 0);
    $offerIblockId = intval(isset($targetsMeta['offer_iblock_id']) ? $targetsMeta['offer_iblock_id'] : 0);
    $offerCatalogIsOffers = isset($targetsMeta['offer_catalog_is_offers']) ? $targetsMeta['offer_catalog_is_offers'] : null;
    $expectedOffers = !empty($targetsMeta['expected_offers']);
    $usedFallbackParentOnly = (count($targets) === 1 && intval($targets[0]) === $b24Id);
    if (count($targets) > 50) {
        $targets = array_slice($targets, 0, 50);
    }

    // Важный стоп-контроль: для parent-SKU (type=3) нельзя считать успехом запись только в родителя.
    if ($expectedOffers && $usedFallbackParentOnly) {
        $err = 'Для SKU-товара не найдены торговые предложения (offers) по parentId=' . $b24Id
            . ', offerIblockId=' . $offerIblockId . '. Цена в строке вариации Б24 не обновится.';
        updateProductSyncState($db, $productId, 'error', $err, $attemptAt);
        return array(
            'ok' => false,
            'message' => $err,
            'sync_report_bucket' => 'errors',
            'sync_report_row' => makeB24PriceSyncListRow($pid, $pName, $b24Id, $err)
        );
    }
    if ($expectedOffers && $offerIblockId > 0 && $offerCatalogIsOffers === false) {
        $err = 'Найден iblock предложений=' . $offerIblockId . ', но catalog.catalog.isOffers=false.'
            . ' Проверьте структуру каталога Б24.';
        updateProductSyncState($db, $productId, 'error', $err, $attemptAt);
        return array(
            'ok' => false,
            'message' => $err,
            'sync_report_bucket' => 'errors',
            'sync_report_row' => makeB24PriceSyncListRow($pid, $pName, $b24Id, $err)
        );
    }

    updateProductB24SyncAudit($db, $productId, $targets, $parentType);

    $crmFields = array();
    if ($retailPrice > 0) {
        $crmFields['PRICE'] = $retailPrice;
        $crmFields['CURRENCY_ID'] = $currencyId;
    }

    $crmResp = null;
    $crmAnyOk = false;
    if ($retailPrice > 0 && !empty($targets)) {
        foreach ($targets as $targetIdx => $tid) {
            $tid = intval($tid);
            if ($tid <= 0) {
                continue;
            }
            $crmR = sendToBitrix('crm.product.update', array(
                'id' => $tid,
                'fields' => $crmFields
            ));
            if ($targetIdx === 0) {
                $crmResp = $crmR;
            }
            if (is_array($crmR) && !isset($crmR['error'])) {
                $crmAnyOk = true;
            }
        }
    }

    $priceUpsertResp = null;
    $priceUpsertAllOk = true;
    if ($retailPrice > 0 && !empty($targets)) {
        foreach ($targets as $tid) {
            $tid = intval($tid);
            if ($tid <= 0) {
                continue;
            }
            $priceUpsertResp = upsertB24RetailPrice($tid, $retailPrice, $currencyId);
            if (!isset($priceUpsertResp['ok']) || !$priceUpsertResp['ok']) {
                $priceUpsertAllOk = false;
                break;
            }
        }
    } elseif ($retailPrice > 0) {
        $priceUpsertAllOk = false;
    }

    $verifyNeedsPrice = $retailPrice > 0;
    $verified = false;
    $verifyError = '';
    if ($crmAnyOk || $priceUpsertAllOk) {
        if ($verifyNeedsPrice && !empty($targets)) {
            $catalogAligned = b24RetailPriceMatchesOnAllTargets($targets, $retailPrice);
            $crmAligned = b24CrmRetailPriceMatchesOnAnyTarget($targets, $retailPrice);
            $verified = ($catalogAligned || $crmAligned);
            if (!$verified) {
                $verifyError = 'Проверка после обновления: в Б24 цена по типовой цене не совпала на всех вариантах';
            }
        } else {
            $verified = true;
        }
    }

    if (($crmAnyOk || $priceUpsertAllOk) && $verified) {
        updateProductSyncState($db, $productId, 'sent', null, $attemptAt);
        return array(
            'ok' => true,
            'message' => 'Обновлено в Б24',
            'sync_report_bucket' => 'updated',
            'sync_report_row' => makeB24PriceSyncListRow(
                $pid,
                $pName,
                $b24Id,
                'Отправлено ' . $retailPrice . ' ' . $currencyId . ' '
                . '(' . (count($targets) <= 1
                    ? 'каталог id ' . (isset($targets[0]) ? intval($targets[0]) : $b24Id)
                    : ('офферы каталога: ' . implode(', ', array_map('intval', $targets)) . '; родитель ' . $b24Id))
                . '; parent_iblock=' . $parentIblockId
                . '; offer_iblock=' . $offerIblockId
                . '; is_offers=' . ($offerCatalogIsOffers === null ? 'unknown' : ($offerCatalogIsOffers ? 'Y' : 'N'))
                . '; target_offer_ids=' . implode(',', array_map('intval', $targets))
                . '; type=' . $parentType . '), подтверждено чтением после записи'
            )
        );
    }

    $crmErr = 'crm.product.update не вызывался';
    if (is_array($crmResp)) {
        if (isset($crmResp['error_description']) && $crmResp['error_description'] !== '') {
            $crmErr = $crmResp['error_description'];
        } elseif (isset($crmResp['error']) && $crmResp['error'] !== '') {
            $crmErr = $crmResp['error'];
        } elseif ($retailPrice > 0 && !$crmAnyOk) {
            $crmErr = 'crm.product.update failed';
        }
    }

    $priceErr = 'catalog.price: не выполнено на всех вариантах';
    if (is_array($priceUpsertResp) && isset($priceUpsertResp['response']) && is_array($priceUpsertResp['response'])) {
        $respRow = $priceUpsertResp['response'];
        if (isset($respRow['error_description']) && $respRow['error_description'] !== '') {
            $priceErr = $respRow['error_description'];
        } elseif (isset($respRow['error']) && $respRow['error'] !== '') {
            $priceErr = $respRow['error'];
        }
    }

    $err = $crmErr . ' | ' . $priceErr;
    if ($verifyError !== '') {
        $err .= ' | ' . $verifyError;
    }
    updateProductSyncState($db, $productId, 'error', $err, $attemptAt);
    return array(
        'ok' => false,
        'message' => $err,
        'sync_report_bucket' => 'errors',
        'sync_report_row' => makeB24PriceSyncListRow($pid, $pName, $b24Id, $err)
    );
}

function inspectProductPriceInB24($db, $productId) {
    $pid = intval($productId);
    if ($pid <= 0) {
        return array('ok' => false, 'message' => 'Некорректный product_id');
    }
    $stmt = $db->prepare("
        SELECT id, name, b24_product_id, price_per_meter
        FROM products
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute(array($pid));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return array('ok' => false, 'message' => 'Товар не найден');
    }
    $b24Id = intval(isset($row['b24_product_id']) ? $row['b24_product_id'] : 0);
    if ($b24Id <= 0) {
        return array('ok' => false, 'message' => 'Нет b24_product_id');
    }
    $localPrice = round(floatval(isset($row['price_per_meter']) ? $row['price_per_meter'] : 0), 4);
    $meta = b24ResolveRetailPriceCatalogTargetIdsMeta($b24Id);
    $targets = isset($meta['targets']) && is_array($meta['targets']) ? $meta['targets'] : array();
    $parentType = intval(isset($meta['parent_type']) ? $meta['parent_type'] : 0);
    updateProductB24SyncAudit($db, $pid, $targets, $parentType);
    if (empty($targets)) {
        return array('ok' => false, 'message' => "Проверка #{$pid}: не найдены target IDs в Б24");
    }

    $parts = array();
    $allMatch = true;
    foreach ($targets as $tid) {
        $tid = intval($tid);
        if ($tid <= 0) {
            continue;
        }
        $snap = fetchB24RetailPrice($tid);
        if (!isset($snap['ok']) || !$snap['ok']) {
            $parts[] = "target {$tid}: ошибка чтения";
            $allMatch = false;
            continue;
        }
        $remotePrice = $snap['price'];
        if ($remotePrice === null) {
            $parts[] = "target {$tid}: цены нет";
            $allMatch = false;
            continue;
        }
        $remotePrice = round(floatval($remotePrice), 4);
        $isMatch = abs($remotePrice - $localPrice) < 0.02;
        $parts[] = "target {$tid}: {$remotePrice}" . ($isMatch ? ' (OK)' : ' (mismatch)');
        if (!$isMatch) {
            $allMatch = false;
        }
    }

    $head = "Проверка #{$pid} (b24 {$b24Id}, type={$parentType}, local={$localPrice}): ";
    return array(
        'ok' => $allMatch,
        'message' => $head . implode('; ', $parts)
    );
}

if (isset($_GET['delete_id'])) {
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute(array(intval($_GET['delete_id'])));
    header("Location: products.php");
    exit;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'save';

    if ($action === 'sync_to_b24') {
        header("Location: products.php?sync_job=prices&sync_offset=0&sync_ok=0&sync_err=0&sync_skip=0");
        exit;
    }

    if ($action === 'sync_create_missing_b24') {
        $stats = runCreateMissingProductsInB24($db, 2000);
        header("Location: products.php?sync_msg=" . urlencode("Создание в Б24: создано {$stats['created']}, ошибок {$stats['errors']}, обработано {$stats['total']}"));
        exit;
    }

    if ($action === 'sync_one') {
        $productId = intval(isset($_POST['product_id']) ? $_POST['product_id'] : 0);
        resetB24PriceSyncSessionReport();
        $res = syncProductPriceToB24($db, $productId);
        if (!empty($res['sync_report_bucket']) && isset($res['sync_report_row'])) {
            appendB24PriceSyncSessionReport($res['sync_report_bucket'], $res['sync_report_row']);
        }
        if (!empty($res['skipped'])) {
            $msg = "Отправка товара #{$productId}: пропущено (нет цены за метр)";
        } else {
            $msg = $res['ok'] ? "Отправка товара #{$productId}: успешно" : ("Отправка товара #{$productId}: " . $res['message']);
        }
        header("Location: products.php?sync_msg=" . urlencode($msg) . "&sync_show_report=1");
        exit;
    }

    if ($action === 'sync_selected') {
        $ids = isset($_POST['selected_ids']) && is_array($_POST['selected_ids']) ? $_POST['selected_ids'] : array();
        $stats = runB24SyncForProductIds($db, $ids);
        $sk = intval(isset($stats['skip']) ? $stats['skip'] : 0);
        header("Location: products.php?sync_msg=" . urlencode("Отправить выбранные: обновлено {$stats['ok']}, ошибок {$stats['err']}, пропущено {$sk}, всего {$stats['total']}") . "&sync_show_report=1");
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
        $sk = intval(isset($stats['skip']) ? $stats['skip'] : 0);
        header("Location: products.php?sync_msg=" . urlencode("Повтор ошибок: обновлено {$stats['ok']}, ошибок {$stats['err']}, пропущено {$sk}, всего {$stats['total']}") . "&sync_show_errors=1&sync_show_report=1");
        exit;
    }

    if ($action === 'sync_row_to_b24') {
        $productId = intval(isset($_POST['product_id']) ? $_POST['product_id'] : 0);
        resetB24PriceSyncSessionReport();
        $result = syncProductPriceToB24($db, $productId);
        if (!empty($result['sync_report_bucket']) && isset($result['sync_report_row'])) {
            appendB24PriceSyncSessionReport($result['sync_report_bucket'], $result['sync_report_row']);
        }
        if (!empty($result['skipped'])) {
            $message = "Товар #{$productId}: пропущено (нет цены за метр)";
        } else {
            $message = $result['ok'] ? "Товар #{$productId}: отправка в Б24 выполнена" : ("Товар #{$productId}: " . $result['message']);
        }
        header("Location: products.php?sync_msg=" . urlencode($message) . "&sync_show_report=1");
        exit;
    }

    if ($action === 'check_row_in_b24') {
        $productId = intval(isset($_POST['product_id']) ? $_POST['product_id'] : 0);
        $check = inspectProductPriceInB24($db, $productId);
        $msg = isset($check['message']) ? (string)$check['message'] : 'Проверка завершена';
        header("Location: products.php?sync_msg=" . urlencode($msg));
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

if (isset($_GET['sync_job']) && $_GET['sync_job'] === 'prices') {
    @set_time_limit(30);
    $offset = isset($_GET['sync_offset']) ? intval($_GET['sync_offset']) : 0;
    $okAcc = isset($_GET['sync_ok']) ? intval($_GET['sync_ok']) : 0;
    $errAcc = isset($_GET['sync_err']) ? intval($_GET['sync_err']) : 0;
    $skipAcc = isset($_GET['sync_skip']) ? intval($_GET['sync_skip']) : 0;
    $limit = getB24SyncBatchSize($db);
    if ($limit < 10) {
        $limit = 10;
    }

    if ($offset === 0) {
        resetB24PriceSyncSessionReport();
    }

    $chunk = processPriceSyncChunk($db, $offset, $limit);
    $okAcc += intval(isset($chunk['ok']) ? $chunk['ok'] : 0);
    $errAcc += intval(isset($chunk['err']) ? $chunk['err'] : 0);
    $skipAcc += intval(isset($chunk['skip']) ? $chunk['skip'] : 0);
    $nextOffset = intval(isset($chunk['next_offset']) ? $chunk['next_offset'] : $offset);
    $total = intval(isset($chunk['total']) ? $chunk['total'] : 0);
    $chunkErrors = isset($chunk['errors']) && is_array($chunk['errors']) ? $chunk['errors'] : array();
    $lastChunkError = '';
    if (!empty($chunkErrors)) {
        $firstErr = $chunkErrors[0];
        $errPid = intval(isset($firstErr['product_id']) ? $firstErr['product_id'] : 0);
        $errText = isset($firstErr['message']) ? trim((string)$firstErr['message']) : 'Неизвестная ошибка';
        $lastChunkError = ($errPid > 0 ? ('#' . $errPid . ': ') : '') . $errText;
    }

    if (!empty($chunk['done'])) {
        header("Location: products.php?sync_msg=" . urlencode("Отправить в Б24: обновлено {$okAcc}, ошибок {$errAcc}, пропущено {$skipAcc}, всего {$total}") . "&sync_show_errors=1&sync_show_report=1");
        exit;
    }
    $nextUrl = "products.php?sync_job=prices&sync_offset={$nextOffset}&sync_ok={$okAcc}&sync_err={$errAcc}&sync_skip={$skipAcc}";
    $progressPercent = $total > 0 ? min(100, intval(round(($nextOffset / $total) * 100))) : 0;
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Синхронизация цен с Б24</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<style>body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;padding:24px}.box{max-width:720px;margin:0 auto;background:#fff;border:1px solid #d9e2ec;border-radius:10px;padding:20px}.bar{height:10px;background:#e2e8f0;border-radius:999px;overflow:hidden}.fill{height:100%;background:#2563eb;width:' . $progressPercent . '%}.muted{color:#64748b}</style>';
    echo '</head><body><div class="box">';
    echo '<h3 style="margin-top:0">Синхронизация цен с Б24...</h3>';
    echo '<p class="muted">Обработано: <strong>' . intval($nextOffset) . '</strong> из <strong>' . intval($total) . '</strong>. Успешно: <strong>' . intval($okAcc) . '</strong>, ошибок: <strong>' . intval($errAcc) . '</strong>, пропущено (нет цены/м): <strong>' . intval($skipAcc) . '</strong>.</p>';
    if ($lastChunkError !== '') {
        echo '<p class="muted" style="color:#b91c1c"><strong>Последняя ошибка:</strong> ' . htmlspecialchars($lastChunkError, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '<div class="bar"><div class="fill"></div></div>';
    echo '<p class="muted" style="margin-bottom:0;margin-top:14px">Страница обновится автоматически. Не закрывайте вкладку.</p>';
    echo '</div><script>setTimeout(function(){ window.location.href = ' . json_encode($nextUrl) . '; }, 120);</script></body></html>';
    exit;
}

$hasCatalogId = hasColumn($db, 'products', 'catalog_id');
$syncMsg = isset($_GET['sync_msg']) ? $_GET['sync_msg'] : '';
$showSyncReport = isset($_GET['sync_show_report']) && $_GET['sync_show_report'] === '1';
$priceSyncReport = null;
if ($showSyncReport && isset($_SESSION['b24_price_sync_report']) && is_array($_SESSION['b24_price_sync_report'])) {
    $priceSyncReport = $_SESSION['b24_price_sync_report'];
    unset($_SESSION['b24_price_sync_report']);
}
$showSyncErrors = isset($_GET['sync_show_errors']) && $_GET['sync_show_errors'] === '1';
$recentSyncErrors = array();
if ($showSyncErrors) {
    $recentSyncErrors = $db->query("
        SELECT id, name, b24_product_id, last_error, last_attempt_at
        FROM products
        WHERE sync_status = 'error'
          AND last_attempt_at IS NOT NULL
        ORDER BY last_attempt_at DESC, id DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
}
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

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$brandPrefix = isset($_GET['brand_prefix']) ? trim($_GET['brand_prefix']) : '';
$catalogFilter = isset($_GET['catalog_id']) ? trim($_GET['catalog_id']) : '';
$hasB24Filter = isset($_GET['has_b24']) ? $_GET['has_b24'] : 'all';

$priceFilter = isset($_GET['price_filter']) ? trim($_GET['price_filter']) : 'all';
$allowedPriceFilters = array('all', 'no_meter', 'empty_any', 'ready_b24_sync');
if (!in_array($priceFilter, $allowedPriceFilters, true)) {
    $priceFilter = 'all';
}
if (isset($_GET['empty_prices']) && $_GET['empty_prices'] === '1') {
    $priceFilter = 'empty_any';
}

$syncStatusFilter = isset($_GET['sync_status']) ? trim($_GET['sync_status']) : 'all';
$allowedSyncStatus = array('all', 'pending', 'sent', 'error');
if (!in_array($syncStatusFilter, $allowedSyncStatus, true)) {
    $syncStatusFilter = 'all';
}

$allowedPerPage = array(50, 100, 200);
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 50;
}
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) {
    $page = 1;
}

$where = array();
$params = array();

if ($search !== '') {
    $where[] = "name LIKE ?";
    $params[] = '%' . $search . '%';
}
if ($hasCatalogId && $catalogFilter !== '') {
    $where[] = "catalog_id = ?";
    $params[] = intval($catalogFilter);
}
if ($brandPrefix !== '') {
    $where[] = "name LIKE ?";
    $params[] = $brandPrefix . '%';
}
if ($hasB24Filter === 'yes') {
    $where[] = "b24_product_id IS NOT NULL AND b24_product_id <> 0";
} elseif ($hasB24Filter === 'no') {
    $where[] = "(b24_product_id IS NULL OR b24_product_id = 0)";
}

if ($priceFilter === 'no_meter') {
    $where[] = '(price_per_meter IS NULL OR price_per_meter = 0)';
} elseif ($priceFilter === 'empty_any') {
    $where[] = '(
        purchase_price IS NULL OR purchase_price = 0
        OR delivery_price IS NULL OR delivery_price = 0
        OR price_per_meter IS NULL OR price_per_meter = 0
        OR price_1_4 IS NULL OR price_1_4 = 0
        OR price_5_9 IS NULL OR price_5_9 = 0
        OR price_10_19 IS NULL OR price_10_19 = 0
        OR price_20_plus IS NULL OR price_20_plus = 0
    )';
} elseif ($priceFilter === 'ready_b24_sync') {
    $where[] = 'b24_product_id IS NOT NULL AND b24_product_id <> 0'
        . ' AND price_per_meter IS NOT NULL AND price_per_meter > 0';
}

if ($syncStatusFilter !== 'all') {
    $where[] = 'sync_status = ?';
    $params[] = $syncStatusFilter;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = ' WHERE ' . implode(' AND ', $where);
}

$countStmt = $db->prepare("SELECT COUNT(*) AS total FROM products" . $whereSql);
$countStmt->execute($params);
$totalRows = intval($countStmt->fetchColumn());
$totalPages = $totalRows > 0 ? intval(ceil($totalRows / $perPage)) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$orderSql = $hasCatalogId ? "catalog_id ASC, id DESC" : "id DESC";
$sql = "SELECT * FROM products" . $whereSql . " ORDER BY " . $orderSql . " LIMIT " . intval($perPage) . " OFFSET " . intval($offset);
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$catalogOptions = array();
if ($hasCatalogId) {
    $catalogRows = $db->query("SELECT DISTINCT catalog_id FROM products WHERE catalog_id IS NOT NULL ORDER BY catalog_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($catalogRows as $catalogRow) {
        $cid = intval($catalogRow['catalog_id']);
        if ($cid > 0) {
            $catalogOptions[] = $cid;
        }
    }
}

$b24GlobStats = $db->query("
    SELECT
        SUM(CASE WHEN b24_product_id IS NOT NULL AND b24_product_id <> 0 THEN 1 ELSE 0 END) AS b24_linked,
        SUM(CASE WHEN b24_product_id IS NOT NULL AND b24_product_id <> 0
                 AND price_per_meter IS NOT NULL AND price_per_meter > 0 THEN 1 ELSE 0 END) AS b24_ready,
        SUM(CASE WHEN b24_product_id IS NOT NULL AND b24_product_id <> 0
                 AND (price_per_meter IS NULL OR price_per_meter = 0) THEN 1 ELSE 0 END) AS b24_no_meter_price,
        SUM(CASE WHEN sync_status = 'error' THEN 1 ELSE 0 END) AS sync_error_cnt
    FROM products
")->fetch(PDO::FETCH_ASSOC);
$b24LinkedTotal = intval(isset($b24GlobStats['b24_linked']) ? $b24GlobStats['b24_linked'] : 0);
$b24ReadyTotal = intval(isset($b24GlobStats['b24_ready']) ? $b24GlobStats['b24_ready'] : 0);
$b24NoMeterTotal = intval(isset($b24GlobStats['b24_no_meter_price']) ? $b24GlobStats['b24_no_meter_price'] : 0);
$syncErrorTotal = intval(isset($b24GlobStats['sync_error_cnt']) ? $b24GlobStats['sync_error_cnt'] : 0);

$page_title = 'Товары';
require 'includes/header.php';
?>

<style>
.products-table .sync-status-col {
    width: 118px;
    min-width: 118px;
    white-space: nowrap;
}
.products-table .sticky-col-actions {
    min-width: 232px;
    width: 232px;
}
.products-table .products-row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: nowrap;
    gap: 4px;
}
</style>

<main class="container products-catalog-page">
    <h2>Каталог товаров</h2>

    <?php if ($syncMsg): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($syncMsg); ?></div>
    <?php endif; ?>
    <?php
    $reportUpd = ($priceSyncReport && isset($priceSyncReport['updated']) && is_array($priceSyncReport['updated']))
        ? $priceSyncReport['updated'] : array();
    $reportSk = ($priceSyncReport && isset($priceSyncReport['skipped']) && is_array($priceSyncReport['skipped']))
        ? $priceSyncReport['skipped'] : array();
    $reportEr = ($priceSyncReport && isset($priceSyncReport['errors']) && is_array($priceSyncReport['errors']))
        ? $priceSyncReport['errors'] : array();
    $reportTotal = count($reportUpd) + count($reportSk) + count($reportEr);
    ?>
    <?php if ($priceSyncReport !== null && $reportTotal > 0): ?>
        <?php $reportMaxHit = (
            count($reportUpd) >= B24_PRICE_SYNC_REPORT_MAX_ROWS
            || count($reportSk) >= B24_PRICE_SYNC_REPORT_MAX_ROWS
            || count($reportEr) >= B24_PRICE_SYNC_REPORT_MAX_ROWS
        ); ?>
        <div class="card b24-sync-report-card" id="b24-sync-report">
            <h3 style="margin-top:0">Отчёт по последней отправке цен в Б24</h3>
            <p class="muted" style="margin-top:0">Сверяйте в Битрикс24 по колонке «ID в Б24». Списком удобно проверить выборочно несколько SKU.
                <?php if ($reportMaxHit): ?>
                    <strong>Внимание:</strong> в одном из блоков сохранены не более <?php echo intval(B24_PRICE_SYNC_REPORT_MAX_ROWS); ?> строк — очень длинную выгрузку разбивайте фильтром или выбором.
                <?php endif; ?></p>

            <?php if (!empty($reportUpd)): ?>
            <details open>
                <summary><strong>Обновлено в Б24 (проверка пройдена)</strong> — <?php echo count($reportUpd); ?> шт.</summary>
                <div class="table-responsive" style="margin-top:.75rem">
                    <table class="table table-sm">
                        <thead><tr><th>ID</th><th>Товар</th><th>B24 ID</th><th>Что отправлено</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($reportUpd as $rw): ?>
                            <tr>
                                <td><?php echo intval(isset($rw['id']) ? $rw['id'] : 0); ?></td>
                                <td><?php echo htmlspecialchars(isset($rw['name']) ? $rw['name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo intval(isset($rw['b24_id']) ? $rw['b24_id'] : 0); ?></td>
                                <td><?php echo htmlspecialchars(isset($rw['detail']) ? $rw['detail'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><a href="<?php echo htmlspecialchars(buildProductsUrl(array('edit_id' => intval(isset($rw['id']) ? $rw['id'] : 0), 'page' => null))); ?>">в каталоге</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
            <?php endif; ?>

            <?php if (!empty($reportSk)): ?>
            <details <?php echo empty($reportUpd) ? 'open' : ''; ?> style="margin-top:12px">
                <summary><strong>Пропущено</strong> (нет цены за метр) — <?php echo count($reportSk); ?> шт.</summary>
                <div class="table-responsive" style="margin-top:.75rem">
                    <table class="table table-sm">
                        <thead><tr><th>ID</th><th>Товар</th><th>B24 ID</th><th>Причина</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($reportSk as $rw): ?>
                            <tr>
                                <td><?php echo intval(isset($rw['id']) ? $rw['id'] : 0); ?></td>
                                <td><?php echo htmlspecialchars(isset($rw['name']) ? $rw['name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo intval(isset($rw['b24_id']) ? $rw['b24_id'] : 0); ?></td>
                                <td><?php echo htmlspecialchars(isset($rw['detail']) ? $rw['detail'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><a href="<?php echo htmlspecialchars(buildProductsUrl(array('edit_id' => intval(isset($rw['id']) ? $rw['id'] : 0), 'page' => null))); ?>">в каталоге</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
            <?php endif; ?>

            <?php if (!empty($reportEr)): ?>
            <details open style="margin-top:12px">
                <summary><strong>Ошибки при этой отправке</strong> — <?php echo count($reportEr); ?> шт.</summary>
                <div class="table-responsive" style="margin-top:.75rem">
                    <table class="table table-sm">
                        <thead><tr><th>ID</th><th>Товар</th><th>B24 ID</th><th>Текст ошибки</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($reportEr as $rw): ?>
                            <tr>
                                <td><?php echo intval(isset($rw['id']) ? $rw['id'] : 0); ?></td>
                                <td><?php echo htmlspecialchars(isset($rw['name']) ? $rw['name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo intval(isset($rw['b24_id']) ? $rw['b24_id'] : 0); ?></td>
                                <td><?php echo htmlspecialchars(isset($rw['detail']) ? $rw['detail'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><a href="<?php echo htmlspecialchars(buildProductsUrl(array('edit_id' => intval(isset($rw['id']) ? $rw['id'] : 0), 'page' => null))); ?>">в каталоге</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($showSyncErrors && !empty($recentSyncErrors)): ?>
        <div class="card">
            <h3>Причины ошибок синхронизации (последние 20)</h3>
            <table class="table">
                <tr>
                    <th>ID</th>
                    <th>Товар</th>
                    <th>B24 ID</th>
                    <th>Ошибка</th>
                    <th>Когда</th>
                </tr>
                <?php foreach ($recentSyncErrors as $errRow): ?>
                    <tr>
                        <td><?php echo intval(isset($errRow['id']) ? $errRow['id'] : 0); ?></td>
                        <td><?php echo htmlspecialchars(isset($errRow['name']) ? (string)$errRow['name'] : ''); ?></td>
                        <td><?php echo intval(isset($errRow['b24_product_id']) ? $errRow['b24_product_id'] : 0); ?></td>
                        <td><?php echo htmlspecialchars(isset($errRow['last_error']) ? (string)$errRow['last_error'] : ''); ?></td>
                        <td><?php echo htmlspecialchars(isset($errRow['last_attempt_at']) ? (string)$errRow['last_attempt_at'] : ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
    <?php if (!empty($formErrors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($formErrors as $errorText): ?>
                    <li><?php echo htmlspecialchars($errorText); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($formWarnings)): ?>
        <div class="alert alert-warning">
            <ul>
                <?php foreach ($formWarnings as $warningText): ?>
                    <li><?php echo htmlspecialchars($warningText); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card products-toolbar">
        <form method="GET" class="products-filter-form">
            <div class="products-filter-grid">
                <div>
                    <label class="form-label" for="q">Поиск по названию</label>
                    <input class="form-control" id="q" type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Например: Oracal 641">
                </div>
                <div>
                    <label class="form-label" for="brand_prefix">Бренд (префикс)</label>
                    <input class="form-control" id="brand_prefix" type="text" name="brand_prefix" value="<?php echo htmlspecialchars($brandPrefix); ?>" placeholder="Например: Oracal">
                </div>
                <?php if ($hasCatalogId): ?>
                <div>
                    <label class="form-label" for="catalog_id">catalog_id</label>
                    <select class="form-control" id="catalog_id" name="catalog_id">
                        <option value="">Все</option>
                        <?php foreach ($catalogOptions as $catalogId): ?>
                            <?php $label = isset($catalogLabels[$catalogId]) ? $catalogLabels[$catalogId] : ('Каталог #' . $catalogId); ?>
                            <option value="<?php echo $catalogId; ?>" <?php echo ((string)$catalogId === $catalogFilter) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <label class="form-label" for="has_b24">Привязка к Битрикс24</label>
                    <select class="form-control" id="has_b24" name="has_b24">
                        <option value="all" <?php echo $hasB24Filter === 'all' ? 'selected' : ''; ?>>Все товары</option>
                        <option value="yes" <?php echo $hasB24Filter === 'yes' ? 'selected' : ''; ?>>Только с ID в Б24</option>
                        <option value="no" <?php echo $hasB24Filter === 'no' ? 'selected' : ''; ?>>Только без ID в Б24</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="price_filter">Цена за метр / готовность к отправке</label>
                    <select class="form-control" id="price_filter" name="price_filter">
                        <option value="all" <?php echo $priceFilter === 'all' ? 'selected' : ''; ?>>Не фильтровать по цене/м</option>
                        <option value="no_meter" <?php echo $priceFilter === 'no_meter' ? 'selected' : ''; ?>>Цена за метр не указана (=0 или пусто)</option>
                        <option value="ready_b24_sync" <?php echo $priceFilter === 'ready_b24_sync' ? 'selected' : ''; ?>>Готовы к отправке цены в Б24 (есть ID и цена/м&gt;0)</option>
                        <option value="empty_any" <?php echo $priceFilter === 'empty_any' ? 'selected' : ''; ?>>Есть «дырка» в любой цене (себестоимость, доставка, сетку и т.д.)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="sync_status">Последний статус отправки в Б24</label>
                    <select class="form-control" id="sync_status" name="sync_status">
                        <option value="all" <?php echo $syncStatusFilter === 'all' ? 'selected' : ''; ?>>Любой</option>
                        <option value="pending" <?php echo $syncStatusFilter === 'pending' ? 'selected' : ''; ?>>Не отправлено / не проверено (pending)</option>
                        <option value="sent" <?php echo $syncStatusFilter === 'sent' ? 'selected' : ''; ?>>Успешно отправлено (sent)</option>
                        <option value="error" <?php echo $syncStatusFilter === 'error' ? 'selected' : ''; ?>>Были ошибки (error)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="per_page">Строк на странице</label>
                    <select class="form-control" id="per_page" name="per_page">
                        <?php foreach ($allowedPerPage as $pp): ?>
                            <option value="<?php echo $pp; ?>" <?php echo $perPage === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="products-filter-actions">
                <div class="muted" style="flex:1;min-width:220px;line-height:1.5">
                    <strong>По складу всего:</strong>
                    с Б24 —
                    <?php echo $b24LinkedTotal; ?>,
                    готовы отправить цену —
                    <?php echo $b24ReadyTotal; ?>,
                    в Б24 без цены за метр —
                    <?php echo $b24NoMeterTotal; ?>,
                    в статусе ошибки —
                    <?php echo $syncErrorTotal; ?>.
                    <span class="d-block" style="margin-top:.35rem"><strong>Быстрые пресеты:</strong>
                        <a href="<?php echo htmlspecialchars(buildProductsUrl(array('price_filter' => 'ready_b24_sync', 'has_b24' => 'all', 'sync_status' => 'all', 'page' => null))); ?>">только что реально отправим массовой кнопкой</a>
                        · <a href="<?php echo htmlspecialchars(buildProductsUrl(array('price_filter' => 'no_meter', 'has_b24' => 'yes', 'sync_status' => 'all', 'page' => null))); ?>">в Б24, но без цены/м</a>
                        · <a href="<?php echo htmlspecialchars(buildProductsUrl(array('price_filter' => 'all', 'has_b24' => 'all', 'sync_status' => 'error', 'page' => null))); ?>">ошибки синхронизации</a>
                        · <a href="<?php echo htmlspecialchars(buildProductsUrl(array('price_filter' => 'empty_any', 'has_b24' => 'all', 'sync_status' => 'all', 'page' => null))); ?>">нужно добить прайс (любое поле пустое)</a>
                    </span>
                </div>
                <div class="d-flex gap-1 align-items-start">
                    <button class="btn btn-primary btn-sm" type="submit">Применить</button>
                    <a class="btn btn-light btn-sm" href="products.php">Сбросить</a>
                </div>
            </div>
        </form>

        <div class="products-bulk-tools">
            <form method="POST" class="js-sync-action-form">
                <input type="hidden" name="action" value="sync_to_b24">
                <button class="btn btn-warning btn-sm" type="submit" title="Товары без цены за метр не трогаем в Б24 (считаются «пропущено»)." data-loading-text="Отправка...">Отправить все цены в Б24</button>
            </form>
            <form method="POST" class="js-sync-action-form">
                <input type="hidden" name="action" value="sync_create_missing_b24">
                <button class="btn btn-secondary btn-sm" type="submit" data-loading-text="Создание...">Создать отсутствующие товары в Б24</button>
            </form>
            <form method="POST" id="bulk-sync-form" class="js-sync-action-form">
                <button class="btn btn-light btn-sm" type="submit" name="action" value="sync_selected" data-loading-text="Отправка...">Отправить выбранные</button>
                <button class="btn btn-light btn-sm" type="submit" name="action" value="retry_sync_errors" data-loading-text="Повтор...">Повторить ошибки отправки</button>
            </form>
            <span class="text-muted">Найдено: <?php echo $totalRows; ?></span>
        </div>
    </div>

    <details class="card" <?php echo $editProduct ? 'open' : ''; ?>>
        <summary><strong><?php echo $editProduct ? 'Редактирование через форму' : 'Добавить товар'; ?></strong></summary>
        <form method="POST" class="products-legacy-form mt-2">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo isset($editProduct['id']) ? $editProduct['id'] : ''; ?>">

            <input class="form-control mb-1" name="name" placeholder="Название" value="<?php echo isset($editProduct['name']) ? htmlspecialchars($editProduct['name']) : ''; ?>" required>
            <input class="form-control mb-1" name="roll_length" placeholder="Метраж рулона" value="<?php echo isset($editProduct['roll_length']) ? $editProduct['roll_length'] : ''; ?>" required>
            <input class="form-control mb-1" name="price_per_meter" placeholder="Цена за метр (KGS)" value="<?php echo isset($editProduct['price_per_meter']) ? $editProduct['price_per_meter'] : ''; ?>">
            <input class="form-control mb-1" name="purchase_price" placeholder="Себестоимость (KGS)" value="<?php echo isset($editProduct['purchase_price']) ? $editProduct['purchase_price'] : ''; ?>">
            <input class="form-control mb-1" name="delivery_price" placeholder="С доставкой за рулон (KGS)" value="<?php echo isset($editProduct['delivery_price']) ? $editProduct['delivery_price'] : ''; ?>">
            <input class="form-control mb-1" name="price_1_4" placeholder="1-4" value="<?php echo isset($editProduct['price_1_4']) ? $editProduct['price_1_4'] : ''; ?>">
            <input class="form-control mb-1" name="price_5_9" placeholder="5-9" value="<?php echo isset($editProduct['price_5_9']) ? $editProduct['price_5_9'] : ''; ?>">
            <input class="form-control mb-1" name="price_10_19" placeholder="10-19" value="<?php echo isset($editProduct['price_10_19']) ? $editProduct['price_10_19'] : ''; ?>">
            <input class="form-control mb-2" name="price_20_plus" placeholder="20+" value="<?php echo isset($editProduct['price_20_plus']) ? $editProduct['price_20_plus'] : ''; ?>">

            <button class="btn btn-success btn-sm" type="submit"><?php echo $editProduct ? 'Обновить' : 'Сохранить'; ?></button>
        </form>
    </details>

    <?php if ($editProduct): ?>
    <div class="card">
        <h3>История цен (последние 10)</h3>
        <?php if (empty($editProductHistory)): ?>
            <p>Записей пока нет.</p>
        <?php else: ?>
            <table class="table">
                <tr><th>Когда</th><th>Старая цена/м</th><th>Новая цена/м</th><th>Старая себестоимость</th><th>Новая себестоимость</th><th>Старая с доставкой</th><th>Новая с доставкой</th></tr>
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
    </div>
    <?php endif; ?>

    <div class="table-responsive products-table-wrap">
        <table class="table products-table">
            <thead>
                <tr>
                    <th class="sticky-col sticky-col-select"><input type="checkbox" id="select-all-rows"></th>
                    <th class="sticky-col sticky-col-id">ID</th>
                    <th class="sticky-col sticky-col-name">Название</th>
                    <th class="b24-id-col">B24 ID</th>
                    <th>Метраж</th>
                    <th>Себест.</th>
                    <th>Доставка</th>
                    <th class="sticky-col sticky-col-price">Цена за метр (KGS)</th>
                    <th>1-4</th>
                    <th>5-9</th>
                    <th>10-19</th>
                    <th>20+</th>
                    <th class="sync-status-col">Синхр.</th>
                    <th class="sticky-col sticky-col-actions">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="14" class="text-center text-muted">Ничего не найдено по текущим фильтрам.</td></tr>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                    <?php
                    $catalogId = $hasCatalogId ? intval(isset($p['catalog_id']) ? $p['catalog_id'] : 0) : 0;
                    $catalogLabel = $catalogId > 0
                        ? (isset($catalogLabels[$catalogId]) ? $catalogLabels[$catalogId] : ('#' . $catalogId))
                        : '—';
                    $b24Id = isset($p['b24_product_id']) ? intval($p['b24_product_id']) : 0;
                    $syncStatus = isset($p['sync_status']) ? trim((string)$p['sync_status']) : '';
                    $isSynced = ($syncStatus === 'sent');
                    $syncLabel = $isSynced ? 'Синхронизированы' : 'Не синхр.';
                    $syncClass = $isSynced ? 'text-success' : 'text-danger';
                    ?>
                    <tr class="product-row" data-product-id="<?php echo intval($p['id']); ?>" data-product-name="<?php echo htmlspecialchars(isset($p['name']) ? $p['name'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        <td class="sticky-col sticky-col-select"><input type="checkbox" class="row-selector" name="selected_ids[]" form="bulk-sync-form" value="<?php echo intval($p['id']); ?>"></td>
                        <td class="sticky-col sticky-col-id"><?php echo intval($p['id']); ?></td>
                        <td class="product-name-cell sticky-col sticky-col-name">
                            <span class="product-name-text" title="<?php echo htmlspecialchars(isset($p['name']) ? $p['name'] : '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(isset($p['name']) ? $p['name'] : ''); ?></span>
                            <div class="inline-row-error"></div>
                        </td>
                        <td class="b24-id-col"><?php echo $b24Id > 0 ? $b24Id : '—'; ?></td>
                        <td>
                            <span class="cell-view"><?php echo htmlspecialchars((string)$p['roll_length']); ?></span>
                            <input class="form-control cell-edit" data-field="roll_length" type="text" value="<?php echo htmlspecialchars((string)$p['roll_length']); ?>">
                        </td>
                        <td>
                            <span class="cell-view"><?php echo htmlspecialchars((string)$p['purchase_price']); ?></span>
                            <input class="form-control cell-edit" data-field="purchase_price" type="text" value="<?php echo htmlspecialchars((string)$p['purchase_price']); ?>">
                        </td>
                        <td>
                            <span class="cell-view"><?php echo htmlspecialchars((string)$p['delivery_price']); ?></span>
                            <input class="form-control cell-edit" data-field="delivery_price" type="text" value="<?php echo htmlspecialchars((string)$p['delivery_price']); ?>">
                        </td>
                        <td class="sticky-col sticky-col-price">
                            <span class="cell-view"><?php echo htmlspecialchars((string)$p['price_per_meter']); ?></span>
                            <input class="form-control cell-edit" data-field="price_per_meter" type="text" value="<?php echo htmlspecialchars((string)$p['price_per_meter']); ?>">
                        </td>
                        <td>
                            <span class="cell-view"><?php echo htmlspecialchars((string)$p['price_1_4']); ?></span>
                            <input class="form-control cell-edit" data-field="price_1_4" type="text" value="<?php echo htmlspecialchars((string)$p['price_1_4']); ?>">
                        </td>
                        <td>
                            <span class="cell-view"><?php echo htmlspecialchars((string)$p['price_5_9']); ?></span>
                            <input class="form-control cell-edit" data-field="price_5_9" type="text" value="<?php echo htmlspecialchars((string)$p['price_5_9']); ?>">
                        </td>
                        <td>
                            <span class="cell-view"><?php echo htmlspecialchars((string)$p['price_10_19']); ?></span>
                            <input class="form-control cell-edit" data-field="price_10_19" type="text" value="<?php echo htmlspecialchars((string)$p['price_10_19']); ?>">
                        </td>
                        <td>
                            <span class="cell-view"><?php echo htmlspecialchars((string)$p['price_20_plus']); ?></span>
                            <input class="form-control cell-edit" data-field="price_20_plus" type="text" value="<?php echo htmlspecialchars((string)$p['price_20_plus']); ?>">
                        </td>
                        <td class="sync-status-col">
                            <span class="<?php echo $syncClass; ?>" title="Последний sync_status: <?php echo htmlspecialchars($syncStatus !== '' ? $syncStatus : 'pending', ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo $syncLabel; ?>
                            </span>
                        </td>
                        <td class="sticky-col sticky-col-actions">
                            <div class="products-row-actions">
                                <button type="button" class="btn btn-light btn-sm inline-edit-btn">Ред.</button>
                                <button type="button" class="btn btn-success btn-sm inline-save-btn" title="Сохранить">Сохр.</button>
                                <button type="button" class="btn btn-light btn-sm inline-cancel-btn" title="Отмена">Отм.</button>
                                <button type="button" class="btn btn-warning btn-sm inline-sync-btn" title="Отправить в B24">B24</button>
                                <button type="button" class="btn btn-light btn-sm inline-check-b24-btn" title="Проверить текущую цену в Б24">Проверить B24</button>
                                <a class="btn btn-light btn-sm" title="Открыть форму редактирования" href="<?php echo buildProductsUrl(array('edit_id' => intval($p['id']))); ?>">Форма</a>
                                <a class="btn btn-danger btn-sm" title="Удалить товар" href="products.php?delete_id=<?php echo intval($p['id']); ?>" onclick="return confirm('Удалить товар #<?php echo intval($p['id']); ?>?');">Удал.</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $pageWindow = 2;
    $pageStart = max(1, $page - $pageWindow);
    $pageEnd = min($totalPages, $page + $pageWindow);
    ?>
    <div class="products-pagination">
        <span>Страница <?php echo $page; ?> из <?php echo $totalPages; ?></span>
        <div class="d-flex gap-1">
            <?php if ($page > 1): ?>
                <a class="btn btn-light btn-sm" href="<?php echo htmlspecialchars(buildProductsUrl(array('page' => 1))); ?>">« Первая</a>
                <a class="btn btn-light btn-sm" href="<?php echo htmlspecialchars(buildProductsUrl(array('page' => $page - 1))); ?>">‹ Назад</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-light btn-sm" href="<?php echo htmlspecialchars(buildProductsUrl(array('page' => $page + 1))); ?>">Вперед ›</a>
                <a class="btn btn-light btn-sm" href="<?php echo htmlspecialchars(buildProductsUrl(array('page' => $totalPages))); ?>">Последняя »</a>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-1 products-page-numbers">
            <?php for ($i = $pageStart; $i <= $pageEnd; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="btn btn-primary btn-sm is-current-page"><?php echo $i; ?></span>
                <?php else: ?>
                    <a class="btn btn-light btn-sm" href="<?php echo htmlspecialchars(buildProductsUrl(array('page' => $i))); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>
</main>

<script>
(function () {
    var syncMsg = <?php echo json_encode($syncMsg, JSON_UNESCAPED_UNICODE); ?>;
    if (syncMsg) {
        window.setTimeout(function () {
            alert(syncMsg);
        }, 50);
    }

    function lockSubmitButton(form, submitter) {
        var btn = submitter;
        if (!btn) {
            btn = form.querySelector('button[type="submit"]');
        }
        if (!btn) {
            return;
        }
        var loadingText = btn.getAttribute('data-loading-text');
        if (loadingText) {
            btn.textContent = loadingText;
        }
        btn.disabled = true;
    }

    var syncForms = document.querySelectorAll('.js-sync-action-form');
    for (var sf = 0; sf < syncForms.length; sf++) {
        (function (form) {
            form.addEventListener('submit', function (event) {
                var submitter = event.submitter ? event.submitter : null;
                lockSubmitButton(form, submitter);
            });
        })(syncForms[sf]);
    }

    function postForm(payload) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'products.php';
        for (var key in payload) {
            if (!payload.hasOwnProperty(key)) {
                continue;
            }
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = payload[key];
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
    }

    function isNumericValue(value) {
        if (value === null || value === '') {
            return true;
        }
        var normalized = String(value).replace(/\s+/g, '').replace(',', '.');
        return !isNaN(parseFloat(normalized)) && isFinite(normalized);
    }

    function getRowPayload(row) {
        var payload = {
            action: 'save',
            id: row.getAttribute('data-product-id'),
            name: row.getAttribute('data-product-name')
        };
        var edits = row.querySelectorAll('.cell-edit');
        for (var i = 0; i < edits.length; i++) {
            payload[edits[i].getAttribute('data-field')] = edits[i].value;
        }
        return payload;
    }

    function setRowMode(row, editing) {
        var views = row.querySelectorAll('.cell-view');
        var edits = row.querySelectorAll('.cell-edit');
        for (var i = 0; i < views.length; i++) {
            views[i].style.display = editing ? 'none' : '';
        }
        for (var j = 0; j < edits.length; j++) {
            edits[j].style.display = editing ? 'block' : 'none';
        }
        row.classList[editing ? 'add' : 'remove']('is-editing');
    }

    function showRowError(row, message) {
        var errorEl = row.querySelector('.inline-row-error');
        if (errorEl) {
            errorEl.textContent = message;
        }
    }

    var rows = document.querySelectorAll('.product-row');
    for (var i = 0; i < rows.length; i++) {
        (function (row) {
            var edits = row.querySelectorAll('.cell-edit');
            for (var j = 0; j < edits.length; j++) {
                edits[j].setAttribute('data-original', edits[j].value);
            }
            setRowMode(row, false);

            var editBtn = row.querySelector('.inline-edit-btn');
            var saveBtn = row.querySelector('.inline-save-btn');
            var cancelBtn = row.querySelector('.inline-cancel-btn');
            var syncBtn = row.querySelector('.inline-sync-btn');
            var checkB24Btn = row.querySelector('.inline-check-b24-btn');

            if (editBtn) {
                editBtn.addEventListener('click', function () {
                    showRowError(row, '');
                    setRowMode(row, true);
                });
            }
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    for (var k = 0; k < edits.length; k++) {
                        edits[k].value = edits[k].getAttribute('data-original');
                    }
                    showRowError(row, '');
                    setRowMode(row, false);
                });
            }
            if (saveBtn) {
                saveBtn.addEventListener('click', function () {
                    var fieldsToValidate = row.querySelectorAll('.cell-edit');
                    for (var p = 0; p < fieldsToValidate.length; p++) {
                        if (!isNumericValue(fieldsToValidate[p].value)) {
                            showRowError(row, 'Проверьте числовые поля в строке перед сохранением.');
                            return;
                        }
                    }
                    postForm(getRowPayload(row));
                });
            }
            if (syncBtn) {
                syncBtn.addEventListener('click', function () {
                    postForm({
                        action: 'sync_row_to_b24',
                        product_id: row.getAttribute('data-product-id')
                    });
                });
            }
            if (checkB24Btn) {
                checkB24Btn.addEventListener('click', function () {
                    postForm({
                        action: 'check_row_in_b24',
                        product_id: row.getAttribute('data-product-id')
                    });
                });
            }
        })(rows[i]);
    }

    var selectAll = document.getElementById('select-all-rows');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            var selectors = document.querySelectorAll('.row-selector');
            for (var i = 0; i < selectors.length; i++) {
                selectors[i].checked = selectAll.checked;
            }
        });
    }

})();
</script>

<?php require 'includes/footer.php'; ?>