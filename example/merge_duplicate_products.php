<?php
/**
 * Слияние дубликатов в таблице products (после массовых приходов и сбоев синка).
 *
 * Режим 1 (по умолчанию): несколько строк с одним и тем же b24_product_id > 0.
 * Оставляется одна карточка (с большинством связей: рулоны, строки складских документов, движения),
 * остальные id переназыначаются и удаляются.
 *
 * Режим 2 (осторожно): одинаковое TRIM(name) при b24_product_id IS NULL или 0 —
 * только если понимаете, что это действительно дубликаты без привязки к Б24.
 *
 * Запуск из корня проекта:
 *   php example/merge_duplicate_products.php dry-run
 *   php example/merge_duplicate_products.php exec
 *
 * По имени (только где нет привязки к Битриксу):
 *   php example/merge_duplicate_products.php dry-run-by-name-empty-b24
 *   php example/merge_duplicate_products.php exec-by-name-empty-b24
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Только CLI.\n");
    exit(1);
}

$cmd = isset($argv[1]) ? strtolower(trim((string)$argv[1])) : '';
if ($cmd === '') {
    fwrite(STDERR, "Укажите: dry-run | exec | dry-run-by-name-empty-b24 | exec-by-name-empty-b24\n");
    exit(1);
}

require_once dirname(__DIR__) . '/db.php';

$db = getDB();

function tableExistsCli(PDO $db, $table) {
    $st = $db->prepare('SHOW TABLES LIKE ?');
    $st->execute(array($table));
    return (bool)$st->fetch(PDO::FETCH_NUM);
}

function countRowsForProduct(PDO $db, $productId) {
    $pid = intval($productId);
    $r = array('rolls' => 0, 'stock_operation_lines' => 0, 'stock_movements' => 0, 'sales' => 0);

    $r['rolls'] = intval($db->query('SELECT COUNT(*) FROM rolls WHERE product_id=' . $pid)->fetchColumn());

    if (tableExistsCli($db, 'stock_operation_lines')) {
        $r['stock_operation_lines'] = intval($db->query('SELECT COUNT(*) FROM stock_operation_lines WHERE product_id=' . $pid)->fetchColumn());
    }
    if (tableExistsCli($db, 'stock_movements')) {
        $r['stock_movements'] = intval($db->query('SELECT COUNT(*) FROM stock_movements WHERE product_id=' . $pid)->fetchColumn());
    }
    if (tableExistsCli($db, 'sales')) {
        $r['sales'] = intval($db->query('SELECT COUNT(*) FROM sales WHERE product_id=' . $pid)->fetchColumn());
    }

    $r['_score'] = $r['rolls'] + $r['stock_operation_lines'] + $r['stock_movements'] + $r['sales'];
    return $r;
}

/** Оставить id с большим числом связей; при равенстве — меньший id. */
function pickCanonicalProductId(PDO $db, array $ids) {
    $bestId = null;
    $bestScore = -1;
    foreach ($ids as $id) {
        $id = intval($id);
        if ($id <= 0) {
            continue;
        }
        $c = countRowsForProduct($db, $id);
        $sc = intval($c['_score']);
        if ($bestId === null || $sc > $bestScore || ($sc === $bestScore && $id < $bestId)) {
            $bestId = $id;
            $bestScore = $sc;
        }
    }
    return $bestId !== null ? intval($bestId) : 0;
}

function fetchProduct(PDO $db, $id) {
    $st = $db->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $st->execute(array(intval($id)));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/**
 * Если в строке-хранителе пустые поля, подтягиваем с дубликата (простые decimal / text).
 *
 * @return bool true если делали UPDATE
 */
function enrichKeeperFromDuplicates(PDO $db, array $keeper, array $dupeRows) {
    $id = intval($keeper['id']);
    $upd = array();
    $nums = array('roll_length', 'purchase_price', 'delivery_price', 'price_per_meter',
        'price_1_4', 'price_5_9', 'price_10_19', 'price_20_plus', 'base_roll_price');
    foreach ($nums as $col) {
        if (!isset($keeper[$col])) {
            continue;
        }
        $cur = isset($keeper[$col]) ? $keeper[$col] : null;
        $have = ($cur !== null && (string)$cur !== '' && floatval($cur) > 0);
        if ($have) {
            continue;
        }
        foreach ($dupeRows as $dr) {
            if (!isset($dr[$col])) {
                continue;
            }
            $v = isset($dr[$col]) ? $dr[$col] : null;
            if ($v !== null && (string)$v !== '') {
                $upd[$col] = $v;
                break;
            }
        }
    }
    if (isset($keeper['catalog_id'])) {
        $ck = isset($keeper['catalog_id']) ? intval($keeper['catalog_id']) : 0;
        if ($ck <= 0) {
            foreach ($dupeRows as $dr) {
                $c = isset($dr['catalog_id']) ? intval($dr['catalog_id']) : 0;
                if ($c > 0) {
                    $upd['catalog_id'] = $c;
                    break;
                }
            }
        }
    }
    if (isset($keeper['description'])) {
        $d = isset($keeper['description']) ? trim((string)$keeper['description']) : '';
        if ($d === '') {
            foreach ($dupeRows as $dr) {
                $t = isset($dr['description']) ? trim((string)$dr['description']) : '';
                if ($t !== '') {
                    $upd['description'] = $t;
                    break;
                }
            }
        }
    }

    $nameKeeper = isset($keeper['name']) ? trim((string)$keeper['name']) : '';
    foreach ($dupeRows as $dr) {
        $nm = isset($dr['name']) ? trim((string)$dr['name']) : '';
        if ($nm !== '' && (strlen($nm) > strlen($nameKeeper))) {
            $upd['name'] = $nm;
            $nameKeeper = $nm;
        }
    }

    if (empty($upd)) {
        return false;
    }
    $sets = array();
    $parms = array();
    foreach ($upd as $k => $v) {
        $sets[] = '`' . str_replace('`', '', $k) . '` = ?';
        $parms[] = $v;
    }
    $parms[] = $id;
    $sql = 'UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1';
    $db->prepare($sql)->execute($parms);
    return true;
}

function mergeRemoveDupesInto(PDO $db, $keepId, array $removeIds, $dryRun, array &$logSink) {
    $keepId = intval($keepId);
    $removeIds = array_values(array_unique(array_map('intval', $removeIds)));
    $removeIds = array_values(array_filter($removeIds, function ($x) use ($keepId) {
        return $x > 0 && $x !== $keepId;
    }));

    if (empty($removeIds)) {
        return;
    }

    $inSql = '(' . implode(',', $removeIds) . ')';

    $logSink[] = '  KEEP #' . $keepId . '; REMOVE #' . implode(', #', $removeIds);

    if ($dryRun) {
        return;
    }

    $db->beginTransaction();
    try {
        $db->exec('UPDATE rolls SET product_id=' . $keepId . ' WHERE product_id IN ' . $inSql);

        if (tableExistsCli($db, 'stock_operation_lines')) {
            $db->exec('UPDATE stock_operation_lines SET product_id=' . $keepId . ' WHERE product_id IN ' . $inSql);
        }
        if (tableExistsCli($db, 'stock_movements')) {
            $db->exec('UPDATE stock_movements SET product_id=' . $keepId . ' WHERE product_id IN ' . $inSql);
        }
        if (tableExistsCli($db, 'sales')) {
            $db->exec('UPDATE sales SET product_id=' . $keepId . ' WHERE product_id IN ' . $inSql);
        }
        if (tableExistsCli($db, 'order_allocations')) {
            $db->exec('UPDATE order_allocations SET product_id=' . $keepId . ' WHERE product_id IN ' . $inSql);
        }
        if (tableExistsCli($db, 'product_price_history')) {
            $db->exec('UPDATE product_price_history SET product_id=' . $keepId . ' WHERE product_id IN ' . $inSql);
        }
        if (tableExistsCli($db, 'b24_sale_lines')) {
            $db->exec('UPDATE b24_sale_lines SET product_id=' . $keepId . ' WHERE product_id IN ' . $inSql);
        }
        if (tableExistsCli($db, 'b24_sync_conflicts')) {
            $db->exec('UPDATE b24_sync_conflicts SET local_product_id=' . $keepId . ' WHERE local_product_id IN ' . $inSql);
        }

        foreach ($removeIds as $rid) {
            $db->prepare('DELETE FROM products WHERE id = ?')->execute(array($rid));
        }
        $db->commit();
    } catch (Exception $e) {
        try {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        } catch (Exception $e2) {
        }
        throw $e;
    }
}

/** @var array $logSink */
function runMergeByB24(PDO $db, $dryRun, array &$logOut) {
    $sql = "
        SELECT b24_product_id AS bid, COUNT(*) AS cnt,
               GROUP_CONCAT(id ORDER BY id) AS ids
        FROM products
        WHERE b24_product_id IS NOT NULL AND b24_product_id > 0
        GROUP BY b24_product_id
        HAVING cnt > 1
        ORDER BY cnt DESC, bid ASC
    ";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows || count($rows) === 0) {
        $logOut[] = 'Групп с одинаковым b24_product_id > 1 не найдено.';
        return;
    }

    $logOut[] = 'Групп с дубликатами по Б24 ID: ' . count($rows);
    foreach ($rows as $r) {
        $bid = intval($r['bid']);
        $ids = array_filter(array_map('intval', explode(',', (string)$r['ids'])));

        $keep = pickCanonicalProductId($db, $ids);
        $keepers = fetchProduct($db, $keep);
        if (!$keepers) {
            $logOut[] = '! Пропуск b24#' . $bid . ': канонический id не найден';
            continue;
        }

        $dupRows = array();
        foreach ($ids as $iid) {
            if ($iid === $keep) {
                continue;
            }
            $row = fetchProduct($db, $iid);
            if ($row) {
                $dupRows[] = $row;
            }
        }

        enrichKeeperFromDuplicates($db, $keepers, $dupRows);
        mergeRemoveDupesInto($db, $keep, array_values(array_diff($ids, array($keep))), $dryRun, $logOut);
    }
}

function runMergeByNameEmptyB24(PDO $db, $dryRun, array &$logOut) {
    $sql = "
        SELECT TRIM(name) AS nname,
               COUNT(*) AS cnt,
               GROUP_CONCAT(id ORDER BY id) AS ids
        FROM products
        WHERE b24_product_id IS NULL OR b24_product_id = 0
        GROUP BY TRIM(name)
        HAVING cnt > 1
        ORDER BY cnt DESC
    ";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows || count($rows) === 0) {
        $logOut[] = 'Групп с одинаковым именем (без привязки Б24) не найдено.';
        return;
    }

    $logOut[] = 'Групп по имени (пустой b24): ' . count($rows);
    foreach ($rows as $r) {
        $ids = array_filter(array_map('intval', explode(',', (string)$r['ids'])));

        $keep = pickCanonicalProductId($db, $ids);
        $keepers = fetchProduct($db, $keep);
        if (!$keepers) {
            continue;
        }

        $dupRows = array();
        foreach ($ids as $iid) {
            if ($iid === $keep) {
                continue;
            }
            $row = fetchProduct($db, $iid);
            if ($row) {
                $dupRows[] = $row;
            }
        }

        enrichKeeperFromDuplicates($db, $keepers, $dupRows);
        $logOut[] = 'Имя: ' . (isset($r['nname']) ? $r['nname'] : '') . ' (ids: ' . (string)$r['ids'] . ')';
        mergeRemoveDupesInto($db, $keep, array_values(array_diff($ids, array($keep))), $dryRun, $logOut);
    }
}

$logOut = array();
$dryRun = true;

if ($cmd === 'dry-run') {
    $dryRun = true;
    $logOut[] = 'Режим: dry-run (по b24_product_id)';
    runMergeByB24($db, $dryRun, $logOut);
} elseif ($cmd === 'exec') {
    $dryRun = false;
    $logOut[] = 'Режим: EXEC (изменения в БД) по b24_product_id';
    runMergeByB24($db, $dryRun, $logOut);
} elseif ($cmd === 'dry-run-by-name-empty-b24') {
    $dryRun = true;
    $logOut[] = 'Режим: dry-run (одинаковое имя, без b24_product_id)';
    runMergeByNameEmptyB24($db, $dryRun, $logOut);
} elseif ($cmd === 'exec-by-name-empty-b24') {
    $dryRun = false;
    $logOut[] = 'Режим: EXEC (одинаковое имя, без b24_product_id)';
    runMergeByNameEmptyB24($db, $dryRun, $logOut);
} else {
    fwrite(STDERR, "Неизвестная команда.\n");
    exit(1);
}

foreach ($logOut as $line) {
    echo $line . "\n";
}
echo "\nГотово" . ($dryRun ? ' (ничего не меняли — был dry-run)' : '') . ".\n";
