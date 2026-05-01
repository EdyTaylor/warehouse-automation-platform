<?php

require_once __DIR__ . '/app_settings.php';

/** @return string */
function integrationAllSyncPausedSettingsKey()
{
    return 'integration_all_sync_paused';
}

function integrationAllSyncPaused(PDO $db)
{
    return trim((string)getAppSetting($db, integrationAllSyncPausedSettingsKey(), '0')) === '1';
}

/** @return string */
function integrationAllowLocalReceiptDuringPauseSettingsKey()
{
    return 'integration_allow_local_receipt_during_pause';
}

/**
 * Явное разрешение прихода с local_only, пока включена пауза синхронизации (по умолчанию выключено).
 */
function integrationAllowsLocalReceiptDuringPause(PDO $db)
{
    return trim((string)getAppSetting($db, integrationAllowLocalReceiptDuringPauseSettingsKey(), '0')) === '1';
}

/**
 * При паузе синхронизации не создаём рулоны через обычные формы склада/дашборда.
 * Приход документом — только при integrationAllowsLocalReceiptDuringPause + local_only.
 *
 * @return string пустая строка = можно, иначе текст ошибки для пользователя
 */
function integrationStockRollCreationBlockedMessage(PDO $db)
{
    if (!integrationAllSyncPaused($db)) {
        return '';
    }
    return 'Синхронизация отключена: добавление рулонов через эту страницу недоступно. '
        . 'Включите синхронизацию или в Центре интеграции отметьте «Разрешить локальный приход при паузе» и проведите приход в режиме «только локально» (форма / JSON).';
}

/**
 * Метод Б24, который может менять данные в портале (блокируется при паузе).
 * Списки/get остаются доступны (справочники в Центре интеграции, чтение каталога и т.д.).
 *
 * @param string $method
 * @return bool
 */
function integrationBitrixMethodLooksLikeWriteMutation($method)
{
    $m = strtolower((string)$method);
    if ($m === '') {
        return true;
    }

    $hints = array(
        '.update',
        '.add',
        '.delete',
        '.conduct',
        '.copy',
        '.register',
        '.set',
        '.remove',
        '.move',
        '.merge',
        'crm.timeline.comment.add',
        'crm.activity.add',
    );
    foreach ($hints as $hint) {
        if (strpos($m, $hint) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * JSON-ответ и exit для cron/импорта/цикла синхронизации.
 *
 * @param PDO $db
 */
function integrationAbortJsonIfAllSyncPaused(PDO $db)
{
    if (!integrationAllSyncPaused($db)) {
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'ok' => false,
        'integration_sync_paused' => true,
        'message' => 'Синхронизация отключена администратором (Центр интеграции). Включите переключатель, чтобы снова импортировать и слать данные в Б24.',
    ));
    exit;
}
