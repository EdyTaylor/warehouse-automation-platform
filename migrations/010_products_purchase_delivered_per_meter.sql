-- Закуп с доставкой за 1 метр (для PURCHASING_PRICE в Б24). При наличии — приоритет над вычислением delivery_price/roll_length.
-- Автоматически также добавляется из ensureStockOperationTables() при первом обращении к складу.

ALTER TABLE `products`
  ADD COLUMN `purchase_delivered_per_meter` decimal(18,6) NOT NULL DEFAULT 0 AFTER `delivery_price`;
