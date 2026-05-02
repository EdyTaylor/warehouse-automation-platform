<?php
/**
 * Однократно выравнивает NAME в Битрикс24 (crm.product) по локальному каталогу products.name.
 * Нужен после того, как старая логика прихода успела записать в Б24 заглушки «Товар Б24 #…».
 *
 * Сначала восстановите нормальные имена в MySQL (снимок, ручной правки, импорт), затем:
 *
 *   php example/push_local_product_names_to_b24_cli.php dry-run
 *   php example/push_local_product_names_to_b24_cli.php exec
 *
 * Строки с пустым именем или с заглушкой «Товар Б24 #» пропускаются (чтобы не слать мусор).
 * Между запросами пауза (см. --delay-ms=) — нагрузка на вебхук Б24.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Только CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/db.php';
require_once $root . '/functions/stock_movements.php';
require_once $root . '/api/bitrix/send.php';
require_once $root . '/includes/stock_operations_core.php';

$mode = 'dry-run';
$delayMs = 150;
foreach (isset($argv) ? array_slice($argv, 1) : array() as $a) {
    $a = trim((string)$a);
    if ($a === 'exec' || $a === 'dry-run') {
        $mode = $a;
        continue;
    }
    if (strpos($a, '--delay-ms=') === 0) {
        $delayMs = intval(substr($a, strlen('--delay-ms=')));
        if ($delayMs < 0) {
            $delayMs = 0;
        }
        continue;
    }
    if ($a !== '' && $a !== 'help' && $a !== '-h') {
        fwrite(STDERR, "Неизвестный аргумент: {$a}\n");
        exit(1);
    }
}

$db = getDB();
$rows = $db->query("
    SELECT id, name, b24_product_id
    FROM products
    WHERE b24_product_id IS NOT NULL AND b24_product_id > 0
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$would = 0;
$done = 0;
$skipped = 0;
$errors = 0;

foreach ($rows as $r) {
    $localId = intval(isset($r['id']) ? $r['id'] : 0);
    $b24 = intval(isset($r['b24_product_id']) ? $r['b24_product_id'] : 0);
    $name = isset($r['name']) ? trim((string)$r['name']) : '';

    if ($b24 <= 0 || $name === '') {
        $skipped++;
        continue;
    }
    if (stockReceiptIsPlaceholderB24ProductName($name)) {
        $skipped++;
        continue;
    }

    if ($mode === 'dry-run') {
        $preview = function_exists('mb_substr') ? mb_substr($name, 0, 72, 'UTF-8') : substr($name, 0, 72);
        echo 'B24 id=' . $b24 . ' <- local id=' . $localId . ' | ' . $preview . "\n";
        $would++;
        continue;
    }

    $resp = sendToBitrix('crm.product.update', array(
        'id' => $b24,
        'fields' => array('NAME' => $name),
    ));
    if (!is_array($resp) || isset($resp['error'])) {
        $errors++;
        $err = extractBitrixErrorText($resp);
        if ($err === '') {
            $err = is_array($resp) ? json_encode($resp, JSON_UNESCAPED_UNICODE) : 'нет ответа';
        }
        fwrite(STDERR, 'Ошибка B24 id=' . $b24 . ' local=' . $localId . ': ' . $err . "\n");
    } else {
        $done++;
    }
    if ($delayMs > 0) {
        usleep($delayMs * 1000);
    }
}

if ($mode === 'dry-run') {
    echo "\nК отправке (после exec): " . $would . ", пропущено: " . $skipped . ".\n";
} else {
    echo "\nОбновлено в Б24: " . $done . ", ошибок: " . $errors . ", пропущено: " . $skipped . ".\n";
}

exit($mode === 'exec' && $errors > 0 ? 2 : 0);
