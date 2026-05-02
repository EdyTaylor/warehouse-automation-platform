<?php
/**
 * Полный откат ЛОКАЛЬНОГО прихода: движения (receipt по roll_id), рулоны с receipt_doc_id,
 * строки склада, сам документ stock_operation_docs.
 *
 * НЕ отменяет документ в Битрикс24 — при необходимости удалите/проведите обратный документ в CRM вручную.
 *
 * Сначала узнайте id документа или номер:
 *   mysql> SELECT id, doc_number, supplier, total_amount, created_at FROM stock_operation_docs WHERE operation_type='receipt' ORDER BY id DESC LIMIT 15;
 *
 * Запуск из корня сайта:
 *   php example/revert_receipt_doc_cli.php dry-run --doc-id=742
 *   php example/revert_receipt_doc_cli.php exec --doc-id=742
 *   php example/revert_receipt_doc_cli.php exec --doc-number='PR-LLUMAR-BULK-2026-05-01'
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Только CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/db.php';

$mode = 'dry-run';
$docId = 0;
$docNumber = '';

if (isset($argv) && is_array($argv)) {
    foreach (array_slice($argv, 1) as $a) {
        $a = trim((string)$a);
        if ($a === 'exec' || $a === 'dry-run') {
            $mode = $a;
            continue;
        }
        if (strpos($a, '--doc-id=') === 0) {
            $docId = intval(substr($a, strlen('--doc-id=')));
            continue;
        }
        if (strpos($a, '--doc-number=') === 0) {
            $docNumber = trim(substr($a, strlen('--doc-number=')));
            continue;
        }
        if ($a !== '' && $a !== 'help' && $a !== '-h') {
            fwrite(STDERR, "Неизвестный аргумент: {$a}\n");
            exit(1);
        }
    }
}

$db = getDB();

if ($docId <= 0 && $docNumber !== '') {
    $st = $db->prepare("SELECT id FROM stock_operation_docs WHERE operation_type = 'receipt' AND doc_number = ? LIMIT 1");
    $st->execute(array($docNumber));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (is_array($row) && isset($row['id'])) {
        $docId = intval($row['id']);
    }
}

if ($docId <= 0) {
    fwrite(STDERR, "Укажите --doc-id=ЧИСЛО или --doc-number=\"...\" (приход).\n");
    exit(2);
}

$dSt = $db->prepare("SELECT id, operation_type, doc_number, supplier, created_at FROM stock_operation_docs WHERE id = ? LIMIT 1");
$dSt->execute(array($docId));
$doc = $dSt->fetch(PDO::FETCH_ASSOC);
if (!$doc) {
    fwrite(STDERR, "Документ #" . $docId . " не найден.\n");
    exit(3);
}
if ((string)$doc['operation_type'] !== 'receipt') {
    fwrite(STDERR, 'Документ #' . $docId . ' не приход (тип: ' . (string)$doc['operation_type'] . ").\n");
    exit(4);
}

$rolls = $db->query("SELECT id, product_id, current_length, status FROM rolls WHERE receipt_doc_id = " . intval($docId))->fetchAll(PDO::FETCH_ASSOC);
$nRolls = count($rolls);
$movCount = 0;
if ($nRolls > 0) {
    $ids = array();
    foreach ($rolls as $rr) {
        $ids[] = intval($rr['id']);
    }
    $inList = '(' . implode(',', $ids) . ')';
    $movCount = intval($db->query("SELECT COUNT(*) FROM stock_movements WHERE roll_id IN " . $inList . " AND movement_type = 'receipt'")->fetchColumn());
}
$lineCount = intval($db->query("SELECT COUNT(*) FROM stock_operation_lines WHERE doc_id = " . intval($docId))->fetchColumn());

echo "Документ #" . $docId . " приход\n";
echo "  doc_number: " . (isset($doc['doc_number']) ? $doc['doc_number'] : '') . "\n";
echo "  создан: " . (isset($doc['created_at']) ? $doc['created_at'] : '') . "\n";
echo "  рулонов (receipt_doc_id): " . $nRolls . "\n";
echo "  движений receipt по этим рулонам: " . $movCount . "\n";
echo "  строк документа: " . $lineCount . "\n";
echo "\nВ Битрикс24 документ прихода нужно обработать отдельно (отмена / обратный документ).\n\n";

if ($mode === 'dry-run') {
    echo "Dry-run. Для выполнения: php example/revert_receipt_doc_cli.php exec --doc-id=" . $docId . "\n";
    exit(0);
}

$db->beginTransaction();
try {
    if ($nRolls > 0) {
        $ids = array();
        foreach ($rolls as $rr) {
            $ids[] = intval($rr['id']);
        }
        $inList = '(' . implode(',', $ids) . ')';
        $db->exec("DELETE FROM stock_movements WHERE roll_id IN " . $inList . " AND movement_type = 'receipt'");
        $db->exec("DELETE FROM rolls WHERE receipt_doc_id = " . intval($docId));
    }
    $db->prepare("DELETE FROM stock_operation_lines WHERE doc_id = ?")->execute(array($docId));
    $db->prepare("DELETE FROM stock_operation_docs WHERE id = ?")->execute(array($docId));
    $db->commit();
    echo "Откат выполнен: удалены движения, рулоны, строки и документ #" . $docId . ".\n";
} catch (Exception $e) {
    try {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    } catch (Exception $e2) {
    }
    fwrite(STDERR, "Ошибка: " . $e->getMessage() . "\n");
    exit(5);
}

exit(0);
