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

/** @return string */
function integrationStockAbortEpochSettingsKey()
{
    return 'integration_stock_abort_epoch';
}

/** Текущая «эпоха»: при сохранении паузы или нажатии «Прервать приход» увеличивается — долгий приход откатывается при несовпадении. */
function integrationGetStockAbortEpoch(PDO $db)
{
    return (int)trim((string)getAppSetting($db, integrationStockAbortEpochSettingsKey(), '0'));
}

function integrationBumpStockAbortEpoch(PDO $db)
{
    $cur = integrationGetStockAbortEpoch($db);
    $next = $cur + 1;
    if ($next > 2000000000) {
        $next = 1;
    }
    setAppSetting($db, integrationStockAbortEpochSettingsKey(), (string)$next);
    return $next;
}

/**
 * Быстрое чтение счётчика прерывания без ensureAppSettingsTable (иначе в цикле на тысячи рулонов
 * тысячи раз выполняются CREATE TABLE IF NOT EXISTS из getAppSetting — «залипание»).
 *
 * @return int
 */
function integrationReceiptAbortEpochReadBare(PDO $db)
{
    $stmt = $db->prepare('SELECT `value` FROM app_settings WHERE `key` = ? LIMIT 1');
    $stmt->execute(array(integrationStockAbortEpochSettingsKey()));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !isset($row['value'])) {
        return 0;
    }
    return (int)trim((string)$row['value']);
}

/**
 * Вызвать из долгого прихода: если эпоха изменилась (настройки сохранили в другом запросе) — прервать и откатить транзакцию.
 *
 * @param PDOStatement|null $prepared если передан уже prepare('SELECT …') — переиспользуем (ещё меньше накладных расходов на рулон).
 * @param int $epochAtStart
 * @throws Exception
 */
function integrationAssertReceiptAbortEpochUnchanged(PDO $db, $epochAtStart, $prepared = null)
{
    if ($prepared !== null && is_object($prepared)) {
        $prepared->execute(array(integrationStockAbortEpochSettingsKey()));
        $row = $prepared->fetch(PDO::FETCH_ASSOC);
        $now = (!$row || !isset($row['value'])) ? 0 : (int)trim((string)$row['value']);
        $prepared->closeCursor();
    } else {
        $now = integrationReceiptAbortEpochReadBare($db);
    }
    if ($now !== (int)$epochAtStart) {
        throw new Exception('Приход прерван: изменены настройки интеграции (пауза / разрешение прихода) или нажато «Прервать приход». Повторите при необходимости.');
    }
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
