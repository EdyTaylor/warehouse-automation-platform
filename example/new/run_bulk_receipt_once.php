<?php
/**
 * One-time bulk receipt runner (CLI).
 *
 * Input JSON is in USD. Script also writes a KGS snapshot file for review,
 * then creates receipt + sends document to B24 in KGS via shared core logic.
 *
 * Usage:
 *   php example/new/run_bulk_receipt_once.php
 *   php example/new/run_bulk_receipt_once.php --force
 *   php example/new/run_bulk_receipt_once.php --input="example/new/bulk_receipt_from_llumar.generated.json"
 *   php example/new/run_bulk_receipt_once.php --local-only   (или "local_only": true в JSON — нужно при паузе синхронизации)
 */

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "CLI only.\n";
    exit(1);
}

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/functions/stock_movements.php';
require_once dirname(__DIR__, 2) . '/api/bitrix/send.php';
require_once dirname(__DIR__, 2) . '/functions/app_settings.php';
require_once dirname(__DIR__, 2) . '/includes/stock_operations_core.php';

date_default_timezone_set('Asia/Bishkek');

function argValue($name, $defaultValue) {
    global $argv;
    $prefix = '--' . $name . '=';
    foreach ($argv as $a) {
        if (strpos($a, $prefix) === 0) {
            return substr($a, strlen($prefix));
        }
    }
    return $defaultValue;
}

function hasArg($name) {
    global $argv;
    return in_array('--' . $name, $argv, true);
}

function money2($n) {
    return round(floatval($n) * 100) / 100;
}

$inputRel = argValue('input', 'example/new/bulk_receipt_from_llumar.generated.json');
$force = hasArg('force');
$root = dirname(__DIR__, 2);
$inputPath = $root . '/' . str_replace('\\', '/', $inputRel);
$snapshotPath = $root . '/example/new/bulk_receipt_from_llumar.kgs.snapshot.json';
$resultPath = $root . '/example/new/bulk_receipt_from_llumar.run_result.json';

if (!file_exists($inputPath)) {
    echo "Input file not found: " . $inputPath . "\n";
    exit(2);
}

$raw = file_get_contents($inputPath);
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    echo "Invalid JSON input.\n";
    exit(3);
}

if (hasArg('local-only')) {
    $payload['local_only'] = true;
}

$db = getDB();
ensureStockOperationTables($db);
$usdRate = getUsdToKgsRate($db);
$docNumber = isset($payload['doc_number']) ? trim((string)$payload['doc_number']) : '';

if ($docNumber === '') {
    $docNumber = 'PR-BULK-' . date('Ymd-His');
    $payload['doc_number'] = $docNumber;
}

if (!$force) {
    $stDup = $db->prepare("SELECT id FROM stock_operation_docs WHERE operation_type='receipt' AND doc_number = ? LIMIT 1");
    $stDup->execute(array($docNumber));
    $dup = $stDup->fetch(PDO::FETCH_ASSOC);
    if ($dup) {
        echo "Receipt with doc_number already exists (doc_id=" . intval($dup['id']) . ").\n";
        echo "Use --force to run anyway.\n";
        exit(4);
    }
}

if (!isset($payload['receipt_currency'])) {
    $payload['receipt_currency'] = 'USD';
}
$inCurrency = strtoupper(trim((string)$payload['receipt_currency']));
if (!in_array($inCurrency, array('USD', 'KGS'), true)) {
    $inCurrency = 'USD';
    $payload['receipt_currency'] = 'USD';
}

$lines = isset($payload['lines']) && is_array($payload['lines']) ? $payload['lines'] : array();
if (empty($lines)) {
    echo "Input has no lines.\n";
    exit(5);
}

// Create KGS snapshot file for audit/review.
$snapshot = $payload;
$snapshot['receipt_currency'] = 'KGS';
$snapshot['generated_from_currency'] = $inCurrency;
$snapshot['usd_to_kgs_rate_used'] = $usdRate;
$snapshotLines = array();
foreach ($lines as $line) {
    if (!is_array($line)) {
        continue;
    }
    $pr = floatval(isset($line['purchase_per_roll']) ? $line['purchase_per_roll'] : 0);
    $dr = floatval(isset($line['delivery_per_roll']) ? $line['delivery_per_roll'] : 0);
    if ($inCurrency === 'USD') {
        $pr = money2($pr * $usdRate);
        $dr = money2($dr * $usdRate);
    } else {
        $pr = money2($pr);
        $dr = money2($dr);
    }
    $line['purchase_per_roll'] = $pr;
    $line['delivery_per_roll'] = $dr;
    $snapshotLines[] = $line;
}
$snapshot['lines'] = $snapshotLines;
file_put_contents(
    $snapshotPath,
    json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
);

echo "KGS snapshot written: " . $snapshotPath . "\n";
echo "USD->KGS rate used: " . number_format($usdRate, 4, '.', '') . "\n";
echo "Sending receipt with lines: " . count($lines) . "\n";

$result = stockOperationsProcessCreateReceiptPayload($db, $payload);

$out = array(
    'run_at' => date('c'),
    'input_file' => $inputPath,
    'kgs_snapshot_file' => $snapshotPath,
    'doc_number' => $docNumber,
    'usd_to_kgs_rate' => $usdRate,
    'result' => $result
);
file_put_contents(
    $resultPath,
    json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
);

echo "Run result written: " . $resultPath . "\n";
echo "ok=" . (!empty($result['ok']) ? 'true' : 'false') . "\n";
if (isset($result['doc_id'])) {
    echo "doc_id=" . intval($result['doc_id']) . "\n";
}
if (isset($result['b24_document_id'])) {
    echo "b24_document_id=" . intval($result['b24_document_id']) . "\n";
}
if (isset($result['sync_status'])) {
    echo "sync_status=" . (string)$result['sync_status'] . "\n";
}
if (!empty($result['success_message'])) {
    echo "success_message=" . (string)$result['success_message'] . "\n";
}
if (!empty($result['error_message'])) {
    echo "error_message=" . (string)$result['error_message'] . "\n";
}

exit(!empty($result['ok']) ? 0 : 10);
