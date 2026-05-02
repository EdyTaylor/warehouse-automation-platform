<?php
/**
 * Удаление локальных товаров, у которых b24_product_id больше заданного порога
 * (если в каталоге Битрикс24 максимальный id = N, строки с b24_product_id > N в приложении — ошибочные).
 *
 * Перед exec сделайте резервную копию БД.
 *
 * Запуск из корня сайта:
 *   php example/purge_products_above_b24_id.php dry-run
 *   php example/purge_products_above_b24_id.php 1447 dry-run
 *   php example/purge_products_above_b24_id.php 1447 exec
 *
 * По умолчанию порог = 1447 и режим dry-run, если не указано иное.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Только CLI.\n");
    exit(1);
}

$maxB24 = 1447;
$mode = 'dry-run';
$args = isset($argv) ? array_slice($argv, 1) : array();
foreach ($args as $a) {
    $a = trim((string)$a);
    if ($a === '') {
        continue;
    }
    if (ctype_digit($a)) {
        $maxB24 = intval($a);
        continue;
    }
    if ($a === 'exec' || $a === 'dry-run') {
        $mode = $a;
        continue;
    }
    fwrite(STDERR, "Неизвестный аргумент: {$a}\n");
    exit(1);
}

require_once dirname(__DIR__) . '/db.php';

$db = getDB();

function tableExistsPurge(PDO $db, $table) {
    $st = $db->prepare('SHOW TABLES LIKE ?');
    $st->execute(array($table));
    return (bool)$st->fetch(PDO::FETCH_NUM);
}

$stIds = $db->prepare(
    'SELECT id, name, b24_product_id FROM products WHERE b24_product_id IS NOT NULL AND b24_product_id > ? ORDER BY b24_product_id ASC, id ASC'
);
$stIds->execute(array($maxB24));
$targets = $stIds->fetchAll(PDO::FETCH_ASSOC);

if (!$targets || count($targets) === 0) {
    echo 'Нет записей с b24_product_id > ' . $maxB24 . ".\n";
    exit(0);
}

$ids = array();
foreach ($targets as $t) {
    $ids[] = intval($t['id']);
}
$inList = '(' . implode(',', array_map('intval', $ids)) . ')';

echo 'Порог: b24_product_id > ' . $maxB24 . "\n";
echo 'Режим: ' . $mode . "\n";
echo 'Найдено товаров: ' . count($ids) . "\n";
foreach ($targets as $t) {
    echo '  id=' . intval($t['id']) . ' b24=' . intval($t['b24_product_id'])
        . ' ' . substr((isset($t['name']) ? (string)$t['name'] : ''), 0, 72) . "\n";
}

$dry = ($mode !== 'exec');
if ($dry) {
    echo "\nDry-run — БД не меняется. Запуск с аргументом exec для удаления.\n";
    exit(0);
}

$db->beginTransaction();
try {
    $rollRows = $db->query('SELECT id FROM rolls WHERE product_id IN ' . $inList)->fetchAll(PDO::FETCH_ASSOC);
    $rollIds = array();
    foreach ($rollRows as $rr) {
        $rollIds[] = intval($rr['id']);
    }
    $inRolls = null;
    if (!empty($rollIds)) {
        $inRolls = '(' . implode(',', $rollIds) . ')';
    }

    if (tableExistsPurge($db, 'b24_sale_lines')) {
        $lineIds = $db->query(
            'SELECT id FROM b24_sale_lines WHERE product_id IN ' . $inList
        )->fetchAll(PDO::FETCH_ASSOC);
        $lids = array();
        foreach ($lineIds as $lr) {
            $lids[] = intval($lr['id']);
        }
        if (!empty($lids) && tableExistsPurge($db, 'b24_sale_line_cuts')) {
            $inLids = '(' . implode(',', $lids) . ')';
            $db->exec('DELETE FROM b24_sale_line_cuts WHERE line_id IN ' . $inLids);
        }
    }

    if ($inRolls !== null && tableExistsPurge($db, 'b24_sale_line_cuts')) {
        $db->exec('DELETE FROM b24_sale_line_cuts WHERE roll_id IN ' . $inRolls);
    }

    if (tableExistsPurge($db, 'order_allocations')) {
        $db->exec('DELETE FROM order_allocations WHERE product_id IN ' . $inList);
    }

    if (tableExistsPurge($db, 'stock_movements')) {
        $db->exec('DELETE FROM stock_movements WHERE product_id IN ' . $inList);
    }
    if (tableExistsPurge($db, 'stock_operation_lines')) {
        $db->exec('DELETE FROM stock_operation_lines WHERE product_id IN ' . $inList);
    }
    if (tableExistsPurge($db, 'sales')) {
        $db->exec('DELETE FROM sales WHERE product_id IN ' . $inList);
    }
    if (tableExistsPurge($db, 'product_price_history')) {
        $db->exec('DELETE FROM product_price_history WHERE product_id IN ' . $inList);
    }
    if (tableExistsPurge($db, 'b24_sale_lines')) {
        $db->exec('DELETE FROM b24_sale_lines WHERE product_id IN ' . $inList);
    }
    if (tableExistsPurge($db, 'b24_sync_conflicts')) {
        $db->exec('DELETE FROM b24_sync_conflicts WHERE local_product_id IN ' . $inList);
    }

    $db->exec('DELETE FROM rolls WHERE product_id IN ' . $inList);

    $db->exec('DELETE FROM products WHERE id IN ' . $inList);

    $db->commit();
    echo "\nУдалено товаров: " . count($ids) . ".\n";
} catch (Exception $e) {
    try {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    } catch (Exception $e2) {
    }
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . "\n");
    exit(1);
}
