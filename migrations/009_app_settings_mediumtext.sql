-- Длинные JSON-настройки интеграции (воронки, правила стадий)
ALTER TABLE app_settings MODIFY `value` MEDIUMTEXT NULL;
