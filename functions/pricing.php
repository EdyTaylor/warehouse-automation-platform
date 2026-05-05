<?php

/**
 * Resolve roll price by quantity tiers with deterministic fallback.
 *
 * Returns:
 * - price (float)
 * - sourceTier (string): price_1_4|price_5_9|price_10_19|price_20_plus|meter_roll_fallback|none
 * - fallbackUsed (bool)
 */
function resolveTierPrice($product, $qtyRolls) {
    $qty = intval($qtyRolls);
    if ($qty < 1) {
        $qty = 1;
    }

    $tiers = array(
        'price_1_4' => floatval(isset($product['price_1_4']) ? $product['price_1_4'] : 0),
        'price_5_9' => floatval(isset($product['price_5_9']) ? $product['price_5_9'] : 0),
        'price_10_19' => floatval(isset($product['price_10_19']) ? $product['price_10_19'] : 0),
        'price_20_plus' => floatval(isset($product['price_20_plus']) ? $product['price_20_plus'] : 0)
    );

    if ($qty <= 4) {
        $targetTier = 'price_1_4';
    } elseif ($qty <= 9) {
        $targetTier = 'price_5_9';
    } elseif ($qty <= 19) {
        $targetTier = 'price_10_19';
    } else {
        $targetTier = 'price_20_plus';
    }

    if ($tiers[$targetTier] > 0) {
        return array(
            'price' => $tiers[$targetTier],
            'sourceTier' => $targetTier,
            'fallbackUsed' => false
        );
    }

    // Fallback priority: first base tier 1-4, then nearest previous filled tier.
    if ($tiers['price_1_4'] > 0) {
        return array(
            'price' => $tiers['price_1_4'],
            'sourceTier' => 'price_1_4',
            'fallbackUsed' => ($targetTier !== 'price_1_4')
        );
    }

    $previousOrder = array(
        'price_1_4' => array(),
        'price_5_9' => array('price_1_4'),
        'price_10_19' => array('price_5_9', 'price_1_4'),
        'price_20_plus' => array('price_10_19', 'price_5_9', 'price_1_4')
    );

    if (isset($previousOrder[$targetTier])) {
        foreach ($previousOrder[$targetTier] as $candidateTier) {
            if ($tiers[$candidateTier] > 0) {
                return array(
                    'price' => $tiers[$candidateTier],
                    'sourceTier' => $candidateTier,
                    'fallbackUsed' => true
                );
            }
        }
    }

    $pricePerMeter = floatval(isset($product['price_per_meter']) ? $product['price_per_meter'] : 0);
    $rollLength = floatval(isset($product['roll_length']) ? $product['roll_length'] : 0);
    if ($pricePerMeter > 0 && $rollLength > 0) {
        return array(
            'price' => $pricePerMeter * $rollLength,
            'sourceTier' => 'meter_roll_fallback',
            'fallbackUsed' => true
        );
    }

    return array(
        'price' => 0.0,
        'sourceTier' => 'none',
        'fallbackUsed' => true
    );
}

function formatTierSourceLabel($sourceTier) {
    $map = array(
        'price_1_4' => 'Тир 1-4',
        'price_5_9' => 'Тир 5-9',
        'price_10_19' => 'Тир 10-19',
        'price_20_plus' => 'Тир 20+',
        'meter_roll_fallback' => 'Fallback: цена за метр * длина рулона',
        'none' => 'Цена не задана'
    );
    $key = (string)$sourceTier;
    return isset($map[$key]) ? $map[$key] : $key;
}

function getTargetTierByQty($qtyRolls) {
    $qty = intval($qtyRolls);
    if ($qty < 1) {
        $qty = 1;
    }
    if ($qty <= 4) {
        return 'price_1_4';
    }
    if ($qty <= 9) {
        return 'price_5_9';
    }
    if ($qty <= 19) {
        return 'price_10_19';
    }
    return 'price_20_plus';
}

/**
 * Сколько рулонов соответствует строке сделки для тиров: метраж / длина рулона (ceil), минимум 1.
 * Если длина рулона не задана — используем ceil(кол-во) как целое число единиц.
 *
 * @param array $product
 * @param float $quantityLine
 * @return int
 */
function pricingRollCountForTier($product, $quantityLine) {
    $qty = floatval($quantityLine);
    if ($qty <= 0) {
        return 1;
    }
    $rollLength = floatval(isset($product['roll_length']) ? $product['roll_length'] : 0);
    if ($rollLength > 0.0001) {
        $rolls = (int)ceil($qty / $rollLength);
        if ($rolls < 1) {
            $rolls = 1;
        }
        return $rolls;
    }
    $qi = (int)ceil($qty);
    if ($qi < 1) {
        $qi = 1;
    }
    return $qi;
}

/**
 * Розничная цена за метр по тирам для строки сделки (quantity = метраж в строке).
 * Учитывает resolveTierPrice + fallbacks. Нужна в очереди склада: иначе при цене > 0 из Б24
 * тир не применяется и при смене количества (напр. 1 м) остаётся каталожная цена.
 *
 * @param array $product
 * @param float $quantityLine
 * @return float|null
 */
function pricingTierRetailPricePerMeter($product, $quantityLine) {
    $qty = floatval($quantityLine);
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

function explainTierPriceResolution($product, $qtyRolls) {
    $resolved = resolveTierPrice($product, $qtyRolls);
    $targetTier = getTargetTierByQty($qtyRolls);
    $reason = 'direct_tier_match';

    if ($resolved['sourceTier'] === 'meter_roll_fallback') {
        $reason = 'fallback_meter_roll';
    } elseif ($resolved['sourceTier'] === 'none') {
        $reason = 'missing_all_prices';
    } elseif ($resolved['sourceTier'] === 'price_1_4' && $targetTier !== 'price_1_4') {
        $reason = 'fallback_to_base_tier';
    } elseif ($resolved['sourceTier'] !== $targetTier) {
        $reason = 'fallback_to_previous_tier';
    }

    return array(
        'qty' => intval($qtyRolls),
        'targetTier' => $targetTier,
        'targetLabel' => formatTierSourceLabel($targetTier),
        'price' => floatval($resolved['price']),
        'sourceTier' => $resolved['sourceTier'],
        'sourceLabel' => formatTierSourceLabel($resolved['sourceTier']),
        'fallbackUsed' => !empty($resolved['fallbackUsed']),
        'reason' => $reason
    );
}

function getTierAutofillSuggestions($rawTiers) {
    $tierOrder = array('price_1_4', 'price_5_9', 'price_10_19', 'price_20_plus');
    $normalized = array();
    $isEmpty = array();
    $suggestions = array();
    $lastKnown = 0.0;

    foreach ($tierOrder as $tierKey) {
        $value = isset($rawTiers[$tierKey]) ? trim((string)$rawTiers[$tierKey]) : '';
        $value = str_replace(' ', '', $value);
        $value = str_replace(',', '.', $value);
        $isEmpty[$tierKey] = ($value === '');
        $normalized[$tierKey] = $isEmpty[$tierKey] ? 0.0 : floatval($value);
    }

    if ($normalized['price_1_4'] > 0) {
        $lastKnown = $normalized['price_1_4'];
    }

    foreach ($tierOrder as $tierKey) {
        if ($normalized[$tierKey] > 0) {
            $lastKnown = $normalized[$tierKey];
            continue;
        }
        if (!$isEmpty[$tierKey]) {
            continue;
        }
        if ($lastKnown > 0) {
            $suggestions[$tierKey] = $lastKnown;
        }
    }

    return $suggestions;
}

/**
 * Закуп «с доставкой за рулон» в пересчёте на один метр (для PURCHASING_PRICE в Б24 и складских строк).
 * Приоритет столбца purchase_delivered_per_meter; иначе delivery_price / roll_length по карточке товара.
 *
 * @param array $product строка из products или массив с нужными ключами
 * @return float
 */
function resolveProductPurchaseDeliveredPerMeter(array $product) {
    $explicit = floatval(isset($product['purchase_delivered_per_meter']) ? $product['purchase_delivered_per_meter'] : 0);
    if ($explicit > 0) {
        return $explicit;
    }
    $roll = floatval(isset($product['roll_length']) ? $product['roll_length'] : 0);
    $delivery = floatval(isset($product['delivery_price']) ? $product['delivery_price'] : 0);
    if ($roll > 0 && $delivery > 0) {
        return $delivery / $roll;
    }
    return 0.0;
}

/**
 * Optional manual helper for quick verification in dev/debug.
 * Open with ?debug_price_selfcheck=1 on pages that include this file.
 */
function tierPricingSelfCheckCases() {
    $product = array(
        'price_1_4' => 1200,
        'price_5_9' => 0,
        'price_10_19' => 950,
        'price_20_plus' => 0,
        'price_per_meter' => 40,
        'roll_length' => 30
    );
    return array(
        'qty_3_direct_1_4' => resolveTierPrice($product, 3),
        'qty_7_fallback_to_1_4' => resolveTierPrice($product, 7),
        'qty_12_direct_10_19' => resolveTierPrice($product, 12),
        'qty_25_fallback_to_1_4_before_prev' => resolveTierPrice($product, 25),
        'all_empty_then_meter_roll' => resolveTierPrice(array(
            'price_1_4' => 0,
            'price_5_9' => 0,
            'price_10_19' => 0,
            'price_20_plus' => 0,
            'price_per_meter' => 33,
            'roll_length' => 30
        ), 8)
    );
}
