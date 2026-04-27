-- Unified movement journal for warehouse accounting and Bitrix sync.

CREATE TABLE `stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `roll_id` int DEFAULT NULL,
  `movement_type` enum('receipt','reserve','reserve_release','sale_meter','sale_roll','writeoff','adjustment') NOT NULL,
  `quantity_m` decimal(10,2) NOT NULL DEFAULT '0.00',
  `quantity_rolls` int NOT NULL DEFAULT 0,
  `price_per_unit` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `deal_id` int DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `bitrix_status` enum('pending','sent','error') NOT NULL DEFAULT 'pending',
  `bitrix_response` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stock_movements_product` (`product_id`),
  KEY `idx_stock_movements_deal` (`deal_id`),
  KEY `idx_stock_movements_bitrix_status` (`bitrix_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
