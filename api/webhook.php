<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require '../db.php';
$db = getDB();
require_once __DIR__ . '/../functions/webhook_log_schema.php';

/** @var int|null Строка в webhook_log текущего запроса (для итога обработки). */
$GLOBALS['webhook_log_id'] = null;

function extractDealPayload($data) {
    return webhookLogExtractDealPayload($data);
}

function webhookLoadHandlers() {
    require_once __DIR__ . '/bitrix/deal.php';
    require_once __DIR__ . '/bitrix/warehouse_gate.php';
    require_once __DIR__ . '/bitrix/send.php';
    require_once __DIR__ . '/../functions/stock_movements.php';
}

/**
 * Исходящий вебхук Битрикс24 часто приходит как application/x-www-form-urlencoded:
 * event=ONCRMDEALUPDATE&data[FIELDS][ID]=...&auth[domain]=...
 * вместо JSON. Приводим к той же форме массива, что и у JSON-тела.
 */
function bitrixWebhookNormalizeFromParsedForm($parsed) {
    if (!is_array($parsed) || empty($parsed['event'])) {
        return null;
    }

    return array(
        'event' => $parsed['event'],
        'data' => (isset($parsed['data']) && is_array($parsed['data'])) ? $parsed['data'] : array(),
        'auth' => (isset($parsed['auth']) && is_array($parsed['auth'])) ? $parsed['auth'] : array(),
        'ts' => isset($parsed['ts']) ? $parsed['ts'] : null,
        'event_handler_id' => isset($parsed['event_handler_id']) ? $parsed['event_handler_id'] : null,
    );
}

function bitrixWebhookDecodeRequestBody($rawInput) {
    $data = json_decode((string)$rawInput, true);
    if (is_array($data) && isset($data['event'])) {
        return $data;
    }

    $parsed = array();

    if (!empty($_POST) && isset($_POST['event'])) {
        $parsed = $_POST;
    }

    $trimRaw = trim((string)$rawInput);
    if (empty($parsed['event']) && $trimRaw !== '') {
        parse_str((string)$rawInput, $parsed);
    }

    $normalized = bitrixWebhookNormalizeFromParsedForm($parsed);
    if ($normalized !== null) {
        return $normalized;
    }

    return null;
}

function ensureWebhookLockTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS webhook_event_lock (
            id int NOT NULL AUTO_INCREMENT,
            event_hash varchar(64) NOT NULL,
            event_name varchar(120) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_webhook_event_hash (event_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensureDynamicItemInboxTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS b24_dynamic_item_inbox (
            id int NOT NULL AUTO_INCREMENT,
            event_name varchar(80) NOT NULL,
            entity_type_id int DEFAULT NULL,
            item_id int DEFAULT NULL,
            item_payload longtext,
            product_rows_payload longtext,
            source_payload longtext,
            process_status varchar(20) NOT NULL DEFAULT 'new',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_b24_dynamic_item_lookup (entity_type_id, item_id),
            KEY idx_b24_dynamic_item_status (process_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// Получаем данные от Битрикс24 (сначала лог в БД — до тяжёлых require)
$input = file_get_contents('php://input');
$data = bitrixWebhookDecodeRequestBody($input);

webhookLogEnsureSchema($db);

if (!is_array($data)) {
    $raw = mb_substr((string)$input, 0, 60000);
    $GLOBALS['webhook_log_id'] = webhookLogInsertIncoming(
        $db,
        'INVALID_JSON_OR_BODY',
        array(
            'hint' => 'neither_json_nor_form-urlencoded',
            'body_chars' => strlen((string)$input),
            'body_preview' => $raw,
        ),
        null,
        null
    );
    webhookLogFinish($db, 'json_decode_failed_or_empty_body');
    echo json_encode(array('error' => 'No data received'));
    exit;
}

$event = isset($data['event']) ? $data['event'] : '';
$auth = (isset($data['auth']) && is_array($data['auth'])) ? $data['auth'] : array();
$eventDataForHash = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : array();
$eventHash = hash('sha256', $event . '|' . json_encode($eventDataForHash, JSON_UNESCAPED_UNICODE));

list($incomingDealId, $incomingProductId) = webhookLogExtractEntityIds($event, $data);
$GLOBALS['webhook_log_id'] = webhookLogInsertIncoming($db, $event === '' ? 'EMPTY_EVENT_FIELD' : $event, $data, $incomingDealId, $incomingProductId);

webhookRegisterFatalOutcomeGuard($db);

webhookLoadHandlers();

ensureWebhookLockTable($db);
$lockStmt = $db->prepare("INSERT IGNORE INTO webhook_event_lock (event_hash, event_name) VALUES (?, ?)");
$lockStmt->execute([$eventHash, $event]);
if ($lockStmt->rowCount() === 0) {
    webhookLogFinish($db, 'duplicate_delivery_skipped');
    echo json_encode(array('status' => 'duplicate_event_ignored', 'event' => $event));
    exit;
}

switch ($event) {
    case 'ONCRMDEALADD':
        // Новый сделка в Б24
        handleNewDeal($db, $data);
        break;
        
    case 'ONCRMDEALUPDATE':
        // Обновление сделки
        handleDealUpdate($db, $data);
        break;
        
    case 'ONCRMPRODUCTADD':
        // Новый товар
        handleNewProduct($db, $data);
        break;
        
    case 'ONCRMPRODUCTUPDATE':
        // Обновление товара
        handleProductUpdate($db, $data);
        break;

    case 'ONCRMDYNAMICITEMADD':
        handleDynamicItemEvent($db, $data, 'add');
        break;

    case 'ONCRMDYNAMICITEMUPDATE':
        handleDynamicItemEvent($db, $data, 'update');
        break;

    case 'ONCRMDYNAMICITEMDELETE':
        handleDynamicItemEvent($db, $data, 'delete');
        break;
        
    default:
        webhookLogFinish($db, 'unknown_event:' . substr((string)$event, 0, 110));
        echo json_encode(['status' => 'unknown_event', 'event' => $event]);
        exit;
}

function handleNewDeal($db, $data) {
    $deal = extractDealPayload($data);
    
    if (empty($deal)) {
        webhookLogFinish($db, 'deal_add_empty_payload');
        echo json_encode(['error' => 'No deal data']);
        exit;
    }
    
    $dealId = intval(isset($deal['ID']) ? $deal['ID'] : 0);
    $dealName = isset($deal['TITLE']) ? $deal['TITLE'] : '';
    $responsibleId = isset($deal['ASSIGNED_BY_ID']) ? $deal['ASSIGNED_BY_ID'] : '';
    
    if ($dealId <= 0) {
        webhookLogFinish($db, 'deal_add_invalid_id');
        echo json_encode(['error' => 'Invalid deal ID']);
        exit;
    }

    $cfg = require __DIR__ . '/bitrix/config.php';
    $gate = isset($cfg['warehouse_queue']) && is_array($cfg['warehouse_queue']) ? $cfg['warehouse_queue'] : [];
    $dealCtx = $deal;
    if (!empty($gate['filter_enabled'])) {
        $dealCtx = bitrixMergeDealWebhookAndCrm($deal, getDealDetails($dealId));
    }
    if (!bitrixWarehouseQueueAllowed($dealCtx, $gate)) {
        webhookLogFinish($db, 'skipped_warehouse_gate', $dealId, null);
        echo json_encode([
            'status' => 'skipped_warehouse_gate',
            'deal_id' => $dealId,
            'category_id' => isset($dealCtx['CATEGORY_ID']) ? $dealCtx['CATEGORY_ID'] : null,
            'stage_id' => isset($dealCtx['STAGE_ID']) ? $dealCtx['STAGE_ID'] : null,
        ]);
        exit;
    }
    
    // Получаем информацию о ответственном
    $responsible = getUserName($db, $responsibleId);
    
    // Получаем товары в сделке
    $products = getDealProducts($db, $dealId);
    $firstPidNew = (!empty($products) && isset($products[0]['id'])) ? intval($products[0]['id']) : 0;
    $logProductId = ($firstPidNew > 0 ? $firstPidNew : null);
    
    if (!empty($products)) {
        $dealData = [
            'deal_id' => $dealId,
            'deal_name' => $dealName,
            'responsible' => $responsible,
            'products' => $products
        ];
        $result = queueDealForWarehouse($db, $dealData);
        if (isset($result['error'])) {
            webhookLogFinish($db, 'queue_error', $dealId, $logProductId);
        } else {
            webhookLogFinish($db, 'deal_processed', $dealId, $logProductId);
        }
        echo json_encode(isset($result['error'])
            ? ['status' => 'error', 'deal_id' => $dealId, 'error' => $result['error']]
            : array('status' => 'deal_processed', 'deal_id' => $dealId, 'request_id' => isset($result['request_id']) ? $result['request_id'] : null)
        );
    } else {
        webhookFinishNoProducts($db, $dealId);
        echo json_encode(['status' => 'no_products', 'deal_id' => $dealId]);
    }
}

function handleDealUpdate($db, $data) {
    $deal = extractDealPayload($data);
    $dealId = intval(isset($deal['ID']) ? $deal['ID'] : 0);
    
    if ($dealId <= 0) {
        webhookLogFinish($db, 'deal_update_invalid_id');
        echo json_encode(['error' => 'Invalid deal ID']);
        exit;
    }
    
    // Получаем актуальные товары и пересобираем заявку для кладовщика
    $dealData = getDealDetails($dealId);
    $products = getDealProducts($db, $dealId);
    $firstPidUpd = (!empty($products) && isset($products[0]['id'])) ? intval($products[0]['id']) : 0;
    $logProductIdDeal = ($firstPidUpd > 0 ? $firstPidUpd : null);

    $cfg = require __DIR__ . '/bitrix/config.php';
    $gate = isset($cfg['warehouse_queue']) && is_array($cfg['warehouse_queue']) ? $cfg['warehouse_queue'] : [];
    $dealCtx = bitrixMergeDealWebhookAndCrm($deal, $dealData);
    
    if (!empty($products) && bitrixWarehouseQueueAllowed($dealCtx, $gate)) {
        $result = queueDealForWarehouse($db, [
            'deal_id' => $dealId,
            'deal_name' => isset($dealData['TITLE']) ? $dealData['TITLE'] : ('Deal #' . $dealId),
            'responsible' => isset($dealData['ASSIGNED_BY_ID']) ? getUserName($db, $dealData['ASSIGNED_BY_ID']) : '',
            'products' => $products
        ]);
        if (isset($result['error'])) {
            webhookLogFinish($db, 'queue_error', $dealId, $logProductIdDeal);
        } else {
            webhookLogFinish($db, 'deal_updated', $dealId, $logProductIdDeal);
        }
        echo json_encode(isset($result['error'])
            ? ['status' => 'error', 'deal_id' => $dealId, 'error' => $result['error']]
            : array('status' => 'deal_updated', 'deal_id' => $dealId, 'request_id' => isset($result['request_id']) ? $result['request_id'] : null)
        );
    } elseif (!empty($products)) {
        webhookLogFinish($db, 'skipped_warehouse_gate', $dealId, $logProductIdDeal);
        echo json_encode([
            'status' => 'skipped_warehouse_gate',
            'deal_id' => $dealId,
            'category_id' => isset($dealCtx['CATEGORY_ID']) ? $dealCtx['CATEGORY_ID'] : null,
            'stage_id' => isset($dealCtx['STAGE_ID']) ? $dealCtx['STAGE_ID'] : null,
        ]);
    } else {
        webhookFinishNoProducts($db, $dealId);
        echo json_encode(['status' => 'no_products', 'deal_id' => $dealId]);
    }

    applyDealPaidOrReserveMark($db, $dealId, $dealData);
}

function handleNewProduct($db, $data) {
    $product = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : array();
    
    if (empty($product)) {
        webhookLogFinish($db, 'product_add_empty_payload');
        echo json_encode(['error' => 'No product data']);
        exit;
    }
    
    $productId = intval(isset($product['ID']) ? $product['ID'] : 0);
    $productName = isset($product['NAME']) ? $product['NAME'] : '';
    $productPrice = floatval(isset($product['PRICE']) ? $product['PRICE'] : 0);
    
    if ($productId <= 0) {
        webhookLogFinish($db, 'product_add_invalid_id');
        echo json_encode(['error' => 'Invalid product ID']);
        exit;
    }
    
    // Добавляем товар в локальную БД
    $stmt = $db->prepare("SELECT id FROM products WHERE b24_product_id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
        $upd = $db->prepare("UPDATE products SET name = ?, price_per_meter = ? WHERE id = ?");
        $upd->execute([$productName, $productPrice, intval($exists['id'])]);
        webhookLogFinish($db, 'product_upserted_existing', null, $productId);
    } else {
        $ins = $db->prepare("
            INSERT INTO products (name, roll_length, price_per_meter, b24_product_id)
            VALUES (?, 30, ?, ?)
        ");
        $ins->execute([$productName, $productPrice, $productId]);
        webhookLogFinish($db, 'product_inserted_local', null, $productId);
    }
    
    echo json_encode(['status' => 'product_added', 'product_id' => $productId]);
}

function handleProductUpdate($db, $data) {
    $product = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : array();
    
    if (empty($product)) {
        webhookLogFinish($db, 'product_update_empty_payload');
        echo json_encode(['error' => 'No product data']);
        exit;
    }
    
    $productId = intval(isset($product['ID']) ? $product['ID'] : 0);
    $productName = isset($product['NAME']) ? $product['NAME'] : '';
    $productPrice = floatval(isset($product['PRICE']) ? $product['PRICE'] : 0);
    
    if ($productId <= 0) {
        webhookLogFinish($db, 'product_update_invalid_id');
        echo json_encode(['error' => 'Invalid product ID']);
        exit;
    }
    
    // Обновляем товар в локальной БД
    $stmt = $db->prepare("
        UPDATE products 
        SET name = ?, price_per_meter = ?
        WHERE b24_product_id = ?
    ");
    $stmt->execute([$productName, $productPrice, $productId]);
    
    webhookLogFinish($db, 'product_updated_local', null, $productId);
    echo json_encode(['status' => 'product_updated', 'product_id' => $productId]);
}

function handleDynamicItemEvent($db, $data, $action) {
    ensureDynamicItemInboxTable($db);

    $ids = extractDynamicItemIds($data);
    $entityTypeId = intval($ids['entity_type_id']);
    $itemId = intval($ids['item_id']);
    if ($entityTypeId <= 0 || $itemId <= 0) {
        webhookLogFinish($db, 'dynamic_item_missing_ids');
        echo json_encode([
            'status' => 'dynamic_item_skipped',
            'reason' => 'missing_entity_or_item_id',
            'action' => $action
        ]);
        return;
    }

    $itemPayload = null;
    $rowsPayload = null;

    if ($action !== 'delete') {
        $itemResp = sendToBitrix('crm.item.get', [
            'entityTypeId' => $entityTypeId,
            'id' => $itemId
        ]);
        $itemPayload = is_array($itemResp) ? json_encode($itemResp, JSON_UNESCAPED_UNICODE) : null;

        $rowsResp = sendToBitrix('crm.item.productrow.get', [
            'entityTypeId' => $entityTypeId,
            'id' => $itemId
        ]);
        $rowsPayload = is_array($rowsResp) ? json_encode($rowsResp, JSON_UNESCAPED_UNICODE) : null;
    }

    $ins = $db->prepare("
        INSERT INTO b24_dynamic_item_inbox
        (event_name, entity_type_id, item_id, item_payload, product_rows_payload, source_payload, process_status)
        VALUES (?, ?, ?, ?, ?, ?, 'new')
    ");
    $ins->execute([
        'ONCRMDYNAMICITEM' . strtoupper($action),
        $entityTypeId,
        $itemId,
        $itemPayload,
        $rowsPayload,
        json_encode($data, JSON_UNESCAPED_UNICODE)
    ]);

    webhookLogFinish($db, 'dynamic_item_queued_et' . $entityTypeId);
    echo json_encode([
        'status' => 'dynamic_item_queued',
        'action' => $action,
        'entity_type_id' => $entityTypeId,
        'item_id' => $itemId,
        'inbox_id' => intval($db->lastInsertId())
    ]);
}

function extractDynamicItemIds($data) {
    $entityTypeId = 0;
    $itemId = 0;
    $payload = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
    $fields = isset($payload['FIELDS']) && is_array($payload['FIELDS']) ? $payload['FIELDS'] : $payload;

    if (isset($fields['ENTITY_TYPE_ID'])) {
        $entityTypeId = intval($fields['ENTITY_TYPE_ID']);
    } elseif (isset($payload['ENTITY_TYPE_ID'])) {
        $entityTypeId = intval($payload['ENTITY_TYPE_ID']);
    }

    if (isset($fields['ID'])) {
        $itemId = intval($fields['ID']);
    } elseif (isset($fields['ITEM_ID'])) {
        $itemId = intval($fields['ITEM_ID']);
    } elseif (isset($payload['ID'])) {
        $itemId = intval($payload['ID']);
    } elseif (isset($payload['ITEM_ID'])) {
        $itemId = intval($payload['ITEM_ID']);
    }

    return [
        'entity_type_id' => $entityTypeId,
        'item_id' => $itemId
    ];
}

function getUserName($db, $userId) {
    // Здесь можно добавить получение имени пользователя из Б24
    // Пока возвращаем ID
    return "User {$userId}";
}

function applyDealPaidOrReserveMark($db, $dealId, $dealData) {
    if ($dealId <= 0 || !is_array($dealData)) {
        return;
    }

    try {
        $stage = strtoupper(trim((string)(isset($dealData['STAGE_ID']) ? $dealData['STAGE_ID'] : '')));
        $semantic = strtolower(trim((string)(isset($dealData['SEMANTICS']) ? $dealData['SEMANTICS'] : '')));
        $isPaid = $semantic === 's' || in_array($stage, ['WON', 'C4:WON', 'FINAL_INVOICE', 'UC_1G5NIZ'], true);

        if ($isPaid) {
            $db->prepare("UPDATE b24_sale_requests SET status = 'completed', updated_at = NOW() WHERE b24_deal_id = ?")
                ->execute([$dealId]);

            $lineIdsStmt = $db->prepare("
                SELECT l.id
                FROM b24_sale_lines l
                JOIN b24_sale_requests r ON r.id = l.request_id
                WHERE r.b24_deal_id = ? AND l.status != 'completed'
            ");
            $lineIdsStmt->execute([$dealId]);
            $lineIds = $lineIdsStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($lineIds)) {
                $placeholders = implode(',', array_fill(0, count($lineIds), '?'));
                $cutsStmt = $db->prepare("
                    SELECT c.line_id, c.roll_id, c.meters, l.product_id
                    FROM b24_sale_line_cuts c
                    JOIN b24_sale_lines l ON l.id = c.line_id
                    WHERE c.line_id IN ($placeholders)
                ");
                $cutsStmt->execute($lineIds);
                $cuts = $cutsStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($cuts as $cut) {
                    $dupStmt = $db->prepare("
                        SELECT id
                        FROM stock_movements
                        WHERE deal_id = ?
                          AND roll_id = ?
                          AND movement_type = 'sale_meter'
                          AND comment = 'Сделка оплачена в Б24'
                        LIMIT 1
                    ");
                    $dupStmt->execute([$dealId, intval($cut['roll_id'])]);
                    if ($dupStmt->fetch(PDO::FETCH_ASSOC)) {
                        continue;
                    }

                    logAndSyncMovement($db, [
                        'product_id' => intval($cut['product_id']),
                        'roll_id' => intval($cut['roll_id']),
                        'movement_type' => 'sale_meter',
                        'quantity_m' => floatval($cut['meters']),
                        'quantity_rolls' => 0,
                        'deal_id' => $dealId,
                        'comment' => 'Сделка оплачена в Б24'
                    ]);
                }
            }
        } else {
            $isCancelled = $semantic === 'f' || strpos($stage, 'LOSE') !== false || strpos($stage, 'CANCEL') !== false;
            if ($isCancelled) {
                cancelDealReservations($db, $dealId);
                return;
            }
            $db->prepare("UPDATE b24_sale_requests SET status = IF(status='completed','completed','in_progress'), updated_at = NOW() WHERE b24_deal_id = ?")
                ->execute([$dealId]);
        }
    } catch (Exception $e) {
        // Keep webhook resilient even when optional B24 queue tables are absent.
        return;
    }
}

/**
 * Извлекает массив строк товаров из ответа crm.deal.productrows.get (разные форматы портала).
 */
function bitrixUnwrapDealProductRows($resp) {
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

/** Ответ crm.item.productrow.list — универсальный API строк сделки. */
function bitrixUnwrapItemProductRowList($resp) {
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
 * Сырые строки CRM → список для queueDealForWarehouse.
 * Возвращает массив с ключами products, debug.
 */
function bitrixBuildWarehouseProductsFromCrmRows(array $rows) {
    $debug = '';
    $products = array();

    foreach ($rows as $item) {
        if (!is_array($item)) {
            continue;
        }

        $productId = 0;
        if (isset($item['PRODUCT_ID']) && $item['PRODUCT_ID'] !== '' && $item['PRODUCT_ID'] !== null) {
            $productId = intval($item['PRODUCT_ID']);
        } elseif (isset($item['productId']) && $item['productId'] !== '' && $item['productId'] !== null) {
            $productId = intval($item['productId']);
        }

        $price = 0.0;
        if (isset($item['PRICE']) && $item['PRICE'] !== '' && $item['PRICE'] !== null) {
            $price = floatval(str_replace(',', '.', (string)$item['PRICE']));
        } elseif (isset($item['price']) && $item['price'] !== '' && $item['price'] !== null) {
            $price = floatval(str_replace(',', '.', (string)$item['price']));
        }

        $quantity = 0.0;
        if (isset($item['QUANTITY']) && $item['QUANTITY'] !== '' && $item['QUANTITY'] !== null) {
            $quantity = floatval(str_replace(',', '.', (string)$item['QUANTITY']));
        } elseif (isset($item['quantity']) && $item['quantity'] !== '' && $item['quantity'] !== null) {
            $quantity = floatval(str_replace(',', '.', (string)$item['quantity']));
        }
        if ($quantity <= 0 && $price > 0) {
            $quantity = 1.0;
        }

        $name = '';
        if (isset($item['PRODUCT_NAME'])) {
            $name = (string)$item['PRODUCT_NAME'];
        } elseif (isset($item['productName'])) {
            $name = (string)$item['productName'];
        }

        if ($quantity <= 0) {
            continue;
        }

        if ($productId <= 0) {
            $debug = trim($debug . ' row_without_product_id');
            continue;
        }

        $products[] = array(
            'id' => $productId,
            'name' => $name,
            'quantity' => $quantity,
            'price' => $price
        );
    }

    if (empty($products) && $debug === '') {
        $debug = 'crm_rows_empty_after_filter';
    }

    return array('products' => $products, 'debug' => $debug);
}

function getDealProducts($db, $dealId) {
    require_once __DIR__ . '/bitrix/send.php';

    $GLOBALS['webhook_debug_productrows'] = '';

    $classic = sendToBitrix('crm.deal.productrows.get', array('id' => $dealId));
    $classicRows = array();

    if (!is_array($classic)) {
        $GLOBALS['webhook_debug_productrows'] = 'productrows_resp_not_array';
    } elseif (isset($classic['error'])) {
        $GLOBALS['webhook_debug_productrows'] = isset($classic['error_description'])
            ? (string)$classic['error_description']
            : (isset($classic['error']) ? (string)$classic['error'] : 'productrows_error');
    } else {
        $rows = bitrixUnwrapDealProductRows($classic);
        if ($rows === null) {
            $GLOBALS['webhook_debug_productrows'] = 'productrows_bad_result_shape';
        } else {
            $classicRows = $rows;
        }
    }

    $products = array();
    if (!empty($classicRows)) {
        $built = bitrixBuildWarehouseProductsFromCrmRows($classicRows);
        $products = $built['products'];
        if (!empty($built['debug']) && empty($products)) {
            $GLOBALS['webhook_debug_productrows'] = trim($GLOBALS['webhook_debug_productrows'] . ' ' . $built['debug']);
        }
    }

    if (!empty($products)) {
        return $products;
    }

    $dbgClassic = isset($GLOBALS['webhook_debug_productrows']) ? trim((string)$GLOBALS['webhook_debug_productrows']) : '';

    $itemResp = sendToBitrix('crm.item.productrow.list', array(
        'filter' => array(
            '=ownerType' => 'D',
            '=ownerId' => $dealId,
        ),
    ));

    $GLOBALS['webhook_debug_productrows'] = '';

    if (!is_array($itemResp)) {
        $GLOBALS['webhook_debug_productrows'] = 'item_rows_resp_not_array';
    } elseif (isset($itemResp['error'])) {
        $GLOBALS['webhook_debug_productrows'] = isset($itemResp['error_description'])
            ? (string)$itemResp['error_description']
            : (isset($itemResp['error']) ? (string)$itemResp['error'] : 'item_rows_error');
    } else {
        $itemRows = bitrixUnwrapItemProductRowList($itemResp);
        if ($itemRows === null) {
            $GLOBALS['webhook_debug_productrows'] = 'item_rows_bad_result_shape';
        } elseif (empty($itemRows)) {
            $GLOBALS['webhook_debug_productrows'] = 'item_rows_empty_list';
        } else {
            $builtItem = bitrixBuildWarehouseProductsFromCrmRows($itemRows);
            if (!empty($builtItem['products'])) {
                return $builtItem['products'];
            }
            $GLOBALS['webhook_debug_productrows'] = trim($builtItem['debug'] . ' item_rows_unparsed');
        }
    }

    $dbgItem = isset($GLOBALS['webhook_debug_productrows']) ? trim((string)$GLOBALS['webhook_debug_productrows']) : '';
    if ($dbgClassic !== '' && $dbgItem !== '') {
        $GLOBALS['webhook_debug_productrows'] = $dbgClassic . ' | ' . $dbgItem;
    } elseif ($dbgClassic !== '') {
        $GLOBALS['webhook_debug_productrows'] = $dbgClassic;
    } elseif ($dbgItem !== '') {
        $GLOBALS['webhook_debug_productrows'] = $dbgItem;
    } else {
        $GLOBALS['webhook_debug_productrows'] = 'no_product_rows_both_methods';
    }

    return array();
}

function webhookFinishNoProducts($db, $dealId) {
    $suffix = '';
    if (!empty($GLOBALS['webhook_debug_productrows'])) {
        $s = preg_replace('/\s+/', ' ', (string)$GLOBALS['webhook_debug_productrows']);
        if (strlen($s) > 90) {
            $s = substr($s, 0, 90);
        }
        $suffix = ':' . $s;
    }
    $tag = 'no_products' . $suffix;
    if (strlen($tag) > 155) {
        $tag = substr($tag, 0, 155);
    }
    webhookLogFinish($db, $tag, $dealId, null);
}

function getDealDetails($dealId) {
    $resp = sendToBitrix('crm.deal.get', array('id' => $dealId));
    if (!is_array($resp) || isset($resp['error'])) {
        return [];
    }
    return isset($resp['result']) && is_array($resp['result']) ? $resp['result'] : array();
}
