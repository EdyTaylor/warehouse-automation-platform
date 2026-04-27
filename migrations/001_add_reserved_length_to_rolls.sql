-- Adds partial reservation (in meters) per roll.
-- This keeps Bitrix stock accurate when reserving meters from rolls.

ALTER TABLE `rolls`
  ADD COLUMN `reserved_length` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `current_length`;

-- Helpful indexes for deal flows.
CREATE INDEX `idx_rolls_product_reserved` ON `rolls` (`product_id`, `reserved`, `status`);
CREATE INDEX `idx_rolls_deal` ON `rolls` (`deal_id`);
