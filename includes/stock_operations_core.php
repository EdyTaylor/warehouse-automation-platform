<?php
/** Shared: stock tables, Bitrix document sync, receipt product helpers. */

require_once __DIR__ . '/../functions/stock_movements.php';

function ensureColumnExists($db, $tableName, $columnName, $columnSql) {
    $stmt = $db->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
    $stmt->execute(array($columnName));
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$exists) {
        $db->exec("ALTER TABLE `{$tableName}` ADD COLUMN {$columnSql}");
    }
}

function ensureStockOperationTables($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS stock_operation_docs (
            id int NOT NULL AUTO_INCREMENT,
            operation_type varchar(20) NOT NULL,
            doc_number varchar(64) DEFAULT NULL,
            supplier varchar(255) DEFAULT NULL,
            comment_text text,
            total_amount decimal(14,2) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'posted',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_stock_operation_type (operation_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS stock_operation_lines (
            id int NOT NULL AUTO_INCREMENT,
            doc_id int NOT NULL,
            product_id int NOT NULL,
            product_name varchar(255) DEFAULT NULL,
            qty_rolls int NOT NULL DEFAULT 0,
            roll_length decimal(10,2) NOT NULL DEFAULT 0,
            quantity_m decimal(12,2) NOT NULL DEFAULT 0,
            price_per_roll decimal(14,2) NOT NULL DEFAULT 0,
            line_total decimal(14,2) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_stock_operation_doc (doc_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    ensureColumnExists($db, 'stock_operation_docs', 'b24_document_id', '`b24_document_id` int DEFAULT NULL');
    ensureColumnExists($db, 'stock_operation_docs', 'b24_sync_status', '`b24_sync_status` varchar(20) NOT NULL DEFAULT \'pending\'');
    ensureColumnExists($db, 'stock_operation_docs', 'b24_sync_response', '`b24_sync_response` longtext');
    ensureColumnExists($db, 'stock_operation_lines', 'delivery_price_per_roll', '`delivery_price_per_roll` decimal(14,2) NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'stock_operation_lines', 'price_per_roll_usd', '`price_per_roll_usd` decimal(14,2) NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'stock_operation_lines', 'delivery_price_per_roll_usd', '`delivery_price_per_roll_usd` decimal(14,2) NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'stock_operation_lines', 'usd_to_kgs_rate', '`usd_to_kgs_rate` decimal(12,4) NOT NULL DEFAULT 90');
    ensureColumnExists($db, 'products', 'delivery_price', '`delivery_price` decimal(14,2) NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'rolls', 'receipt_doc_id', '`receipt_doc_id` int DEFAULT NULL');
    ensureColumnExists($db, 'rolls', 'cost_per_meter', '`cost_per_meter` decimal(14,4) NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'sales', 'cost_fact', '`cost_fact` decimal(14,2) NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'sales', 'gross_profit', '`gross_profit` decimal(14,2) NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'sales', 'gross_margin_percent', '`gross_margin_percent` decimal(8,2) NOT NULL DEFAULT 0');
}

function getUsdToKgsRate($db) {
    $raw = getAppSetting($db, 'usd_to_kgs_rate', '90');
    $rate = floatval($raw);
    if ($rate <= 0) {
        $rate = 90.0;
    }
    return $rate;
}

function resolveDocTypeCodeFromBitrix($logicalType) {
    static $cache = null;
    if ($cache === null) {
        $cache = array();
        $resp = sendToBitrix('catalog.enum.getStoreDocumentTypes', array());
        if (is_array($resp) && !isset($resp['error']) && isset($resp['result']) && is_array($resp['result'])) {
            $cache = $resp['result'];
        }
    }

    if (empty($cache)) {
        if ($logicalType === 'receipt') {
            return 'A';
        }
        if ($logicalType === 'writeoff') {
            return 'D';
        }
        return '';
    }

    $findByName = function($keywords) use ($cache) {
        foreach ($cache as $item) {
            $id = isset($item['id']) ? (string)$item['id'] : '';
            $name = (string)(isset($item['name']) ? $item['name'] : '');
            foreach ($keywords as $kw) {
                if ($kw !== '' && @preg_match('/' . preg_quote($kw, '/') . '/iu', $name) && $id !== '') {
                    return $id;
                }
            }
        }
        return '';
    };

    if ($logicalType === 'receipt') {
        $code = $findByName(array('приход', 'оприход', 'receipt', 'arrival'));
        if ($code !== '') {
            return $code;
        }
        return 'A';
    }

    if ($logicalType === 'writeoff') {
        $code = $findByName(array('списан', 'write-off', 'write off', 'deduct', 'disposal'));
        if ($code !== '') {
            return $code;
        }
        return 'D';
    }

    return '';
}

function calculateDocumentTotalFromLines($lineRows) {
    $total = 0.0;
    foreach ($lineRows as $line) {
        if (isset($line['line_total'])) {
            $total += floatval($line['line_total']);
            continue;
        }
        $qtyRolls = floatval(isset($line['qty_rolls']) ? $line['qty_rolls'] : 0);
        $pricePerRoll = floatval(isset($line['delivery_price_per_roll']) ? $line['delivery_price_per_roll'] : 0);
        if ($pricePerRoll <= 0) {
            $pricePerRoll = floatval(isset($line['price_per_roll']) ? $line['price_per_roll'] : 0);
        }
        if ($qtyRolls > 0 && $pricePerRoll > 0) {
            $total += ($qtyRolls * $pricePerRoll);
        }
    }
    return $total;
}

function updateB24DocumentTotal($b24DocId, $total, $currency) {
    if ($total <= 0) {
        return;
    }
    $fields = array(
        'total' => $total,
        'currency' => (string)$currency,
        'TOTAL' => $total,
        'CURRENCY' => (string)$currency
    );
    sendToBitrix('catalog.document.update', array(
        'id' => intval($b24DocId),
        'fields' => $fields
    ));
}

function pauseBeforeConduct($db) {
    $delayMs = intval(getAppSetting($db, 'b24_doc_delay_ms', '700'));
    if ($delayMs < 0) {
        $delayMs = 0;
    }
    if ($delayMs > 5000) {
        $delayMs = 5000;
    }
    if ($delayMs > 0) {
        usleep($delayMs * 1000);
    }
}

/** Пауза между добавлением строк складского документа Б24 — снижает ERROR_DOCUMENT_STATUS / «document not found» при пакетной записи. */
function pauseBetweenB24DocumentLineAdds($db) {
    $delayMs = intval(getAppSetting($db, 'b24_doc_line_delay_ms', '250'));
    if ($delayMs < 0) {
        $delayMs = 0;
    }
    if ($delayMs > 2000) {
        $delayMs = 2000;
    }
    if ($delayMs > 0) {
        usleep($delayMs * 1000);
    }
}

function bitrixDocumentElementAddLooksTransient($resp) {
    if (!is_array($resp) || !isset($resp['error'])) {
        return false;
    }
    $code = (string)$resp['error'];
    if ($code === 'ERROR_DOCUMENT_STATUS') {
        return true;
    }
    $desc = isset($resp['error_description']) ? (string)$resp['error_description'] : '';
    if ($desc !== '') {
        $d = function_exists('mb_strtolower') ? mb_strtolower($desc, 'UTF-8') : strtolower($desc);
        if (strpos($d, 'not found') !== false) {
            return true;
        }
        if (strpos($d, 'document') !== false && strpos($d, 'status') !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Одна строка документа: element.add → при ошибке без цен — при ERROR_DOCUMENT_STATUS одна повторная попытка после паузы.
 *
 * @param PDO $db
 * @param array $elementFields
 * @return array ok(bool), resp|lineResp,fallbackResp,fallbackFields
 */
function bitrixAppendDocumentLineWithPricingFallbackAndRetry($db, array $elementFields) {
    $runAttempt = function (array $fields) {
        $lineResp = sendToBitrix('catalog.document.element.add', array('fields' => $fields));
        if (is_array($lineResp) && !isset($lineResp['error'])) {
            return array('ok' => true, 'resp' => $lineResp);
        }
        $fallbackFields = $fields;
        unset(
            $fallbackFields['price'],
            $fallbackFields['purchasingPrice'],
            $fallbackFields['currency'],
            $fallbackFields['purchasingCurrency'],
            $fallbackFields['PRICE'],
            $fallbackFields['PURCHASING_PRICE'],
            $fallbackFields['PURCHASING_CURRENCY']
        );
        $fallbackResp = sendToBitrix('catalog.document.element.add', array('fields' => $fallbackFields));
        if (is_array($fallbackResp) && !isset($fallbackResp['error'])) {
            return array('ok' => true, 'resp' => $fallbackResp, 'used_pricing_fallback' => true);
        }
        return array(
            'ok' => false,
            'lineResp' => $lineResp,
            'fallbackResp' => $fallbackResp,
            'fallbackFields' => $fallbackFields
        );
    };

    $first = $runAttempt($elementFields);
    if (!empty($first['ok'])) {
        return $first;
    }
    $transient = bitrixDocumentElementAddLooksTransient($first['lineResp'])
        || bitrixDocumentElementAddLooksTransient($first['fallbackResp']);
    if ($transient) {
        $delayMs = intval(getAppSetting($db, 'b24_doc_line_retry_delay_ms', '500'));
        if ($delayMs < 200) {
            $delayMs = 200;
        }
        if ($delayMs > 3000) {
            $delayMs = 3000;
        }
        usleep($delayMs * 1000);
        $second = $runAttempt($elementFields);
        if (!empty($second['ok'])) {
            $second['retried_after_transient'] = true;
            return $second;
        }
        return $second;
    }
    return $first;
}

function parseBitrixListRows($resp) {
    if (!is_array($resp) || isset($resp['error']) || !isset($resp['result'])) {
        return array();
    }
    $rows = $resp['result'];
    if (isset($rows['items']) && is_array($rows['items'])) {
        return $rows['items'];
    }
    if (isset($rows['documentElements']) && is_array($rows['documentElements'])) {
        return $rows['documentElements'];
    }
    if (isset($rows['elements']) && is_array($rows['elements'])) {
        return $rows['elements'];
    }
    if (isset($rows['documents']) && is_array($rows['documents'])) {
        return $rows['documents'];
    }
    if (is_array($rows)) {
        return $rows;
    }
    return array();
}

/**
 * Рекурсивно вытащить id складского документа из сохранённого JSON ответа (частый случай:
 * документ уже создан в Б24, но b24_document_id в MySQL так и не записали после сбоя).
 *
 * @param string $json
 * @return int
 */
function stockOperationsExtractB24DocumentIdFromSavedSyncJson($json) {
    $s = trim((string)$json);
    if ($s === '') {
        return 0;
    }
    $dec = json_decode($s, true);
    if (!is_array($dec)) {
        return 0;
    }
    return stockOperationsExtractB24DocumentIdWalk($dec, 10);
}

/**
 * @param array $node
 * @param int $depthLeft
 * @return int
 */
function stockOperationsExtractB24DocumentIdWalk($node, $depthLeft) {
    if ($depthLeft <= 0 || !is_array($node)) {
        return 0;
    }
    foreach ($node as $k => $v) {
        if (($k === 'b24_document_id' || $k === 'documentId') && (is_numeric($v) || is_string($v))) {
            $id = intval($v);
            if ($id > 0) {
                return $id;
            }
        }
    }
    foreach ($node as $v) {
        if (!is_array($v)) {
            continue;
        }
        $hit = stockOperationsExtractB24DocumentIdWalk($v, $depthLeft - 1);
        if ($hit > 0) {
            return $hit;
        }
    }
    return 0;
}

/**
 * Уже есть документ склада Б24 с таким номером — чтобы «Повторить» не вызывал document.add второй раз.
 *
 * @param PDO $db
 * @param string $docNumber
 * @param string $operationType receipt|writeoff
 * @return int
 */
function stockOperationsFindB24DocumentIdByDocNumber($db, $docNumber, $operationType) {
    $dn = trim((string)$docNumber);
    if ($dn === '') {
        return 0;
    }
    $b24DocType = '';
    if ($operationType === 'receipt') {
        $b24DocType = resolveDocTypeCodeFromBitrix('receipt');
    } elseif ($operationType === 'writeoff') {
        $b24DocType = resolveDocTypeCodeFromBitrix('writeoff');
    }

    // Разные порталы — разные ключи фильтра; перебираем до первого списка с подходящей строкой.
    $filterVariants = array(
        array('=docNumber' => $dn),
        array('docNumber' => $dn),
        array('DOC_NUMBER' => $dn),
    );

    foreach ($filterVariants as $filt) {
        $resp = sendToBitrix('catalog.document.list', array(
            'filter' => $filt,
            'select' => array('id', 'docNumber', 'docType', 'status', 'STATUS'),
            'order' => array('id' => 'DESC'),
            'start' => 0
        ));
        $rows = parseBitrixListRows($resp);
        if (empty($rows) || !is_array($rows)) {
            continue;
        }
        $batchBest = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rid = intval(isset($row['id']) ? $row['id'] : (isset($row['ID']) ? $row['ID'] : 0));
            if ($rid <= 0) {
                continue;
            }
            $rowNum = '';
            if (isset($row['docNumber'])) {
                $rowNum = trim((string)$row['docNumber']);
            } elseif (isset($row['DOC_NUMBER'])) {
                $rowNum = trim((string)$row['DOC_NUMBER']);
            } elseif (isset($row['NUMBER'])) {
                $rowNum = trim((string)$row['NUMBER']);
            }
            if ($rowNum !== '' && $rowNum !== $dn) {
                continue;
            }
            $rowDt = '';
            if (isset($row['docType'])) {
                $rowDt = trim((string)$row['docType']);
            } elseif (isset($row['DOC_TYPE'])) {
                $rowDt = trim((string)$row['DOC_TYPE']);
            }
            if ($b24DocType !== '' && $rowDt !== '' && $rowDt !== $b24DocType) {
                continue;
            }
            if ($rid > $batchBest) {
                $batchBest = $rid;
            }
        }
        if ($batchBest > 0) {
            return $batchBest;
        }
    }

    return 0;
}

/**
 * Определить id документа Б24 для синка операции со склада.
 *
 * strategy:
 * - full — учитываем колонку b24_document_id, JSON ответ и поиск по doc_number («Дофиксировать» к существующему документу).
 * - portal_by_number_only — только поиск активного документа по номеру в портале; игнорируем устаревший id после удаления в Б24 («Повторить» заново или присоединиться к уже созданному только по номеру).
 *
 * @param PDO $db
 * @param array $doc строка stock_operation_docs
 * @param string $strategy full|portal_by_number_only
 * @return int
 */
function stockOperationsResolveB24DocumentIdForRetry($db, array $doc, $strategy = 'full') {
    $strategy = strtolower(trim((string)$strategy));
    if ($strategy === 'portal_by_number_only') {
        $op = isset($doc['operation_type']) ? (string)$doc['operation_type'] : '';
        $num = isset($doc['doc_number']) ? (string)$doc['doc_number'] : '';
        return stockOperationsFindB24DocumentIdByDocNumber($db, $num, $op);
    }

    $fromCol = intval(isset($doc['b24_document_id']) ? $doc['b24_document_id'] : 0);
    if ($fromCol > 0) {
        return $fromCol;
    }
    $fromJson = 0;
    if (isset($doc['b24_sync_response'])) {
        $fromJson = stockOperationsExtractB24DocumentIdFromSavedSyncJson((string)$doc['b24_sync_response']);
    }
    if ($fromJson > 0) {
        return $fromJson;
    }
    $op = isset($doc['operation_type']) ? (string)$doc['operation_type'] : '';
    $num = isset($doc['doc_number']) ? (string)$doc['doc_number'] : '';
    return stockOperationsFindB24DocumentIdByDocNumber($db, $num, $op);
}

/**
 * После синка складского документа пробросить локальное имя и розничную цену в crm.product (упрощённо: один id товара, без SKU-офферов).
 *
 * @param PDO $db
 * @param array $lineRows stock_operation_lines
 */
function stockOperationsPushReceiptProductsNamePriceToB24Catalog($db, array $lineRows) {
    $seen = array();
    foreach ($lineRows as $line) {
        $pid = intval(isset($line['product_id']) ? $line['product_id'] : 0);
        if ($pid <= 0 || isset($seen[$pid])) {
            continue;
        }
        $seen[$pid] = true;

        $st = $db->prepare('SELECT id, name, b24_product_id, price_per_meter FROM products WHERE id = ? LIMIT 1');
        $st->execute(array($pid));
        $pr = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pr || !is_array($pr)) {
            continue;
        }

        $b24 = intval(isset($pr['b24_product_id']) ? $pr['b24_product_id'] : 0);
        if ($b24 <= 0) {
            continue;
        }

        $nm = trim(isset($pr['name']) ? (string)$pr['name'] : '');
        if ($nm === '' || strpos($nm, 'Товар Б24 #') === 0) {
            continue;
        }

        $fields = array('NAME' => $nm);
        $ppm = round(floatval(isset($pr['price_per_meter']) ? $pr['price_per_meter'] : 0), 4);
        if ($ppm > 0) {
            $fields['PRICE'] = $ppm;
            $cid = strtoupper(trim((string)getAppSetting($db, 'default_currency', 'KGS')));
            if ($cid !== '') {
                $fields['CURRENCY_ID'] = $cid;
            }
        }

        sendToBitrix('crm.product.update', array(
            'id' => $b24,
            'fields' => $fields
        ));
    }
}

function ensureB24CompanyId($supplierName) {
    $name = trim((string)$supplierName);
    if ($name === '') {
        return 0;
    }

    $listResp = sendToBitrix('crm.company.list', array(
        'filter' => array('TITLE' => $name),
        'select' => array('ID', 'TITLE'),
        'order' => array('ID' => 'ASC')
    ));
    $rows = parseBitrixListRows($listResp);
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = intval(isset($row['ID']) ? $row['ID'] : (isset($row['id']) ? $row['id'] : 0));
        if ($id <= 0) {
            continue;
        }
        $title = trim((string)(isset($row['TITLE']) ? $row['TITLE'] : (isset($row['title']) ? $row['title'] : '')));
        $titleCmp = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
        $nameCmp = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        if ($title === '' || $titleCmp === $nameCmp) {
            return $id;
        }
    }

    $addResp = sendToBitrix('crm.company.add', array(
        'fields' => array(
            'TITLE' => $name
        )
    ));
    if (!is_array($addResp) || isset($addResp['error'])) {
        return 0;
    }
    if (isset($addResp['result'])) {
        return intval($addResp['result']);
    }
    return 0;
}

function ensureDocumentSupplierForReceipt($b24DocId, $supplierName) {
    $supplierName = trim((string)$supplierName);
    if ($supplierName === '') {
        $supplierName = 'Поставщик по умолчанию';
    }
    $companyId = ensureB24CompanyId($supplierName);
    if ($companyId <= 0) {
        return false;
    }
    $docId = intval($b24DocId);

    // Bind supplier via documentcontractor API (company entityTypeId=4).
    $bound = false;
    $listResp = sendToBitrix('catalog.documentcontractor.list', array(
        'filter' => array('documentId' => $docId),
        'select' => array('id', 'documentId', 'entityTypeId', 'entityId')
    ));
    $rows = parseBitrixListRows($listResp);
    if (!empty($rows)) {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entityTypeId = intval(isset($row['entityTypeId']) ? $row['entityTypeId'] : (isset($row['ENTITY_TYPE_ID']) ? $row['ENTITY_TYPE_ID'] : 0));
            $entityId = intval(isset($row['entityId']) ? $row['entityId'] : (isset($row['ENTITY_ID']) ? $row['ENTITY_ID'] : 0));
            if ($entityTypeId === 4 && $entityId === $companyId) {
                $bound = true;
                break;
            }
        }
    }

    if (!$bound) {
        $payloadFields = array(
            'documentId' => $docId,
            'entityTypeId' => 4,
            'entityId' => $companyId,
            'DOCUMENT_ID' => $docId,
            'ENTITY_TYPE_ID' => 4,
            'ENTITY_ID' => $companyId
        );
        $addResp = sendToBitrix('catalog.documentcontractor.add', array(
            'fields' => array(
                'documentId' => $docId,
                'entityTypeId' => 4,
                'entityId' => $companyId
            )
        ));
        if (is_array($addResp) && isset($addResp['error'])) {
            // Fallback for portals expecting flat payload without fields wrapper.
            $addResp = sendToBitrix('catalog.documentcontractor.add', $payloadFields);
        }
        if (is_array($addResp) && !isset($addResp['error'])) {
            $bound = true;
        }
    }

    // Compatibility fallback for portals that accept direct document update.
    if (!$bound) {
        $updResp = sendToBitrix('catalog.document.update', array(
            'id' => $docId,
            'fields' => array(
                'contractorId' => $companyId,
                'CONTRACTOR_ID' => $companyId
            )
        ));
        if (is_array($updResp) && !isset($updResp['error'])) {
            $bound = true;
        }
    }

    return $bound;
}

function extractBitrixErrorText($resp) {
    if (!is_array($resp)) {
        return '';
    }
    if (isset($resp['error_description'])) {
        return trim((string)$resp['error_description']);
    }
    if (isset($resp['error'])) {
        return trim((string)$resp['error']);
    }
    return '';
}

function stockReceiptShouldPushCrmCatalogPrice($db) {
    return trim((string)getAppSetting($db, 'stock_receipt_push_crm_catalog_price', '0')) === '1';
}

/**
 * По умолчанию не трогаем имя товара в crm.product при приходе (название ведёт каталог приложения / Битрикс).
 * Включить перезапись NAME из приложения при приходе: app_settings stock_receipt_push_crm_catalog_name = 1
 */
function stockReceiptShouldPushCrmCatalogName($db) {
    return trim((string)getAppSetting($db, 'stock_receipt_push_crm_catalog_name', '0')) === '1';
}

function stockReceiptIsPlaceholderB24ProductName($name) {
    $n = trim((string)$name);
    if ($n === '') {
        return true;
    }
    if (strpos($n, 'Товар Б24 #') === 0) {
        return true;
    }
    return false;
}

/**
 * Имя crm.product в Битрикс24 по ID (например, чтобы не создавать «Товар Б24 #…», если в JSON без product_name).
 *
 * @param int $crmProductId ID товара в Б24 (тот же, что приход в b24_product_id)
 * @return string
 */
function stockReceiptFetchCrmProductName($crmProductId) {
    $crmProductId = intval($crmProductId);
    if ($crmProductId <= 0) {
        return '';
    }
    $resp = sendToBitrix('crm.product.get', array('id' => $crmProductId));
    if (!is_array($resp) || isset($resp['error'])) {
        return '';
    }
    if (!isset($resp['result']) || !is_array($resp['result'])) {
        return '';
    }
    $r = $resp['result'];
    $nm = '';
    if (isset($r['NAME']) && trim((string)$r['NAME']) !== '') {
        $nm = trim((string)$r['NAME']);
    } elseif (isset($r['name']) && trim((string)$r['name']) !== '') {
        $nm = trim((string)$r['name']);
    }
    return $nm;
}

function forceCreateStockCloneProduct($db, $localProductId, $currentName, $pricePerMeter) {
    $baseName = trim((string)$currentName);
    if ($baseName === '') {
        $baseName = 'Товар #' . intval($localProductId);
    }
    $cloneName = $baseName;
    if (stripos($cloneName, '[stock]') === false) {
        $cloneName .= ' [stock]';
    }
    $fields = array(
        'NAME' => $cloneName,
        'TYPE' => 1
    );
    if (stockReceiptShouldPushCrmCatalogPrice($db) && floatval($pricePerMeter) > 0) {
        $fields['PRICE'] = floatval($pricePerMeter);
    }
    $createResp = sendToBitrix('crm.product.add', array('fields' => $fields));
    $newB24Id = 0;
    if (is_array($createResp) && !isset($createResp['error']) && isset($createResp['result'])) {
        $newB24Id = intval($createResp['result']);
    }
    if ($newB24Id > 0) {
        $db->prepare("UPDATE products SET b24_product_id = ? WHERE id = ?")
            ->execute(array($newB24Id, intval($localProductId)));
    }
    return $newB24Id;
}

function parseInvalidProductIdFromConductError($text) {
    $src = trim((string)$text);
    if ($src === '') {
        return 0;
    }
    if (preg_match('/#\s*(\d+)/u', $src, $m)) {
        return intval($m[1]);
    }
    return 0;
}

function replaceInvalidElementInB24Document($db, $b24DocId, $oldB24ProductId, $newB24ProductId, $docType) {
    $rowsResp = sendToBitrix('catalog.document.element.list', array(
        'filter' => array('docId' => intval($b24DocId)),
        'select' => array('id', 'elementId', 'amount')
    ));
    $rows = parseBitrixListRows($rowsResp);
    if (empty($rows)) {
        return false;
    }
    $storeFrom = intval(getAppSetting($db, 'default_store_from_id', '1'));
    $storeTo = intval(getAppSetting($db, 'default_store_to_id', '1'));
    $currency = (string)getAppSetting($db, 'default_currency', 'KGS');
    $replaced = false;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rowId = intval(isset($row['id']) ? $row['id'] : 0);
        $elementId = intval(isset($row['elementId']) ? $row['elementId'] : (isset($row['ELEMENT_ID']) ? $row['ELEMENT_ID'] : 0));
        $amount = floatval(isset($row['amount']) ? $row['amount'] : (isset($row['AMOUNT']) ? $row['AMOUNT'] : 0));
        if ($rowId <= 0 || $elementId !== intval($oldB24ProductId)) {
            continue;
        }
        sendToBitrix('catalog.document.element.delete', array('id' => $rowId));
        $fields = array(
            'docId' => intval($b24DocId),
            'elementId' => intval($newB24ProductId),
            'amount' => $amount
        );
        if ($docType === 'receipt') {
            $fields['storeTo'] = $storeTo;
        } else {
            $fields['storeFrom'] = $storeFrom;
        }
        $fields['currency'] = $currency;
        sendToBitrix('catalog.document.element.add', array('fields' => $fields));
        $replaced = true;
    }
    return $replaced;
}

function getB24ProductType($b24ProductId) {
    $id = intval($b24ProductId);
    if ($id <= 0) {
        return null;
    }
    $catalogGetResp = sendToBitrix('catalog.product.get', array('id' => $id));
    if (is_array($catalogGetResp) && !isset($catalogGetResp['error']) && isset($catalogGetResp['result']) && is_array($catalogGetResp['result'])) {
        if (isset($catalogGetResp['result']['type'])) {
            return intval($catalogGetResp['result']['type']);
        }
        if (isset($catalogGetResp['result']['TYPE'])) {
            return intval($catalogGetResp['result']['TYPE']);
        }
    }

    $crmGetResp = sendToBitrix('crm.product.get', array('id' => $id));
    if (is_array($crmGetResp) && !isset($crmGetResp['error']) && isset($crmGetResp['result']) && is_array($crmGetResp['result']) && isset($crmGetResp['result']['TYPE'])) {
        return intval($crmGetResp['result']['TYPE']);
    }
    return null;
}

function ensureUsableB24ProductId($db, $localProductId, $b24ProductId, $productName, $pricePerMeter) {
    $id = intval($b24ProductId);
    if ($id <= 0) {
        return 0;
    }

    $currentType = getB24ProductType($id);
    if ($currentType === 1) {
        return $id;
    }

    sendToBitrix('crm.product.update', array('id' => $id, 'fields' => array('TYPE' => 1)));
    sendToBitrix('catalog.product.update', array('id' => $id, 'fields' => array('type' => 1, 'TYPE' => 1)));
    $afterType = getB24ProductType($id);
    if ($afterType === 1) {
        return $id;
    }

    /** По умолчанию без клонов «… [stock]» — они плодят дубликаты в каталоге. Включить: app_settings stock_b24_clone_on_type_mismatch = 1 */
    $allowStockClone = trim((string)getAppSetting($db, 'stock_b24_clone_on_type_mismatch', '0')) === '1';
    if (!$allowStockClone) {
        return $id;
    }

    // Hard fallback: создать складской клон только если включено явно выше.
    $newName = trim((string)$productName);
    if ($newName === '') {
        $newName = 'Товар #' . intval($localProductId);
    }
    $createFields = array(
        'NAME' => $newName . ' [stock]',
        'TYPE' => 1
    );
    if (stockReceiptShouldPushCrmCatalogPrice($db) && floatval($pricePerMeter) > 0) {
        $createFields['PRICE'] = floatval($pricePerMeter);
    }
    $createResp = sendToBitrix('crm.product.add', array('fields' => $createFields));
    $newB24Id = 0;
    if (is_array($createResp) && !isset($createResp['error']) && isset($createResp['result'])) {
        $newB24Id = intval($createResp['result']);
    }
    if ($newB24Id > 0) {
        $db->prepare("UPDATE products SET b24_product_id = ? WHERE id = ?")
            ->execute(array($newB24Id, intval($localProductId)));
        return $newB24Id;
    }

    return 0;
}

function waitUntilB24DocumentConducted($db, $b24DocId) {
    $attempts = intval(getAppSetting($db, 'b24_conduct_check_attempts', '5'));
    if ($attempts < 1) {
        $attempts = 1;
    }
    if ($attempts > 20) {
        $attempts = 20;
    }
    $sleepMs = intval(getAppSetting($db, 'b24_doc_delay_ms', '700'));
    if ($sleepMs < 100) {
        $sleepMs = 100;
    }
    if ($sleepMs > 5000) {
        $sleepMs = 5000;
    }
    for ($i = 0; $i < $attempts; $i++) {
        if (isB24DocumentConducted($b24DocId)) {
            return true;
        }
        usleep($sleepMs * 1000);
    }
    return false;
}

function conductAndEnsurePosted($db, $b24DocId, $docType, $supplierName) {
    if ((string)$docType === 'receipt' && trim((string)$supplierName) === '') {
        $supplierName = 'Поставщик по умолчанию';
    }
    if ((string)$docType === 'receipt') {
        ensureDocumentSupplierForReceipt($b24DocId, $supplierName);
    }
    $conductResp = sendToBitrix('catalog.document.conduct', array('id' => intval($b24DocId)));
    $conductError = extractBitrixErrorText($conductResp);

    if ($conductError !== '' && stripos($conductError, 'Не указан поставщик') !== false && (string)$docType === 'receipt') {
        ensureDocumentSupplierForReceipt($b24DocId, $supplierName);
        $conductResp = sendToBitrix('catalog.document.conduct', array('id' => intval($b24DocId)));
        $conductError = extractBitrixErrorText($conductResp);
    }

    if ($conductError !== '' && stripos($conductError, 'Неверный тип товара') !== false) {
        /** По умолчанию не создаём [stock]-клон при проведении. Включить: stock_b24_conduct_stock_clone_fallback = 1 */
        if (trim((string)getAppSetting($db, 'stock_b24_conduct_stock_clone_fallback', '0')) === '1') {
            $invalidB24Id = parseInvalidProductIdFromConductError($conductError);
            if ($invalidB24Id > 0) {
                $prodStmt = $db->prepare("SELECT id, name, price_per_meter FROM products WHERE b24_product_id = ? LIMIT 1");
                $prodStmt->execute(array($invalidB24Id));
                $localProd = $prodStmt->fetch(PDO::FETCH_ASSOC);
                if ($localProd) {
                    $newB24Id = forceCreateStockCloneProduct(
                        $db,
                        intval($localProd['id']),
                        isset($localProd['name']) ? $localProd['name'] : '',
                        floatval(isset($localProd['price_per_meter']) ? $localProd['price_per_meter'] : 0)
                    );
                    if ($newB24Id > 0) {
                        replaceInvalidElementInB24Document($db, $b24DocId, $invalidB24Id, $newB24Id, $docType);
                        $conductResp = sendToBitrix('catalog.document.conduct', array('id' => intval($b24DocId)));
                    }
                }
            }
        }
    }
    if (waitUntilB24DocumentConducted($db, $b24DocId)) {
        return array(
            'ok' => true,
            'b24_document_id' => intval($b24DocId),
            'conduct_response' => $conductResp,
            'status_checked' => 'Y'
        );
    }

    // Fallback: some portals expose posting as status update.
    $updateResp = sendToBitrix('catalog.document.update', array(
        'id' => intval($b24DocId),
        'fields' => array(
            'status' => 'Y',
            'STATUS' => 'Y'
        )
    ));
    if (waitUntilB24DocumentConducted($db, $b24DocId)) {
        return array(
            'ok' => true,
            'b24_document_id' => intval($b24DocId),
            'conduct_response' => $conductResp,
            'conduct_fallback_update' => $updateResp,
            'status_checked' => 'Y'
        );
    }

    return array(
        'ok' => false,
        'stage' => 'document.conduct',
        'b24_document_id' => intval($b24DocId),
        'response' => $conductResp,
        'fallback_update_response' => $updateResp
    );
}

function syncOperationDocumentToBitrix($db, $docId, $docType, $docNumber, $commentary, $lineRows, $supplierName) {
    $storeFrom = intval(getAppSetting($db, 'default_store_from_id', '1'));
    $storeTo = intval(getAppSetting($db, 'default_store_to_id', '1'));
    $responsibleId = intval(getAppSetting($db, 'default_responsible_id', '1'));
    $currency = (string)getAppSetting($db, 'default_currency', 'KGS');

    $docMap = array(
        'receipt' => resolveDocTypeCodeFromBitrix('receipt'),
        'writeoff' => resolveDocTypeCodeFromBitrix('writeoff')
    );
    if (!isset($docMap[$docType])) {
        return array('ok' => false, 'error' => 'unsupported_doc_type');
    }
    $b24DocType = $docMap[$docType];

    $docResp = sendToBitrix('catalog.document.add', array(
        'fields' => array(
            'docType' => $b24DocType,
            'currency' => $currency,
            'responsibleId' => $responsibleId,
            'docNumber' => (string)$docNumber,
            'title' => ($docType === 'receipt' ? 'Приход' : 'Списание') . ' #' . intval($docId),
            'commentary' => (string)$commentary
        )
    ));
    if (!is_array($docResp) || isset($docResp['error'])) {
        return array('ok' => false, 'stage' => 'document.add', 'response' => $docResp);
    }

    $b24DocId = 0;
    if (isset($docResp['result']['document']['id'])) {
        $b24DocId = intval($docResp['result']['document']['id']);
    } elseif (isset($docResp['result']['id'])) {
        $b24DocId = intval($docResp['result']['id']);
    } elseif (isset($docResp['result'])) {
        $b24DocId = intval($docResp['result']);
    }
    if ($b24DocId <= 0) {
        return array('ok' => false, 'stage' => 'document.add', 'response' => $docResp);
    }

    // Поставщик до строк: на части порталов без контрагента document.element.add падает с ERROR_DOCUMENT_STATUS.
    if ($docType === 'receipt') {
        ensureDocumentSupplierForReceipt($b24DocId, $supplierName);
    }

    $lineResponses = array();
    foreach ($lineRows as $line) {
        $localProductId = intval(isset($line['product_id']) ? $line['product_id'] : 0);
        if ($localProductId <= 0) {
            continue;
        }

        $pStmt = $db->prepare("SELECT b24_product_id, price_per_meter, delivery_price, name FROM products WHERE id = ? LIMIT 1");
        $pStmt->execute(array($localProductId));
        $prod = $pStmt->fetch(PDO::FETCH_ASSOC);
        $b24ProductId = intval(isset($prod['b24_product_id']) ? $prod['b24_product_id'] : 0);
        if ($b24ProductId <= 0) {
            $lineResponses[] = array(
                'product_id' => $localProductId,
                'status' => 'skip_no_b24_product_id'
            );
            continue;
        }
        $resolvedB24ProductId = ensureUsableB24ProductId(
            $db,
            $localProductId,
            $b24ProductId,
            isset($prod['name']) ? (string)$prod['name'] : '',
            floatval(isset($prod['price_per_meter']) ? $prod['price_per_meter'] : 0)
        );
        if ($resolvedB24ProductId <= 0) {
            return array(
                'ok' => false,
                'stage' => 'product.type',
                'b24_document_id' => $b24DocId,
                'line_responses' => $lineResponses,
                'response' => array(
                    'error' => 'invalid_product_type',
                    'error_description' => 'Товар #' . $b24ProductId . ' в Б24 имеет неподдерживаемый тип для складского документа, и не удалось создать складской клон.'
                )
            );
        }
        $b24ProductId = $resolvedB24ProductId;

        $amount = floatval(isset($line['quantity_m']) ? $line['quantity_m'] : 0);
        if ($amount <= 0) {
            $amount = floatval(isset($line['qty_rolls']) ? $line['qty_rolls'] : 0);
        }
        if ($amount <= 0) {
            continue;
        }

        $elementFields = array(
            'docId' => $b24DocId,
            'elementId' => $b24ProductId,
            'amount' => $amount
        );
        if ($docType === 'receipt') {
            $elementFields['storeTo'] = $storeTo;
        } else {
            $elementFields['storeFrom'] = $storeFrom;
        }

        $lineRollLength = floatval(isset($line['roll_length']) ? $line['roll_length'] : 0);
        $linePricePerRoll = floatval(isset($line['price_per_roll']) ? $line['price_per_roll'] : 0);
        $lineDeliveryPerRoll = floatval(isset($line['delivery_price_per_roll']) ? $line['delivery_price_per_roll'] : 0);
        $pricePerMeter = 0.0;
        if ($lineDeliveryPerRoll > 0 && $lineRollLength > 0) {
            $pricePerMeter = $lineDeliveryPerRoll / $lineRollLength;
        } elseif ($linePricePerRoll > 0 && $lineRollLength > 0) {
            $pricePerMeter = $linePricePerRoll / $lineRollLength;
        } elseif (floatval(isset($prod['delivery_price']) ? $prod['delivery_price'] : 0) > 0 && $lineRollLength > 0) {
            $pricePerMeter = floatval($prod['delivery_price']) / $lineRollLength;
        } else {
            $pricePerMeter = floatval(isset($prod['price_per_meter']) ? $prod['price_per_meter'] : 0);
        }
        if ($pricePerMeter > 0) {
            $elementFields['price'] = $pricePerMeter;
            $elementFields['purchasingPrice'] = $pricePerMeter;
            $elementFields['currency'] = $currency;
            $elementFields['purchasingCurrency'] = $currency;
            // Compatibility aliases for some B24 portals.
            $elementFields['PRICE'] = $pricePerMeter;
            $elementFields['PURCHASING_PRICE'] = $pricePerMeter;
            $elementFields['PURCHASING_CURRENCY'] = $currency;
        }

        $addResult = bitrixAppendDocumentLineWithPricingFallbackAndRetry($db, $elementFields);
        if (empty($addResult['ok'])) {
            $lineResponses[] = array(
                'product_id' => $localProductId,
                'b24_product_id' => $b24ProductId,
                'amount' => $amount,
                'response' => isset($addResult['lineResp']) ? $addResult['lineResp'] : null,
                'fallback_response' => isset($addResult['fallbackResp']) ? $addResult['fallbackResp'] : null
            );
            return array(
                'ok' => false,
                'stage' => 'document.element.add',
                'b24_document_id' => $b24DocId,
                'line_responses' => $lineResponses,
                'response' => isset($addResult['fallbackResp']) ? $addResult['fallbackResp'] : (isset($addResult['lineResp']) ? $addResult['lineResp'] : null)
            );
        }

        $oneLine = array(
            'product_id' => $localProductId,
            'b24_product_id' => $b24ProductId,
            'amount' => $amount,
            'response' => $addResult['resp']
        );
        if (!empty($addResult['used_pricing_fallback'])) {
            $oneLine['used_pricing_fallback'] = true;
        }
        if (!empty($addResult['retried_after_transient'])) {
            $oneLine['retried_after_transient'] = true;
        }
        $lineResponses[] = $oneLine;
        pauseBetweenB24DocumentLineAdds($db);
    }

    $docTotal = calculateDocumentTotalFromLines($lineRows);
    updateB24DocumentTotal($b24DocId, $docTotal, $currency);
    pauseBeforeConduct($db);
    $conductResult = conductAndEnsurePosted($db, $b24DocId, $docType, $supplierName);
    if (!$conductResult['ok']) {
        $conductResult['line_responses'] = $lineResponses;
        return $conductResult;
    }
    $conductResult['line_responses'] = $lineResponses;
    return $conductResult;
}

function isB24DocumentConducted($b24DocId) {
    $resp = sendToBitrix('catalog.document.get', array('id' => intval($b24DocId)));
    if (!is_array($resp) || isset($resp['error']) || !isset($resp['result'])) {
        return false;
    }
    $row = is_array($resp['result']) ? $resp['result'] : array();
    $status = '';
    if (isset($row['status'])) {
        $status = (string)$row['status'];
    } elseif (isset($row['STATUS'])) {
        $status = (string)$row['STATUS'];
    } elseif (isset($row['document']['status'])) {
        $status = (string)$row['document']['status'];
    }
    return strtoupper($status) === 'Y';
}

function fetchB24DocumentElementsMap($b24DocId) {
    $map = array();
    $resp = sendToBitrix('catalog.document.element.list', array(
        'filter' => array('docId' => intval($b24DocId)),
        'select' => array('id', 'elementId', 'amount')
    ));
    if (!is_array($resp) || isset($resp['error']) || !isset($resp['result']) || !is_array($resp['result'])) {
        // Fallback for portals that expect flat docId parameter.
        $resp = sendToBitrix('catalog.document.element.list', array('docId' => intval($b24DocId)));
    }
    $rows = parseBitrixListRows($resp);
    if (empty($rows)) {
        return $map;
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $elementId = 0;
        if (isset($row['elementId'])) {
            $elementId = intval($row['elementId']);
        } elseif (isset($row['ELEMENT_ID'])) {
            $elementId = intval($row['ELEMENT_ID']);
        }
        if ($elementId <= 0) {
            continue;
        }
        $amount = 0.0;
        if (isset($row['amount'])) {
            $amount = floatval($row['amount']);
        } elseif (isset($row['AMOUNT'])) {
            $amount = floatval($row['AMOUNT']);
        }
        if (!isset($map[$elementId])) {
            $map[$elementId] = 0.0;
        }
        $map[$elementId] += $amount;
    }
    return $map;
}

function conductExistingB24Document($b24DocId, $docType, $supplierName) {
    // Legacy wrapper kept for compatibility.
    return conductAndEnsurePosted(getDB(), $b24DocId, $docType, $supplierName);
}

function addLinesAndConductExistingB24Document($db, $b24DocId, $docType, $lineRows, $supplierName) {
    $storeFrom = intval(getAppSetting($db, 'default_store_from_id', '1'));
    $storeTo = intval(getAppSetting($db, 'default_store_to_id', '1'));
    $currency = (string)getAppSetting($db, 'default_currency', 'KGS');

    if ($docType === 'receipt') {
        ensureDocumentSupplierForReceipt(intval($b24DocId), $supplierName);
    }

    $lineResponses = array();
    $existingElements = fetchB24DocumentElementsMap($b24DocId);
    foreach ($lineRows as $line) {
        $localProductId = intval(isset($line['product_id']) ? $line['product_id'] : 0);
        if ($localProductId <= 0) {
            continue;
        }

        $pStmt = $db->prepare("SELECT b24_product_id, price_per_meter, delivery_price, name FROM products WHERE id = ? LIMIT 1");
        $pStmt->execute(array($localProductId));
        $prod = $pStmt->fetch(PDO::FETCH_ASSOC);
        $b24ProductId = intval(isset($prod['b24_product_id']) ? $prod['b24_product_id'] : 0);
        if ($b24ProductId <= 0) {
            $lineResponses[] = array('product_id' => $localProductId, 'status' => 'skip_no_b24_product_id');
            continue;
        }
        $resolvedB24ProductId = ensureUsableB24ProductId(
            $db,
            $localProductId,
            $b24ProductId,
            isset($prod['name']) ? (string)$prod['name'] : '',
            floatval(isset($prod['price_per_meter']) ? $prod['price_per_meter'] : 0)
        );
        if ($resolvedB24ProductId <= 0) {
            return array(
                'ok' => false,
                'stage' => 'product.type',
                'b24_document_id' => intval($b24DocId),
                'line_responses' => $lineResponses,
                'response' => array(
                    'error' => 'invalid_product_type',
                    'error_description' => 'Товар #' . $b24ProductId . ' в Б24 имеет неподдерживаемый тип для складского документа, и не удалось создать складской клон.'
                )
            );
        }
        $b24ProductId = $resolvedB24ProductId;

        $amount = floatval(isset($line['quantity_m']) ? $line['quantity_m'] : 0);
        if ($amount <= 0) {
            $amount = floatval(isset($line['qty_rolls']) ? $line['qty_rolls'] : 0);
        }
        if ($amount <= 0) {
            continue;
        }
        if (isset($existingElements[$b24ProductId]) && floatval($existingElements[$b24ProductId]) >= ($amount - 0.0001)) {
            $lineResponses[] = array(
                'product_id' => $localProductId,
                'b24_product_id' => $b24ProductId,
                'amount' => $amount,
                'status' => 'skip_existing_element'
            );
            continue;
        }

        $lineRollLength = floatval(isset($line['roll_length']) ? $line['roll_length'] : 0);
        $linePricePerRoll = floatval(isset($line['price_per_roll']) ? $line['price_per_roll'] : 0);
        $lineDeliveryPerRoll = floatval(isset($line['delivery_price_per_roll']) ? $line['delivery_price_per_roll'] : 0);
        $pricePerMeter = 0.0;
        if ($lineDeliveryPerRoll > 0 && $lineRollLength > 0) {
            $pricePerMeter = $lineDeliveryPerRoll / $lineRollLength;
        } elseif ($linePricePerRoll > 0 && $lineRollLength > 0) {
            $pricePerMeter = $linePricePerRoll / $lineRollLength;
        } elseif (floatval(isset($prod['delivery_price']) ? $prod['delivery_price'] : 0) > 0 && $lineRollLength > 0) {
            $pricePerMeter = floatval($prod['delivery_price']) / $lineRollLength;
        } else {
            $pricePerMeter = floatval(isset($prod['price_per_meter']) ? $prod['price_per_meter'] : 0);
        }

        $elementFields = array(
            'docId' => intval($b24DocId),
            'elementId' => $b24ProductId,
            'amount' => $amount
        );
        if ($docType === 'receipt') {
            $elementFields['storeTo'] = $storeTo;
        } else {
            $elementFields['storeFrom'] = $storeFrom;
        }
        if ($pricePerMeter > 0) {
            $elementFields['price'] = $pricePerMeter;
            $elementFields['purchasingPrice'] = $pricePerMeter;
            $elementFields['currency'] = $currency;
            $elementFields['purchasingCurrency'] = $currency;
            // Compatibility aliases for some B24 portals.
            $elementFields['PRICE'] = $pricePerMeter;
            $elementFields['PURCHASING_PRICE'] = $pricePerMeter;
            $elementFields['PURCHASING_CURRENCY'] = $currency;
        }

        $addResult = bitrixAppendDocumentLineWithPricingFallbackAndRetry($db, $elementFields);
        if (empty($addResult['ok'])) {
            $lineResponses[] = array(
                'product_id' => $localProductId,
                'b24_product_id' => $b24ProductId,
                'amount' => $amount,
                'response' => isset($addResult['lineResp']) ? $addResult['lineResp'] : null,
                'fallback_response' => isset($addResult['fallbackResp']) ? $addResult['fallbackResp'] : null
            );
            $msg = '';
            if (isset($addResult['fallbackResp']) && is_array($addResult['fallbackResp'])) {
                $msg = isset($addResult['fallbackResp']['error_description']) ? (string)$addResult['fallbackResp']['error_description'] : '';
                if ($msg === '') {
                    $msg = isset($addResult['fallbackResp']['error']) ? (string)$addResult['fallbackResp']['error'] : '';
                }
            }
            if ($msg === '' && isset($addResult['lineResp']) && is_array($addResult['lineResp'])) {
                $msg = isset($addResult['lineResp']['error_description']) ? (string)$addResult['lineResp']['error_description'] : '';
                if ($msg === '') {
                    $msg = isset($addResult['lineResp']['error']) ? (string)$addResult['lineResp']['error'] : '';
                }
            }
            if ($msg !== '' && (stripos($msg, 'already') !== false || stripos($msg, 'уже') !== false || stripos($msg, 'duplicate') !== false || stripos($msg, 'дублик') !== false)) {
                continue;
            }
            return array(
                'ok' => false,
                'stage' => 'document.element.add',
                'b24_document_id' => intval($b24DocId),
                'line_responses' => $lineResponses,
                'response' => isset($addResult['fallbackResp']) ? $addResult['fallbackResp'] : (isset($addResult['lineResp']) ? $addResult['lineResp'] : null)
            );
        }

        $lineResponses[] = array(
            'product_id' => $localProductId,
            'b24_product_id' => $b24ProductId,
            'amount' => $amount,
            'response' => $addResult['resp']
        );
        if (!isset($existingElements[$b24ProductId])) {
            $existingElements[$b24ProductId] = 0.0;
        }
        $existingElements[$b24ProductId] += $amount;
        pauseBetweenB24DocumentLineAdds($db);
    }

    $docTotal = calculateDocumentTotalFromLines($lineRows);
    updateB24DocumentTotal($b24DocId, $docTotal, $currency);
    pauseBeforeConduct($db);
    $conductResult = conductAndEnsurePosted($db, $b24DocId, $docType, $supplierName);
    if (!$conductResult['ok']) {
        $conductResult['line_responses'] = $lineResponses;
        return $conductResult;
    }
    $conductResult['line_responses'] = $lineResponses;
    return $conductResult;
}

function tryFinalizePartialDocument($db, $operationType, $syncResult, $lineRows, $supplierName) {
    if (!is_array($syncResult)) {
        return $syncResult;
    }
    if (isset($syncResult['ok']) && $syncResult['ok']) {
        return $syncResult;
    }
    $b24DocId = intval(isset($syncResult['b24_document_id']) ? $syncResult['b24_document_id'] : 0);
    if ($b24DocId <= 0 || empty($lineRows)) {
        return $syncResult;
    }
    $stage = isset($syncResult['stage']) ? (string)$syncResult['stage'] : '';
    if ($stage === 'document.conduct') {
        $finalizeResult = conductAndEnsurePosted($db, $b24DocId, $operationType, $supplierName);
    } else {
        $finalizeResult = addLinesAndConductExistingB24Document($db, $b24DocId, (string)$operationType, $lineRows, $supplierName);
    }
    if (is_array($finalizeResult)) {
        $finalizeResult['auto_finalize_attempted'] = true;
        $finalizeResult['auto_finalize_mode'] = ($stage === 'document.conduct') ? 'conduct_only' : 'add_lines_and_conduct';
    }
    return $finalizeResult;
}

function ensureFormToken($name) {
    if (!isset($_SESSION['form_tokens']) || !is_array($_SESSION['form_tokens'])) {
        $_SESSION['form_tokens'] = array();
    }
    if (function_exists('random_bytes')) {
        $token = bin2hex(random_bytes(16));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $token = bin2hex(openssl_random_pseudo_bytes(16));
    } else {
        $token = md5(uniqid(mt_rand(), true) . microtime(true));
    }
    $_SESSION['form_tokens'][$name] = $token;
    return $token;
}

function validateFormToken($name, $token) {
    if (!isset($_SESSION['form_tokens']) || !is_array($_SESSION['form_tokens'])) {
        return false;
    }
    if (!isset($_SESSION['form_tokens'][$name])) {
        return false;
    }
    $valid = hash_equals((string)$_SESSION['form_tokens'][$name], (string)$token);
    unset($_SESSION['form_tokens'][$name]);
    return $valid;
}

function resolveB24SyncStatus($syncResult) {
    if (is_array($syncResult) && !empty($syncResult['local_only'])) {
        return 'skipped';
    }
    if (is_array($syncResult) && isset($syncResult['ok']) && $syncResult['ok']) {
        return 'sent';
    }
    if (is_array($syncResult) && !empty($syncResult['b24_document_id'])) {
        return 'partial';
    }
    return 'error';
}

function localizeOperationType($operationType) {
    $map = array(
        'receipt' => 'Приход',
        'writeoff' => 'Списание',
        'sale' => 'Реализация'
    );
    $key = (string)$operationType;
    return isset($map[$key]) ? $map[$key] : $key;
}

/**
 * Если задан &$deferBitrixProductPrices — HTTP к Битриксу (crm.product.*) выполняется ПОСЛЕ commit транзакции прихода,
 * иначе MySQL рвёт idle-сессию во время HTTP к Bitrix («MySQL server has gone away», wait_timeout на шаринге).
 *
 * При передаче массива: product_id → price_per_meter (последняя строка прихода по товару перезапишет число для Б24).
 *
 * @param PDO $db
 * @param float $rollLength
 * @param float $pricePerRoll
 * @param float $deliveryPricePerRoll
 * @param array|null $deferBitrixProductPrices передать array(); иначе null — прежнее поведение внутри транзакции
 * @return array запись продукта (локальная)
 */
function ensureProductForReceipt($db, $productId, $productName, $rollLength, $pricePerRoll, $deliveryPricePerRoll, &$deferBitrixProductPrices = null) {
    $baseRollPrice = $deliveryPricePerRoll > 0 ? $deliveryPricePerRoll : $pricePerRoll;
    $pricePerMeter = ($baseRollPrice > 0 && $rollLength > 0) ? ($baseRollPrice / $rollLength) : 0;
    $useDefer = ($deferBitrixProductPrices !== null);

    if ($productId > 0) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $stmt->execute(array($productId));
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($p) {
            $dbNmFix = isset($p['name']) ? trim((string)$p['name']) : '';
            $b24ForName = intval(isset($p['b24_product_id']) ? $p['b24_product_id'] : 0);
            if ($b24ForName > 0 && stockReceiptIsPlaceholderB24ProductName($dbNmFix)) {
                $fromB24Nm = stockReceiptFetchCrmProductName($b24ForName);
                if ($fromB24Nm !== '') {
                    $db->prepare('UPDATE products SET name = ? WHERE id = ?')->execute(array($fromB24Nm, $productId));
                    $p['name'] = $fromB24Nm;
                }
            }
            if ($pricePerRoll > 0 || $deliveryPricePerRoll > 0 || $rollLength > 0) {
                $db->prepare("UPDATE products SET purchase_price = ?, delivery_price = ?, roll_length = ?, price_per_meter = ? WHERE id = ?")
                    ->execute(array($pricePerRoll, $deliveryPricePerRoll, $rollLength, $pricePerMeter, $productId));
            }
            $p['purchase_price'] = $pricePerRoll;
            $p['delivery_price'] = $deliveryPricePerRoll;
            $p['roll_length'] = $rollLength;
            $p['price_per_meter'] = $pricePerMeter;
            if ($useDefer) {
                $deferBitrixProductPrices[intval($p['id'])] = $pricePerMeter;
                return $p;
            }
            return ensureProductInBitrix($db, $p, $pricePerMeter);
        }
    }

    $name = trim((string)$productName);
    if ($name === '') {
        throw new Exception('Укажите товар в строке прихода.');
    }

    $find = $db->prepare("SELECT * FROM products WHERE name = ? ORDER BY id ASC LIMIT 1");
    $find->execute(array($name));
    $existing = $find->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $db->prepare("UPDATE products SET purchase_price = ?, delivery_price = ?, roll_length = ?, price_per_meter = ? WHERE id = ?")
            ->execute(array($pricePerRoll, $deliveryPricePerRoll, $rollLength, $pricePerMeter, intval($existing['id'])));
        $existing['purchase_price'] = $pricePerRoll;
        $existing['delivery_price'] = $deliveryPricePerRoll;
        $existing['roll_length'] = $rollLength;
        $existing['price_per_meter'] = $pricePerMeter;
        if ($useDefer) {
            $deferBitrixProductPrices[intval($existing['id'])] = $pricePerMeter;
            return $existing;
        }
        return ensureProductInBitrix($db, $existing, $pricePerMeter);
    }

    $ins = $db->prepare("
        INSERT INTO products (name, roll_length, purchase_price, delivery_price, price_per_meter)
        VALUES (?, ?, ?, ?, ?)
    ");
    $ins->execute(array($name, $rollLength, $pricePerRoll, $deliveryPricePerRoll, $pricePerMeter));
    $newId = intval($db->lastInsertId());

    $created = array('id' => $newId, 'name' => $name, 'b24_product_id' => 0);
    if ($useDefer) {
        $deferBitrixProductPrices[$newId] = $pricePerMeter;
        return $created;
    }
    return ensureProductInBitrix($db, $created, $pricePerMeter);
}

/**
 * Выполнить отложенные при приходе вызовы ensureProductInBitrix (после commit).
 *
 * @param PDO $db
 * @param array $productIdToPriceMeter int => float price per meter для crm.product
 */
function stockOperationsFlushDeferredEnsureProductBitrix($db, array $productIdToPriceMeter) {
    foreach ($productIdToPriceMeter as $prodId => $ppm) {
        $prodId = intval($prodId);
        if ($prodId <= 0) {
            continue;
        }
        $st = $db->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $st->execute(array($prodId));
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            ensureProductInBitrix($db, $row, floatval($ppm));
        }
    }
}

/**
 * Найти в Битриксе crm.product с точным именем (без создания дубликата в каталоге).
 * При нескольких совпадениях — минимальный ID (старейший).
 *
 * @param string $name
 * @return int
 */
function stockOperationsFindCrmProductIdByExactName($name) {
    $name = trim((string)$name);
    if ($name === '') {
        return 0;
    }
    $filters = array(
        array('=NAME' => $name),
        array('NAME' => $name),
    );
    foreach ($filters as $filter) {
        $resp = sendToBitrix('crm.product.list', array(
            'filter' => $filter,
            'select' => array('ID', 'NAME'),
            'start' => 0,
        ));
        if (!is_array($resp) || isset($resp['error']) || !isset($resp['result']) || !is_array($resp['result'])) {
            continue;
        }
        $bestId = 0;
        foreach ($resp['result'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = intval(isset($row['ID']) ? $row['ID'] : (isset($row['id']) ? $row['id'] : 0));
            $nm = isset($row['NAME']) ? trim((string)$row['NAME']) : (isset($row['name']) ? trim((string)$row['name']) : '');
            if ($id <= 0) {
                continue;
            }
            if ($nm !== '' && $nm !== $name) {
                if (function_exists('mb_strtoupper')) {
                    if (mb_strtoupper($nm, 'UTF-8') !== mb_strtoupper($name, 'UTF-8')) {
                        continue;
                    }
                } elseif (strcasecmp($nm, $name) !== 0) {
                    continue;
                }
            }
            if ($bestId === 0 || $id < $bestId) {
                $bestId = $id;
            }
        }
        if ($bestId > 0) {
            return $bestId;
        }
    }
    return 0;
}

function ensureProductInBitrix($db, $product, $pricePerMeter) {
    $productId = intval(isset($product['id']) ? $product['id'] : 0);
    $productName = isset($product['name']) ? (string)$product['name'] : '';
    $productNameTrim = trim($productName);
    $b24ProductId = intval(isset($product['b24_product_id']) ? $product['b24_product_id'] : 0);

    if ($productId <= 0 || $productName === '') {
        return $product;
    }

    // Не создаём вторую карточку каталога, если позиция с таким именем уже есть в Б24.
    if ($b24ProductId <= 0 && $productNameTrim !== '') {
        $doLinkSetting = trim((string)getAppSetting($db, 'stock_receipt_link_b24_by_exact_name', '1'));
        if ($doLinkSetting !== '0') {
            $linkedId = stockOperationsFindCrmProductIdByExactName($productNameTrim);
            if ($linkedId > 0) {
                $db->prepare('UPDATE products SET b24_product_id = ? WHERE id = ?')
                    ->execute(array($linkedId, $productId));
                $b24ProductId = $linkedId;
                $product['b24_product_id'] = $linkedId;
            }
        }
    }

    if ($b24ProductId > 0) {
        $fields = array();
        if (stockReceiptShouldPushCrmCatalogName($db) && $productNameTrim !== '' && !stockReceiptIsPlaceholderB24ProductName($productNameTrim)) {
            $fields['NAME'] = $productNameTrim;
        }
        if (stockReceiptShouldPushCrmCatalogPrice($db) && $pricePerMeter > 0) {
            $fields['PRICE'] = floatval($pricePerMeter);
        }
        if (!empty($fields)) {
            sendToBitrix('crm.product.update', array(
                'id' => $b24ProductId,
                'fields' => $fields
            ));
        }
        return $product;
    }

    $createPayload = array('fields' => array('NAME' => $productName));
    if (stockReceiptShouldPushCrmCatalogPrice($db) && $pricePerMeter > 0) {
        $createPayload['fields']['PRICE'] = $pricePerMeter;
    }
    $resp = sendToBitrix('crm.product.add', $createPayload);
    if (is_array($resp) && !isset($resp['error']) && isset($resp['result'])) {
        $newB24Id = intval($resp['result']);
        if ($newB24Id > 0) {
            $db->prepare("UPDATE products SET b24_product_id = ? WHERE id = ?")
                ->execute(array($newB24Id, $productId));
            $product['b24_product_id'] = $newB24Id;
        }
    }

    return $product;
}

function consumeWriteoffMeters($db, $productId, $meters) {
    $need = floatval($meters);
    if ($need <= 0) {
        return array();
    }

    $stmt = $db->prepare("
        SELECT *
        FROM rolls
        WHERE product_id = ?
          AND status NOT IN ('sold', 'written_off', 'waste')
          AND current_length > 0
          AND reserved = 0
        ORDER BY
            CASE WHEN status='cut' THEN 0 ELSE 1 END,
            current_length ASC,
            id ASC
    ");
    $stmt->execute(array($productId));
    $rolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $taken = array();
    foreach ($rolls as $roll) {
        if ($need <= 0) {
            break;
        }
        $available = floatval($roll['current_length']);
        if ($available <= 0) {
            continue;
        }

        $take = min($available, $need);
        $newLen = $available - $take;
        $newStatus = $newLen <= 0 ? 'written_off' : 'cut';
        if ($newLen < 0) {
            $newLen = 0;
        }

        $db->prepare("
            UPDATE rolls
            SET current_length = ?, status = ?
            WHERE id = ?
        ")->execute(array($newLen, $newStatus, intval($roll['id'])));

        $taken[] = array(
            'roll_id' => intval($roll['id']),
            'meters' => $take
        );
        $need -= $take;
    }

    if ($need > 0.0001) {
        throw new Exception('Недостаточно метров для списания по товару ID ' . intval($productId) . '.');
    }

    return $taken;
}

function consumeWriteoffFromRoll($db, $rollId, $meters) {
    $rollId = intval($rollId);
    $need = floatval($meters);
    if ($rollId <= 0 || $need <= 0) {
        throw new Exception('Некорректный рулон или метраж списания.');
    }

    $stmt = $db->prepare("
        SELECT r.id, r.product_id, r.current_length, r.status, r.reserved, p.name as product_name
        FROM rolls r
        JOIN products p ON p.id = r.product_id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->execute(array($rollId));
    $roll = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$roll) {
        throw new Exception('Рулон не найден (ID ' . $rollId . ').');
    }

    if (intval($roll['reserved']) === 1) {
        throw new Exception('Рулон #' . $rollId . ' зарезервирован и недоступен для списания.');
    }
    if (in_array((string)$roll['status'], array('sold', 'written_off', 'waste'), true)) {
        throw new Exception('Рулон #' . $rollId . ' недоступен по статусу.');
    }

    $available = floatval($roll['current_length']);
    if ($available <= 0) {
        throw new Exception('Рулон #' . $rollId . ' не имеет доступного остатка.');
    }
    if ($need > $available + 0.0001) {
        throw new Exception('Для рулона #' . $rollId . ' доступно только ' . round($available, 2) . ' м.');
    }

    $newLen = $available - $need;
    if ($newLen < 0) {
        $newLen = 0;
    }
    $newStatus = $newLen <= 0 ? 'written_off' : 'cut';

    $db->prepare("
        UPDATE rolls
        SET current_length = ?, status = ?
        WHERE id = ?
    ")->execute(array($newLen, $newStatus, $rollId));

    return array(
        'roll_id' => $rollId,
        'product_id' => intval($roll['product_id']),
        'product_name' => (string)$roll['product_name'],
        'meters' => $need
    );
}

/**
 * Совпадающий doc_number после 504-повтора не должен плодить второй документ.
 *
 * @param string $docNumber trimmed
 * @return string MYSQL user lock name или '' если номер не задан
 */
function stockReceiptBuildAdvisoryLockName($docNumber) {
    $s = trim((string)$docNumber);
    if ($s === '') {
        return '';
    }
    return 'fcrm_rp_' . substr(hash('sha1', $s), 0, 40);
}

/**
 * @param string $lockName из stockReceiptBuildAdvisoryLockName
 * @return bool
 */
function stockReceiptMysqlGetLock(PDO $db, $lockName) {
    if ($lockName === '') {
        return true;
    }
    $st = $db->prepare('SELECT GET_LOCK(?, 55)');
    $st->execute(array($lockName));
    $rw = $st->fetch(PDO::FETCH_NUM);
    return $rw !== false && intval($rw[0]) === 1;
}

/**
 * @param string $lockName
 */
function stockReceiptMysqlReleaseLock(PDO $db, $lockName) {
    if ($lockName === '') {
        return;
    }
    try {
        $st = $db->prepare('SELECT RELEASE_LOCK(?)');
        $st->execute(array($lockName));
    } catch (Exception $eRl) {
    }
}

/**
 * Создаёт приход локально (документ, рулоны, движения) и синхронизирует складской документ в Б24
 * (тип «приход», строки с amount=метры, закупочная цена за метр в KGS и валютой из настроек).
 *
 * Параметры $params:
 * - doc_number, supplier, comment_text — как в форме
 * - receipt_currency: USD или KGS
 * - min_full: мин. остаток рулона (м), по умолчанию 0.5
 * - lines[]: массив строк, каждая с полями:
 *     product_id (локальный; подсказка; если указан и b24_product_id — приоритет у b24, чужой product_id игнорируется),
 *     product_name из JSON при отсутствии заглушки «Товар Б24 #» переопределяет локальный name после привязки по b24 (LLumar-батч и др.),
 *     b24_product_id — ID товара в Б24: ищется локальный products по b24_product_id или создаётся минимальная строка,
 *     product_name (для нового локального товара или подписи),
 *     qty_rolls, roll_length,
 *     purchase_per_roll и delivery_per_roll — в выбранной receipt_currency (как поля закупки/доставки за рулон на форме)
 *
 * Совместимые алиасы в строке: price_per_roll, delivery_price_per_roll, line_price_per_roll_usd, line_delivery_price_per_roll_usd.
 *
 * Параметры:
 *   local_only (bool) — если true: один локальный документ + рулоны + движения, без документа Б24 и без
 *     syncProductAvailable/logAndSyncMovement по Б24 (быстрее, без 504 на больших партиях).
 *
 * Возвращает массив: ok, doc_id, b24_document_id, sync_status, success_message, error_message, sync_result,
 * duplicate_receipt_skip (bool, если уже был приход с этим doc_number), usd_to_kgs_rate, total_amount_kgs, receipt_currency
 */
function stockOperationsProcessCreateReceiptPayload($db, array $params) {
    @ini_set('max_execution_time', '0');
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    $localOnly = !empty($params['local_only']);

    $docNumber = trim(isset($params['doc_number']) ? (string)$params['doc_number'] : '');
    $supplier = trim(isset($params['supplier']) ? (string)$params['supplier'] : '');
    $commentText = trim(isset($params['comment_text']) ? (string)$params['comment_text'] : '');
    $receiptCurrency = strtoupper(trim(isset($params['receipt_currency']) ? (string)$params['receipt_currency'] : 'USD'));
    if (!in_array($receiptCurrency, array('USD', 'KGS'), true)) {
        $receiptCurrency = 'USD';
    }
    $minFull = floatval(isset($params['min_full']) ? $params['min_full'] : 0.5);
    $linesIn = isset($params['lines']) && is_array($params['lines']) ? $params['lines'] : array();
    $usdToKgsRate = getUsdToKgsRate($db);

    $outBase = array(
        'ok' => false,
        'doc_id' => null,
        'b24_document_id' => null,
        'sync_status' => null,
        'success_message' => '',
        'error_message' => '',
        'sync_result' => null,
        'duplicate_receipt_skip' => false,
        'usd_to_kgs_rate' => $usdToKgsRate,
        'total_amount_kgs' => null,
        'receipt_currency' => $receiptCurrency
    );

    require_once __DIR__ . '/../functions/stock_emergency_kill.php';
    $emergencyStopCreates = stockEmergencyRollCreationStoppedMessage($db);
    if ($emergencyStopCreates !== '') {
        $outBase['error_message'] = $emergencyStopCreates;
        $outBase['emergency_blocked'] = true;
        return $outBase;
    }

    if (empty($linesIn)) {
        $outBase['error_message'] = 'Пустой список строк прихода.';
        return $outBase;
    }

    require_once __DIR__ . '/../functions/integration_sync_control.php';
    if (integrationAllSyncPaused($db)) {
        if (!$localOnly) {
            $outBase['error_message'] = 'Синхронизация отключена: обычный приход недоступен. Включите синхронизацию, '
                . 'либо в Центре интеграции включите «Разрешить локальный приход при паузе» и используйте режим только локально '
                . '(local_only / галочка «Только локально»).';
            return $outBase;
        }
        if (!integrationAllowsLocalReceiptDuringPause($db)) {
            $outBase['error_message'] = 'Синхронизация отключена: локальный приход при паузе запрещён настройкой. '
                . 'В Центре интеграции включите «Разрешить локальный приход при паузе» или снимите паузу.';
            return $outBase;
        }
    }

    $advisoryLockName = stockReceiptBuildAdvisoryLockName($docNumber);
    try {
        if ($advisoryLockName !== '') {
            if (!stockReceiptMysqlGetLock($db, $advisoryLockName)) {
                $outBase['error_message'] = 'Приход с этим номером документа уже выполняется другим запросом (или блокировка не получена за 55 с). Подождите и не дублируйте отправку.';
                return $outBase;
            }

            $exStmt = $db->prepare('
                SELECT id, COALESCE(b24_document_id, 0) AS b24d,
                       COALESCE(b24_sync_status, \'\') AS st
                FROM stock_operation_docs
                WHERE operation_type = \'receipt\' AND doc_number = ?
                ORDER BY id ASC
                LIMIT 1
            ');
            $exStmt->execute(array($docNumber));
            $existingReceipt = $exStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($existingReceipt)) {
                $extId = intval(isset($existingReceipt['id']) ? $existingReceipt['id'] : 0);
                $extB24 = intval(isset($existingReceipt['b24d']) ? $existingReceipt['b24d'] : 0);
                $extSt = isset($existingReceipt['st']) ? (string)$existingReceipt['st'] : '';
                $outBase['ok'] = true;
                $outBase['doc_id'] = $extId;
                $outBase['duplicate_receipt_skip'] = true;
                $outBase['sync_status'] = $extSt !== '' ? $extSt : null;
                $outBase['b24_document_id'] = $extB24 > 0 ? $extB24 : null;
                $outBase['total_amount_kgs'] = null;
                $outBase['success_message'] = 'Повторный запрос игнорирован: приход с номером «'
                    . $docNumber . '» уже есть (документ #' . $extId . '). Новый локальный документ и повторная отправка в Битрикс24 не выполнялись.';
                $outBase['error_message'] = '';
                stockReceiptMysqlReleaseLock($db, $advisoryLockName);
                return $outBase;
            }
        }

    $isoTweakedForReceipt = false;
    $docId = 0;
    $receiptCommitted = false;
    try {
        // Чтобы другой запрос (сохранение паузы / «прервать приход») увиделся внутри длинной транзакции
        @$db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $isoTweakedForReceipt = true;

        $receiptAbortEpoch = integrationGetStockAbortEpoch($db);
        $db->beginTransaction();
        $abortEpochStmt = $db->prepare('SELECT `value` FROM app_settings WHERE `key` = ? LIMIT 1');
        integrationAssertReceiptAbortEpochUnchanged($db, $receiptAbortEpoch, $abortEpochStmt);

        $heartbeatRollCounter = 0;

        $insDoc = $db->prepare("
            INSERT INTO stock_operation_docs (operation_type, doc_number, supplier, comment_text, total_amount, status)
            VALUES ('receipt', ?, ?, ?, 0, 'posted')
        ");
        $insDoc->execute(array($docNumber, $supplier, $commentText));
        $docId = intval($db->lastInsertId());

        $insLine = $db->prepare("
            INSERT INTO stock_operation_lines
            (doc_id, product_id, product_name, qty_rolls, roll_length, quantity_m, price_per_roll, delivery_price_per_roll, price_per_roll_usd, delivery_price_per_roll_usd, usd_to_kgs_rate, line_total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $totalAmount = 0.0;
        $addedAny = false;
        /** crm.product add/update только после commit — см. ensureProductForReceipt(..., &$defer…) */
        $deferBitrixProductPrices = array();
        /** @var array локальные product_id для одного синка каталога/склада после commit (иначе на каждый рулон — десятки вызовов Б24 → «зависание») */
        $receiptProductIdsNeedCatalogPush = array();

        foreach ($linesIn as $row) {
            if (!is_array($row)) {
                continue;
            }
            integrationAssertReceiptAbortEpochUnchanged($db, $receiptAbortEpoch, $abortEpochStmt);

            $qtyRolls = intval(isset($row['qty_rolls']) ? $row['qty_rolls'] : (isset($row['qtyRolls']) ? $row['qtyRolls'] : 0));
            $rollLength = floatval(isset($row['roll_length']) ? $row['roll_length'] : (isset($row['rollLength']) ? $row['rollLength'] : 0));
            $inputPricePerRoll = floatval(isset($row['purchase_per_roll'])
                ? $row['purchase_per_roll']
                : (isset($row['price_per_roll'])
                    ? $row['price_per_roll']
                    : (isset($row['line_price_per_roll_usd']) ? $row['line_price_per_roll_usd'] : 0)));
            $inputDeliveryPricePerRoll = floatval(isset($row['delivery_per_roll'])
                ? $row['delivery_per_roll']
                : (isset($row['delivery_price_per_roll'])
                    ? $row['delivery_price_per_roll']
                    : (isset($row['line_delivery_price_per_roll_usd']) ? $row['line_delivery_price_per_roll_usd'] : 0)));

            if ($receiptCurrency === 'USD') {
                $pricePerRollUsd = $inputPricePerRoll;
                $deliveryPricePerRollUsd = $inputDeliveryPricePerRoll;
                $pricePerRoll = $pricePerRollUsd * $usdToKgsRate;
                $deliveryPricePerRoll = $deliveryPricePerRollUsd * $usdToKgsRate;
            } else {
                $pricePerRoll = $inputPricePerRoll;
                $deliveryPricePerRoll = $inputDeliveryPricePerRoll;
                $pricePerRollUsd = $usdToKgsRate > 0 ? ($pricePerRoll / $usdToKgsRate) : 0;
                $deliveryPricePerRollUsd = $usdToKgsRate > 0 ? ($deliveryPricePerRoll / $usdToKgsRate) : 0;
            }
            $productId = intval(isset($row['product_id']) ? $row['product_id'] : (isset($row['productId']) ? $row['productId'] : 0));
            $nameFromJson = isset($row['product_name']) ? trim((string)$row['product_name']) : (isset($row['productName']) ? trim((string)$row['productName']) : '');
            $productName = $nameFromJson;
            $b24LineId = intval(isset($row['b24_product_id']) ? $row['b24_product_id'] : (isset($row['b24ProductId']) ? $row['b24ProductId'] : 0));

            $jsonNameUsable = ($nameFromJson !== '' && !stockReceiptIsPlaceholderB24ProductName($nameFromJson));

            // Если в строке указан Bitrix-ID товара — приход всегда вешаем на локальную карточку с этим b24_product_id
            // (каталог приложения ↔ Б24), даже если в JSON ошибочно передан другой product_id.
            // Если в JSON явно передано нормальное product_name (например из bulk LLumar) — оно важнее локальной подписи и обновляет products.name.
            if ($b24LineId > 0) {
                $stB24 = $db->prepare('SELECT id, name FROM products WHERE b24_product_id = ? ORDER BY id ASC LIMIT 1');
                $stB24->execute(array($b24LineId));
                $foundByB24 = $stB24->fetch(PDO::FETCH_ASSOC);
                if (is_array($foundByB24)) {
                    $productId = intval(isset($foundByB24['id']) ? $foundByB24['id'] : 0);
                    $dbNm = isset($foundByB24['name']) ? trim((string)$foundByB24['name']) : '';
                    if ($productId > 0 && ($dbNm === '' || stockReceiptIsPlaceholderB24ProductName($dbNm))) {
                        $fromB24Line = stockReceiptFetchCrmProductName($b24LineId);
                        if ($fromB24Line !== '') {
                            $db->prepare('UPDATE products SET name = ? WHERE id = ?')->execute(array($fromB24Line, $productId));
                            $dbNm = $fromB24Line;
                        }
                    }
                    if ($jsonNameUsable) {
                        $productName = $nameFromJson;
                        if ($productId > 0) {
                            $db->prepare('UPDATE products SET name = ? WHERE id = ?')->execute(array($nameFromJson, $productId));
                        }
                    } elseif ($dbNm !== '') {
                        $productName = $dbNm;
                    } elseif ($productName === '') {
                        $productName = $dbNm;
                    }
                } else {
                    $nmIns = $nameFromJson !== '' ? $nameFromJson : $productName;
                    if ($nmIns === '' || stockReceiptIsPlaceholderB24ProductName($nmIns)) {
                        $fromB24New = stockReceiptFetchCrmProductName($b24LineId);
                        if ($fromB24New !== '') {
                            $nmIns = $fromB24New;
                        }
                    }
                    if ($nmIns === '') {
                        $nmIns = 'Товар Б24 #' . $b24LineId;
                    }
                    $productName = $nmIns;
                    $insP = $db->prepare('
                        INSERT INTO products (name, b24_product_id, roll_length, purchase_price, delivery_price, price_per_meter)
                        VALUES (?, ?, ?, 0, 0, 0)
                    ');
                    $insP->execute(array($nmIns, $b24LineId, $rollLength));
                    $productId = intval($db->lastInsertId());
                }
            }

            if ($qtyRolls <= 0 || $rollLength <= 0) {
                continue;
            }

            $product = ensureProductForReceipt($db, $productId, $productName, $rollLength, $pricePerRoll, $deliveryPricePerRoll, $deferBitrixProductPrices);
            $localProductId = intval($product['id']);
            $localProductName = isset($product['name']) ? $product['name'] : $productName;

            $quantityM = $qtyRolls * $rollLength;
            $lineTotal = $qtyRolls * ($deliveryPricePerRoll > 0 ? $deliveryPricePerRoll : $pricePerRoll);
            $totalAmount += $lineTotal;
            $addedAny = true;

            $insLine->execute(array(
                $docId,
                $localProductId,
                $localProductName,
                $qtyRolls,
                $rollLength,
                $quantityM,
                $pricePerRoll,
                $deliveryPricePerRoll,
                $pricePerRollUsd,
                $deliveryPricePerRollUsd,
                $usdToKgsRate,
                $lineTotal
            ));

            for ($r = 0; $r < $qtyRolls; $r++) {
                integrationAssertReceiptAbortEpochUnchanged($db, $receiptAbortEpoch, $abortEpochStmt);

                $heartbeatRollCounter++;
                if ($heartbeatRollCounter % 60 === 0) {
                    try {
                        $db->query('SELECT 1');
                    } catch (Exception $eHb) {
                    }
                }

                $effectiveRollPrice = ($deliveryPricePerRoll > 0 ? $deliveryPricePerRoll : $pricePerRoll);
                $costPerMeter = $rollLength > 0 ? ($effectiveRollPrice / $rollLength) : 0;
                $insRoll = $db->prepare("
                    INSERT INTO rolls (product_id, original_length, current_length, min_full_length, status, receipt_doc_id, cost_per_meter)
                    VALUES (?, ?, ?, ?, 'active', ?, ?)
                ");
                $insRoll->execute(array($localProductId, $rollLength, $rollLength, $minFull, $docId, $costPerMeter));
                $rollId = intval($db->lastInsertId());

                $movPayload = array(
                    'product_id' => $localProductId,
                    'roll_id' => $rollId,
                    'movement_type' => 'receipt',
                    'quantity_m' => $rollLength,
                    'quantity_rolls' => 1,
                    'price_per_unit' => ($deliveryPricePerRoll > 0 ? $deliveryPricePerRoll : $pricePerRoll),
                    'total' => ($deliveryPricePerRoll > 0 ? $deliveryPricePerRoll : $pricePerRoll),
                    'comment' => 'Оприходование через документ #' . $docId
                );

                if ($localOnly) {
                    $movIdLocal = logStockMovement($db, $movPayload);
                    $updM = $db->prepare('UPDATE stock_movements SET bitrix_status = ?, bitrix_response = ? WHERE id = ?');
                    $updM->execute(array(
                        'sent',
                        json_encode(array('skipped' => 'local_only_receipt', 'hint' => 'Битрикс24 не вызывался (local_only).')),
                        $movIdLocal
                    ));
                } else {
                    $movBulk = logStockMovement($db, $movPayload);
                    $updBulk = $db->prepare('UPDATE stock_movements SET bitrix_status = ?, bitrix_response = ? WHERE id = ?');
                    $updBulk->execute(array(
                        'sent',
                        json_encode(array(
                            'skipped' => 'bulk_receipt_deferred_catalog',
                            'hint' => 'Остаток в каталоге/магазине Б24 синхронизируется один раз по товару после проведения прихода.'
                        )),
                        $movBulk
                    ));
                }
            }

            if (!$localOnly) {
                $receiptProductIdsNeedCatalogPush[$localProductId] = true;
            }
        }

        if (!$addedAny) {
            throw new Exception('Добавьте хотя бы одну корректную строку прихода.');
        }

        $db->prepare("UPDATE stock_operation_docs SET total_amount = ? WHERE id = ?")
            ->execute(array($totalAmount, $docId));
        $db->commit();
        $receiptCommitted = true;

        if (!empty($deferBitrixProductPrices)) {
            stockOperationsFlushDeferredEnsureProductBitrix($db, $deferBitrixProductPrices);
        }

        if (!$localOnly && !empty($receiptProductIdsNeedCatalogPush)) {
            foreach (array_keys($receiptProductIdsNeedCatalogPush) as $pidForCatalog) {
                syncProductAvailableToBitrix($db, intval($pidForCatalog));
            }
        }

        $lineRowsForSync = $db->query("SELECT product_id, qty_rolls, quantity_m, roll_length, price_per_roll, delivery_price_per_roll, line_total FROM stock_operation_lines WHERE doc_id=" . intval($docId))->fetchAll(PDO::FETCH_ASSOC);

        if ($localOnly) {
            $syncResult = array(
                'ok' => true,
                'local_only' => true,
                'reason' => 'Режим только локальный склад (local_only): документ прихода в Битрикс24 не создавался.'
            );
            $syncStatus = 'skipped';

            $db->prepare("UPDATE stock_operation_docs SET b24_document_id = NULL, b24_sync_status = ?, b24_sync_response = ? WHERE id = ?")
                ->execute(array($syncStatus, json_encode($syncResult, JSON_UNESCAPED_UNICODE), $docId));

            $successMessage = 'Документ прихода #' . $docId . ' проведён только в приложении. Валюта: ' . $receiptCurrency
                . '. Курс USD: ' . number_format($usdToKgsRate, 2, '.', ' ') . ' | Сумма: ' . number_format($totalAmount, 2, '.', ' ') . ' KGS'
                . ' | Битрикс24 пропущен (local_only).';

            $outBase['ok'] = true;
            $outBase['doc_id'] = $docId;
            $outBase['sync_result'] = $syncResult;
            $outBase['sync_status'] = $syncStatus;
            $outBase['total_amount_kgs'] = $totalAmount;
            $outBase['success_message'] = $successMessage;
            $outBase['b24_document_id'] = null;
            $outBase['error_message'] = '';

            return $outBase;
        }

        $syncResult = syncOperationDocumentToBitrix($db, $docId, 'receipt', $docNumber, $commentText, $lineRowsForSync, $supplier);
        $syncResult = tryFinalizePartialDocument($db, 'receipt', $syncResult, $lineRowsForSync, $supplier);
        $syncStatus = resolveB24SyncStatus($syncResult);

        $db->prepare("UPDATE stock_operation_docs SET b24_document_id = ?, b24_sync_status = ?, b24_sync_response = ? WHERE id = ?")
            ->execute(array(
                isset($syncResult['b24_document_id']) ? intval($syncResult['b24_document_id']) : null,
                $syncStatus,
                json_encode($syncResult, JSON_UNESCAPED_UNICODE),
                $docId
            ));

        $successMessage = 'Документ прихода #' . $docId . ' проведен. Валюта ввода: ' . $receiptCurrency . '. Курс USD: ' . number_format($usdToKgsRate, 2, '.', ' ') . ' | Сумма: ' . number_format($totalAmount, 2, '.', ' ') . ' KGS';
        $errorMessage = '';

        $outBase['ok'] = true;
        $outBase['doc_id'] = $docId;
        $outBase['sync_result'] = $syncResult;
        $outBase['sync_status'] = $syncStatus;
        $outBase['total_amount_kgs'] = $totalAmount;
        $outBase['success_message'] = $successMessage;
        $outBase['b24_document_id'] = isset($syncResult['b24_document_id']) ? intval($syncResult['b24_document_id']) : null;

        if ($syncStatus === 'sent') {
            $outBase['success_message'] = $successMessage . ' | Б24 документ #' . intval($syncResult['b24_document_id']);
        } elseif ($syncStatus === 'partial') {
            $outBase['error_message'] = 'Приход создан в Б24 (#' . intval($syncResult['b24_document_id']) . '), но фиксация строк/проведение завершились с ошибкой.';
        } else {
            $outBase['error_message'] = 'Приход проведен локально, но синк в Б24 завершился с ошибкой.';
        }

        return $outBase;
    } catch (Exception $e) {
        try {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        } catch (Exception $eRb) {
        }
        // После commit() локальный приход уже в БД; исключение в post-commit (Битрикс, PDO вне транзакции)
        // не должно возвращать ok=false «как будто прихода не было».
        if ($receiptCommitted && intval($docId) > 0) {
            $payloadErr = array(
                'ok' => false,
                'stage' => 'post_commit_exception',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            );
            try {
                $db->prepare("UPDATE stock_operation_docs SET b24_sync_status = 'error', b24_sync_response = ? WHERE id = ?")
                    ->execute(array(json_encode($payloadErr, JSON_UNESCAPED_UNICODE), intval($docId)));
            } catch (Exception $eDoc) {
            }
            $outBase['ok'] = true;
            $outBase['doc_id'] = intval($docId);
            $outBase['sync_status'] = 'error';
            $outBase['sync_result'] = $payloadErr;
            $outBase['error_message'] = 'Приход сохранён в приложении (документ #' . intval($docId)
                . '), но при обработке после сохранения произошла ошибка: ' . $e->getMessage();
            $outBase['success_message'] = 'Документ #' . intval($docId) . ' создан локально; синхронизация с Б24 прервана ошибкой — см. детали ниже или кнопку «Повторить».';
            try {
                $stTa = $db->prepare('SELECT total_amount FROM stock_operation_docs WHERE id = ? LIMIT 1');
                $stTa->execute(array(intval($docId)));
                $rowTa = $stTa->fetch(PDO::FETCH_ASSOC);
                if (is_array($rowTa) && isset($rowTa['total_amount'])) {
                    $outBase['total_amount_kgs'] = floatval($rowTa['total_amount']);
                }
            } catch (Exception $eTa) {
            }
            return $outBase;
        }
        $outBase['error_message'] = $e->getMessage();
        return $outBase;
    } finally {
        if ($isoTweakedForReceipt) {
            try {
                $db->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE-READ');
            } catch (Exception $eIso) {
            }
        }
    }
    } finally {
        try {
            stockReceiptMysqlReleaseLock($db, $advisoryLockName);
        } catch (Exception $eLock) {
        }
    }
}

/**
 * Кол-во рулонов из строки JSON прихода.
 *
 * @param array $line
 * @return int
 */
function stockOperationsReceiptLineQtyRolls(array $line) {
    $q = intval(isset($line['qty_rolls']) ? $line['qty_rolls'] : (isset($line['qtyRolls']) ? $line['qtyRolls'] : 0));
    if ($q < 0) {
        return 0;
    }
    return $q;
}

/**
 * Копия строки с указанным qty_rolls (для дробления длинной строки).
 *
 * @param array $line
 * @param int $qtyRolls
 * @return array
 */
function stockOperationsReceiptLineCloneQty(array $line, $qtyRolls) {
    $out = $line;
    $out['qty_rolls'] = intval($qtyRolls);
    if (isset($out['qtyRolls'])) {
        unset($out['qtyRolls']);
    }
    return $out;
}

/**
 * Разбивает строки так, чтобы каждый кусок был не более $maxRollsPerPiece рулонов (если лимит задан).
 *
 * @param array $lines
 * @param int $maxRollsPerPiece 0 или меньше = не дробить внутри строки
 * @return array массив сегментов: line + rolls
 */
function stockOperationsReceiptFlattenLineSegments(array $lines, $maxRollsPerPiece) {
    $segments = array();
    $cap = intval($maxRollsPerPiece);
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $qty = stockOperationsReceiptLineQtyRolls($line);
        if ($qty <= 0) {
            continue;
        }
        if ($cap <= 0) {
            $segments[] = array(
                'line' => stockOperationsReceiptLineCloneQty($line, $qty),
                'rolls' => $qty
            );
            continue;
        }
        $left = $qty;
        while ($left > 0) {
            $take = min($left, $cap);
            $segments[] = array(
                'line' => stockOperationsReceiptLineCloneQty($line, $take),
                'rolls' => $take
            );
            $left -= $take;
        }
    }
    return $segments;
}

/**
 * Упаковка сегментов в партии прихода по числу строк и сумме рулонов в партии.
 *
 * @param array $segments результат stockOperationsReceiptFlattenLineSegments
 * @param int $maxLinesPerChunk
 * @param int $maxRollsPerChunk 0 = без лимита рулонов (только лимит строк)
 * @return array массив чанков, каждый — список строк lines[]
 */
function stockOperationsReceiptPackSegmentsIntoChunks(array $segments, $maxLinesPerChunk, $maxRollsPerChunk) {
    $maxLinesPerChunk = max(1, min(300, intval($maxLinesPerChunk)));
    $chunkRollCap = intval($maxRollsPerChunk);
    if ($chunkRollCap <= 0) {
        $chunkRollCap = 2147483647;
    } else {
        $chunkRollCap = max(10, min(50000, $chunkRollCap));
    }

    $chunks = array();
    $curLines = array();
    $curRolls = 0;
    $curLineCount = 0;

    $flush = function () use (&$chunks, &$curLines, &$curRolls, &$curLineCount) {
        if (!empty($curLines)) {
            $chunks[] = $curLines;
        }
        $curLines = array();
        $curRolls = 0;
        $curLineCount = 0;
    };

    foreach ($segments as $seg) {
        if (!is_array($seg) || !isset($seg['line']) || !isset($seg['rolls'])) {
            continue;
        }
        $r = intval($seg['rolls']);
        if ($r <= 0) {
            continue;
        }

        while ($curLineCount > 0 && ($curLineCount >= $maxLinesPerChunk || $curRolls + $r > $chunkRollCap)) {
            $flush();
        }

        $curLines[] = $seg['line'];
        $curRolls += $r;
        $curLineCount++;

        if ($curLineCount >= $maxLinesPerChunk || $curRolls >= $chunkRollCap) {
            $flush();
        }
    }
    $flush();

    return $chunks;
}

/**
 * Настройки чанков из UI/API.
 *
 * @param int $linesPerChunk 0 = не использовать чанки
 * @param int $maxRollUnits при чанках 0 означает «взять лимит по умолчанию» (разбиение длинных строк)
 *
 * @return array active, lines_per_chunk, max_roll_units (для упаковки партии), max_roll_piece (для дробления длинной строки)
 */
function stockOperationsReceiptNormalizeChunkOptions($linesPerChunk, $maxRollUnits) {
    $lpc = intval($linesPerChunk);
    if ($lpc <= 0) {
        return array(
            'active' => false,
            'lines_per_chunk' => 0,
            'max_roll_units' => 0,
            'max_roll_piece' => 0
        );
    }
    $lpc = max(5, min(200, $lpc));
    $mru = intval($maxRollUnits);
    if ($mru <= 0) {
        $mru = 400;
    }
    $mru = max(50, min(20000, $mru));
    $piece = min($mru, 500);
    if ($piece < 50) {
        $piece = 50;
    }
    return array(
        'active' => true,
        'lines_per_chunk' => $lpc,
        'max_roll_units' => $mru,
        'max_roll_piece' => $piece
    );
}

/**
 * Номер документа для части n из N (длина ≤ 64).
 *
 * @param string $baseDoc
 * @param int $chunkIndex 0-based
 * @param int $chunkTotal
 * @param string $hashSeed соль для автогенерации при пустом baseDoc
 *
 * @return string
 */
function stockReceiptDocNumberForChunk($baseDoc, $chunkIndex, $chunkTotal, $hashSeed) {
    $base = trim((string)$baseDoc);
    $n = intval($chunkIndex);
    $t = intval($chunkTotal);
    if ($t <= 1 && $base !== '') {
        $s = stockReceiptTruncateDocNumber($base);
        return $s;
    }

    $suffix = '-C' . ($n + 1) . 'of' . $t;

    if ($base === '') {
        $seed = trim((string)$hashSeed);
        $auto = 'ACHK-' . substr(hash('sha256', $seed !== '' ? ($seed . '|' . ($n + 1)) : (string)$n), 0, 40);
        if (strlen($auto) + strlen($suffix) <= 64) {
            return stockReceiptTruncateDocNumber($auto . $suffix);
        }
        return stockReceiptTruncateDocNumber(substr($auto, 0, 64 - strlen($suffix)) . $suffix);
    }

    if (strlen($base) + strlen($suffix) <= 64) {
        return stockReceiptTruncateDocNumber($base . $suffix);
    }
    $room = 64 - strlen($suffix);
    if ($room < 12) {
        return stockReceiptTruncateDocNumber(substr(hash('sha256', $base . $suffix), 0, 48) . $suffix);
    }
    return stockReceiptTruncateDocNumber(substr($base, 0, $room) . $suffix);
}

/**
 * @param string $s
 *
 * @return string
 */
function stockReceiptTruncateDocNumber($s) {
    $s = trim((string)$s);
    if (strlen($s) <= 64) {
        return $s;
    }
    return substr($s, 0, 64);
}

/**
 * Несколько последовательных приходов с общим телом JSON, чтобы уменьшить 504.
 *
 * @param PDO $db по ссылке: после 1-й части переподключается к MySQL (Beget рвёт wait_timeout на длинных приходах).
 * @param array $template doc_number может быть временным для seed; задаёт supplier, comment_text, receipt_currency, min_full, local_only
 * @param array $lines
 * @param int $linesPerChunk
 * @param int $maxRollUnitsPerChunk
 * @param string $canonicalSeed хешируется в номера ACHK если doc_number не задан
 *
 * @return array ok, chunked, chunks_total, results[], doc_ids[], error_message, aggregate duplicate_receipt_skip только если всё было skip...
 */
function stockOperationsRunChunkedReceiptFromPayload(&$db, array $template, array $lines, $linesPerChunk, $maxRollUnitsPerChunk, $canonicalSeed) {
    $norm = stockOperationsReceiptNormalizeChunkOptions($linesPerChunk, $maxRollUnitsPerChunk);
    $outWrap = array(
        'ok' => false,
        'chunked' => false,
        'chunks_total' => 0,
        'chunks_completed' => 0,
        'results' => array(),
        'doc_ids' => array(),
        'error_message' => '',
        'duplicate_receipt_skips' => 0,
    );

    if (!$norm['active']) {
        $outWrap['error_message'] = 'Внутренняя ошибка: режим частей прихода не активирован.';
        return $outWrap;
    }

    $segments = stockOperationsReceiptFlattenLineSegments($lines, $norm['max_roll_piece']);
    $chunks = stockOperationsReceiptPackSegmentsIntoChunks(
        $segments,
        $norm['lines_per_chunk'],
        $norm['max_roll_units']
    );

    if (empty($chunks)) {
        $outWrap['error_message'] = 'Нет строк прихода для обработки (пустые или неверные qty_rolls).';
        return $outWrap;
    }

    $outWrap['chunked'] = true;
    $outWrap['chunks_total'] = count($chunks);

    $baseDn = isset($template['doc_number']) ? trim((string)$template['doc_number']) : '';
    $seed = trim((string)$canonicalSeed);

    foreach ($chunks as $ci => $chunkLines) {
        if (intval($ci) > 0) {
            require_once __DIR__ . '/../db.php';
            $db = getDB();
        }

        $chunkTotal = count($chunks);
        $docNum = stockReceiptDocNumberForChunk($baseDn, intval($ci), $chunkTotal, $seed !== '' ? $seed : $baseDn . '|' . $chunkTotal);

        $commentBase = isset($template['comment_text']) ? trim((string)$template['comment_text']) : '';
        $partNote = '[Часть ' . ($ci + 1) . '/' . $chunkTotal . ' массового прихода в ' . gmdate('Y-m-d\\TH:i:s\\Z') . '] ';
        $commentMerged = trim($commentBase !== '' ? ($commentBase . ' ' . $partNote) : $partNote);

        $paramsChunk = array(
            'doc_number' => $docNum,
            'supplier' => isset($template['supplier']) ? $template['supplier'] : '',
            'comment_text' => $commentMerged,
            'receipt_currency' => isset($template['receipt_currency']) ? $template['receipt_currency'] : 'USD',
            'min_full' => isset($template['min_full']) ? $template['min_full'] : 0.5,
            'lines' => $chunkLines,
            'local_only' => !empty($template['local_only']),
        );

        $res = stockOperationsProcessCreateReceiptPayload($db, $paramsChunk);
        $outWrap['results'][] = $res;
        $outWrap['chunks_completed'] = intval($ci) + 1;

        if (!empty($res['duplicate_receipt_skip'])) {
            $outWrap['duplicate_receipt_skips']++;
        }

        if (empty($res['ok'])) {
            $outWrap['ok'] = false;
            $outWrap['error_message'] = 'Ошибка в части ' . ($ci + 1) . '/' . $chunkTotal . ': '
                . trim(isset($res['error_message']) ? (string)$res['error_message'] : 'ошибка');
            return $outWrap;
        }
        if (!empty($res['doc_id'])) {
            $outWrap['doc_ids'][] = (int)$res['doc_id'];
        }
    }

    $outWrap['ok'] = true;
    $outWrap['error_message'] = '';

    return $outWrap;
}
