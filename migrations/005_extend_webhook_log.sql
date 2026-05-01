-- Диагностика вебхуков: исход обработки и привязка к CRM-сущности
ALTER TABLE `webhook_log`
  MODIFY `data` MEDIUMTEXT NOT NULL COMMENT 'полный JSON Bitrix outbound';
ALTER TABLE `webhook_log`
  ADD COLUMN `handler_outcome` varchar(160) DEFAULT NULL AFTER `processed`,
  ADD COLUMN `entity_deal_id` int DEFAULT NULL AFTER `handler_outcome`,
  ADD COLUMN `entity_product_id` int DEFAULT NULL AFTER `entity_deal_id`;
