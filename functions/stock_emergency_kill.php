<?php

require_once __DIR__ . '/app_settings.php';

/**
 * Аварийная остановка создания рулонов — два способа:
 *
 * 1) Файл в корне сайта (рядом с index.php): STOCK_CREATES_OFF или STOCK_CREATES_OFF.txt
 * 2) В БД app_settings ключ emergency_block_roll_creates = 1 (кнопка в Центре интеграции или SQL в phpMyAdmin)
 *
 * Что блокируется: приход (JSON/UI/ядро), склад/add_stock/дашборд, конфликты продаж (addMetersToLocalStock).
 *
 * @param PDO|null $db если передан — учитывается флаг из app_settings (надёжно, если старый PHP не видит файл).
 * @return string сообщение блокировки или ''
 */
function stockEmergencyRollCreationDbKey()
{
    return 'emergency_block_roll_creates';
}

function stockEmergencyCreatesOffFilePaths()
{
    $root = dirname(dirname(__FILE__));
    return array(
        $root . DIRECTORY_SEPARATOR . 'STOCK_CREATES_OFF',
        $root . DIRECTORY_SEPARATOR . 'STOCK_CREATES_OFF.txt',
    );
}

function stockEmergencyRollCreationStoppedMessage($db = null)
{
    foreach (stockEmergencyCreatesOffFilePaths() as $path) {
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $text = (is_string($raw) ? trim($raw) : '');
            if ($text !== '') {
                return $text;
            }
            return 'Аварийно: добавление рулонов отключено (файл STOCK_CREATES_OFF в корне сайта). '
                . 'Чтобы возобновить — удалите этот файл на хостинге.';
        }
    }

    if ($db instanceof PDO) {
        try {
            ensureAppSettingsTable($db);
            if (trim((string)getAppSetting($db, stockEmergencyRollCreationDbKey(), '0')) === '1') {
                return 'Аварийно: создание рулонов отключено в базе (emergency_block_roll_creates). '
                    . 'Снимите в Центре интеграции (чекбокс) или выполните: UPDATE app_settings SET value=\'0\' WHERE `key`=\''
                    . stockEmergencyRollCreationDbKey() . '\';';
            }
        } catch (Exception $eDb) {
        }
    }

    return '';
}
