CREATE TABLE IF NOT EXISTS product_price_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT NOT NULL,
    old_price_per_meter DECIMAL(14,2) DEFAULT NULL,
    new_price_per_meter DECIMAL(14,2) DEFAULT NULL,
    old_purchase_price DECIMAL(14,2) DEFAULT NULL,
    new_purchase_price DECIMAL(14,2) DEFAULT NULL,
    old_delivery_price DECIMAL(14,2) DEFAULT NULL,
    new_delivery_price DECIMAL(14,2) DEFAULT NULL,
    change_source VARCHAR(50) NOT NULL DEFAULT 'products.php',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_product_created (product_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
