<?php
/**
 * Post/Redirect/Get: после успешной обработки POST — ответ 303 и GET следующей загрузкой,
 * чтобы F5 не повторял действие («Подтвердите повторную отправку формы»).
 *
 * Сообщения храним в сессии одним запросом (flash), чтобы не засорять длинными строками URL.
 */

if (!defined('PRG_FLASH_SESSION_KEY')) {
    define('PRG_FLASH_SESSION_KEY', 'app_prg_flash_once_v1');
}

/**
 * Обрезка для размера сессии и читаемости.
 *
 * @param string $s
 * @param int $max
 * @return string
 */
function prgTruncateFlashText($s, $max = 4000) {
    $t = trim((string)$s);
    if ($t === '') {
        return '';
    }
    if (strlen($t) <= $max) {
        return $t;
    }
    return substr($t, 0, $max) . '…';
}

/**
 * Безопасное имя PHP-скрипта для Location (только файл в корне сайта приложения).
 *
 * @param string $basename
 * @return string
 */
function prgSafeRedirectBasename($basename) {
    $b = basename((string)$basename);
    if (!preg_match('/^[A-Za-z0-9._-]+\.php$/', $b)) {
        return 'dashboard.php';
    }
    return $b;
}

/**
 * Сохранить flash и немедленно ответить 303 на GET этого скрипта.
 *
 * @param string $basename например stock_operations.php
 * @param array $flash ключи success|error (строки)
 */
function prgFlashCommitAndRedirect303($basename, array $flash) {
    $base = prgSafeRedirectBasename($basename);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $clean = array();
    $e = isset($flash['error']) ? prgTruncateFlashText(trim((string)$flash['error'])) : '';
    $s = isset($flash['success']) ? prgTruncateFlashText(trim((string)$flash['success'])) : '';
    if ($e !== '') {
        $clean['error'] = $e;
    } elseif ($s !== '') {
        $clean['success'] = $s;
    }
    $_SESSION[PRG_FLASH_SESSION_KEY] = $clean;
    header('Location: ' . $base, true, 303);
    exit;
}

/**
 * То же для целевых GET-параметров (напр. product_id после ошибки на add_stock.php).
 *
 * @param string $basename только имя файла, без пути и без '?'
 * @param array $getParams простые скалярные значения для http_build_query
 * @param array $flash ключи success|error
 */
function prgFlashCommitAndRedirect303WithQuery($basename, array $getParams, array $flash) {
    $base = prgSafeRedirectBasename($basename);
    $q = http_build_query($getParams);
    $suffix = '';
    if ($q !== '') {
        $suffix = '?' . $q;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $clean = array();
    $e = isset($flash['error']) ? prgTruncateFlashText(trim((string)$flash['error'])) : '';
    $s = isset($flash['success']) ? prgTruncateFlashText(trim((string)$flash['success'])) : '';
    if ($e !== '') {
        $clean['error'] = $e;
    } elseif ($s !== '') {
        $clean['success'] = $s;
    }
    $_SESSION[PRG_FLASH_SESSION_KEY] = $clean;
    header('Location: ' . $base . $suffix, true, 303);
    exit;
}

/**
 * Взять и очистить flash (на GET после редиректа).
 *
 * @return array ключи success, error или пустой массив
 */
function prgFlashConsume() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return array();
    }
    if (!isset($_SESSION[PRG_FLASH_SESSION_KEY])) {
        return array();
    }
    $raw = $_SESSION[PRG_FLASH_SESSION_KEY];
    unset($_SESSION[PRG_FLASH_SESSION_KEY]);
    if (!is_array($raw)) {
        return array();
    }
    return $raw;
}
