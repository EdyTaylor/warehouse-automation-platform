<?php
/**
 * Резервная копия поля products.name перед массовым приходом или правками.
 *
 * Из корня проекта:
 *   php example/product_names_snapshot_cli.php snapshot bulk-2026-05-02
 *   php example/product_names_snapshot_cli.php list
 *   php example/product_names_snapshot_cli.php restore-latest bulk-2026-05-02
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Только CLI.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/db.php';

$db = getDB();
$cmd = isset($argv[1]) ? trim((string)$argv[1]) : '';

function ensureSnapshotsTableCli(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS product_name_snapshots (
          id int NOT NULL AUTO_INCREMENT,
          product_id int NOT NULL,
          b24_product_id int DEFAULT NULL,
          name_was varchar(255) NOT NULL,
          snapshot_label varchar(96) DEFAULT NULL,
          created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_pns_pid (product_id),
          KEY idx_pns_created (created_at),
          KEY idx_pns_label (snapshot_label)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function printHelpCli() {
    echo "Использование:\n";
    echo "  php example/product_names_snapshot_cli.php snapshot МЕТКА\n";
    echo "      — сохранить текущие name всех строк products (метка произвольная, напр. before-llumar-2026).\n";
    echo "  php example/product_names_snapshot_cli.php list\n";
    echo "      — сколько строк в последних снимках по меткам.\n";
    echo "  php example/product_names_snapshot_cli.php restore-latest МЕТКА\n";
    echo "      — вернуть name из последнего по времени снимка с данной меткой (по каждому product_id).\n";
}

if ($cmd === '' || $cmd === 'help' || $cmd === '-h') {
    printHelpCli();
    exit($cmd === '' ? 1 : 0);
}

ensureSnapshotsTableCli($db);

if ($cmd === 'snapshot') {
    $label = isset($argv[2]) ? trim((string)$argv[2]) : '';
    if ($label === '') {
        fwrite(STDERR, "Укажите метку: php ... snapshot МЕТКА\n");
        exit(2);
    }
    $before = intval($db->query('SELECT COUNT(*) FROM product_name_snapshots')->fetchColumn());
    $ins = $db->prepare('
        INSERT INTO product_name_snapshots (product_id, b24_product_id, name_was, snapshot_label)
        SELECT id, b24_product_id, name, ? FROM products
    ');
    $ins->execute(array($label));
    $after = intval($db->query('SELECT COUNT(*) FROM product_name_snapshots')->fetchColumn());
    $delta = $after - $before;
    echo 'Снимок: метка "' . $label . '". Добавлено строк: ' . $delta . '. Всего снимков в таблице: ' . $after . ".\n";
    exit(0);
}

if ($cmd === 'list') {
    $rows = $db->query("
        SELECT snapshot_label, COUNT(*) AS cnt, MAX(created_at) AS last_at
        FROM product_name_snapshots
        GROUP BY snapshot_label
        ORDER BY last_at DESC
        LIMIT 30
    ")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "Таблица пуста. Сделайте snapshot МЕТКА.\n";
        exit(0);
    }
    foreach ($rows as $r) {
        echo isset($r['snapshot_label']) ? (string)$r['snapshot_label'] : '';
        echo "\t\t";
        echo isset($r['cnt']) ? (string)$r['cnt'] : '';
        echo "\t\t";
        echo isset($r['last_at']) ? (string)$r['last_at'] : '';
        echo "\n";
    }
    exit(0);
}

if ($cmd === 'restore-latest') {
    $label = isset($argv[2]) ? trim((string)$argv[2]) : '';
    if ($label === '') {
        fwrite(STDERR, "Укажите метку: php ... restore-latest МЕТКА\n");
        exit(2);
    }
    $distinct = $db->prepare('
        SELECT DISTINCT product_id FROM product_name_snapshots WHERE snapshot_label = ?
    ');
    $distinct->execute(array($label));
    $pids = $distinct->fetchAll(PDO::FETCH_COLUMN);
    if (empty($pids)) {
        echo "Нет строк с меткой \"" . $label . "\".\n";
        exit(3);
    }
    $upd = $db->prepare('UPDATE products SET name = ? WHERE id = ?');
    $get = $db->prepare('
        SELECT name_was FROM product_name_snapshots
        WHERE snapshot_label = ? AND product_id = ?
        ORDER BY id DESC
        LIMIT 1
    ');
    $fixed = 0;
    foreach ($pids as $pid) {
        $pid = intval($pid);
        if ($pid <= 0) {
            continue;
        }
        $get->execute(array($label, $pid));
        $row = $get->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            continue;
        }
        $nw = isset($row['name_was']) ? trim((string)$row['name_was']) : '';
        if ($nw === '') {
            continue;
        }
        $upd->execute(array($nw, $pid));
        $fixed++;
    }
    echo 'Восстановлено записей products: ' . $fixed . " (метка " . $label . ").\n";
    exit(0);
}

fwrite(STDERR, "Неизвестная команда. Введите help.\n");
exit(99);
