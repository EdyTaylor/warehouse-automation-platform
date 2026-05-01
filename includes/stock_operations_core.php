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
    if (is_array($rows)) {
        return $rows;
    }
    return array();
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
    if (floatval($pricePerMeter) > 0) {
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

    // Hard fallback: create a dedicated stock-compatible product and remap local link.
    $newName = trim((string)$productName);
    if ($newName === '') {
        $newName = 'Товар #' . intval($localProductId);
    }
    $createFields = array(
        'NAME' => $newName . ' [stock]',
        'TYPE' => 1
    );
    if (floatval($pricePerMeter) > 0) {
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

        $lineResp = sendToBitrix('catalog.document.element.add', array('fields' => $elementFields));
        if (is_array($lineResp) && isset($lineResp['error'])) {
            $fallbackFields = $elementFields;
            unset($fallbackFields['price'], $fallbackFields['purchasingPrice'], $fallbackFields['currency']);
            $fallbackResp = sendToBitrix('catalog.document.element.add', array('fields' => $fallbackFields));
            $lineResponses[] = array(
                'product_id' => $localProductId,
                'b24_product_id' => $b24ProductId,
                'amount' => $amount,
                'response' => $lineResp,
                'fallback_response' => $fallbackResp
            );
            if (!is_array($fallbackResp) || isset($fallbackResp['error'])) {
                return array(
                    'ok' => false,
                    'stage' => 'document.element.add',
                    'b24_document_id' => $b24DocId,
                    'line_responses' => $lineResponses,
                    'response' => $fallbackResp
                );
            }
            continue;
        }

        $lineResponses[] = array(
            'product_id' => $localProductId,
            'b24_product_id' => $b24ProductId,
            'amount' => $amount,
            'response' => $lineResp
        );
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

        $lineResp = sendToBitrix('catalog.document.element.add', array('fields' => $elementFields));
        if (is_array($lineResp) && isset($lineResp['error'])) {
            // Compatibility fallback: some portals reject pricing fields for element.add.
            $fallbackFields = $elementFields;
            unset($fallbackFields['price'], $fallbackFields['purchasingPrice'], $fallbackFields['currency']);
            $fallbackResp = sendToBitrix('catalog.document.element.add', array('fields' => $fallbackFields));
            $lineResponses[] = array(
                'product_id' => $localProductId,
                'b24_product_id' => $b24ProductId,
                'amount' => $amount,
                'response' => $lineResp,
                'fallback_response' => $fallbackResp
            );
            if (!is_array($fallbackResp) || isset($fallbackResp['error'])) {
                $fallbackError = isset($fallbackResp['error_description']) ? (string)$fallbackResp['error_description'] : (isset($fallbackResp['error']) ? (string)$fallbackResp['error'] : '');
                if ($fallbackError !== '' && (stripos($fallbackError, 'already') !== false || stripos($fallbackError, 'уже') !== false || stripos($fallbackError, 'duplicate') !== false || stripos($fallbackError, 'дублик') !== false)) {
                    continue;
                }
                return array(
                    'ok' => false,
                    'stage' => 'document.element.add',
                    'b24_document_id' => intval($b24DocId),
                    'line_responses' => $lineResponses,
                    'response' => $fallbackResp
                );
            }
            continue;
        }

        $lineResponses[] = array(
            'product_id' => $localProductId,
            'b24_product_id' => $b24ProductId,
            'amount' => $amount,
            'response' => $lineResp
        );
        if (!isset($existingElements[$b24ProductId])) {
            $existingElements[$b24ProductId] = 0.0;
        }
        $existingElements[$b24ProductId] += $amount;
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

function ensureProductForReceipt($db, $productId, $productName, $rollLength, $pricePerRoll, $deliveryPricePerRoll) {
    $baseRollPrice = $deliveryPricePerRoll > 0 ? $deliveryPricePerRoll : $pricePerRoll;
    $pricePerMeter = ($baseRollPrice > 0 && $rollLength > 0) ? ($baseRollPrice / $rollLength) : 0;

    if ($productId > 0) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $stmt->execute(array($productId));
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($p) {
            if ($pricePerRoll > 0 || $deliveryPricePerRoll > 0 || $rollLength > 0) {
                $db->prepare("UPDATE products SET purchase_price = ?, delivery_price = ?, roll_length = ?, price_per_meter = ? WHERE id = ?")
                    ->execute(array($pricePerRoll, $deliveryPricePerRoll, $rollLength, $pricePerMeter, $productId));
            }
            $p['purchase_price'] = $pricePerRoll;
            $p['delivery_price'] = $deliveryPricePerRoll;
            $p['roll_length'] = $rollLength;
            $p['price_per_meter'] = $pricePerMeter;
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
        return ensureProductInBitrix($db, $existing, $pricePerMeter);
    }

    $ins = $db->prepare("
        INSERT INTO products (name, roll_length, purchase_price, delivery_price, price_per_meter)
        VALUES (?, ?, ?, ?, ?)
    ");
    $ins->execute(array($name, $rollLength, $pricePerRoll, $deliveryPricePerRoll, $pricePerMeter));
    $newId = intval($db->lastInsertId());

    $created = array('id' => $newId, 'name' => $name, 'b24_product_id' => 0);
    return ensureProductInBitrix($db, $created, $pricePerMeter);
}

function ensureProductInBitrix($db, $product, $pricePerMeter) {
    $productId = intval(isset($product['id']) ? $product['id'] : 0);
    $productName = isset($product['name']) ? (string)$product['name'] : '';
    $b24ProductId = intval(isset($product['b24_product_id']) ? $product['b24_product_id'] : 0);

    if ($productId <= 0 || $productName === '') {
        return $product;
    }

    if ($b24ProductId > 0) {
        $payload = array(
            'id' => $b24ProductId,
            'fields' => array('NAME' => $productName)
        );
        if ($pricePerMeter > 0) {
            $payload['fields']['PRICE'] = $pricePerMeter;
        }
        sendToBitrix('crm.product.update', $payload);
        return $product;
    }

    $createPayload = array('fields' => array('NAME' => $productName));
    if ($pricePerMeter > 0) {
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
 *     product_id (локальный; 0 можно не указывать, если задан b24_product_id),
 *     b24_product_id — ID товара в Б24: находит или создаёт минимальную строку в products без «связки» с таблицами прайса,
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
    $emergencyStopCreates = stockEmergencyRollCreationStoppedMessage();
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
                    . $docNumber . '» уже есть (документ #' . $extId . '). Дубликаты не созданы.';
                $outBase['error_message'] = '';
                return $outBase;
            }
        }

    $isoTweakedForReceipt = false;
    try {
        // Чтобы другой запрос (сохранение паузы / «прервать приход») увиделся внутри длинной транзакции
        @$db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $isoTweakedForReceipt = true;

        $receiptAbortEpoch = integrationGetStockAbortEpoch($db);
        $db->beginTransaction();
        integrationAssertReceiptAbortEpochUnchanged($db, $receiptAbortEpoch);

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
        /** @var array локальные product_id для одного синка каталога/склада после commit (иначе на каждый рулон — десятки вызовов Б24 → «зависание») */
        $receiptProductIdsNeedCatalogPush = array();

        foreach ($linesIn as $row) {
            if (!is_array($row)) {
                continue;
            }
            integrationAssertReceiptAbortEpochUnchanged($db, $receiptAbortEpoch);

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
            $productName = isset($row['product_name']) ? trim((string)$row['product_name']) : (isset($row['productName']) ? trim((string)$row['productName']) : '');
            $b24LineId = intval(isset($row['b24_product_id']) ? $row['b24_product_id'] : (isset($row['b24ProductId']) ? $row['b24ProductId'] : 0));

            if ($productId <= 0 && $b24LineId > 0) {
                $stB24 = $db->prepare('SELECT id, name FROM products WHERE b24_product_id = ? LIMIT 1');
                $stB24->execute(array($b24LineId));
                $foundByB24 = $stB24->fetch(PDO::FETCH_ASSOC);
                if ($foundByB24) {
                    $productId = intval($foundByB24['id']);
                    if ($productName === '' && isset($foundByB24['name'])) {
                        $productName = trim((string)$foundByB24['name']);
                    }
                } else {
                    $nmIns = ($productName !== '') ? $productName : ('Товар Б24 #' . $b24LineId);
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

            $product = ensureProductForReceipt($db, $productId, $productName, $rollLength, $pricePerRoll, $deliveryPricePerRoll);
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
                if ($r === 0 || ($r % 10) === 0) {
                    integrationAssertReceiptAbortEpochUnchanged($db, $receiptAbortEpoch);
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
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $outBase['error_message'] = $e->getMessage();
        return $outBase;
    } finally {
        if ($isoTweakedForReceipt) {
            @$db->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE-READ');
        }
    }
    } finally {
        stockReceiptMysqlReleaseLock($db, $advisoryLockName);
    }
}
