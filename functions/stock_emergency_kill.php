<?php

/**
 * Аварийная остановка создания рулонов без захода в БД админкой.
 *
 * FTP/SSH в корень сайта (рядом с index.php и sync_monitor.php):
 *   создайте ПУСТОЙ файл  STOCK_CREATES_OFF   или  STOCK_CREATES_OFF.txt
 *   необязательно: первая строка — текст для пользователей/API.
 *
 * Что блокируется: приход (JSON/UI/ядро), добавление рулонов склад/дашборд/add_stock,
 * принятие остатка из конфликтов через addMetersToLocalStock (см. точки подключения).
 *
 * После этого удалите файл, чтобы включить обратно.
 */

function stockEmergencyCreatesOffFilePaths()
{
    $root = dirname(dirname(__FILE__));
    return array(
        $root . DIRECTORY_SEPARATOR . 'STOCK_CREATES_OFF',
        $root . DIRECTORY_SEPARATOR . 'STOCK_CREATES_OFF.txt',
    );
}

/**
 * @return string сообщение блокировки или пустая строка если всё включено
 */
function stockEmergencyRollCreationStoppedMessage()
{
    static $evaluated = false;
    static $message = '';

    if ($evaluated) {
        return $message;
    }
    $evaluated = true;

    foreach (stockEmergencyCreatesOffFilePaths() as $path) {
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $text = (is_string($raw) ? trim($raw) : '');
            if ($text !== '') {
                $message = $text;
            } else {
                $message = 'Аварийно: добавление рулонов отключено (файл STOCK_CREATES_OFF в корне сайта). '
                    . 'Чтобы возобновить — удалите этот файл на хостинге.';
            }
            return $message;
        }
    }

    return '';
}
