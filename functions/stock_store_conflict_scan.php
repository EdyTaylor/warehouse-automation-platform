<?php
/**
 * Сканирование: свободные метры в приложении vs остаток на складе Б24 (catalog.storeproduct).
 * Результат — записи b24_sync_conflicts (stock_store_mismatch).
 */

require_once __DIR__ . '/b24_sync_conflicts.php';
require_once __DIR__ . '/stock_movements.php';
require_once __DIR__ . '/../api/bitrix/send.php';
require_once __DIR__ . '/app_settings.php';

/**
 * Прочитать amount на складе Б24 для товара.
 *
 * @param int $b24ProductId
 * @param int $storeId
 * @return float|null null если строки нет
 */
function stockStoreConflictReadB24StoreAmount($b24ProductId, $storeId) {
    $b24ProductId = intval($b24ProductId);
    $storeId = intval($storeId);
    if ($b24ProductId <= 0 || $storeId <= 0) {
        return null;
    }
    $listResp = sendToBitrix('catalog.storeproduct.list', array(
        'filter' => array(
            'productId' => $b24ProductId,
            'storeId' => $storeId
        ),
        'select' => array('id', 'amount', 'productId', 'storeId')
    ));
    $rows = b24ExtractListRowsLocal($listResp);
    if (empty($rows[0]) || !is_array($rows[0])) {
        return null;
    }
    $am = isset($rows[0]['amount']) ? $rows[0]['amount'] : (isset($rows[0]['AMOUNT']) ? $rows[0]['AMOUNT'] : null);
    if ($am === null) {
        return null;
    }
    return round(floatval($am), 2);
}

/**
 * Одна порция товаров: сравнение и upsert конфликтов.
 *
 * @param PDO $db
 * @param int $offset
 * @param int $limit
 * @param int $storeId
 * @return array processed, mismatch_upserted, match_count, next_offset, total, done
 */
function stockStoreConflictScanChunk($db, $offset, $limit, $storeId) {
    ensureB24SyncConflictsSchema($db);
    $storeId = intval($storeId);
    if ($storeId <= 0) {
        return array(
            'processed' => 0,
            'mismatch_upserted' => 0,
            'match_count' => 0,
            'next_offset' => 0,
            'total' => 0,
            'done' => true,
            'error' => 'store_id'
        );
    }
    $totalRow = $db->query("
        SELECT COUNT(*) as cnt
        FROM products
        WHERE b24_product_id IS NOT NULL AND b24_product_id <> 0
    ")->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow ? intval($totalRow['cnt']) : 0;

    $offset = max(0, intval($offset));
    $limit = max(1, min(80, intval($limit)));

    $rows = $db->query("
        SELECT
            p.id,
            p.name,
            p.b24_product_id,
            COALESCE(SUM(CASE WHEN r.reserved = 0 AND r.current_length > 0 AND r.status NOT IN ('sold','waste','written_off') THEN r.current_length ELSE 0 END), 0) as free_meters
        FROM products p
        LEFT JOIN rolls r ON r.product_id = p.id
        WHERE p.b24_product_id IS NOT NULL AND p.b24_product_id <> 0
        GROUP BY p.id, p.name, p.b24_product_id
        ORDER BY p.id ASC
        LIMIT " . intval($limit) . " OFFSET " . intval($offset) . "
    ")->fetchAll(PDO::FETCH_ASSOC);

    $processed = 0;
    $mismatchUpserted = 0;
    $matchCount = 0;

    foreach ($rows as $row) {
        $processed++;
        $localId = intval(isset($row['id']) ? $row['id'] : 0);
        $b24Id = intval(isset($row['b24_product_id']) ? $row['b24_product_id'] : 0);
        $localStock = round(floatval(isset($row['free_meters']) ? $row['free_meters'] : 0), 2);
        $b24Store = stockStoreConflictReadB24StoreAmount($b24Id, $storeId);
        if ($b24Store === null) {
            if ($localStock > 0.01) {
                $mismatchUpserted++;
                $nm = isset($row['name']) ? trim((string)$row['name']) : '';
                b24UpsertSyncConflict(
                    $db,
                    'stock_store_mismatch',
                    $b24Id,
                    $localId,
                    $localStock,
                    0,
                    'Склад приложения: ' . $localStock . ' м; на складе Б24 (store #' . $storeId . ') строки остатка нет (считаем 0). ' . $nm
                );
            } else {
                $matchCount++;
            }
            continue;
        }
        if (abs($localStock - $b24Store) > 0.01) {
            $mismatchUpserted++;
            $nm = isset($row['name']) ? trim((string)$row['name']) : '';
            b24UpsertSyncConflict(
                $db,
                'stock_store_mismatch',
                $b24Id,
                $localId,
                $localStock,
                $b24Store,
                'Сравнение склада: приложение ' . $localStock . ' м vs склад Б24 #' . $storeId . ' ' . $b24Store . ' м. ' . $nm
            );
        } else {
            $matchCount++;
        }
    }

    $nextOffset = $offset + $processed;
    $done = ($nextOffset >= $total) || $processed === 0;

    return array(
        'processed' => $processed,
        'mismatch_upserted' => $mismatchUpserted,
        'match_count' => $matchCount,
        'next_offset' => $nextOffset,
        'total' => $total,
        'done' => $done
    );
}

/**
 * Несколько порок подряд (ограничение по времени для веб-запроса).
 *
 * @param PDO $db
 * @param int $storeId
 * @param float $maxSeconds
 * @param int $chunkSize
 * @return array summarized
 */
function stockStoreConflictScanProgressive($db, $storeId, $maxSeconds = 20.0, $chunkSize = 40, $initialOffset = 0) {
    $started = microtime(true);
    $offset = max(0, intval($initialOffset));
    $totalProcessed = 0;
    $totalMismatch = 0;
    $totalMatch = 0;
    $chunks = 0;
    $done = false;
    $totalCatalog = 0;
    /** @var array|null $chunk */
    $chunk = null;

    while ((microtime(true) - $started) < $maxSeconds) {
        $chunk = stockStoreConflictScanChunk($db, $offset, $chunkSize, $storeId);
        $chunks++;
        if ($chunks === 1) {
            $totalCatalog = intval(isset($chunk['total']) ? $chunk['total'] : 0);
        }
        $totalProcessed += intval(isset($chunk['processed']) ? $chunk['processed'] : 0);
        $totalMismatch += intval(isset($chunk['mismatch_upserted']) ? $chunk['mismatch_upserted'] : 0);
        $totalMatch += intval(isset($chunk['match_count']) ? $chunk['match_count'] : 0);
        $offset = intval(isset($chunk['next_offset']) ? $chunk['next_offset'] : 0);
        if (!empty($chunk['done']) || intval(isset($chunk['processed']) ? $chunk['processed'] : 0) === 0) {
            $done = true;
            break;
        }
    }

    return array(
        'done' => $done,
        'next_offset' => $offset,
        'total_products_with_b24' => $totalCatalog,
        'processed_this_run' => $totalProcessed,
        'mismatch_upserted' => $totalMismatch,
        'matches' => $totalMatch,
        'chunks' => $chunks,
        'elapsed_sec' => round(microtime(true) - $started, 2),
        'store_id' => intval($storeId)
    );
}
