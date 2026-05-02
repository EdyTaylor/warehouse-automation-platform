-- Снимки названий локального каталога (перед большим приходом / экспериментами).
-- Создание таблицы дублируется в example/product_names_snapshot_cli.php (ensureSnapshotsTable).

CREATE TABLE IF NOT EXISTS `product_name_snapshots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `b24_product_id` int DEFAULT NULL,
  `name_was` varchar(255) NOT NULL,
  `snapshot_label` varchar(96) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pns_pid` (`product_id`),
  KEY `idx_pns_created` (`created_at`),
  KEY `idx_pns_label` (`snapshot_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
