<?php

/**
 * Ценообразование строк сделки Б24 → фактическая выручка со скидкой и план по тарифу.
 * PHP 5.6-совместимо.
 */

function b24SalePricingNum($v) {
    if ($v === null || $v === '') {
        return 0.0;
    }
    return floatval(str_replace(array(',', ' '), array('.', ''), (string)$v));
}

/**
 * Вытянуть из строки CRM положительную денежную сумму (первая найденная из списка ключей).
 *
 * @param array $item
 * @param array $keys
 * @return float
 */
function b24SalePricingFirstPositiveSum($item, $keys) {
    foreach ($keys as $k) {
        if (!isset($item[$k])) {
            continue;
        }
        $n = b24SalePricingNum($item[$k]);
        if ($n > 0.0001) {
            return $n;
        }
    }
    return 0.0;
}

/**
 * Разбор сумм по сырой строке товара (crm.deal.productrows / crm.item.productrow).
 * list_subtotal — ориентир «по прайсу строки» (цена × кол-во), fact_total — после скидки.
 *
 * @param array $item
 * @param float $unitPriceParsed уже извлечённая цена за единицу из строки
 * @param float $quantityParsed количество по строке
 * @return array list_subtotal (float), fact_total (float)
 */
function b24SalePricingParseRowTotals($item, $unitPriceParsed, $quantityParsed) {
    $qty = floatval($quantityParsed);
    if ($qty <= 0) {
        return array('list_subtotal' => 0.0, 'fact_total' => 0.0);
    }

    $price = floatval($unitPriceParsed);
    $listSubtotal = round($price * $qty, 2);

    $directKeys = array(
        'SUM', 'sum',
        'TOTAL', 'total', 'TOTAL_SUM', 'SUMM', 'sumFormatted',
        'PRICE_NETTO', 'BASE_SUM', 'TOTAL_WITH_DISCOUNT', 'totalWithDiscount'
    );
    $factDirect = b24SalePricingFirstPositiveSum($item, $directKeys);

    $discTypeId = 0;
    if (isset($item['DISCOUNT_TYPE_ID'])) {
        $discTypeId = intval($item['DISCOUNT_TYPE_ID']);
    } elseif (isset($item['discountTypeId'])) {
        $discTypeId = intval($item['discountTypeId']);
    }

    $discSum = 0.0;
    if (isset($item['DISCOUNT_SUM'])) {
        $discSum = b24SalePricingNum($item['DISCOUNT_SUM']);
    } elseif (isset($item['discountSum'])) {
        $discSum = b24SalePricingNum($item['discountSum']);
    }

    $discRate = 0.0;
    if (isset($item['DISCOUNT_RATE'])) {
        $discRate = b24SalePricingNum($item['DISCOUNT_RATE']);
    } elseif (isset($item['discountRate'])) {
        $discRate = b24SalePricingNum($item['discountRate']);
    }

    $discountAmt = 0.0;
    if ($discTypeId === 2) {
        $discountAmt = $listSubtotal * ($discRate / 100.0);
    } elseif ($discTypeId === 1 && $discSum > 0) {
        $discountAmt = $discSum;
    } else {
        if ($discRate > 0.0001) {
            $discountAmt = $listSubtotal * ($discRate / 100.0);
        } elseif ($discSum > 0) {
            $discountAmt = $discSum;
        }
    }

    $computedFact = round(max(0, $listSubtotal - $discountAmt), 2);
    if ($factDirect > 0.0001) {
        return array('list_subtotal' => $listSubtotal, 'fact_total' => round($factDirect, 2));
    }

    return array('list_subtotal' => $listSubtotal, 'fact_total' => $computedFact);
}

/**
 * DDL: колонки финансов строки заявки склада.
 *
 * @param PDO $db
 */
function ensureB24SaleLinesFinanceColumns($db) {
    $cols = array(
        'list_price_per_unit' => "`list_price_per_unit` decimal(14,4) NOT NULL DEFAULT 0 AFTER `price_per_unit`",
        'line_total_list' => "`line_total_list` decimal(14,2) NOT NULL DEFAULT 0 AFTER `list_price_per_unit`",
        'line_total_fact' => "`line_total_fact` decimal(14,2) NOT NULL DEFAULT 0 AFTER `line_total_list`"
    );
    foreach ($cols as $name => $sqlFragment) {
        $stmt = $db->prepare('SHOW COLUMNS FROM `b24_sale_lines` LIKE ?');
        $stmt->execute(array($name));
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec('ALTER TABLE `b24_sale_lines` ADD COLUMN ' . $sqlFragment);
        }
    }
}

/**
 * DDL: плановая выручка по строке продажи (для сравнения со скидкой).
 *
 * @param PDO $db
 */
function ensureSalesRevenuePlannedColumn($db) {
    $stmt = $db->prepare("SHOW COLUMNS FROM `sales` LIKE 'revenue_planned'");
    $stmt->execute();
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $db->exec(
            "ALTER TABLE `sales` ADD COLUMN `revenue_planned` decimal(14,2) NOT NULL DEFAULT 0 AFTER `total`"
        );
    }
}

/**
 * По строке b24_sale_lines: факт в sales и план (тип/прайс).
 *
 * @param array $line
 * @return array price_per_unit, total, revenue_planned
 */
function b24SaleLineResolveRevenueForSale($line) {
    $qty = floatval(isset($line['quantity_m']) ? $line['quantity_m'] : 0);
    $factTotalRaw = floatval(isset($line['line_total_fact']) ? $line['line_total_fact'] : 0);
    $listTotalRaw = floatval(isset($line['line_total_list']) ? $line['line_total_list'] : 0);
    $listPu = floatval(isset($line['list_price_per_unit']) ? $line['list_price_per_unit'] : 0);
    $pu = floatval(isset($line['price_per_unit']) ? $line['price_per_unit'] : 0);

    if ($qty <= 0) {
        return array('price_per_unit' => 0.0, 'total' => 0.0, 'revenue_planned' => 0.0);
    }

    if ($factTotalRaw > 0.0001) {
        $total = $factTotalRaw;
        $pricePerUnit = $total / $qty;
    } else {
        $pricePerUnit = $pu;
        $total = round($pricePerUnit * $qty, 2);
    }

    if ($listTotalRaw > 0.0001) {
        $planned = $listTotalRaw;
    } elseif ($listPu > 0.0001) {
        $planned = round($listPu * $qty, 2);
    } else {
        $planned = round($pu * $qty, 2);
    }

    return array(
        'price_per_unit' => $pricePerUnit,
        'total' => round($total, 2),
        'revenue_planned' => round($planned, 2)
    );
}

/**
 * Цена за единицу для подстановки в payload синхронизации строк в Б24 (тариф, без скидки).
 *
 * @param array $row строка из b24_sale_lines
 * @return float
 */
function b24SaleLineListPriceForBitrixSync($row) {
    $listPu = floatval(isset($row['list_price_per_unit']) ? $row['list_price_per_unit'] : 0);
    if ($listPu > 0.0001) {
        return $listPu;
    }
    return floatval(isset($row['price_per_unit']) ? $row['price_per_unit'] : 0);
}
