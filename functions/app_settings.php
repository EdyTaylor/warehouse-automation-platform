<?php

function ensureAppSettingsTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            `key` varchar(100) NOT NULL,
            `value` varchar(255) DEFAULT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function getAppSetting($db, $key, $defaultValue = null) {
    ensureAppSettingsTable($db);
    $stmt = $db->prepare("SELECT `value` FROM app_settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $defaultValue;
    }
    return $row['value'];
}

function setAppSetting($db, $key, $value) {
    ensureAppSettingsTable($db);
    $stmt = $db->prepare("
        INSERT INTO app_settings (`key`, `value`)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
    ");
    $stmt->execute([$key, (string)$value]);
}
