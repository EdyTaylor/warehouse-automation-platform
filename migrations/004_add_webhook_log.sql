-- Таблица для логирования входящих вебхуков от Битрикс24
CREATE TABLE `webhook_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event` varchar(100) NOT NULL,
  `data` text NOT NULL,
  `processed` tinyint DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_event` (`event`),
  KEY `idx_webhook_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
