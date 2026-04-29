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
