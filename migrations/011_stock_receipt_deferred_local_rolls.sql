-- Документация: приход через Б24 и отложенные рулоны в приложении.
-- Обычно столбцы создаются автоматически: ensureStockOperationTables() в PHP.
-- Если применяете вручную и столбец уже есть — строка упадёт: пропустите её.

ALTER TABLE `stock_operation_docs` ADD COLUMN `receipt_min_full` decimal(10,2) NOT NULL DEFAULT '0.50';
ALTER TABLE `stock_operation_docs` ADD COLUMN `receipt_local_only` tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `stock_operation_docs` ADD COLUMN `receipt_rolls_applied_at` datetime DEFAULT NULL;
