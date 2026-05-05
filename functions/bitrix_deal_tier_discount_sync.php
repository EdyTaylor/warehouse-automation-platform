<?php

/**
 * При skipped_warehouse_gate: выровнять цену за единицу по тирам из products (цена_тира_за_рулон / roll_length),
 * скидку на строке обнулять — без «моста» каталожная цена + огромная скидка.
 *
 * Сначала crm.item.productrow (list/update), затем фолбэк crm.deal.productrows.
 */

require_once __DIR__ . '/pricing.php';
require_once __DIR__ . '/../api/bitrix/send.php';

/**
 * Ответ crm.item.productrow.list — тот же разбор, что в api/webhook.php.
 *
 * @param array $resp
 * @return array|null
 */
function bitrixTierSyncUnwrapItemProductRowList($resp)
{
    if (!is_array($resp) || isset($resp['error']) || !array_key_exists('result', $resp)) {
        return null;
    }
    $r = $resp['result'];
    if (!is_array($r)) {
        return array();
    }
    if (isset($r['productRows']) && is_array($r['productRows'])) {
        return $r['productRows'];
    }
    if (isset($r['rows']) && is_array($r['rows'])) {
        return $r['rows'];
    }
    if (isset($r[0]) && is_array($r[0])) {
        return $r;
    }
    if (empty($r)) {
        return array();
    }
    $vals = array_values($r);
    if (!empty($vals) && isset($vals[0]) && is_array($vals[0])) {
        return $vals;
    }
    return array();
}

/**
 * Извлечь массив строк из ответа crm.deal.productrows.get.
 *
 * @param array $resp
 * @return array|null
 */
function bitrixTierSyncUnwrapClassicProductRows($resp)
{
    if (!is_array($resp) || isset($resp['error']) || !array_key_exists('result', $resp)) {
        return null;
    }
    $r = $resp['result'];
    if (!is_array($r)) {
        return array();
    }
    if (isset($r['productRows']) && is_array($r['productRows'])) {
        return $r['productRows'];
    }
    if (isset($r[0]) && is_array($r[0])) {
        return $r;
    }
    if (isset($r['PRODUCT_ID']) || isset($r['productId']) || isset($r['PRODUCT_NAME']) || isset($r['ID'])) {
        return array($r);
    }
    if (empty($r)) {
        return array();
    }
    $vals = array_values($r);
    if (!empty($vals) && isset($vals[0]) && is_array($vals[0])
        && (isset($vals[0]['PRODUCT_ID']) || isset($vals[0]['PRODUCT_NAME']))) {
        return $vals;
    }
    return $r;
}

/**
 * @param object $db PDO
 * @param int $b24ProductId
 * @return array|null
 */
function bitrixTierSyncLoadProductByB24Id($db, $b24ProductId)
{
    $b24ProductId = intval($b24ProductId);
    if ($b24ProductId <= 0) {
        return null;
    }
    $stmt = $db->prepare("
        SELECT id, b24_product_id, roll_length, price_per_meter, price_1_4, price_5_9, price_10_19, price_20_plus
        FROM products
        WHERE b24_product_id = ?
        LIMIT 1
    ");
    $stmt->execute(array($b24ProductId));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}

/**
 * Целевая цена за единицу в строке (метр): цена тира за рулон / длина рулона.
 *
 * @param array $product
 * @param float $qty метраж/кол-во в строке
 * @return float|null
 */
function bitrixTierSyncTargetUnitPrice($product, $qty)
{
    $qty = floatval($qty);
    if ($qty <= 0) {
        return null;
    }
    $rolls = pricingRollCountForTier($product, $qty);
    $tier = resolveTierPrice($product, $rolls);
    $tierMoney = floatval(isset($tier['price']) ? $tier['price'] : 0);
    if ($tierMoney <= 0) {
        return null;
    }
    $rollLen = floatval(isset($product['roll_length']) ? $product['roll_length'] : 0);
    if ($rollLen <= 0.0001) {
        return null;
    }
    return round($tierMoney / $rollLen, 2);
}

/**
 * Универсальный API строк (crm.item.productrow.update): price + обнуление скидки.
 *
 * @param object $db
 * @param int $dealId
 * @return array ok, stage, api, rows_updated, row_errors
 */
function bitrixDealTierDiscountSyncViaItemRows($db, $dealId)
{
    $dealId = intval($dealId);
    $listResp = sendToBitrix('crm.item.productrow.list', array(
        'filter' => array(
            '=ownerType' => 'D',
            '=ownerId' => $dealId,
        ),
    ));

    if (!is_array($listResp) || isset($listResp['error'])) {
        $err = '';
        if (is_array($listResp) && isset($listResp['error_description'])) {
            $err = (string)$listResp['error_description'];
        } elseif (is_array($listResp) && isset($listResp['error'])) {
            $err = (string)$listResp['error'];
        } else {
            $err = 'item_productrow_list_failed';
        }
        return array('ok' => false, 'stage' => 'crm.item.productrow.list', 'api' => 'item', 'error' => $err);
    }

    $rows = bitrixTierSyncUnwrapItemProductRowList($listResp);
    if ($rows === null || empty($rows)) {
        return array('ok' => true, 'stage' => 'item_empty', 'api' => 'item', 'skipped' => true);
    }

    $rowErrors = array();
    $updated = 0;
    $anyPriceWrite = false;
    $tierCandidates = 0;

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $rowId = isset($row['id']) ? intval($row['id']) : 0;
        if ($rowId <= 0) {
            continue;
        }

        $b24Pid = isset($row['productId']) ? intval($row['productId']) : 0;
        $unitPrice = floatval(isset($row['price']) ? $row['price'] : 0);
        $quantity = floatval(isset($row['quantity']) ? $row['quantity'] : 0);

        if ($b24Pid <= 0 || $quantity <= 0) {
            continue;
        }

        $product = bitrixTierSyncLoadProductByB24Id($db, $b24Pid);
        if (!$product) {
            continue;
        }

        $targetUnit = bitrixTierSyncTargetUnitPrice($product, $quantity);
        if ($targetUnit === null) {
            continue;
        }

        $tierCandidates++;

        $discSum = 0.0;
        if (isset($row['discountSum']) && $row['discountSum'] !== '' && $row['discountSum'] !== null) {
            $discSum = floatval($row['discountSum']);
        }
        $needsWrite = (abs($unitPrice - $targetUnit) > 0.005) || ($discSum > 0.005);
        if (!$needsWrite) {
            continue;
        }

        $fields = array(
            'price' => $targetUnit,
            'discountTypeId' => 1,
            'discountSum' => 0,
            'discountRate' => 0,
            'customized' => 'Y',
        );

        if (abs($unitPrice - $targetUnit) > 0.005) {
            $anyPriceWrite = true;
        }

        $updResp = sendToBitrix('crm.item.productrow.update', array(
            'id' => $rowId,
            'fields' => $fields,
        ));

        if (!is_array($updResp) || isset($updResp['error'])) {
            $e = '';
            if (is_array($updResp) && isset($updResp['error_description'])) {
                $e = (string)$updResp['error_description'];
            } elseif (is_array($updResp) && isset($updResp['error'])) {
                $e = (string)$updResp['error'];
            } else {
                $e = 'update_failed';
            }
            $rowErrors[] = array('row_id' => $rowId, 'error' => $e);
        } else {
            $updated++;
        }
    }

    if ($updated === 0 && empty($rowErrors) && $tierCandidates === 0) {
        return array(
            'ok' => true,
            'stage' => 'item_no_matching_products',
            'api' => 'item',
            'skipped' => true,
            'rows_seen' => count($rows)
        );
    }

    if ($updated === 0 && empty($rowErrors) && $tierCandidates > 0) {
        return array(
            'ok' => true,
            'stage' => 'item_already_aligned',
            'api' => 'item',
            'skipped' => false,
            'rows_seen' => count($rows),
            'tier_candidates' => $tierCandidates
        );
    }

    return array(
        'ok' => count($rowErrors) === 0,
        'stage' => count($rowErrors) > 0 ? 'crm.item.productrow.update_partial' : 'done',
        'api' => 'item',
        'rows_updated' => $updated,
        'unit_price_applied' => $anyPriceWrite,
        'discount_applied' => false,
        'row_errors' => $rowErrors,
        'error' => count($rowErrors) > 0 && isset($rowErrors[0]['error']) ? $rowErrors[0]['error'] : ''
    );
}

/**
 * Классический crm.deal.productrows: PRICE = цена за метр по тиру, скидка 0.
 *
 * @param object $db
 * @param int $dealId
 * @return array
 */
function bitrixDealTierDiscountSyncViaClassicProductRows($db, $dealId)
{
    $dealId = intval($dealId);
    $getResp = sendToBitrix('crm.deal.productrows.get', array('id' => $dealId));
    if (!is_array($getResp) || isset($getResp['error'])) {
        $err = '';
        if (is_array($getResp) && isset($getResp['error_description'])) {
            $err = (string)$getResp['error_description'];
        } elseif (is_array($getResp) && isset($getResp['error'])) {
            $err = (string)$getResp['error'];
        } else {
            $err = 'productrows_get_failed';
        }
        return array('ok' => false, 'stage' => 'crm.deal.productrows.get', 'api' => 'classic', 'error' => $err);
    }

    $rows = bitrixTierSyncUnwrapClassicProductRows($getResp);
    if (!is_array($rows) || empty($rows)) {
        return array('ok' => true, 'stage' => 'classic_empty', 'api' => 'classic', 'skipped' => true);
    }

    $outRows = array();
    $anyPriceChange = false;

    foreach ($rows as $item) {
        if (!is_array($item)) {
            continue;
        }

        $b24Pid = 0;
        if (isset($item['PRODUCT_ID']) && $item['PRODUCT_ID'] !== '' && $item['PRODUCT_ID'] !== null) {
            $b24Pid = intval($item['PRODUCT_ID']);
        } elseif (isset($item['productId']) && $item['productId'] !== '' && $item['productId'] !== null) {
            $b24Pid = intval($item['productId']);
        }

        $unitPrice = 0.0;
        if (isset($item['PRICE']) && $item['PRICE'] !== '' && $item['PRICE'] !== null) {
            $unitPrice = floatval(str_replace(',', '.', (string)$item['PRICE']));
        } elseif (isset($item['price']) && $item['price'] !== '' && $item['price'] !== null) {
            $unitPrice = floatval(str_replace(',', '.', (string)$item['price']));
        }

        $quantity = 0.0;
        if (isset($item['QUANTITY']) && $item['QUANTITY'] !== '' && $item['QUANTITY'] !== null) {
            $quantity = floatval(str_replace(',', '.', (string)$item['QUANTITY']));
        } elseif (isset($item['quantity']) && $item['quantity'] !== '' && $item['quantity'] !== null) {
            $quantity = floatval(str_replace(',', '.', (string)$item['quantity']));
        }

        if ($b24Pid <= 0 || $quantity <= 0) {
            $outRows[] = $item;
            continue;
        }

        $product = bitrixTierSyncLoadProductByB24Id($db, $b24Pid);
        if (!$product) {
            $outRows[] = $item;
            continue;
        }

        $targetUnit = bitrixTierSyncTargetUnitPrice($product, $quantity);
        if ($targetUnit === null) {
            $outRows[] = $item;
            continue;
        }

        if (abs($unitPrice - $targetUnit) > 0.005) {
            $anyPriceChange = true;
        }

        $newRow = $item;
        $newRow['PRICE'] = $targetUnit;
        $newRow['DISCOUNT_TYPE_ID'] = 0;
        $newRow['DISCOUNT_SUM'] = 0;
        if (isset($newRow['discountTypeId'])) {
            $newRow['discountTypeId'] = 0;
        }
        if (isset($newRow['discountSum'])) {
            $newRow['discountSum'] = 0;
        }
        if (isset($newRow['price'])) {
            $newRow['price'] = $targetUnit;
        }

        $outRows[] = $newRow;
    }

    if (empty($outRows)) {
        return array('ok' => true, 'stage' => 'classic_no_output', 'api' => 'classic', 'skipped' => true);
    }

    $setResp = sendToBitrix('crm.deal.productrows.set', array('id' => $dealId, 'rows' => $outRows));
    if (!is_array($setResp) || isset($setResp['error'])) {
        $err = '';
        if (is_array($setResp) && isset($setResp['error_description'])) {
            $err = (string)$setResp['error_description'];
        } elseif (is_array($setResp) && isset($setResp['error'])) {
            $err = (string)$setResp['error'];
        } else {
            $err = 'productrows_set_failed';
        }
        return array('ok' => false, 'stage' => 'crm.deal.productrows.set', 'api' => 'classic', 'error' => $err);
    }

    return array(
        'ok' => true,
        'stage' => 'done',
        'api' => 'classic',
        'rows' => count($outRows),
        'unit_price_applied' => $anyPriceChange,
        'discount_applied' => false
    );
}

/**
 * @param object $db PDO
 * @param int $dealId
 * @return array
 */
function bitrixDealTierDiscountSyncWhenQueueSkipped($db, $dealId)
{
    $dealId = intval($dealId);
    if ($dealId <= 0) {
        return array('ok' => false, 'stage' => 'invalid_deal', 'error' => 'bad_deal_id');
    }

    $itemRes = bitrixDealTierDiscountSyncViaItemRows($db, $dealId);

    $rowsUpdated = intval(isset($itemRes['rows_updated']) ? $itemRes['rows_updated'] : 0);
    $rowErrors = isset($itemRes['row_errors']) && is_array($itemRes['row_errors']) ? $itemRes['row_errors'] : array();

    if (!empty($rowErrors) && $rowsUpdated === 0) {
        $itemRes['used_api'] = 'crm.item.productrow';
        return $itemRes;
    }

    if ($rowsUpdated > 0) {
        $itemRes['used_api'] = 'crm.item.productrow';
        return $itemRes;
    }

    if (empty($itemRes['ok']) && isset($itemRes['stage']) && $itemRes['stage'] === 'crm.item.productrow.list') {
        $classicRes = bitrixDealTierDiscountSyncViaClassicProductRows($db, $dealId);
        $classicRes['item_precheck'] = $itemRes;
        $classicRes['used_api'] = 'crm.deal.productrows';
        return $classicRes;
    }

    if (!empty($itemRes['skipped'])) {
        $classicRes = bitrixDealTierDiscountSyncViaClassicProductRows($db, $dealId);
        $classicRes['item_precheck'] = $itemRes;
        $classicRes['used_api'] = 'crm.deal.productrows';
        return $classicRes;
    }

    $itemRes['used_api'] = 'crm.item.productrow';
    return $itemRes;
}

/**
 * @param array $cfg
 * @return bool
 */
function bitrixTierDiscountSyncEnabled($cfg)
{
    if (isset($cfg['tier_discount_sync_when_warehouse_skipped'])) {
        return !empty($cfg['tier_discount_sync_when_warehouse_skipped']);
    }
    return true;
}
