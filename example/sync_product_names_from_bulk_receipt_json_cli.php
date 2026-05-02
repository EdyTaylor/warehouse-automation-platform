<?php
/**
 * Берёт product_name из JSON массового прихода LLumar и обновляет локальные products.name
 * по b24_product_id (все строки с этим b24_product_id получают одно имя).
 *
 * Если в JSON несколько строк с одним b24 но разными именами — сохраняется первое имя для этого b24, остальные выводятся предупреждением в stderr.
 *
 * Использование:
 *   php example/sync_product_names_from_bulk_receipt_json_cli.php dry-run
 *   php example/sync_product_names_from_bulk_receipt_json_cli.php exec
 *   php example/sync_product_names_from_bulk_receipt_json_cli.php exec --push-b24
 *   php example/sync_product_names_from_bulk_receipt_json_cli.php exec --input=example/new/мой.json
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

$inputRel = 'example/new/bulk_receipt_from_llumar.generated.json';
$mode = 'dry-run';
$pushB24 = false;

if (isset($argv) && is_array($argv)) {
    foreach (array_slice($argv, 1) as $a) {
        $a = trim((string)$a);
        if ($a === '') {
            continue;
        }
        if ($a === 'exec' || $a === 'dry-run') {
            $mode = $a;
            continue;
        }
        if ($a === '--push-b24') {
            $pushB24 = true;
            continue;
        }
        if (strpos($a, '--input=') === 0) {
            $inputRel = trim(substr($a, strlen('--input=')));
            continue;
        }
        fwrite(STDERR, "Неизвестный аргумент: {$a}\n");
        exit(1);
    }
}

$path = $root . '/' . str_replace('\\', '/', $inputRel);
if (!is_readable($path)) {
    fwrite(STDERR, 'Файл не найден: ' . $path . "\n");
    exit(2);
}

$raw = file_get_contents($path);
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['lines']) || !is_array($data['lines'])) {
    fwrite(STDERR, "Некорректный JSON: ожидался объект с ключом lines[].\n");
    exit(3);
}

$nameByB24 = array();
foreach ($data['lines'] as $line) {
    if (!is_array($line)) {
        continue;
    }
    $b24 = intval(isset($line['b24_product_id']) ? $line['b24_product_id'] : (isset($line['b24ProductId']) ? $line['b24ProductId'] : 0));
    $nm = isset($line['product_name']) ? trim((string)$line['product_name']) : (isset($line['productName']) ? trim((string)$line['productName']) : '');
    if ($b24 <= 0 || $nm === '') {
        continue;
    }
    if (stockReceiptIsPlaceholderB24ProductName($nm)) {
        continue;
    }
    if (!isset($nameByB24[$b24])) {
        $nameByB24[$b24] = $nm;
    } elseif ($nameByB24[$b24] !== $nm) {
        fwrite(STDERR, 'Разные имена для одного b24 #' . $b24 . ': остаётся «' . $nameByB24[$b24] . '», игнорируется «' . $nm . "»\n");
    }
}

if (empty($nameByB24)) {
    fwrite(STDERR, "Нет пар b24_product_id + product_name в JSON.\nПерегенерируйте файл: node example/new/build_products_prices_usd_x88.js --bulk-receipt\n");
    exit(4);
}

$db = getDB();

$upd = $db->prepare('UPDATE products SET name = ? WHERE b24_product_id = ?');

$updLocal = 0;
$b24Updates = 0;
$errs = 0;

foreach ($nameByB24 as $b24 => $nm) {
    $b24 = intval($b24);

    $cntSt = $db->prepare('SELECT COUNT(*) FROM products WHERE b24_product_id = ?');
    $cntSt->execute(array($b24));
    $cnt = intval($cntSt->fetchColumn());

    if ($cnt <= 0) {
        fwrite(STDERR, 'Нет локального товара с b24_product_id=' . $b24 . ', пропуск.' . "\n");
        continue;
    }

    if ($mode === 'dry-run') {
        echo 'b24=' . $b24 . ' строк в products: ' . $cnt . ' -> name="' . $nm . '"' . "\n";
        continue;
    }

    $upd->execute(array($nm, $b24));
    $updLocal += intval($upd->rowCount());

    if ($pushB24) {
        $resp = sendToBitrix('crm.product.update', array(
            'id' => $b24,
            'fields' => array('NAME' => $nm),
        ));
        if (!is_array($resp) || isset($resp['error'])) {
            $errs++;
            $et = extractBitrixErrorText($resp);
            if ($et === '') {
                $et = is_array($resp) ? json_encode($resp, JSON_UNESCAPED_UNICODE) : 'нет ответа';
            }
            fwrite(STDERR, 'crm.product.update ошибка id=' . $b24 . ': ' . $et . "\n");
        } else {
            $b24Updates++;
        }
        usleep(150000);
    }
}

if ($mode === 'dry-run') {
    echo "\nDry-run OK. Чтобы применить: php example/sync_product_names_from_bulk_receipt_json_cli.php exec\n";
    echo "Обновить и Bitrix NAME: добавьте --push-b24 (пауза интеграции отключена).\n";
    exit(0);
}

echo 'Обновлено строк products (совокупно): ' . $updLocal . ".\n";
if ($pushB24) {
    echo 'Успешных crm.product.update: ' . $b24Updates . ', ошибок: ' . $errs . ".\n";
}

exit(($pushB24 && $errs > 0) ? 2 : 0);
