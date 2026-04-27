-- Manual queue for incoming B24 sales that warehouse staff fulfill by chunks.

CREATE TABLE IF NOT EXISTS `b24_sale_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `b24_deal_id` int NOT NULL,
  `deal_name` varchar(255) DEFAULT NULL,
  `responsible` varchar(255) DEFAULT NULL,
  `status` enum('new','in_progress','completed','cancelled') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_b24_sale_requests_deal` (`b24_deal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `b24_sale_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `b24_product_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity_m` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_per_unit` decimal(10,2) DEFAULT NULL,
  `status` enum('new','in_progress','completed') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_b24_sale_lines_request` (`request_id`),
  KEY `idx_b24_sale_lines_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `b24_sale_line_cuts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `line_id` int NOT NULL,
  `roll_id` int NOT NULL,
  `meters` decimal(10,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_b24_sale_line_cuts_line` (`line_id`),
  KEY `idx_b24_sale_line_cuts_roll` (`roll_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
