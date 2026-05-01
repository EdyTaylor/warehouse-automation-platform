<?php
/**
 * Быстрая проверка БД и счётчика webhook_log (без секретов от Битрикс).
 * Откройте в браузере: /api/webhook_ping.php
 *
 * Опционально одна тестовая строка в журнал (ключ смените после проверки):
 *   /api/webhook_ping.php?write=1&k=CHANGE_ME_FRIENDCRM_DIAG
 */

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions/webhook_log_schema.php';

const WEBHOOK_DIAG_WRITE_KEY = 'CHANGE_ME_FRIENDCRM_DIAG';

$db = getDB();
webhookLogEnsureSchema($db);

$out = ['ok' => true, 'webhook_log_rows' => 0, 'last_events' => []];

try {
    $out['webhook_log_rows'] = (int)$db->query('SELECT COUNT(*) FROM webhook_log')->fetchColumn();
    $st = $db->query('
        SELECT id, event, COALESCE(handler_outcome, \'\') AS handler_outcome,
               entity_deal_id, entity_product_id, created_at
        FROM webhook_log ORDER BY id DESC LIMIT 5
    ');
    $out['last_events'] = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $out['ok'] = false;
    $out['db_error'] = $e->getMessage();
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$writeKey = isset($_GET['k']) ? (string)$_GET['k'] : '';
if (isset($_GET['write']) && (string)$_GET['write'] === '1') {
    if (WEBHOOK_DIAG_WRITE_KEY !== '' && hash_equals(WEBHOOK_DIAG_WRITE_KEY, $writeKey)) {
        $GLOBALS['webhook_log_id'] = webhookLogInsertIncoming(
            $db,
            'MANUAL_DIAG_PING',
            array('source' => 'webhook_ping.php', 'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', 'time' => gmdate('c')),
            null,
            null
        );
        webhookLogFinish($db, 'manual_ping_ok');
        $out['wrote_test_row_id'] = intval($GLOBALS['webhook_log_id']);
    } else {
        $out['write_skipped'] = 'pass ?write=1&k=... совпадающий с WEBHOOK_DIAG_WRITE_KEY в webhook_ping.php';
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
