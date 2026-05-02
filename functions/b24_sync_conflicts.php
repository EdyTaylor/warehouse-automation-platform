<?php
/**
 * Таблица b24_sync_conflicts: расхождения для sync_monitor / b24_sales.
 */

if (!function_exists('ensureB24SyncConflictsSchema')) {

    function ensureB24SyncConflictsSchema($db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS b24_sync_conflicts (
                id int NOT NULL AUTO_INCREMENT,
                conflict_type varchar(50) NOT NULL,
                b24_product_id int DEFAULT NULL,
                local_product_id int DEFAULT NULL,
                local_value decimal(14,2) DEFAULT NULL,
                b24_value decimal(14,2) DEFAULT NULL,
                details text,
                status varchar(20) NOT NULL DEFAULT 'new',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_conflict_status (status, created_at),
                KEY idx_conflict_product (b24_product_id, local_product_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * @param PDO $db
     * @param string $type
     * @param int $b24ProductId
     * @param int $localProductId
     * @param float|string|null $localValue
     * @param float|string|null $b24Value
     * @param string $details
     * @return int id строки
     */
    function b24UpsertSyncConflict($db, $type, $b24ProductId, $localProductId, $localValue, $b24Value, $details) {
        $sel = $db->prepare("
            SELECT id
            FROM b24_sync_conflicts
            WHERE status = 'new'
              AND conflict_type = ?
              AND b24_product_id = ?
              AND local_product_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $sel->execute(array((string)$type, intval($b24ProductId), intval($localProductId)));
        $existing = $sel->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $db->prepare("
                UPDATE b24_sync_conflicts
                SET local_value = ?, b24_value = ?, details = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute(array($localValue, $b24Value, (string)$details, intval($existing['id'])));
            return intval($existing['id']);
        }
        $db->prepare("
            INSERT INTO b24_sync_conflicts
            (conflict_type, b24_product_id, local_product_id, local_value, b24_value, details, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'new', NOW(), NOW())
        ")->execute(array(
            (string)$type,
            intval($b24ProductId),
            intval($localProductId),
            $localValue,
            $b24Value,
            (string)$details
        ));
        return intval($db->lastInsertId());
    }
}
