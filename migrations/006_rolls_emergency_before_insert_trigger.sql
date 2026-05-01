-- Аварийная блокировка INSERT в rolls на уровне БД (MySQL/MariaDB).
-- Срабатывает, если в app_settings key = emergency_block_roll_creates и value = 1.
-- Работает даже когда на хосте старые PHP-файлы или не там лежит STOCK_CREATES_OFF.
--
-- Включить блокировку (через PHP или phpMyAdmin):
--   INSERT INTO app_settings (`key`, `value`) VALUES ('emergency_block_roll_creates', '1')
--   ON DUPLICATE KEY UPDATE `value` = '1';
--
-- Выключить:
--   UPDATE app_settings SET `value` = '0' WHERE `key` = 'emergency_block_roll_creates';
--
-- Удалить триггер полностью:
--   DROP TRIGGER IF EXISTS rolls_before_insert_emergency;

DROP TRIGGER IF EXISTS rolls_before_insert_emergency;

DELIMITER $$

CREATE TRIGGER rolls_before_insert_emergency
BEFORE INSERT ON rolls
FOR EACH ROW
BEGIN
    DECLARE v VARCHAR(16) DEFAULT NULL;
    SELECT `value` INTO v FROM app_settings WHERE `key` = 'emergency_block_roll_creates' LIMIT 1;
    IF v = '1' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Emergency: insert into rolls blocked (emergency_block_roll_creates=1 in app_settings)';
    END IF;
END$$

DELIMITER ;
