CREATE TABLE IF NOT EXISTS `order_allocations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_request_id` int NOT NULL,
  `deal_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `roll_id` int NOT NULL,
  `allocated_m` decimal(10,2) NOT NULL DEFAULT '0.00',
  `allocated_rolls` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_per_unit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `source` enum('auto','manual') NOT NULL DEFAULT 'auto',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_alloc_req` (`sale_request_id`),
  KEY `idx_order_alloc_deal` (`deal_id`),
  KEY `idx_order_alloc_product` (`product_id`),
  KEY `idx_order_alloc_roll` (`roll_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
