<?php

/**
 * DDL для таблицы webhook_log (первый входящий запрос создаёт/расширяет столбцы).
 */
function webhookLogEnsureSchema(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS webhook_log (
            id int NOT NULL AUTO_INCREMENT,
            event varchar(100) NOT NULL,
            data text NOT NULL,
            processed tinyint DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_webhook_event (event),
            KEY idx_webhook_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $chk = static function (PDO $db, $column) {
        $s = $db->prepare('
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ');
        $s->execute(['webhook_log', $column]);
        return intval($s->fetchColumn()) > 0;
    };
    if (!$chk($db, 'handler_outcome')) {
        $db->exec('ALTER TABLE webhook_log ADD COLUMN handler_outcome varchar(160) DEFAULT NULL AFTER processed');
    }
    if (!$chk($db, 'entity_deal_id')) {
        $db->exec('ALTER TABLE webhook_log ADD COLUMN entity_deal_id int DEFAULT NULL AFTER handler_outcome');
    }
    if (!$chk($db, 'entity_product_id')) {
        $db->exec('ALTER TABLE webhook_log ADD COLUMN entity_product_id int DEFAULT NULL AFTER entity_deal_id');
    }
    try {
        $db->exec('ALTER TABLE webhook_log MODIFY data MEDIUMTEXT NOT NULL');
    } catch (Exception $e) {
        // уже расширено или недоступно на хостинге
    }
}

function webhookLogExtractDealPayload(array $data) {
    if (!isset($data['data']) || !is_array($data['data'])) {
        return [];
    }
    if (isset($data['data']['FIELDS']) && is_array($data['data']['FIELDS'])) {
        return $data['data']['FIELDS'];
    }
    return $data['data'];
}

/**
 * Извлекает ID сделки/товара из тела outbound без доп. запросов в Битрикс.
 */
function webhookLogExtractEntityIds($eventName, array $payload) {
    $dealId = null;
    $productId = null;
    $ev = (string)$eventName;
    if (strpos($ev, 'DEAL') !== false) {
        $fields = webhookLogExtractDealPayload($payload);
        $did = intval(isset($fields['ID']) ? $fields['ID'] : 0);
        if ($did > 0) {
            $dealId = $did;
        }
    }
    if (strpos($ev, 'PRODUCT') !== false) {
        $row = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : array();
        $pidSrc = isset($row['ID']) ? $row['ID'] : (isset($row['id']) ? $row['id'] : 0);
        $pid = intval($pidSrc);
        if ($pid > 0) {
            $productId = $pid;
        }
    }
    return array($dealId, $productId);
}

function webhookLogInsertIncoming(PDO $db, $eventName, array $data, $dealId = null, $productId = null) {
    $stmt = $db->prepare('
        INSERT INTO webhook_log (event, data, processed, created_at, entity_deal_id, entity_product_id)
        VALUES (?, ?, 0, NOW(), ?, ?)
    ');
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    $stmt->execute(array(
        $eventName,
        $payload === false ? '{}' : $payload,
        $dealId,
        $productId,
    ));
    return intval($db->lastInsertId());
}

function webhookLogFinish(PDO $db, $outcome, $dealId = null, $productId = null) {
    $lid = isset($GLOBALS['webhook_log_id']) ? intval($GLOBALS['webhook_log_id']) : 0;
    if ($lid <= 0 || $outcome === null || $outcome === '') {
        return;
    }
    $parts = array('handler_outcome = ?');
    $bind = array($outcome);
    if ($dealId !== null && $dealId > 0) {
        $parts[] = 'entity_deal_id = ?';
        $bind[] = intval($dealId);
    }
    if ($productId !== null && $productId > 0) {
        $parts[] = 'entity_product_id = ?';
        $bind[] = intval($productId);
    }
    $bind[] = $lid;
    $sql = 'UPDATE webhook_log SET ' . implode(', ', $parts) . ' WHERE id = ?';
    $db->prepare($sql)->execute($bind);
}
