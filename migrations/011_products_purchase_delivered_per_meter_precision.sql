-- Расширение точности: иначе при UPDATE delivery_price/roll_length MySQL режет дробную часть (Note #1265 Data truncated).

ALTER TABLE `products`
  MODIFY COLUMN `purchase_delivered_per_meter` decimal(18,6) NOT NULL DEFAULT 0;
