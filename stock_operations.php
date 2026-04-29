<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require 'db.php';
require_once __DIR__ . '/functions/stock_movements.php';
require_once __DIR__ . '/api/bitrix/send.php';
require_once __DIR__ . '/functions/app_settings.php';
$db = getDB();

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
    ensureColumnExists($db, 'products', 'delivery_price', '`delivery_price` decimal(14,2) NOT NULL DEFAULT 0');
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

function ensureB24ProductStockType($b24ProductId) {
    $id = intval($b24ProductId);
    if ($id <= 0) {
        return false;
    }
    $currentType = null;

    $crmGetResp = sendToBitrix('crm.product.get', array('id' => $id));
    if (is_array($crmGetResp) && !isset($crmGetResp['error']) && isset($crmGetResp['result']) && is_array($crmGetResp['result'])) {
        if (isset($crmGetResp['result']['TYPE'])) {
            $currentType = intval($crmGetResp['result']['TYPE']);
        }
    }

    if ($currentType === null) {
        $catalogGetResp = sendToBitrix('catalog.product.get', array('id' => $id));
        if (is_array($catalogGetResp) && !isset($catalogGetResp['error']) && isset($catalogGetResp['result']) && is_array($catalogGetResp['result'])) {
            if (isset($catalogGetResp['result']['type'])) {
                $currentType = intval($catalogGetResp['result']['type']);
            } elseif (isset($catalogGetResp['result']['TYPE'])) {
                $currentType = intval($catalogGetResp['result']['TYPE']);
            }
        }
    }

    if ($currentType === 1) {
        return true;
    }

    $crmUpdResp = sendToBitrix('crm.product.update', array(
        'id' => $id,
        'fields' => array('TYPE' => 1)
    ));
    $catalogUpdResp = sendToBitrix('catalog.product.update', array(
        'id' => $id,
        'fields' => array('type' => 1, 'TYPE' => 1)
    ));

    $crmUpdated = is_array($crmUpdResp) && !isset($crmUpdResp['error']);
    $catalogUpdated = is_array($catalogUpdResp) && !isset($catalogUpdResp['error']);

    // Re-check final state. At least one successful update plus readable type=1.
    $finalType = null;
    $crmGetResp2 = sendToBitrix('crm.product.get', array('id' => $id));
    if (is_array($crmGetResp2) && !isset($crmGetResp2['error']) && isset($crmGetResp2['result']) && is_array($crmGetResp2['result']) && isset($crmGetResp2['result']['TYPE'])) {
        $finalType = intval($crmGetResp2['result']['TYPE']);
    } else {
        $catalogGetResp2 = sendToBitrix('catalog.product.get', array('id' => $id));
        if (is_array($catalogGetResp2) && !isset($catalogGetResp2['error']) && isset($catalogGetResp2['result']) && is_array($catalogGetResp2['result'])) {
            if (isset($catalogGetResp2['result']['type'])) {
                $finalType = intval($catalogGetResp2['result']['type']);
            } elseif (isset($catalogGetResp2['result']['TYPE'])) {
                $finalType = intval($catalogGetResp2['result']['TYPE']);
            }
        }
    }

    if ($finalType === 1) {
        return true;
    }

    // If re-read is unavailable, still allow only when at least one update succeeded.
    if ($finalType === null && ($crmUpdated || $catalogUpdated)) {
        return true;
    }

    return false;
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
    if ((string)$docType === 'receipt') {
        ensureDocumentSupplierForReceipt($b24DocId, $supplierName);
    }
    $conductResp = sendToBitrix('catalog.document.conduct', array('id' => intval($b24DocId)));
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

        $pStmt = $db->prepare("SELECT b24_product_id, price_per_meter, delivery_price FROM products WHERE id = ? LIMIT 1");
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
        if (!ensureB24ProductStockType($b24ProductId)) {
            return array(
                'ok' => false,
                'stage' => 'product.type',
                'b24_document_id' => $b24DocId,
                'line_responses' => $lineResponses,
                'response' => array(
                    'error' => 'invalid_product_type',
                    'error_description' => 'Товар #' . $b24ProductId . ' в Б24 имеет неподдерживаемый тип для складского документа.'
                )
            );
        }

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

        $pStmt = $db->prepare("SELECT b24_product_id, price_per_meter, delivery_price FROM products WHERE id = ? LIMIT 1");
        $pStmt->execute(array($localProductId));
        $prod = $pStmt->fetch(PDO::FETCH_ASSOC);
        $b24ProductId = intval(isset($prod['b24_product_id']) ? $prod['b24_product_id'] : 0);
        if ($b24ProductId <= 0) {
            $lineResponses[] = array('product_id' => $localProductId, 'status' => 'skip_no_b24_product_id');
            continue;
        }
        if (!ensureB24ProductStockType($b24ProductId)) {
            return array(
                'ok' => false,
                'stage' => 'product.type',
                'b24_document_id' => intval($b24DocId),
                'line_responses' => $lineResponses,
                'response' => array(
                    'error' => 'invalid_product_type',
                    'error_description' => 'Товар #' . $b24ProductId . ' в Б24 имеет неподдерживаемый тип для складского документа.'
                )
            );
        }

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

$successMsg = '';
$errorMsg = '';
ensureStockOperationTables($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'retry_b24_sync') {
    if (!validateFormToken('retry_b24_sync', isset($_POST['form_token']) ? $_POST['form_token'] : '')) {
        $errorMsg = 'Сессия формы устарела. Обновите страницу и повторите.';
    } else {
    $docId = intval(isset($_POST['doc_id']) ? $_POST['doc_id'] : 0);
    if ($docId <= 0) {
        $errorMsg = 'Некорректный документ для повторного синка.';
    } else {
        try {
            $docStmt = $db->prepare("
                SELECT id, operation_type, doc_number, comment_text, supplier, b24_sync_status, b24_document_id
                FROM stock_operation_docs
                WHERE id = ?
                LIMIT 1
            ");
            $docStmt->execute(array($docId));
            $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
            if (!$doc) {
                throw new Exception('Документ не найден.');
            }
            if (!in_array($doc['operation_type'], array('receipt', 'writeoff'), true)) {
                throw new Exception('Повторный синк доступен только для прихода и списания.');
            }
            if (intval(isset($doc['b24_document_id']) ? $doc['b24_document_id'] : 0) > 0 && (string)$doc['b24_sync_status'] === 'sent') {
                throw new Exception('Документ уже синхронизирован в Б24: #' . intval($doc['b24_document_id']));
            }

            $linesStmt = $db->prepare("
                SELECT product_id, qty_rolls, quantity_m, roll_length, price_per_roll, delivery_price_per_roll, line_total
                FROM stock_operation_lines
                WHERE doc_id = ?
                ORDER BY id ASC
            ");
            $linesStmt->execute(array($docId));
            $lineRows = $linesStmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($lineRows)) {
                throw new Exception('У документа нет строк для синка.');
            }

            if (intval(isset($doc['b24_document_id']) ? $doc['b24_document_id'] : 0) > 0) {
                $syncResult = addLinesAndConductExistingB24Document(
                    $db,
                    intval($doc['b24_document_id']),
                    (string)$doc['operation_type'],
                    $lineRows,
                    isset($doc['supplier']) ? (string)$doc['supplier'] : ''
                );
            } else {
                $syncResult = syncOperationDocumentToBitrix(
                    $db,
                    $docId,
                    (string)$doc['operation_type'],
                    (string)$doc['doc_number'],
                    (string)$doc['comment_text'],
                    $lineRows,
                    isset($doc['supplier']) ? (string)$doc['supplier'] : ''
                );
            }
            $syncStatus = resolveB24SyncStatus($syncResult);
            $db->prepare("UPDATE stock_operation_docs SET b24_document_id = ?, b24_sync_status = ?, b24_sync_response = ? WHERE id = ?")
                ->execute(array(
                    isset($syncResult['b24_document_id']) ? intval($syncResult['b24_document_id']) : null,
                    $syncStatus,
                    json_encode($syncResult, JSON_UNESCAPED_UNICODE),
                    $docId
                ));

            if ($syncStatus === 'sent') {
                $successMsg = 'Повторный синк документа #' . $docId . ' выполнен. Б24 документ #' . intval($syncResult['b24_document_id']);
            } elseif ($syncStatus === 'partial') {
                $errorMsg = 'Документ #' . $docId . ' уже создан в Б24 (#' . intval($syncResult['b24_document_id']) . '), но есть ошибка в фиксации данных.';
            } else {
                $errorMsg = 'Повторный синк документа #' . $docId . ' завершился с ошибкой.';
            }
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_doc') {
    if (!validateFormToken('delete_doc', isset($_POST['form_token']) ? $_POST['form_token'] : '')) {
        $errorMsg = 'Сессия формы устарела. Обновите страницу и повторите.';
    } else {
    $docId = intval(isset($_POST['doc_id']) ? $_POST['doc_id'] : 0);
    if ($docId <= 0) {
        $errorMsg = 'Некорректный документ для удаления.';
    } else {
        try {
            $db->beginTransaction();
            $docStmt = $db->prepare("SELECT id, operation_type FROM stock_operation_docs WHERE id = ? LIMIT 1");
            $docStmt->execute(array($docId));
            $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
            if (!$doc) {
                throw new Exception('Документ не найден.');
            }

            $db->prepare("DELETE FROM stock_operation_lines WHERE doc_id = ?")->execute(array($docId));
            $db->prepare("DELETE FROM stock_operation_docs WHERE id = ?")->execute(array($docId));
            $db->commit();
            $successMsg = 'Документ #' . $docId . ' удален.';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errorMsg = $e->getMessage();
        }
    }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_receipt') {
    if (!validateFormToken('create_receipt', isset($_POST['form_token']) ? $_POST['form_token'] : '')) {
        $errorMsg = 'Сессия формы устарела. Обновите страницу и повторите.';
    } else {
    $docNumber = trim(isset($_POST['doc_number']) ? $_POST['doc_number'] : '');
    $supplier = trim(isset($_POST['supplier']) ? $_POST['supplier'] : '');
    $commentText = trim(isset($_POST['comment_text']) ? $_POST['comment_text'] : '');
    $minFull = floatval(isset($_POST['min_full']) ? $_POST['min_full'] : 0.5);
    $lineProductId = isset($_POST['line_product_id']) && is_array($_POST['line_product_id']) ? $_POST['line_product_id'] : array();
    $lineProductName = isset($_POST['line_product_name']) && is_array($_POST['line_product_name']) ? $_POST['line_product_name'] : array();
    $lineQtyRolls = isset($_POST['line_qty_rolls']) && is_array($_POST['line_qty_rolls']) ? $_POST['line_qty_rolls'] : array();
    $lineRollLength = isset($_POST['line_roll_length']) && is_array($_POST['line_roll_length']) ? $_POST['line_roll_length'] : array();
    $linePrice = isset($_POST['line_price_per_roll']) && is_array($_POST['line_price_per_roll']) ? $_POST['line_price_per_roll'] : array();
    $lineDeliveryPrice = isset($_POST['line_delivery_price_per_roll']) && is_array($_POST['line_delivery_price_per_roll']) ? $_POST['line_delivery_price_per_roll'] : array();

    try {
        $db->beginTransaction();
        $insDoc = $db->prepare("
            INSERT INTO stock_operation_docs (operation_type, doc_number, supplier, comment_text, total_amount, status)
            VALUES ('receipt', ?, ?, ?, 0, 'posted')
        ");
        $insDoc->execute(array($docNumber, $supplier, $commentText));
        $docId = intval($db->lastInsertId());

        $insLine = $db->prepare("
            INSERT INTO stock_operation_lines
            (doc_id, product_id, product_name, qty_rolls, roll_length, quantity_m, price_per_roll, delivery_price_per_roll, line_total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $totalAmount = 0.0;
        $addedAny = false;

        for ($i = 0; $i < count($lineQtyRolls); $i++) {
            $qtyRolls = intval($lineQtyRolls[$i]);
            $rollLength = floatval(isset($lineRollLength[$i]) ? $lineRollLength[$i] : 0);
            $pricePerRoll = floatval(isset($linePrice[$i]) ? $linePrice[$i] : 0);
            $deliveryPricePerRoll = floatval(isset($lineDeliveryPrice[$i]) ? $lineDeliveryPrice[$i] : 0);
            $productId = intval(isset($lineProductId[$i]) ? $lineProductId[$i] : 0);
            $productName = isset($lineProductName[$i]) ? trim($lineProductName[$i]) : '';

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
                $lineTotal
            ));

            for ($r = 0; $r < $qtyRolls; $r++) {
                $insRoll = $db->prepare("
                    INSERT INTO rolls (product_id, original_length, current_length, min_full_length, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $insRoll->execute(array($localProductId, $rollLength, $rollLength, $minFull));
                $rollId = intval($db->lastInsertId());

                logAndSyncMovement($db, array(
                    'product_id' => $localProductId,
                    'roll_id' => $rollId,
                    'movement_type' => 'receipt',
                    'quantity_m' => $rollLength,
                    'quantity_rolls' => 1,
                    'price_per_unit' => ($deliveryPricePerRoll > 0 ? $deliveryPricePerRoll : $pricePerRoll),
                    'total' => ($deliveryPricePerRoll > 0 ? $deliveryPricePerRoll : $pricePerRoll),
                    'comment' => 'Оприходование через документ #' . $docId
                ));
            }
        }

        if (!$addedAny) {
            throw new Exception('Добавьте хотя бы одну корректную строку прихода.');
        }

        $db->prepare("UPDATE stock_operation_docs SET total_amount = ? WHERE id = ?")
            ->execute(array($totalAmount, $docId));
        $db->commit();
        $lineRowsForSync = $db->query("SELECT product_id, qty_rolls, quantity_m, roll_length, price_per_roll, delivery_price_per_roll, line_total FROM stock_operation_lines WHERE doc_id=" . intval($docId))->fetchAll(PDO::FETCH_ASSOC);
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
        $successMsg = 'Документ прихода #' . $docId . ' проведен. Сумма: ' . number_format($totalAmount, 2, '.', ' ');
        if ($syncStatus === 'sent') {
            $successMsg .= ' | Б24 документ #' . intval($syncResult['b24_document_id']);
        } elseif ($syncStatus === 'partial') {
            $errorMsg = 'Приход создан в Б24 (#' . intval($syncResult['b24_document_id']) . '), но фиксация строк/проведение завершились с ошибкой.';
        } else {
            $errorMsg = 'Приход проведен локально, но синк в Б24 завершился с ошибкой.';
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $errorMsg = $e->getMessage();
    }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_writeoff') {
    if (!validateFormToken('create_writeoff', isset($_POST['form_token']) ? $_POST['form_token'] : '')) {
        $errorMsg = 'Сессия формы устарела. Обновите страницу и повторите.';
    } else {
    $docNumber = trim(isset($_POST['writeoff_doc_number']) ? $_POST['writeoff_doc_number'] : '');
    $commentText = trim(isset($_POST['writeoff_comment_text']) ? $_POST['writeoff_comment_text'] : '');
    $lineProductId = isset($_POST['writeoff_product_id']) && is_array($_POST['writeoff_product_id']) ? $_POST['writeoff_product_id'] : array();
    $lineMeters = isset($_POST['writeoff_meters']) && is_array($_POST['writeoff_meters']) ? $_POST['writeoff_meters'] : array();
    $lineReason = isset($_POST['writeoff_reason']) && is_array($_POST['writeoff_reason']) ? $_POST['writeoff_reason'] : array();

    try {
        $db->beginTransaction();
        $insDoc = $db->prepare("
            INSERT INTO stock_operation_docs (operation_type, doc_number, supplier, comment_text, total_amount, status)
            VALUES ('writeoff', ?, '', ?, 0, 'posted')
        ");
        $insDoc->execute(array($docNumber, $commentText));
        $docId = intval($db->lastInsertId());

        $insLine = $db->prepare("
            INSERT INTO stock_operation_lines
            (doc_id, product_id, product_name, qty_rolls, roll_length, quantity_m, price_per_roll, line_total)
            VALUES (?, ?, ?, 0, 0, ?, 0, 0)
        ");

        $addedAny = false;
        for ($i = 0; $i < count($lineProductId); $i++) {
            $productId = intval(isset($lineProductId[$i]) ? $lineProductId[$i] : 0);
            $meters = floatval(isset($lineMeters[$i]) ? $lineMeters[$i] : 0);
            $reason = isset($lineReason[$i]) ? trim($lineReason[$i]) : '';
            if ($productId <= 0 || $meters <= 0) {
                continue;
            }

            $pStmt = $db->prepare("SELECT id, name FROM products WHERE id = ? LIMIT 1");
            $pStmt->execute(array($productId));
            $product = $pStmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                throw new Exception('Товар для списания не найден (ID ' . $productId . ').');
            }

            $taken = consumeWriteoffMeters($db, $productId, $meters);
            foreach ($taken as $piece) {
                logAndSyncMovement($db, array(
                    'product_id' => $productId,
                    'roll_id' => intval($piece['roll_id']),
                    'movement_type' => 'writeoff',
                    'quantity_m' => floatval($piece['meters']),
                    'quantity_rolls' => 0,
                    'price_per_unit' => 0,
                    'total' => 0,
                    'comment' => 'Списание через документ #' . $docId . ($reason !== '' ? ' | ' . $reason : '')
                ));
            }

            $db->prepare("
                INSERT INTO sales (product_id, type, quantity, price_per_unit, total, deal_id, deal_url)
                VALUES (?, 'writeoff', ?, 0, 0, NULL, NULL)
            ")->execute(array($productId, $meters));

            $insLine->execute(array(
                $docId,
                $productId,
                $product['name'],
                $meters
            ));
            $addedAny = true;
        }

        if (!$addedAny) {
            throw new Exception('Добавьте хотя бы одну корректную строку списания.');
        }

        $db->commit();
        $lineRowsForSync = $db->query("SELECT product_id, qty_rolls, quantity_m, roll_length, price_per_roll, delivery_price_per_roll, line_total FROM stock_operation_lines WHERE doc_id=" . intval($docId))->fetchAll(PDO::FETCH_ASSOC);
        $syncResult = syncOperationDocumentToBitrix($db, $docId, 'writeoff', $docNumber, $commentText, $lineRowsForSync, '');
        $syncResult = tryFinalizePartialDocument($db, 'writeoff', $syncResult, $lineRowsForSync, '');
        $syncStatus = resolveB24SyncStatus($syncResult);
        $db->prepare("UPDATE stock_operation_docs SET b24_document_id = ?, b24_sync_status = ?, b24_sync_response = ? WHERE id = ?")
            ->execute(array(
                isset($syncResult['b24_document_id']) ? intval($syncResult['b24_document_id']) : null,
                $syncStatus,
                json_encode($syncResult, JSON_UNESCAPED_UNICODE),
                $docId
            ));
        $successMsg = 'Документ списания #' . $docId . ' проведен.';
        if ($syncStatus === 'sent') {
            $successMsg .= ' | Б24 документ #' . intval($syncResult['b24_document_id']);
        } elseif ($syncStatus === 'partial') {
            $errorMsg = 'Списание создано в Б24 (#' . intval($syncResult['b24_document_id']) . '), но фиксация строк/проведение завершились с ошибкой.';
        } else {
            $errorMsg = 'Списание проведено локально, но синк в Б24 завершился с ошибкой.';
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $errorMsg = $e->getMessage();
    }
    }
}

$receiptToken = ensureFormToken('create_receipt');
$writeoffToken = ensureFormToken('create_writeoff');
$deleteToken = ensureFormToken('delete_doc');
$retryToken = ensureFormToken('retry_b24_sync');

$products = $db->query("SELECT id, name, roll_length, purchase_price, delivery_price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$stockProducts = $db->query("
    SELECT
        p.id,
        p.name,
        ROUND(SUM(r.current_length), 2) as free_meters
    FROM rolls r
    JOIN products p ON p.id = r.product_id
    WHERE r.status NOT IN ('sold', 'written_off', 'waste')
      AND r.current_length > 0
      AND r.reserved = 0
    GROUP BY p.id, p.name
    HAVING SUM(r.current_length) > 0
    ORDER BY p.name ASC
")->fetchAll(PDO::FETCH_ASSOC);
$recentDocs = $db->query("
    SELECT id, operation_type, doc_number, supplier, total_amount, status, created_at, b24_document_id, b24_sync_status, b24_sync_response
    FROM stock_operation_docs
    ORDER BY id DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Складские операции';
require 'includes/header.php';
?>

<main class="container">
    <div class="card">
        <h2>🧾 Складские операции</h2>
        <p class="text-muted">
            Единая точка работы со складом: приход, списание, реализация и синхронизация с Б24.
        </p>
    </div>

    <div class="card">
        <h3>📥 Создать приход</h3>
        <?php if ($successMsg): ?><div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
        <?php if ($errorMsg): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

        <form method="POST" id="receipt-doc-form">
            <input type="hidden" name="action" value="create_receipt">
            <input type="hidden" name="form_token" value="<?= htmlspecialchars($receiptToken) ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Номер документа</label>
                    <input type="text" name="doc_number" placeholder="Например: ПР-2026-04-28">
                </div>
                <div class="form-group">
                    <label>Поставщик</label>
                    <input type="text" name="supplier" placeholder="Название поставщика">
                </div>
                <div class="form-group">
                    <label>Мин. остаток рулона (м)</label>
                    <input type="number" name="min_full" step="0.1" min="0" value="0.5">
                </div>
            </div>
            <div class="form-group">
                <label>Комментарий</label>
                <input type="text" name="comment_text" placeholder="Примечание к приходу">
            </div>

            <div class="table-responsive receipt-table-wrap">
                <table class="table" id="receipt-lines">
                    <thead>
                        <tr>
                            <th>Товар (из базы)</th>
                            <th>Название (если новый)</th>
                            <th>Рулонов</th>
                            <th>Длина рулона (м)</th>
                            <th>Закупка за рулон</th>
                            <th>С доставкой за рулон</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select name="line_product_id[]">
                                    <option value="0">-- Новый товар --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= intval($p['id']) ?>" data-roll-length="<?= htmlspecialchars((string)$p['roll_length']) ?>" data-price="<?= htmlspecialchars((string)$p['purchase_price']) ?>" data-delivery-price="<?= htmlspecialchars((string)$p['delivery_price']) ?>">
                                            <?= htmlspecialchars($p['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="line_product_name[]" placeholder="Если новый товар"></td>
                            <td><input type="number" name="line_qty_rolls[]" min="1" value="1"></td>
                            <td><input type="number" name="line_roll_length[]" min="0.1" step="0.1" value="30"></td>
                            <td><input type="number" name="line_price_per_roll[]" min="0" step="0.01" value="0"></td>
                            <td><input type="number" name="line_delivery_price_per_roll[]" min="0" step="0.01" value="0"></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-line">×</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" class="btn btn-light" id="add-receipt-line">+ Добавить строку</button>
                <button type="submit" class="btn btn-success">Провести приход</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>🗑️ Создать списание</h3>
        <form method="POST" id="writeoff-doc-form">
            <input type="hidden" name="action" value="create_writeoff">
            <input type="hidden" name="form_token" value="<?= htmlspecialchars($writeoffToken) ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Номер документа</label>
                    <input type="text" name="writeoff_doc_number" placeholder="Например: СП-2026-04-28">
                </div>
                <div class="form-group">
                    <label>Комментарий</label>
                    <input type="text" name="writeoff_comment_text" placeholder="Общее основание списания">
                </div>
            </div>

            <table class="table" id="writeoff-lines">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>К списанию (м)</th>
                        <th>Причина</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <select name="writeoff_product_id[]">
                                <option value="0">-- Выберите товар --</option>
                                <?php foreach ($stockProducts as $p): ?>
                                    <option value="<?= intval($p['id']) ?>">
                                        <?= htmlspecialchars($p['name']) ?> (доступно: <?= number_format(floatval($p['free_meters']), 2, '.', ' ') ?> м)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" name="writeoff_meters[]" min="0.1" step="0.1" value="1"></td>
                        <td><input type="text" name="writeoff_reason[]" placeholder="Например: брак/повреждение"></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-writeoff-line">×</button></td>
                    </tr>
                </tbody>
            </table>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" class="btn btn-light" id="add-writeoff-line">+ Добавить строку</button>
                <button type="submit" class="btn btn-warning">Провести списание</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Последние документы</h3>
        <?php if (empty($recentDocs)): ?>
            <p>Документов пока нет.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Тип</th>
                        <th>№ документа</th>
                        <th>Поставщик</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th>Б24</th>
                        <th>Дата</th>
                        <th>Документ</th>
                        <th>Синк Б24</th>
                        <th>Ошибка</th>
                        <th>Удалить</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDocs as $d): ?>
                        <tr>
                            <td><?= intval($d['id']) ?></td>
                            <td><?= htmlspecialchars(localizeOperationType(isset($d['operation_type']) ? $d['operation_type'] : '')) ?></td>
                            <td><?= htmlspecialchars((string)$d['doc_number']) ?></td>
                            <td><?= htmlspecialchars((string)$d['supplier']) ?></td>
                            <td><?= number_format(floatval($d['total_amount']), 2, '.', ' ') ?></td>
                            <td><?= htmlspecialchars((string)$d['status']) ?></td>
                            <td>
                                <?php if (!empty($d['b24_document_id'])): ?>
                                    #<?= intval($d['b24_document_id']) ?> (<?= htmlspecialchars((string)$d['b24_sync_status']) ?>)
                                <?php else: ?>
                                    <?= htmlspecialchars((string)$d['b24_sync_status']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string)$d['created_at']) ?></td>
                            <td>
                                <a href="stock_operation_print.php?id=<?= intval($d['id']) ?>" class="btn btn-light btn-sm" target="_blank">Открыть</a>
                            </td>
                            <td>
                                <?php if ((string)$d['b24_sync_status'] !== 'sent' && in_array((string)$d['operation_type'], array('receipt', 'writeoff'), true)): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="retry_b24_sync">
                                        <input type="hidden" name="form_token" value="<?= htmlspecialchars($retryToken) ?>">
                                        <input type="hidden" name="doc_id" value="<?= intval($d['id']) ?>">
                                        <button type="submit" class="btn btn-warning btn-sm"><?= intval(isset($d['b24_document_id']) ? $d['b24_document_id'] : 0) > 0 ? 'Дофиксировать' : 'Повторить' ?></button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (in_array((string)$d['b24_sync_status'], array('error', 'partial'), true) && !empty($d['b24_sync_response'])): ?>
                                    <button type="button" class="btn btn-light btn-sm js-show-b24-error" data-error="<?= htmlspecialchars((string)$d['b24_sync_response']) ?>">Подробнее</button>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить документ #<?= intval($d['id']) ?>?');">
                                    <input type="hidden" name="action" value="delete_doc">
                                    <input type="hidden" name="form_token" value="<?= htmlspecialchars($deleteToken) ?>">
                                    <input type="hidden" name="doc_id" value="<?= intval($d['id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Технические моменты</h3>
        <p>Синк, настройки интеграции и скорость вынесены в отдельную вкладку.</p>
        <a href="sync_monitor.php" class="btn btn-secondary">⚙️ Открыть центр интеграции</a>
    </div>
</main>

<script>
(function () {
    var addBtn = document.getElementById('add-receipt-line');
    var tableBody = document.querySelector('#receipt-lines tbody');
    if (!addBtn || !tableBody) {
        return;
    }

    var bindRow = function (row) {
        var select = row.querySelector('select[name="line_product_id[]"]');
        var lenInput = row.querySelector('input[name="line_roll_length[]"]');
        var priceInput = row.querySelector('input[name="line_price_per_roll[]"]');
        var deliveryPriceInput = row.querySelector('input[name="line_delivery_price_per_roll[]"]');
        var removeBtn = row.querySelector('.remove-line');

        if (select) {
            select.addEventListener('change', function () {
                var opt = select.options[select.selectedIndex];
                if (!opt || select.value === '0') {
                    return;
                }
                if (lenInput && opt.getAttribute('data-roll-length')) {
                    lenInput.value = opt.getAttribute('data-roll-length');
                }
                if (priceInput && opt.getAttribute('data-price')) {
                    priceInput.value = opt.getAttribute('data-price');
                }
                if (deliveryPriceInput && opt.getAttribute('data-delivery-price')) {
                    deliveryPriceInput.value = opt.getAttribute('data-delivery-price');
                }
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                if (tableBody.querySelectorAll('tr').length <= 1) {
                    return;
                }
                row.parentNode.removeChild(row);
            });
        }
    };

    bindRow(tableBody.querySelector('tr'));

    addBtn.addEventListener('click', function () {
        var lastRow = tableBody.querySelector('tr:last-child');
        if (!lastRow) {
            return;
        }
        var newRow = lastRow.cloneNode(true);
        var inputs = newRow.querySelectorAll('input');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].name === 'line_qty_rolls[]') {
                inputs[i].value = '1';
            } else if (inputs[i].name === 'line_roll_length[]') {
                inputs[i].value = '30';
            } else if (inputs[i].name === 'line_price_per_roll[]') {
                inputs[i].value = '0';
            } else if (inputs[i].name === 'line_delivery_price_per_roll[]') {
                inputs[i].value = '0';
            } else {
                inputs[i].value = '';
            }
        }
        var select = newRow.querySelector('select[name="line_product_id[]"]');
        if (select) {
            select.value = '0';
        }
        bindRow(newRow);
        tableBody.appendChild(newRow);
    });
})();

(function () {
    var addBtn = document.getElementById('add-writeoff-line');
    var tableBody = document.querySelector('#writeoff-lines tbody');
    if (!addBtn || !tableBody) {
        return;
    }

    var bindRow = function (row) {
        var removeBtn = row.querySelector('.remove-writeoff-line');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                if (tableBody.querySelectorAll('tr').length <= 1) {
                    return;
                }
                row.parentNode.removeChild(row);
            });
        }
    };

    bindRow(tableBody.querySelector('tr'));

    addBtn.addEventListener('click', function () {
        var lastRow = tableBody.querySelector('tr:last-child');
        if (!lastRow) {
            return;
        }
        var newRow = lastRow.cloneNode(true);
        var select = newRow.querySelector('select[name="writeoff_product_id[]"]');
        if (select) {
            select.value = '0';
        }
        var inputs = newRow.querySelectorAll('input');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].name === 'writeoff_meters[]') {
                inputs[i].value = '1';
            } else {
                inputs[i].value = '';
            }
        }
        bindRow(newRow);
        tableBody.appendChild(newRow);
    });
})();

(function () {
    function ensureErrorModal() {
        var existing = document.getElementById('b24-error-modal');
        if (existing) return existing;
        var wrap = document.createElement('div');
        wrap.id = 'b24-error-modal';
        wrap.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;padding:20px;box-sizing:border-box;';
        wrap.innerHTML = ''
            + '<div style="max-width:760px;margin:8vh auto;background:#fff;border-radius:10px;overflow:hidden;">'
            + '<div style="padding:12px 16px;background:#f5f7fb;display:flex;justify-content:space-between;align-items:center;">'
            + '<strong>Ошибка синка Б24</strong><button type="button" id="b24-error-close" style="border:none;background:transparent;font-size:20px;cursor:pointer;">×</button>'
            + '</div>'
            + '<pre id="b24-error-body" style="margin:0;padding:14px 16px;max-height:60vh;overflow:auto;white-space:pre-wrap;"></pre>'
            + '</div>';
        document.body.appendChild(wrap);
        wrap.querySelector('#b24-error-close').addEventListener('click', function () { wrap.style.display = 'none'; });
        wrap.addEventListener('click', function (e) { if (e.target === wrap) wrap.style.display = 'none'; });
        return wrap;
    }
    document.querySelectorAll('.js-show-b24-error').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modal = ensureErrorModal();
            var body = modal.querySelector('#b24-error-body');
            var text = btn.getAttribute('data-error') || '';
            try { text = JSON.stringify(JSON.parse(text), null, 2); } catch (_e) {}
            body.textContent = text;
            modal.style.display = 'block';
        });
    });
})();

(function () {
    document.querySelectorAll('form#receipt-doc-form, form#writeoff-doc-form').forEach(function(form) {
        form.addEventListener('submit', function() {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = '⏳ Ждем ответ Б24...';
            }
        });
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
