-- Учёт фактической выручки (со скидкой из Б24) vs план по тарифу.
-- На проде колонки также создаются через ensure* в PHP при первом обращении.

ALTER TABLE `b24_sale_lines`
  ADD COLUMN `list_price_per_unit` decimal(14,4) NOT NULL DEFAULT 0 AFTER `price_per_unit`,
  ADD COLUMN `line_total_list` decimal(14,2) NOT NULL DEFAULT 0 AFTER `list_price_per_unit`,
  ADD COLUMN `line_total_fact` decimal(14,2) NOT NULL DEFAULT 0 AFTER `line_total_list`;

ALTER TABLE `sales`
  ADD COLUMN `revenue_planned` decimal(14,2) NOT NULL DEFAULT 0 AFTER `total`;
