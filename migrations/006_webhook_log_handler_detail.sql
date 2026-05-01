-- Текст ошибки/пояснение к итогу вебхука (queue_error, fatal и т.п.)
ALTER TABLE `webhook_log`
  ADD COLUMN `handler_detail` text DEFAULT NULL AFTER `handler_outcome`;
