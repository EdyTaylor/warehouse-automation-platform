<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

function ensureColumnExists($db, $tableName, $columnName, $columnSql) {
    $stmt = $db->prepare("
        SELECT COUNT(*) AS cnt
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    $exists = intval($stmt->fetch(PDO::FETCH_ASSOC)['cnt']) > 0;
    if (!$exists) {
        $db->exec("ALTER TABLE `{$tableName}` ADD COLUMN {$columnSql}");
    }
}

function ensureOrderAllocationsTable($db) {
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
    $rollStmt->execute([$rollId]);
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
    ")->execute([$newReservedLen, $newReservedLen, $dealId, $newReservedLen, $rollId]);
}

function releaseRollReservation($db, $dealId, $rollId, $meters) {
    $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id = ? FOR UPDATE");
    $rollStmt->execute([$rollId]);
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
            ->execute([$rollId]);
    } else {
        $db->prepare("UPDATE rolls SET reserved_length=? WHERE id=?")->execute([$newReserved, $rollId]);
    }
}

function releaseRequestAllocations($db, $requestId, $source = null) {
    ensureOrderAllocationsTable($db);
    $sql = "SELECT * FROM order_allocations WHERE sale_request_id = ?";
    $params = [$requestId];
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
    $delParams = [$requestId];
    if ($source !== null) {
        $delSql .= " AND source = ?";
        $delParams[] = $source;
    }
    $db->prepare($delSql)->execute($delParams);
}

function pickCandidateRoll($rolls, $required) {
    $best = null;
    foreach ($rolls as $roll) {
        $available = floatval($roll['available_m']);
        if ($available <= 0) {
            continue;
        }
        $take = min($available, $required);
        if ($take <= 0) {
            continue;
        }
        $waste = max(0, $available - $take);
        if ($best === null || $waste < $best['waste'] || ($waste == $best['waste'] && $available < $best['available'])) {
            $best = [
                'roll' => $roll,
                'take' => $take,
                'waste' => $waste,
                'available' => $available
            ];
        }
    }
    return $best;
}

function autoAllocateRequestLines($db, $requestId, $dealId) {
    ensureOrderAllocationsTable($db);
    releaseRequestAllocations($db, $requestId, 'auto');

    $linesStmt = $db->prepare("
        SELECT id, product_id, quantity_m, price_per_unit
        FROM b24_sale_lines
        WHERE request_id = ? AND status != 'completed'
        ORDER BY id ASC
    ");
    $linesStmt->execute([$requestId]);
    $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lines as $line) {
        $productId = intval($line['product_id']);
        $need = floatval($line['quantity_m']);
        if ($productId <= 0 || $need <= 0) {
            continue;
        }

        $remaining = $need;
        while ($remaining > 0.0001) {
            $rollStmt = $db->prepare("
                SELECT r.*,
                       (r.current_length - r.reserved_length) AS available_m
                FROM rolls r
                WHERE r.product_id = ?
                  AND r.status IN ('cut', 'active')
                  AND r.status NOT IN ('sold', 'written_off', 'waste')
                  AND r.current_length > 0
                  AND (
                      r.reserved = 0
                      OR (r.reserved = 1 AND r.deal_id = ?)
                  )
                ORDER BY FIELD(r.status, 'cut', 'active'), r.current_length ASC, r.id ASC
            ");
            $rollStmt->execute([$productId, $dealId]);
            $rolls = $rollStmt->fetchAll(PDO::FETCH_ASSOC);

            $selected = pickCandidateRoll($rolls, $remaining);
            if ($selected === null) {
                throw new Exception('Недостаточно доступного материала для автоподбора.');
            }

            $roll = $selected['roll'];
            $take = floatval($selected['take']);
            if ($take <= 0) {
                throw new Exception('Автоподбор не смог выбрать метраж.');
            }

            allocateRollToDeal($db, $dealId, intval($roll['id']), $take);

            $price = floatval($line['price_per_unit']);
            $db->prepare("
                INSERT INTO order_allocations
                (sale_request_id, deal_id, product_id, roll_id, allocated_m, allocated_rolls, price_per_unit, line_total, source, created_at)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, 'auto', NOW())
            ")->execute([$requestId, $dealId, $productId, intval($roll['id']), $take, $price, $take * $price]);

            $db->prepare("
                INSERT INTO b24_sale_line_cuts (line_id, roll_id, meters, created_at)
                VALUES (?, ?, ?, NOW())
            ")->execute([intval($line['id']), intval($roll['id']), $take]);

            $remaining -= $take;
        }

        $db->prepare("UPDATE b24_sale_lines SET status='in_progress' WHERE id=? AND status='new'")
            ->execute([intval($line['id'])]);
    }

    $db->prepare("UPDATE b24_sale_requests SET status='in_progress' WHERE id=? AND status='new'")
        ->execute([$requestId]);
}

function replaceAllocationRoll($db, $requestId, $productId, $fromRollId, $toRollId, $meters) {
    ensureOrderAllocationsTable($db);
    $requestStmt = $db->prepare("SELECT b24_deal_id FROM b24_sale_requests WHERE id = ? LIMIT 1");
    $requestStmt->execute([$requestId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        throw new Exception('Заявка не найдена.');
    }
    $dealId = intval($request['b24_deal_id']);

    $allocStmt = $db->prepare("
        SELECT *
        FROM order_allocations
        WHERE sale_request_id = ?
          AND product_id = ?
          AND roll_id = ?
        ORDER BY id ASC
    ");
    $allocStmt->execute([$requestId, $productId, $fromRollId]);
    $allocs = $allocStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($allocs)) {
        throw new Exception('Нет аллокаций на исходном рулоне.');
    }

    $remainingMove = $meters;
    foreach ($allocs as $alloc) {
        if ($remainingMove <= 0.0001) {
            break;
        }
        $chunk = min(floatval($alloc['allocated_m']), $remainingMove);
        if ($chunk <= 0) {
            continue;
        }

        allocateRollToDeal($db, $dealId, $toRollId, $chunk);
        releaseRollReservation($db, $dealId, $fromRollId, $chunk);

        $newFromMeters = floatval($alloc['allocated_m']) - $chunk;
        if ($newFromMeters <= 0.0001) {
            $db->prepare("DELETE FROM order_allocations WHERE id = ?")->execute([intval($alloc['id'])]);
        } else {
            $db->prepare("UPDATE order_allocations SET allocated_m = ?, line_total = ? WHERE id = ?")
                ->execute([$newFromMeters, $newFromMeters * floatval($alloc['price_per_unit']), intval($alloc['id'])]);
        }

        $db->prepare("
            INSERT INTO order_allocations
            (sale_request_id, deal_id, product_id, roll_id, allocated_m, allocated_rolls, price_per_unit, line_total, source, created_at)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, 'manual', NOW())
        ")->execute([$requestId, $dealId, $productId, $toRollId, $chunk, floatval($alloc['price_per_unit']), $chunk * floatval($alloc['price_per_unit'])]);

        $remainingMove -= $chunk;
    }

    if ($remainingMove > 0.0001) {
        throw new Exception('Недостаточно аллокаций на исходном рулоне для замены.');
    }
}

function cancelDealReservations($db, $dealId) {
    ensureOrderAllocationsTable($db);
    $reqStmt = $db->prepare("SELECT id FROM b24_sale_requests WHERE b24_deal_id = ?");
    $reqStmt->execute([$dealId]);
    $request = $reqStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        return;
    }
    $requestId = intval($request['id']);
    releaseRequestAllocations($db, $requestId, null);
    $db->prepare("
        UPDATE rolls
        SET reserved = 0, deal_id = NULL, reserved_length = 0
        WHERE deal_id = ?
    ")->execute([$dealId]);
    $db->prepare("DELETE FROM b24_sale_line_cuts WHERE line_id IN (SELECT id FROM b24_sale_lines WHERE request_id = ?)")
        ->execute([$requestId]);
    $db->prepare("UPDATE b24_sale_requests SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$requestId]);
}

function queueDealForWarehouse($db, $data) {
    $deal_id = intval($data['deal_id'] ?? 0);
    $deal_name = $data['deal_name'] ?? '';
    $responsible = $data['responsible'] ?? '';
    $products = isset($data['products']) && is_array($data['products']) ? $data['products'] : [];

    if (!$deal_id || empty($products)) {
        return ["error" => "Неверные данные"];
    }

    // 1) legacy table for compatibility
    $db->prepare("
        INSERT INTO deals (b24_deal_id, name, status, created_at)
        VALUES (?, ?, 'new', NOW())
        ON DUPLICATE KEY UPDATE name=VALUES(name), status='new'
    ")->execute([$deal_id, $deal_name]);

    // 2) manual queue: upsert request + replace lines
    $db->beginTransaction();
    try {
        $db->prepare("
            INSERT INTO b24_sale_requests (b24_deal_id, deal_name, responsible, status, created_at, updated_at)
            VALUES (?, ?, ?, 'new', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                deal_name = VALUES(deal_name),
                responsible = VALUES(responsible),
                status = IF(status='completed', status, 'new'),
                updated_at = NOW()
        ")->execute([$deal_id, $deal_name, $responsible]);

        $stmt = $db->prepare("SELECT id FROM b24_sale_requests WHERE b24_deal_id = ?");
        $stmt->execute([$deal_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        $requestId = intval($request['id']);

        // Do not overwrite fulfilled lines. Remove cuts for editable lines first.
        $lineIdsStmt = $db->prepare("SELECT id FROM b24_sale_lines WHERE request_id = ? AND status != 'completed'");
        $lineIdsStmt->execute([$requestId]);
        $lineIds = $lineIdsStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($lineIds)) {
            $placeholders = implode(',', array_fill(0, count($lineIds), '?'));
            $delCuts = $db->prepare("DELETE FROM b24_sale_line_cuts WHERE line_id IN ($placeholders)");
            $delCuts->execute($lineIds);
        }
        $db->prepare("DELETE FROM b24_sale_lines WHERE request_id = ? AND status != 'completed'")->execute([$requestId]);
        releaseRequestAllocations($db, $requestId, null);

        $ins = $db->prepare("
            INSERT INTO b24_sale_lines
            (request_id, b24_product_id, product_id, product_name, quantity_m, price_per_unit, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'new', NOW())
        ");

        foreach ($products as $p) {
            $b24_id = intval($p['id'] ?? 0);
            $name = $p['name'] ?? 'Без названия';
            $qty = floatval($p['quantity'] ?? 0);
            $price = floatval($p['price'] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $stmt = $db->prepare("SELECT id FROM products WHERE b24_product_id = ?");
            $stmt->execute([$b24_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $db->prepare("
                    INSERT INTO products (name, roll_length, price_per_meter, b24_product_id)
                    VALUES (?, 30, 0, ?)
                ")->execute([$name, $b24_id]);
                $productId = intval($db->lastInsertId());
            } else {
                $productId = intval($product['id']);
            }

            $ins->execute([$requestId, $b24_id, $productId, $name, $qty, $price]);
        }

        autoAllocateRequestLines($db, $requestId, $deal_id);

        $db->commit();
        return [
            "status" => "queued",
            "deal_id" => $deal_id,
            "request_id" => $requestId
        ];
    } catch (Exception $e) {
        $db->rollBack();
        return ["error" => $e->getMessage()];
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $db = getDB();
    ensureOrderAllocationsTable($db);
    ensureColumnExists($db, 'rolls', 'reserved_length', '`reserved_length` decimal(10,2) NOT NULL DEFAULT 0 AFTER `current_length`');
    $rawInput = json_decode(file_get_contents("php://input"), true);
    $data = (is_array($rawInput) && !empty($rawInput)) ? $rawInput : $_POST;

    if (!is_array($data) || empty($data)) {
        echo json_encode(["error" => "Нет данных"]);
        exit;
    }

    if (isset($data['action']) && $data['action'] === 'replace_roll_allocation') {
        $requestId = intval($data['sale_request_id'] ?? 0);
        $productId = intval($data['product_id'] ?? 0);
        $fromRollId = intval($data['from_roll_id'] ?? 0);
        $toRollId = intval($data['to_roll_id'] ?? 0);
        $meters = floatval($data['meters'] ?? 0);
        if ($requestId <= 0 || $productId <= 0 || $fromRollId <= 0 || $toRollId <= 0 || $meters <= 0) {
            echo json_encode(["error" => "Неверные параметры ручной замены"]);
            exit;
        }
        try {
            $db->beginTransaction();
            replaceAllocationRoll($db, $requestId, $productId, $fromRollId, $toRollId, $meters);
            $db->commit();
            echo json_encode(["status" => "ok", "action" => "replace_roll_allocation"]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            echo json_encode(["error" => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(queueDealForWarehouse($db, $data));
}