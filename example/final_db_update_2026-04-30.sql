-- Final consolidated SQL for current warehouse DB
-- Source:
--   1) latest_db_prefix_recheck.csv (matched rows)
--   2) business rules from chat:
--      - exclude red-marked + "лоб наружка" + sheets "лоб наружка", "тонировка/прайс новый"
--      - USD -> KGS rate = 87.5
--      - otrez -> price_per_meter
--      - delivery -> delivery_price
--      - roll lengths expected: 15/20/25/30/50
--   3) manual fixes:
--      - translit naming normalization (Hard Gloss / Ridgit)
--      - tools + primer ("Инструменты"/"Другое")

START TRANSACTION;

-- ===== Name normalization (translit -> canonical) =====
UPDATE products SET name='Хард Глосс (hard gloss)'
WHERE name IN ('Hard Gloss', 'Хард Глосс', 'Хард Глосс (Hard Gloss)', 'Хард Глосс (hard gloss)')
LIMIT 1;

UPDATE products SET name='Риджит (ridgit)'
WHERE name IN ('Ridgit', 'Ridget', 'Риджит', 'риджит')
LIMIT 1;

-- ===== Update-only by latest DB recheck (safe matched set) =====
UPDATE products SET roll_length=50, price_5_9=15750 WHERE name='ASWF Bronze Matte' LIMIT 1;
UPDATE products SET roll_length=50, price_5_9=15750 WHERE name='ASWF Grey Matte' LIMIT 1;
UPDATE products SET roll_length=50, price_5_9=15750 WHERE name='ASWF Silver Matte' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=21000, delivery_price=27300, price_per_meter=2712.5, price_1_4=41125, price_20_plus=35875 WHERE name='ATR 05 CH SR HPR' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=21000, delivery_price=27300, price_per_meter=2712.5, price_1_4=41125, price_20_plus=35875 WHERE name='ATR 15 CH SR HPR' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=21000, delivery_price=27300, price_per_meter=2712.5, price_1_4=41125, price_20_plus=35875 WHERE name='ATR 20 CH SR HPR' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=21000, delivery_price=27300, price_per_meter=2712.5, price_1_4=41125, price_20_plus=35875 WHERE name='ATR 35 CH SR HPR' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=21000, delivery_price=27300, price_per_meter=2712.5, price_1_4=41125, price_20_plus=35875 WHERE name='ATR 50 CH SR HPR' LIMIT 1;
UPDATE products SET roll_length=50, price_per_meter=0, price_5_9=0, price_20_plus=17500 WHERE name='LLumar ATT 05 S SR HPR 2' LIMIT 1;
UPDATE products SET roll_length=50, purchase_price=35000, price_1_4=35000, price_5_9=35000 WHERE name='LLumar AU 85 UV SR HPR' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4987.5, delivery_price=5862.5, price_per_meter=700, price_1_4=9975, price_5_9=8837.5, price_20_plus=7350 WHERE name='Carbon CBS ST BK 0330' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4987.5, delivery_price=5862.5, price_per_meter=700, price_1_4=9975, price_5_9=8837.5, price_20_plus=7350 WHERE name='Carbon CBS ST BK 0530' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4987.5, delivery_price=5862.5, price_per_meter=700, price_1_4=9975, price_5_9=8837.5, price_20_plus=7350 WHERE name='Carbon CBS ST BK 1530' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4987.5, delivery_price=5862.5, price_per_meter=700, price_1_4=9975, price_5_9=8837.5, price_20_plus=7350 WHERE name='Carbon CBS ST BK 2030' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4987.5, delivery_price=5862.5, price_per_meter=700, price_1_4=9975, price_5_9=8837.5, price_20_plus=7350 WHERE name='Carbon CBS ST BK 3530' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4987.5, delivery_price=5862.5, price_per_meter=700, price_1_4=9975, price_5_9=8837.5, price_20_plus=7350 WHERE name='Carbon CBS ST BK 5030' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4987.5, delivery_price=5862.5, price_per_meter=700, price_1_4=9975, price_5_9=8837.5, price_20_plus=7350 WHERE name='Carbon CBS ST BK 7030' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=11987.5, delivery_price=12862.5, price_per_meter=1487.5, price_1_4=21875, price_5_9=19337.5, price_20_plus=16100 WHERE name='Ceramic PIR8070' LIMIT 1;
UPDATE products SET roll_length=50, price_1_4=17500, price_5_9=17500 WHERE name='LLumar FROSTED SPARKLE' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=5862.5, delivery_price=6737.5, price_per_meter=787.5, price_1_4=11462.5, price_5_9=10150, price_20_plus=8400 WHERE name='HP 03 Black SR PS' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=5862.5, delivery_price=6737.5, price_per_meter=787.5, price_1_4=11462.5, price_5_9=10150, price_20_plus=8400 WHERE name='HP 05 Black SR PS' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=5862.5, delivery_price=6737.5, price_per_meter=787.5, price_1_4=11462.5, price_5_9=10150, price_20_plus=8400 WHERE name='HP 15 Black SR PS' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=5862.5, delivery_price=6737.5, price_per_meter=787.5, price_1_4=11462.5, price_5_9=10150, price_20_plus=8400 WHERE name='HP 20 Black SR PS' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=5862.5, delivery_price=6737.5, price_per_meter=787.5, price_1_4=11462.5, price_5_9=10150, price_20_plus=8400 WHERE name='HP 35 Black SR PS' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=5862.5, delivery_price=6737.5, price_per_meter=787.5, price_1_4=11462.5, price_5_9=10150, price_20_plus=8400 WHERE name='HP 50 Black SR PS' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=5862.5, delivery_price=6737.5, price_per_meter=787.5, price_1_4=11462.5, price_5_9=10150, price_20_plus=8400 WHERE name='HP 70 Black SR PS' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=64750, delivery_price=84175, price_per_meter=5250, price_1_4=126875, price_20_plus=110250 WHERE name='IRXTM 05 CH SR HPR' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=61687.5, delivery_price=80237.5, price_per_meter=5250, price_1_4=120750, price_20_plus=105000 WHERE name='IRXTM 15 CH SR HPR' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=61687.5, delivery_price=80237.5, price_per_meter=5250, price_1_4=120750, price_20_plus=105000 WHERE name='IRXTM 35 CH SR HPR' LIMIT 1;
UPDATE products SET roll_length=50, price_per_meter=25375, price_5_9=25375, price_20_plus=26250 WHERE name='LLumar LA 20 S SR HPR' LIMIT 1;
UPDATE products SET roll_length=50, purchase_price=42875, price_5_9=52500 WHERE name='LLumar LE 35 SR CDF' LIMIT 1;
UPDATE products SET roll_length=50, purchase_price=42875, price_5_9=52500 WHERE name='LLumar LE 50' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=5512.5, delivery_price=7262.5, price_per_meter=875, price_1_4=12337.5, price_5_9=10937.5, price_20_plus=9100 WHERE name='LUXFIL AIR90   Blue' LIMIT 1;
UPDATE products SET roll_length=50, price_per_meter=3150, price_1_4=3150, price_5_9=3150, price_20_plus=3937.5 WHERE name='LUXFIL BLACK 05' LIMIT 1;
UPDATE products SET roll_length=50, price_per_meter=3150, price_1_4=3150, price_5_9=3150, price_20_plus=3937.5 WHERE name='LUXFIL BLACK 15' LIMIT 1;
UPDATE products SET roll_length=50, price_per_meter=3150, price_1_4=3150, price_5_9=3150, price_20_plus=3937.5 WHERE name='LUXFIL BLACK 35' LIMIT 1;
UPDATE products SET roll_length=50, price_per_meter=3150, price_1_4=3150, price_5_9=3150, price_20_plus=3937.5 WHERE name='LUXFIL BLACK 50' LIMIT 1;
UPDATE products SET roll_length=50, price_per_meter=3150, price_1_4=3150, price_5_9=3150, price_20_plus=3937.5 WHERE name='LUXFIL BLACK 70' LIMIT 1;
UPDATE products SET roll_length=50, price_per_meter=3150, price_1_4=3150, price_5_9=3150, price_20_plus=3937.5 WHERE name='LUXFIL BLACK 80' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4900, delivery_price=6387.5, price_per_meter=700, price_1_4=10850, price_5_9=9537.5, price_20_plus=7962.5 WHERE name='LUXFIL CARBON NANO CERAMIC 0590' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4900, delivery_price=6387.5, price_per_meter=700, price_1_4=10850, price_5_9=9537.5, price_20_plus=7962.5 WHERE name='LUXFIL CARBON NANO CERAMIC 1590' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4900, delivery_price=6387.5, price_per_meter=700, price_1_4=10850, price_5_9=9537.5, price_20_plus=7962.5 WHERE name='LUXFIL CARBON NANO CERAMIC 3590' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4900, delivery_price=6387.5, price_per_meter=700, price_1_4=10850, price_5_9=9537.5, price_20_plus=7962.5 WHERE name='LUXFIL CARBON NANO CERAMIC 5090' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=4900, delivery_price=6387.5, price_per_meter=700, price_1_4=10850, price_5_9=9537.5, price_20_plus=7962.5 WHERE name='LUXFIL CARBON NANO CERAMIC 7090' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=6562.5, delivery_price=8575, price_per_meter=962.5, price_1_4=14525, price_5_9=12775, price_20_plus=10675 WHERE name='LUXFIL CERAMIC IR7590' LIMIT 1;
UPDATE products SET roll_length=15, purchase_price=13125, delivery_price=17500, price_per_meter=3062.5, price_1_4=35000, price_5_9=30625, price_20_plus=26250 WHERE name='LUXFIL PPF  Tint 05 limited edition' LIMIT 1;
UPDATE products SET roll_length=15, purchase_price=13125, delivery_price=17500, price_per_meter=3062.5, price_1_4=35000, price_5_9=30625, price_20_plus=26250 WHERE name='LUXFIL PPF  Tint 15 limited edition' LIMIT 1;
UPDATE products SET roll_length=50, purchase_price=3237.5, delivery_price=525, price_per_meter=7437.5, price_1_4=3850 WHERE name='LUXFIL SAFETY 2 light' LIMIT 1;
UPDATE products SET roll_length=50, purchase_price=40512.5, price_1_4=13125, price_5_9=13125 WHERE name='LLumar N1020 SR CDF' LIMIT 1;
UPDATE products SET roll_length=50, price_1_4=13125, price_5_9=13125 WHERE name='LLumar R 35 SR HPR' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=13300, delivery_price=14175, price_per_meter=1662.5, price_1_4=24062.5, price_5_9=21262.5, price_20_plus=17762.5 WHERE name='Super IR 7080 BLUE    NEW+' LIMIT 1;
UPDATE products SET roll_length=30, purchase_price=13300, delivery_price=14175, price_per_meter=1662.5, price_1_4=24062.5, price_5_9=21262.5, price_20_plus=17762.5 WHERE name='Super IR 7080 GREEN' LIMIT 1;

-- ===== "Инструменты" + "Другое" (USD converted to KGS 87.5) =====
-- For piece goods: roll_length=1, price_per_meter = selling unit price,
-- quantity tiers mapped to 1/5/20 packs where available.

UPDATE products
SET roll_length=1,
    purchase_price=674.63,
    delivery_price=674.63,
    price_per_meter=700.00,
    price_1_4=700.00,
    price_5_9=700.00,
    price_20_plus=700.00
WHERE name='OLFA Лезвия SAB - 10B' LIMIT 1;

UPDATE products
SET roll_length=1,
    purchase_price=1350.13,
    delivery_price=1350.13,
    price_per_meter=1312.50,
    price_1_4=1312.50,
    price_5_9=1312.50,
    price_20_plus=1312.50
WHERE name='OLFA Лезвия ABB - 50B' LIMIT 1;

UPDATE products
SET roll_length=1,
    purchase_price=780.50,
    delivery_price=780.50,
    price_per_meter=787.50,
    price_1_4=787.50,
    price_5_9=787.50,
    price_20_plus=787.50
WHERE name='OLFA Ножи OL-SAC-1 ' LIMIT 1;

UPDATE products
SET roll_length=1,
    purchase_price=254.63,
    delivery_price=254.63,
    price_per_meter=262.50,
    price_1_4=262.50,
    price_5_9=262.50,
    price_20_plus=262.50
WHERE name='OLFA Ножи OL-S' LIMIT 1;

UPDATE products
SET roll_length=1,
    purchase_price=11725.00,
    delivery_price=11812.50,
    price_per_meter=1225.00,
    price_1_4=1225.00,
    price_5_9=11812.50,
    price_20_plus=11812.50
WHERE name='Праймер Pro Bond' LIMIT 1;

-- Insert if tool rows are absent in current DB
INSERT INTO products (name, roll_length, purchase_price, delivery_price, price_per_meter, price_1_4, price_5_9, price_20_plus)
SELECT 'OLFA Лезвия SAB - 10B', 1, 674.63, 674.63, 700.00, 700.00, 700.00, 700.00
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name='OLFA Лезвия SAB - 10B');

INSERT INTO products (name, roll_length, purchase_price, delivery_price, price_per_meter, price_1_4, price_5_9, price_20_plus)
SELECT 'OLFA Лезвия ABB - 50B', 1, 1350.13, 1350.13, 1312.50, 1312.50, 1312.50, 1312.50
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name='OLFA Лезвия ABB - 50B');

INSERT INTO products (name, roll_length, purchase_price, delivery_price, price_per_meter, price_1_4, price_5_9, price_20_plus)
SELECT 'OLFA Ножи OL-SAC-1 ', 1, 780.50, 780.50, 787.50, 787.50, 787.50, 787.50
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name='OLFA Ножи OL-SAC-1 ');

INSERT INTO products (name, roll_length, purchase_price, delivery_price, price_per_meter, price_1_4, price_5_9, price_20_plus)
SELECT 'OLFA Ножи OL-S', 1, 254.63, 254.63, 262.50, 262.50, 262.50, 262.50
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name='OLFA Ножи OL-S');

INSERT INTO products (name, roll_length, purchase_price, delivery_price, price_per_meter, price_1_4, price_5_9, price_20_plus)
SELECT 'Праймер Pro Bond', 1, 11725.00, 11812.50, 1225.00, 1225.00, 11812.50, 11812.50
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name='Праймер Pro Bond');

COMMIT;

