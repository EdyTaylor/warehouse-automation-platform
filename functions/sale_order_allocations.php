<?php

/**
 * Резервы рулонов по заявке b24_sale_requests (order_allocations + rolls.*).
 * Подключается из warehouse_orders и из api/bitrix/deal.php без лишних header().
 */

function orderAllocationsTableExists($db) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute(array('order_allocations'));
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Exception $e) {
        return false;
    }
}

function ensureOrderAllocationsTable($db) {
    if (orderAllocationsTableExists($db)) {
        return;
    }
    if ($db->inTransaction()) {
        throw new Exception('order_allocations table is missing; create it before starting a transaction.');
    }
    $db->exec("
        CREATE TABLE IF NOT EXISTS order_allocations (
            id int NOT NULL AUTO_INCREMENT,
            sale_request_id int NOT NULL,
            deal_id int DEFAULT NULL,
            product_id int NOT NULL,
            roll_id int NOT NULL,
            allocated_m decimal(10,2) NOT NULL DEFAULT 0,
            allocated_rolls decimal(10,2) NOT NULL DEFAULT 0,
            price_per_unit decimal(10,2) NOT NULL DEFAULT 0,
            line_total decimal(12,2) NOT NULL DEFAULT 0,
            source enum('auto','manual') NOT NULL DEFAULT 'auto',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_order_alloc_req (sale_request_id),
            KEY idx_order_alloc_deal (deal_id),
            KEY idx_order_alloc_roll (roll_id),
            KEY idx_order_alloc_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function allocateRollToDeal($db, $dealId, $rollId, $meters) {
    $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id = ? FOR UPDATE");
    $rollStmt->execute(array($rollId));
    $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);
    if (!$roll) {
        throw new Exception('Рулон не найден при резервировании.');
    }

    $sameDeal = intval($roll['reserved']) === 1 && intval($roll['deal_id']) === intval($dealId);
    if (intval($roll['reserved']) === 1 && !$sameDeal) {
        throw new Exception('Рулон уже зарезервирован под другую сделку.');
    }

    $reservedLen = floatval($roll['reserved_length']);
    $available = floatval($roll['current_length']) - $reservedLen;
    if ($available + 0.0001 < $meters) {
        throw new Exception('Недостаточно доступного метража для резервирования.');
    }

    $newReservedLen = $reservedLen + $meters;
    $db->prepare("
        UPDATE rolls
        SET reserved = IF(? > 0, 1, reserved),
            deal_id = IF(? > 0, ?, deal_id),
            reserved_length = ?
        WHERE id = ?
    ")->execute(array($newReservedLen, $newReservedLen, $dealId, $newReservedLen, $rollId));
}

function releaseRollReservation($db, $dealId, $rollId, $meters) {
    $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id = ? FOR UPDATE");
    $rollStmt->execute(array($rollId));
    $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);
    if (!$roll) {
        return;
    }
    if (intval($roll['deal_id']) !== intval($dealId)) {
        return;
    }
    $newReserved = max(0, floatval($roll['reserved_length']) - floatval($meters));
    if ($newReserved <= 0) {
        $db->prepare("UPDATE rolls SET reserved=0, deal_id=NULL, reserved_length=0 WHERE id=?")
            ->execute(array($rollId));
    } else {
        $db->prepare("UPDATE rolls SET reserved_length=? WHERE id=?")->execute(array($newReserved, $rollId));
    }
}

function releaseRequestAllocations($db, $requestId, $source = null) {
    ensureOrderAllocationsTable($db);
    $sql = "SELECT * FROM order_allocations WHERE sale_request_id = ?";
    $params = array($requestId);
    if ($source !== null) {
        $sql .= " AND source = ?";
        $params[] = $source;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allocations as $alloc) {
        releaseRollReservation($db, intval($alloc['deal_id']), intval($alloc['roll_id']), floatval($alloc['allocated_m']));
    }

    $delSql = "DELETE FROM order_allocations WHERE sale_request_id = ?";
    $delParams = array($requestId);
    if ($source !== null) {
        $delSql .= " AND source = ?";
        $delParams[] = $source;
    }
    $db->prepare($delSql)->execute($delParams);
}
