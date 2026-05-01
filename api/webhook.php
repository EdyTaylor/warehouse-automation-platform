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

// Получаем данные от Битрикс24 (сначала лог в БД — до тяжёлых require, чтобы видеть даже сбои и «пустые» запросы)
$input = file_get_contents('php://input');
$data = json_decode((string)$input, true);

webhookLogEnsureSchema($db);

if (!is_array($data)) {
    $raw = mb_substr((string)$input, 0, 60000);
    $GLOBALS['webhook_log_id'] = webhookLogInsertIncoming(
        $db,
        'INVALID_JSON_OR_BODY',
        ['hint' => 'json_decode_failed', 'body_chars' => strlen((string)$input), 'body_preview' => $raw],
        null,
        null
    );
    webhookLogFinish($db, 'json_decode_failed_or_empty_body');
    echo json_encode(['error' => 'No data received']);
    exit;
}

$event = $data['event'] ?? '';
$auth = $data['auth'] ?? [];
$eventHash = hash('sha256', $event . '|' . json_encode($data['data'] ?? [], JSON_UNESCAPED_UNICODE));

list($incomingDealId, $incomingProductId) = webhookLogExtractEntityIds($event, $data);
$GLOBALS['webhook_log_id'] = webhookLogInsertIncoming($db, $event === '' ? 'EMPTY_EVENT_FIELD' : $event, $data, $incomingDealId, $incomingProductId);

webhookLoadHandlers();

ensureWebhookLockTable($db);
$lockStmt = $db->prepare("INSERT IGNORE INTO webhook_event_lock (event_hash, event_name) VALUES (?, ?)");
$lockStmt->execute([$eventHash, $event]);
if ($lockStmt->rowCount() === 0) {
    webhookLogFinish($db, 'duplicate_delivery_skipped');
    echo json_encode(['status' => 'duplicate_event_ignored', 'event' => $event]);
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
    
    $dealId = intval($deal['ID'] ?? 0);
    $dealName = $deal['TITLE'] ?? '';
    $responsibleId = $deal['ASSIGNED_BY_ID'] ?? '';
    
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
            'category_id' => $dealCtx['CATEGORY_ID'] ?? null,
            'stage_id' => $dealCtx['STAGE_ID'] ?? null,
        ]);
        exit;
    }
    
    // Получаем информацию о ответственном
    $responsible = getUserName($db, $responsibleId);
    
    // Получаем товары в сделке
    $products = getDealProducts($db, $dealId);
    
    if (!empty($products)) {
        $dealData = [
            'deal_id' => $dealId,
            'deal_name' => $dealName,
            'responsible' => $responsible,
            'products' => $products
        ];
        $result = queueDealForWarehouse($db, $dealData);
        if (isset($result['error'])) {
            webhookLogFinish($db, 'queue_error', $dealId, null);
        } else {
            webhookLogFinish($db, 'deal_processed', $dealId, null);
        }
        echo json_encode(isset($result['error'])
            ? ['status' => 'error', 'deal_id' => $dealId, 'error' => $result['error']]
            : ['status' => 'deal_processed', 'deal_id' => $dealId, 'request_id' => $result['request_id'] ?? null]
        );
    } else {
        webhookLogFinish($db, 'no_products', $dealId, null);
        echo json_encode(['status' => 'no_products', 'deal_id' => $dealId]);
    }
}

function handleDealUpdate($db, $data) {
    $deal = extractDealPayload($data);
    $dealId = intval($deal['ID'] ?? 0);
    
    if ($dealId <= 0) {
        webhookLogFinish($db, 'deal_update_invalid_id');
        echo json_encode(['error' => 'Invalid deal ID']);
        exit;
    }
    
    // Получаем актуальные товары и пересобираем заявку для кладовщика
    $dealData = getDealDetails($dealId);
    $products = getDealProducts($db, $dealId);

    $cfg = require __DIR__ . '/bitrix/config.php';
    $gate = isset($cfg['warehouse_queue']) && is_array($cfg['warehouse_queue']) ? $cfg['warehouse_queue'] : [];
    $dealCtx = bitrixMergeDealWebhookAndCrm($deal, $dealData);
    
    if (!empty($products) && bitrixWarehouseQueueAllowed($dealCtx, $gate)) {
        $result = queueDealForWarehouse($db, [
            'deal_id' => $dealId,
            'deal_name' => $dealData['TITLE'] ?? ('Deal #' . $dealId),
            'responsible' => isset($dealData['ASSIGNED_BY_ID']) ? getUserName($db, $dealData['ASSIGNED_BY_ID']) : '',
            'products' => $products
        ]);
        if (isset($result['error'])) {
            webhookLogFinish($db, 'queue_error', $dealId, null);
        } else {
            webhookLogFinish($db, 'deal_updated', $dealId, null);
        }
        echo json_encode(isset($result['error'])
            ? ['status' => 'error', 'deal_id' => $dealId, 'error' => $result['error']]
            : ['status' => 'deal_updated', 'deal_id' => $dealId, 'request_id' => $result['request_id'] ?? null]
        );
    } elseif (!empty($products)) {
        webhookLogFinish($db, 'skipped_warehouse_gate', $dealId, null);
        echo json_encode([
            'status' => 'skipped_warehouse_gate',
            'deal_id' => $dealId,
            'category_id' => $dealCtx['CATEGORY_ID'] ?? null,
            'stage_id' => $dealCtx['STAGE_ID'] ?? null,
        ]);
    } else {
        webhookLogFinish($db, 'no_products', $dealId, null);
        echo json_encode(['status' => 'no_products', 'deal_id' => $dealId]);
    }

    applyDealPaidOrReserveMark($db, $dealId, $dealData);
}

function handleNewProduct($db, $data) {
    $product = $data['data'] ?? [];
    
    if (empty($product)) {
        webhookLogFinish($db, 'product_add_empty_payload');
        echo json_encode(['error' => 'No product data']);
        exit;
    }
    
    $productId = intval($product['ID'] ?? 0);
    $productName = $product['NAME'] ?? '';
    $productPrice = floatval($product['PRICE'] ?? 0);
    
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
    $product = $data['data'] ?? [];
    
    if (empty($product)) {
        webhookLogFinish($db, 'product_update_empty_payload');
        echo json_encode(['error' => 'No product data']);
        exit;
    }
    
    $productId = intval($product['ID'] ?? 0);
    $productName = $product['NAME'] ?? '';
    $productPrice = floatval($product['PRICE'] ?? 0);
    
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
        $stage = strtoupper(trim((string)($dealData['STAGE_ID'] ?? '')));
        $semantic = strtolower(trim((string)($dealData['SEMANTICS'] ?? '')));
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

function getDealProducts($db, $dealId) {
    require_once __DIR__ . '/bitrix/send.php';
    
    $cfg = require __DIR__ . '/bitrix/config.php';
    $method = $cfg['method_urls']['crm.deal.productrows.get'] ?? null;
    
    if (!$method) {
        return [];
    }
    
    $payload = ['id' => $dealId];
    $resp = sendToBitrix('crm.deal.productrows.get', $payload);
    
    if (!is_array($resp) || isset($resp['error'])) {
        return [];
    }
    
    $products = [];
    foreach (($resp['result'] ?? []) as $item) {
        $productId = intval($item['PRODUCT_ID'] ?? 0);
        $quantity = floatval($item['QUANTITY'] ?? 0);
        $price = floatval($item['PRICE'] ?? 0);
        $name = $item['PRODUCT_NAME'] ?? '';
        
        if ($productId > 0 && $quantity > 0) {
            $products[] = [
                'id' => $productId,
                'name' => $name,
                'quantity' => $quantity,
                'price' => $price
            ];
        }
    }
    
    return $products;
}

function getDealDetails($dealId) {
    $resp = sendToBitrix('crm.deal.get', ['id' => $dealId]);
    if (!is_array($resp) || isset($resp['error'])) {
        return [];
    }
    return isset($resp['result']) && is_array($resp['result']) ? $resp['result'] : [];
}
