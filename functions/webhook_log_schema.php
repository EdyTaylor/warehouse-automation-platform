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
