-- Добавление поля catalog_id для поддержки категорий из Битрикс24
ALTER TABLE `products`
  ADD COLUMN `catalog_id` int DEFAULT NULL AFTER `b24_product_id`,
  ADD COLUMN `description` text DEFAULT NULL AFTER `catalog_id`;

-- Индекс для быстрой фильтрации по категориям
CREATE INDEX `idx_products_catalog` ON `products` (`catalog_id`);
