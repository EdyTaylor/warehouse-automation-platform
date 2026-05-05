<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions/pricing.php';
require_once __DIR__ . '/../../functions/sale_order_allocations.php';

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

function rollbackRealizedDealToStock($db, $dealId, $requestId) {
    $productIdsToSync = array();

    $cutsStmt = $db->prepare("
        SELECT c.roll_id, c.meters, l.product_id
        FROM b24_sale_line_cuts c
        JOIN b24_sale_lines l ON l.id = c.line_id
        WHERE l.request_id = ?
        ORDER BY c.id DESC
    ");
    $cutsStmt->execute(array($requestId));
    $cuts = $cutsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cuts as $cut) {
        $rollId = intval(isset($cut['roll_id']) ? $cut['roll_id'] : 0);
        $meters = floatval(isset($cut['meters']) ? $cut['meters'] : 0);
        $productId = intval(isset($cut['product_id']) ? $cut['product_id'] : 0);
        if ($rollId <= 0 || $meters <= 0) {
            continue;
        }

        $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id = ? FOR UPDATE");
        $rollStmt->execute(array($rollId));
        $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);
        if (!$roll) {
            continue;
        }

        $currentLength = floatval(isset($roll['current_length']) ? $roll['current_length'] : 0);
        $originalLength = floatval(isset($roll['original_length']) ? $roll['original_length'] : 0);
        $restoredLength = $currentLength + $meters;
        if ($originalLength > 0 && $restoredLength > $originalLength) {
            $restoredLength = $originalLength;
        }
        if ($restoredLength < 0) {
            $restoredLength = 0;
        }

        $newStatus = ($originalLength > 0 && $restoredLength + 0.0001 >= $originalLength) ? 'active' : 'cut';

        $db->prepare("
            UPDATE rolls
            SET current_length = ?, status = ?, reserved = 0, deal_id = NULL, reserved_length = 0
            WHERE id = ?
        ")->execute(array($restoredLength, $newStatus, $rollId));

        if ($productId > 0) {
            $productIdsToSync[$productId] = $productId;
        }
    }

    // Убираем следы реализации из отчетов по продажам (продажа отменена в Б24).
    $db->prepare("DELETE FROM sales WHERE deal_id = ? AND type = 'meter'")
        ->execute(array($dealId));

    // Убираем складские движения реализации, чтобы не искажать журнал операций.
    $db->prepare("
        DELETE FROM stock_movements
        WHERE deal_id = ?
          AND movement_type = 'sale_meter'
          AND comment LIKE 'Deal realized in B24%'
    ")->execute(array($dealId));

    $db->prepare("
        UPDATE b24_sale_lines
        SET status = 'new'
        WHERE request_id = ?
    ")->execute(array($requestId));

    $db->prepare("
        DELETE c
        FROM b24_sale_line_cuts c
        INNER JOIN b24_sale_lines l ON l.id = c.line_id
        WHERE l.request_id = ?
    ")->execute(array($requestId));

    $db->prepare("
        UPDATE b24_sale_requests
        SET status = 'cancelled',
            picker_status = 'cancelled',
            shipped_at = NULL,
            cancelled_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ")->execute(array($requestId));

    $db->prepare("UPDATE deals SET status = 'cancelled' WHERE b24_deal_id = ?")
        ->execute(array($dealId));

    if (function_exists('syncProductAvailableToBitrix')) {
        foreach ($productIdsToSync as $pid) {
            syncProductAvailableToBitrix($db, intval($pid));
        }
    }
}

function cancelDealReservations($db, $dealId) {
    ensureOrderAllocationsTable($db);
    $reqStmt = $db->prepare("SELECT * FROM b24_sale_requests WHERE b24_deal_id = ? LIMIT 1");
    $reqStmt->execute([$dealId]);
    $request = $reqStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        return;
    }
    $requestId = intval($request['id']);

    $wasRealized = ((string)(isset($request['status']) ? $request['status'] : '') === 'completed')
        || ((string)(isset($request['picker_status']) ? $request['picker_status'] : '') === 'shipped');

    $db->beginTransaction();
    try {
        if ($wasRealized) {
            rollbackRealizedDealToStock($db, $dealId, $requestId);
        } else {
            releaseRequestAllocations($db, $requestId, null);
            $db->prepare("
                UPDATE rolls
                SET reserved = 0, deal_id = NULL, reserved_length = 0
                WHERE deal_id = ?
            ")->execute([$dealId]);
            $db->prepare("DELETE FROM b24_sale_line_cuts WHERE line_id IN (SELECT id FROM b24_sale_lines WHERE request_id = ?)")
                ->execute([$requestId]);
            $db->prepare("
                UPDATE b24_sale_lines
                SET status = 'new'
                WHERE request_id = ? AND status != 'completed'
            ")->execute(array($requestId));
            $db->prepare("
                UPDATE b24_sale_requests
                SET status='cancelled',
                    picker_status='cancelled',
                    cancelled_at = NOW(),
                    updated_at=NOW()
                WHERE id=?
            ")->execute([$requestId]);
            $db->prepare("UPDATE deals SET status = 'cancelled' WHERE b24_deal_id = ?")
                ->execute(array($dealId));
        }

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function queueDealForWarehouse($db, $data) {
    $deal_id = intval(isset($data['deal_id']) ? $data['deal_id'] : 0);
    $deal_name = isset($data['deal_name']) ? $data['deal_name'] : '';
    $responsible = isset($data['responsible']) ? $data['responsible'] : '';
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
            $b24_id = intval(isset($p['id']) ? $p['id'] : 0);
            $name = isset($p['name']) ? $p['name'] : 'Без названия';
            $qty = floatval(isset($p['quantity']) ? $p['quantity'] : 0);
            $price = floatval(isset($p['price']) ? $p['price'] : 0);

            if ($qty <= 0) {
                continue;
            }

            $product = null;
            if ($b24_id > 0) {
                $stmt = $db->prepare("
                    SELECT id, b24_product_id, roll_length, price_per_meter, price_1_4, price_5_9, price_10_19, price_20_plus
                    FROM products
                    WHERE b24_product_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$b24_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$product && trim((string)$name) !== '') {
                // Фолбэк: если у локального товара тот же name и b24_product_id пустой — привязываем, а не создаём дубль.
                $nameStmt = $db->prepare("
                    SELECT id, b24_product_id, roll_length, price_per_meter, price_1_4, price_5_9, price_10_19, price_20_plus
                    FROM products
                    WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
                    ORDER BY CASE WHEN COALESCE(b24_product_id, 0) = 0 THEN 0 ELSE 1 END, id ASC
                    LIMIT 5
                ");
                $nameStmt->execute([$name]);
                $nameMatches = $nameStmt->fetchAll(PDO::FETCH_ASSOC);

                $singleNoB24 = null;
                $countNoB24 = 0;
                foreach ($nameMatches as $nm) {
                    if (intval(isset($nm['b24_product_id']) ? $nm['b24_product_id'] : 0) <= 0) {
                        $singleNoB24 = $nm;
                        $countNoB24++;
                    }
                }

                if ($countNoB24 === 1 && $singleNoB24) {
                    $product = $singleNoB24;
                    if ($b24_id > 0) {
                        $db->prepare("UPDATE products SET b24_product_id = ? WHERE id = ? AND (b24_product_id IS NULL OR b24_product_id = 0)")
                            ->execute([$b24_id, intval($singleNoB24['id'])]);
                        $product['b24_product_id'] = $b24_id;
                    }
                } elseif (count($nameMatches) === 1) {
                    // Единственный матч по имени (даже если b24_product_id уже проставлен).
                    $product = $nameMatches[0];
                }
            }

            if (!$product) {
                $db->prepare("
                    INSERT INTO products (name, roll_length, price_per_meter, b24_product_id)
                    VALUES (?, 30, 0, ?)
                ")->execute([$name, $b24_id]);
                $productId = intval($db->lastInsertId());
                $product = array(
                    'id' => $productId,
                    'price_1_4' => 0,
                    'price_5_9' => 0,
                    'price_10_19' => 0,
                    'price_20_plus' => 0,
                    'price_per_meter' => 0,
                    'roll_length' => 30
                );
            } else {
                $productId = intval($product['id']);
            }

            // If B24 line price is empty, resolve local tier price by roll quantity.
            if ($price <= 0) {
                $resolvedPrice = resolveTierPrice($product, intval(ceil($qty)));
                $price = floatval($resolvedPrice['price']);
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
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ["error" => $e->getMessage()];
    }
}

$__deal_script = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
if (realpath($__deal_script) === __FILE__) {
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
        $requestId = intval(isset($data['sale_request_id']) ? $data['sale_request_id'] : 0);
        $productId = intval(isset($data['product_id']) ? $data['product_id'] : 0);
        $fromRollId = intval(isset($data['from_roll_id']) ? $data['from_roll_id'] : 0);
        $toRollId = intval(isset($data['to_roll_id']) ? $data['to_roll_id'] : 0);
        $meters = floatval(isset($data['meters']) ? $data['meters'] : 0);
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