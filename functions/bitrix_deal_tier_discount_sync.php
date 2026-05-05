<?php

/**
 * Когда очередь склада отфильтрована (warehouse gate), всё равно выравниваем сумму строки в Б24
 * через «Сумма скидки» на товарной строке: каталожная цена × кол-во минус целевой итог по тирам (products).
 *
 * Требует crm.deal.productrows.get / set и поля DISCOUNT_TYPE_ID=2 (деньги), DISCOUNT_SUM.
 */

require_once __DIR__ . '/pricing.php';
require_once __DIR__ . '/../api/bitrix/send.php';

/**
 * Извлечь массив строк из ответа crm.deal.productrows.get / item API (как в api/webhook.php).
 *
 * @param array $resp
 * @return array|null
 */
function bitrixTierSyncUnwrapProductRows($resp)
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
 * Целевая сумма строки по тирам: метраж × (цена тира за рулон / длина рулона).
 *
 * @param array $product
 * @param float $qty
 * @return float|null null если нечего применять
 */
function bitrixTierSyncTargetLineTotal($product, $qty)
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
    $perMeter = $tierMoney / $rollLen;
    return round($perMeter * $qty, 2);
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
        return array('ok' => false, 'stage' => 'crm.deal.productrows.get', 'error' => $err);
    }

    $rows = bitrixTierSyncUnwrapProductRows($getResp);
    if (!is_array($rows) || empty($rows)) {
        return array('ok' => true, 'stage' => 'empty_rows', 'skipped' => true);
    }

    $outRows = array();
    $anyDiscount = false;

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

        $gross = round($quantity * $unitPrice, 2);
        $target = bitrixTierSyncTargetLineTotal($product, $quantity);
        if ($target === null) {
            $outRows[] = $item;
            continue;
        }

        $discount = round($gross - $target, 2);
        if ($discount < 0) {
            $discount = 0.0;
        }
        if ($discount > 0.005) {
            $anyDiscount = true;
        }

        $newRow = $item;
        $newRow['DISCOUNT_TYPE_ID'] = 2;
        $newRow['DISCOUNT_SUM'] = round($discount, 2);
        if (isset($newRow['discountTypeId'])) {
            $newRow['discountTypeId'] = 2;
        }
        if (isset($newRow['discountSum'])) {
            $newRow['discountSum'] = $newRow['DISCOUNT_SUM'];
        }

        if ($discount <= 0.005) {
            $newRow['DISCOUNT_TYPE_ID'] = 0;
            $newRow['DISCOUNT_SUM'] = 0;
            if (isset($newRow['discountTypeId'])) {
                $newRow['discountTypeId'] = 0;
            }
            if (isset($newRow['discountSum'])) {
                $newRow['discountSum'] = 0;
            }
        }

        $outRows[] = $newRow;
    }

    if (empty($outRows)) {
        return array('ok' => true, 'stage' => 'no_output_rows', 'skipped' => true);
    }

    $setPayload = array('id' => $dealId, 'rows' => $outRows);
    $setResp = sendToBitrix('crm.deal.productrows.set', $setPayload);
    if (!is_array($setResp) || isset($setResp['error'])) {
        $err = '';
        if (is_array($setResp) && isset($setResp['error_description'])) {
            $err = (string)$setResp['error_description'];
        } elseif (is_array($setResp) && isset($setResp['error'])) {
            $err = (string)$setResp['error'];
        } else {
            $err = 'productrows_set_failed';
        }
        return array('ok' => false, 'stage' => 'crm.deal.productrows.set', 'error' => $err);
    }

    return array(
        'ok' => true,
        'stage' => 'done',
        'rows' => count($outRows),
        'discount_applied' => $anyDiscount
    );
}

/**
 * Проверка флага в config (по умолчанию true).
 *
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
