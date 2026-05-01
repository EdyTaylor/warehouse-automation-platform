<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('PUBLIC_AJAX_MODE', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Context;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Crm;
use Bitrix\Catalog;
use Bitrix\Currency\CurrencyManager;

/* ================= НАСТРОЙКИ ================= */

$TOKEN = '2026';
$DEFAULT_STORE_FROM_ID = 1;
$STORE_TO_ID           = 2;
$SITE_ID_FOR_DOC       = 's1';

$FLAG_READY = 'UF_CRM_1769188951';
$REST_WEBHOOK = 'https://hc-crm.ru/rest/1146/65okhepmu99xdwey/';

$STORE_MAPPING = [
    1 => 'Основной склад',
    2 => 'Отгрузка',
    3 => 'Склад списанной продукции',
    4 => 'Точка в ТЦЕ',
    5 => 'Для выставки'
];

$ROLLBACK_STAGE = [
    0  => 'UC_IVAU8Z',
    5  => 'C5:UC_JC2UWK',
    9  => 'C9:UC_OEBC4M',
    10 => 'C10:UC_3920EY',
];

/* ================= ЛОГ ================= */

function logMsg($msg)
{
    $t = date('Y-m-d H:i:s');
    file_put_contents(__DIR__.'/shipping_log.txt', "$t | $msg\n", FILE_APPEND);
}

/* ================= REST ================= */

function addTimelineRest($dealId, $text, $webhook)
{
    if ($dealId <= 0 || trim($text) === '') return false;

    $url = rtrim($webhook, '/') . '/crm.timeline.comment.add.json';

    $postData = [
        'fields' => [
            'ENTITY_TYPE' => 'deal',
            'ENTITY_ID'   => $dealId,
            'COMMENT'     => $text,
        ]
    ];

    $httpClient = new HttpClient();
    $httpClient->setTimeout(10);
    $httpClient->setStreamTimeout(10);

    $response = $httpClient->post($url, http_build_query($postData));

    return $response !== false;
}

function getDocumentItemPriceData(array $row, $productId)
{
    $price = 0.0;
    $currency = '';

    $productInfo = \CCatalogProduct::GetByID($productId);
    if (!empty($productInfo['PURCHASING_PRICE'])) {
        $price = (float)$productInfo['PURCHASING_PRICE'];
        $currency = isset($productInfo['PURCHASING_CURRENCY'])
            ? (string)$productInfo['PURCHASING_CURRENCY']
            : '';
    }

    if ($currency === '' && !empty($productInfo) && is_array($productInfo)) {
        $currency = isset($productInfo['PURCHASING_CURRENCY'])
            ? (string)$productInfo['PURCHASING_CURRENCY']
            : '';
    }

    if ($currency === '' && Loader::includeModule('currency')) {
        $currency = (string)CurrencyManager::getBaseCurrency();
    }

    return [
        'PRICE' => $price,
        'CURRENCY' => $currency,
    ];
}

/* ================= ПРОВЕРКА ================= */

if (!Loader::includeModule('crm') || !Loader::includeModule('catalog') || !Loader::includeModule('sale')) {
    die('Modules not loaded');
}

$request  = Context::getCurrent()->getRequest();
$DEAL_ID  = (int)$request->get('deal_id');
$reqToken = $request->get('token');

if ($reqToken !== $TOKEN || $DEAL_ID <= 0) {
    die('Access denied');
}

logMsg("=== START Deal {$DEAL_ID} ===");

/* ================= ДАННЫЕ ================= */

$deal = Crm\DealTable::getRow([
    'filter' => ['=ID' => $DEAL_ID],
    'select' => ['ID', 'CATEGORY_ID', 'ASSIGNED_BY_ID', $FLAG_READY],
]);

$dealCategory = (int)$deal['CATEGORY_ID'];
$dealResponsibleId = (int)$deal['ASSIGNED_BY_ID'];

if ($dealResponsibleId <= 0) {
    $dealResponsibleId = 1;
}

$rows = Crm\ProductRowTable::getList([
    'select' => [
        'ID',
        'OWNER_TYPE',
        'OWNER_ID',
        'PRODUCT_ID',
        'PRODUCT_NAME',
        'QUANTITY',
        'PRICE',
        'PRICE_EXCLUSIVE',
    ],
    'filter' => [
        '=OWNER_TYPE' => 'D',
        '=OWNER_ID'   => $DEAL_ID,
    ],
])->fetchAll();

$conn = Application::getConnection();
$conn->startTransaction();

$rowsForMove = [];
$alreadyOnTargetStore = [];
$insufficientItems = [];
$movedItems = [];
$hasInsufficient = false;

try {

    foreach ($rows as $row) {

        $pid   = (int)$row['PRODUCT_ID'];
        $qty   = (float)$row['QUANTITY'];
        $rowId = (int)$row['ID'];
        $pName = $row['PRODUCT_NAME'];

        if ($pid <= 0 || $qty <= 0) continue;

        // 🔥 ИДЕАЛЬНОЕ определение склада
        $storeId = 0;

        if ($storeId <= 0) {
            $res = $conn->query("
                SELECT STORE_ID FROM b_crm_product_row_reservation
                WHERE ROW_ID = {$rowId} LIMIT 1
            ")->fetch();

            if (!empty($res['STORE_ID'])) {
                $storeId = (int)$res['STORE_ID'];
            } else {
                $storeId = $DEFAULT_STORE_FROM_ID;
            }
        }

        // уже на складе отгрузки
        if ($storeId == $STORE_TO_ID) {
            $alreadyOnTargetStore[] = [
                'name' => $pName,
                'quantity' => $qty,
                'store' => $STORE_MAPPING[$STORE_TO_ID]
            ];
            continue;
        }

        // проверка остатков на исходном складе
        $stock = Catalog\StoreProductTable::getRow([
            'filter' => [
                '=PRODUCT_ID' => $pid,
                '=STORE_ID' => $storeId
            ],
            'select' => ['AMOUNT'],
        ]);

        $available = $stock ? (float)$stock['AMOUNT'] : 0;

        if ($available < $qty) {
            $hasInsufficient = true;
            $insufficientItems[] = [
                'name' => $pName,
                'quantity' => $qty,
                'available' => $available,
                'store' => isset($STORE_MAPPING[$storeId]) ? $STORE_MAPPING[$storeId] : "Склад {$storeId}"
            ];
            continue;
        }

        $row['STORE_FROM_ID'] = $storeId;
        $rowsForMove[] = $row;
    }

    if ($hasInsufficient) {
        Crm\DealTable::update($DEAL_ID, [
            'STAGE_ID' => $ROLLBACK_STAGE[$dealCategory]
        ]);
    }

    if (!empty($rowsForMove)) {

        $documentTableName = Catalog\StoreDocumentTable::getTableName();
        $elementTableName = Catalog\StoreDocumentElementTable::getTableName();
        $hasPurchasingPriceField = false;
        $hasPriceField = false;
        $hasPurchasingCurrencyField = false;
        $hasBasePriceField = false;
        $hasCurrencyField = false;
        $hasTotalField = false;
        $hasShipmentIdField = false;
        $documentTotal = 0.0;
        $documentCurrency = '';

        $priceFieldCheck = $conn->query("SHOW COLUMNS FROM `{$elementTableName}` LIKE 'PURCHASING_PRICE'");
        if ($priceFieldCheck && $priceFieldCheck->fetch()) {
            $hasPurchasingPriceField = true;
        }

        $priceFieldCheck = $conn->query("SHOW COLUMNS FROM `{$elementTableName}` LIKE 'PRICE'");
        if ($priceFieldCheck && $priceFieldCheck->fetch()) {
            $hasPriceField = true;
        }

        $priceFieldCheck = $conn->query("SHOW COLUMNS FROM `{$elementTableName}` LIKE 'PURCHASING_CURRENCY'");
        if ($priceFieldCheck && $priceFieldCheck->fetch()) {
            $hasPurchasingCurrencyField = true;
        }

        $priceFieldCheck = $conn->query("SHOW COLUMNS FROM `{$elementTableName}` LIKE 'BASE_PRICE'");
        if ($priceFieldCheck && $priceFieldCheck->fetch()) {
            $hasBasePriceField = true;
        }

        $documentFieldCheck = $conn->query("SHOW COLUMNS FROM `{$documentTableName}` LIKE 'TOTAL'");
        if ($documentFieldCheck && $documentFieldCheck->fetch()) {
            $hasTotalField = true;
        }

        $documentFieldCheck = $conn->query("SHOW COLUMNS FROM `{$documentTableName}` LIKE 'CURRENCY'");
        if ($documentFieldCheck && $documentFieldCheck->fetch()) {
            $hasCurrencyField = true;
        }

        $documentFieldCheck = $conn->query("SHOW COLUMNS FROM `{$documentTableName}` LIKE 'SHIPMENT_ID'");
        if ($documentFieldCheck && $documentFieldCheck->fetch()) {
            $hasShipmentIdField = true;
        }

        $docRes = Catalog\StoreDocumentTable::add([
            'DOC_TYPE' => 'M',
            'SITE_ID' => $SITE_ID_FOR_DOC,
            'DATE_DOCUMENT' => new DateTime(),
            'CREATED_BY' => $dealResponsibleId,
            'MODIFIED_BY' => $dealResponsibleId,
            'RESPONSIBLE_ID' => $dealResponsibleId,
            'TITLE' => "Перемещение товаров на склад 'Отгрузка' в сделке №{$DEAL_ID}",
            'STATUS' => 'N',
        ]);

        if (!$docRes->isSuccess()) {
            throw new \Exception('Store document add failed: '.implode('; ', $docRes->getErrorMessages()));
        }

        $docId = $docRes->getId();

        if ($hasShipmentIdField) {
            $conn->queryExecute("
                UPDATE `{$documentTableName}`
                SET SHIPMENT_ID = 0
                WHERE ID = {$docId}
            ");
        }

        foreach ($rowsForMove as $row) {

            $pid   = (int)$row['PRODUCT_ID'];
            $qty   = (float)$row['QUANTITY'];
            $rowId = (int)$row['ID'];
            $pName = $row['PRODUCT_NAME'];
            $storeFromId = (int)$row['STORE_FROM_ID'];

            $priceData = getDocumentItemPriceData($row, $pid);
            $price = (float)$priceData['PRICE'];
            $currency = (string)$priceData['CURRENCY'];
            $priceForDocument = round($price, 2);

            if ($documentCurrency === '' && $currency !== '') {
                $documentCurrency = $currency;
            }

            $docElementFields = [
                'DOC_ID'     => $docId,
                'ELEMENT_ID' => $pid,
                'AMOUNT'     => $qty,
                'STORE_FROM' => $storeFromId,
                'STORE_TO'   => $STORE_TO_ID,
            ];

            if ($price > 0) {
                $documentTotal += ($priceForDocument * $qty);
            }

            if ($hasPurchasingPriceField) {
                $docElementFields['PURCHASING_PRICE'] = $priceForDocument;
            }
            if ($hasPriceField) {
                $docElementFields['PRICE'] = $priceForDocument;
            }
            if ($hasBasePriceField) {
                $docElementFields['BASE_PRICE'] = $priceForDocument;
            }
            if ($hasPurchasingCurrencyField && $currency !== '') {
                $docElementFields['PURCHASING_CURRENCY'] = $currency;
            }

            $docElementRes = Catalog\StoreDocumentElementTable::add($docElementFields);

            if (!$docElementRes->isSuccess()) {
                throw new \Exception('Store document element add failed: '.implode('; ', $docElementRes->getErrorMessages()));
            }

            $conn->queryExecute("UPDATE b_crm_product_row_reservation SET STORE_ID = {$STORE_TO_ID} WHERE ROW_ID = {$rowId}");

            $movedItems[] = [
                'name' => $pName,
                'quantity' => $qty,
                'from_store' => isset($STORE_MAPPING[$storeFromId]) ? $STORE_MAPPING[$storeFromId] : "Склад {$storeFromId}"
            ];

            logMsg("Doc {$docId} item {$pid}: qty={$qty}, price={$priceForDocument}, currency={$currency}, from={$storeFromId}, to={$STORE_TO_ID}");
        }

        // STORE_ID фикс
        $dealRows = \CCrmProductRow::LoadRows('D', $DEAL_ID);

        foreach ($dealRows as &$r) {
            foreach ($rowsForMove as $movedRow) {
                if ($r['ID'] == $movedRow['ID']) {
                    $r['STORE_ID'] = $STORE_TO_ID;
                }
            }
        }
        unset($r);

        \CCrmProductRow::SaveRows('D', $DEAL_ID, $dealRows);

        $documentUpdateFields = [
            'MODIFIED_BY' => $dealResponsibleId,
            'RESPONSIBLE_ID' => $dealResponsibleId,
            'STATUS_BY' => $dealResponsibleId,
        ];

        if ($hasTotalField) {
            $documentUpdateFields['TOTAL'] = round($documentTotal, 2);
        }
        if ($hasCurrencyField && $documentCurrency !== '') {
            $documentUpdateFields['CURRENCY'] = $documentCurrency;
        }

        $documentUpdateResult = \CCatalogDocs::Update($docId, $documentUpdateFields);

        if (!$documentUpdateResult) {
            global $APPLICATION;
            $exception = is_object($APPLICATION) ? $APPLICATION->GetException() : null;
            $errorMessage = $exception ? $exception->GetString() : 'Unknown error while updating store document';
            throw new \Exception($errorMessage);
        }

        $conductCurrency = $documentCurrency;
        if ($conductCurrency === '' && Loader::includeModule('currency')) {
            $conductCurrency = (string)CurrencyManager::getBaseCurrency();
        }

        $movingConductResult = \CCatalogMovingDocs::conductDocument(
            $docId,
            $dealResponsibleId,
            $conductCurrency,
            0
        );

        if (!$movingConductResult) {
            global $APPLICATION;
            $exception = is_object($APPLICATION) ? $APPLICATION->GetException() : null;
            $errorMessage = $exception ? $exception->GetString() : 'Unknown error while conducting moving document';
            throw new \Exception($errorMessage);
        }

                $statusUpdateResult = \CCatalogDocs::Update($docId, [
            'MODIFIED_BY' => $dealResponsibleId,
            'STATUS_BY' => $dealResponsibleId,
            'STATUS' => 'Y',
        ]);

        if (!$statusUpdateResult) {
            global $APPLICATION;
            $exception = is_object($APPLICATION) ? $APPLICATION->GetException() : null;
            $errorMessage = $exception ? $exception->GetString() : 'Unknown error while finalizing moving document status';
            throw new \Exception($errorMessage);
        }

        $docRow = $conn->query("
            SELECT *
            FROM `{$documentTableName}`
            WHERE ID = {$docId}
            LIMIT 1
        ")->fetch();

        logMsg("Store move document {$docId} created and status updated to Y, total=".round($documentTotal, 2).", shipment_id=".(is_array($docRow) && isset($docRow['SHIPMENT_ID']) ? $docRow['SHIPMENT_ID'] : 'n/a').", status=".(is_array($docRow) && isset($docRow['STATUS']) ? $docRow['STATUS'] : 'n/a'));
    }

    if (!$hasInsufficient) {
        Crm\DealTable::update($DEAL_ID, [$FLAG_READY => 1]);
    }

    $conn->commitTransaction();

    // ================= КОММЕНТАРИЙ =================

    $comment = "";

    if (!empty($movedItems)) {
        $comment .= "✅ Перемещены на склад «Отгрузка»:\n";
        foreach ($movedItems as $item) {
            $comment .= "- {$item['name']}, {$item['quantity']} шт (со склада «{$item['from_store']}»)\n";
        }
    }

    if (!empty($alreadyOnTargetStore)) {
        if (!empty($comment)) $comment .= "\n";
        $comment .= "✅ Уже на складе «Отгрузка»:\n";
        foreach ($alreadyOnTargetStore as $item) {
            $comment .= "- {$item['name']}, {$item['quantity']} шт\n";
        }
    }

    if (!empty($insufficientItems)) {
        if (!empty($comment)) $comment .= "\n";
        $comment .= "❌ Не хватает на указанных складах:\n";
        foreach ($insufficientItems as $item) {
            $comment .= "- {$item['name']}: нужно {$item['quantity']}, доступно {$item['available']} на складе «{$item['store']}»\n";
        }
    }

    if ($hasInsufficient) {
        if (!empty($comment)) $comment .= "\n";
        $comment .= "⚠️ Сделка откачена на стадию «Оплачены (нехватка товара)» из-за нехватки товаров";
    } else {
        if (!empty($comment)) $comment .= "\n";
        $comment .= "✅ Все товары перемещены 👌";
    }

    if (!empty($comment)) {
        addTimelineRest($DEAL_ID, $comment, $REST_WEBHOOK);
    }

    logMsg("SUCCESS");

} catch (Exception $e) {

    $conn->rollbackTransaction();

    addTimelineRest($DEAL_ID, "❌ Ошибка: ".$e->getMessage(), $REST_WEBHOOK);

    logMsg("ERROR: ".$e->getMessage());
}