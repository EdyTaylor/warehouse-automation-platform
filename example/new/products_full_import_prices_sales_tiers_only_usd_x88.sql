-- Продажа: закупку, доставку, purchase_delivered_per_meter не трогаем.
-- Обновляет price_per_meter + price_1_4 … price_20_plus (если сборка без --without-meter).
-- Источник LLumar: products_full_import_prices_usd_x88.sql;
-- доп. строки из products.sql; без LLumar-файла фолбэк слабее.
-- Применение: только phpMyAdmin → SQL или mysql CLI на своей БД (сайт этот файл не выполняет сам).
-- Regenerate: node example/new/build_sales_tiers_only_sql.js [--without-meter] [--tier-fallback-from-meter-roll]
-- Rows: 712 UPDATE statements.
-- Generated: 2026-05-04T15:17:01.721Z

SET NAMES utf8mb4;
START TRANSACTION;

-- id=26
UPDATE `products` SET `price_1_4` = '127600.00', `price_5_9` = '110880.00', `price_10_19` = '110880.00', `price_20_plus` = '110880.00', `price_per_meter` = '5280.00' WHERE `id` = 26;
-- id=27
UPDATE `products` SET `price_1_4` = '121440.00', `price_5_9` = '105600.00', `price_10_19` = '105600.00', `price_20_plus` = '105600.00', `price_per_meter` = '5280.00' WHERE `id` = 27;
-- id=28
UPDATE `products` SET `price_1_4` = '121440.00', `price_5_9` = '105600.00', `price_10_19` = '105600.00', `price_20_plus` = '105600.00', `price_per_meter` = '5280.00' WHERE `id` = 28;
-- id=29
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00', `price_per_meter` = '2288.00' WHERE `id` = 29;
-- id=30
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00', `price_per_meter` = '2288.00' WHERE `id` = 30;
-- id=31
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00', `price_per_meter` = '2288.00' WHERE `id` = 31;
-- id=32
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00', `price_per_meter` = '2288.00' WHERE `id` = 32;
-- id=33
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00', `price_per_meter` = '2288.00' WHERE `id` = 33;
-- id=34
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00', `price_per_meter` = '2288.00' WHERE `id` = 34;
-- id=35
UPDATE `products` SET `price_1_4` = '41360.00', `price_5_9` = '36080.00', `price_10_19` = '36080.00', `price_20_plus` = '36080.00', `price_per_meter` = '2728.00' WHERE `id` = 35;
-- id=36
UPDATE `products` SET `price_1_4` = '41360.00', `price_5_9` = '36080.00', `price_10_19` = '36080.00', `price_20_plus` = '36080.00', `price_per_meter` = '2728.00' WHERE `id` = 36;
-- id=37
UPDATE `products` SET `price_1_4` = '41360.00', `price_5_9` = '36080.00', `price_10_19` = '36080.00', `price_20_plus` = '36080.00', `price_per_meter` = '2728.00' WHERE `id` = 37;
-- id=38
UPDATE `products` SET `price_1_4` = '41360.00', `price_5_9` = '36080.00', `price_10_19` = '36080.00', `price_20_plus` = '36080.00', `price_per_meter` = '2728.00' WHERE `id` = 38;
-- id=39
UPDATE `products` SET `price_1_4` = '41360.00', `price_5_9` = '36080.00', `price_10_19` = '36080.00', `price_20_plus` = '36080.00', `price_per_meter` = '2728.00' WHERE `id` = 39;
-- id=40
UPDATE `products` SET `price_1_4` = '124080.00', `price_5_9` = '107360.00', `price_10_19` = '107360.00', `price_20_plus` = '107360.00', `price_per_meter` = '5280.00' WHERE `id` = 40;
-- id=41
UPDATE `products` SET `price_1_4` = '74800.00', `price_5_9` = '64240.00', `price_10_19` = '64240.00', `price_20_plus` = '64240.00', `price_per_meter` = '3520.00' WHERE `id` = 41;
-- id=42
UPDATE `products` SET `price_1_4` = '124080.00', `price_5_9` = '107360.00', `price_10_19` = '107360.00', `price_20_plus` = '107360.00', `price_per_meter` = '5280.00' WHERE `id` = 42;
-- id=43
UPDATE `products` SET `price_1_4` = '74800.00', `price_5_9` = '64240.00', `price_10_19` = '64240.00', `price_20_plus` = '64240.00', `price_per_meter` = '3520.00' WHERE `id` = 43;
-- id=44
UPDATE `products` SET `price_1_4` = '124080.00', `price_5_9` = '107360.00', `price_10_19` = '107360.00', `price_20_plus` = '107360.00', `price_per_meter` = '5280.00' WHERE `id` = 44;
-- id=45
UPDATE `products` SET `price_1_4` = '148720.00', `price_5_9` = '128480.00', `price_10_19` = '128480.00', `price_20_plus` = '128480.00', `price_per_meter` = '7920.00' WHERE `id` = 45;
-- id=46
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00', `price_per_meter` = '704.00' WHERE `id` = 46;
-- id=47
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00', `price_per_meter` = '704.00' WHERE `id` = 47;
-- id=48
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00', `price_per_meter` = '704.00' WHERE `id` = 48;
-- id=49
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00', `price_per_meter` = '704.00' WHERE `id` = 49;
-- id=50
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00', `price_per_meter` = '704.00' WHERE `id` = 50;
-- id=51
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00', `price_per_meter` = '704.00' WHERE `id` = 51;
-- id=52
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00', `price_per_meter` = '704.00' WHERE `id` = 52;
-- id=53
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00', `price_per_meter` = '792.00' WHERE `id` = 53;
-- id=54
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00', `price_per_meter` = '792.00' WHERE `id` = 54;
-- id=55
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00', `price_per_meter` = '792.00' WHERE `id` = 55;
-- id=56
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00', `price_per_meter` = '792.00' WHERE `id` = 56;
-- id=57
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00', `price_per_meter` = '792.00' WHERE `id` = 57;
-- id=58
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00', `price_per_meter` = '792.00' WHERE `id` = 58;
-- id=59
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00', `price_per_meter` = '792.00' WHERE `id` = 59;
-- id=60
UPDATE `products` SET `price_1_4` = '22000.00', `price_5_9` = '19448.00', `price_10_19` = '16192.00', `price_20_plus` = '16192.00', `price_per_meter` = '1496.00' WHERE `id` = 60;
-- id=61
UPDATE `products` SET `price_1_4` = '24200.00', `price_5_9` = '21384.00', `price_10_19` = '17864.00', `price_20_plus` = '17864.00', `price_per_meter` = '1672.00' WHERE `id` = 61;
-- id=62
UPDATE `products` SET `price_1_4` = '24200.00', `price_5_9` = '21384.00', `price_10_19` = '17864.00', `price_20_plus` = '17864.00', `price_per_meter` = '1672.00' WHERE `id` = 62;
-- id=63
UPDATE `products` SET `price_1_4` = '24200.00', `price_5_9` = '21384.00', `price_10_19` = '17864.00', `price_20_plus` = '17864.00', `price_per_meter` = '1672.00' WHERE `id` = 63;
-- id=64
UPDATE `products` SET `price_1_4` = '10912.00', `price_5_9` = '9592.00', `price_10_19` = '8008.00', `price_20_plus` = '8008.00', `price_per_meter` = '6424.00' WHERE `id` = 64;
-- id=65
UPDATE `products` SET `price_1_4` = '10912.00', `price_5_9` = '9592.00', `price_10_19` = '8008.00', `price_20_plus` = '8008.00', `price_per_meter` = '6424.00' WHERE `id` = 65;
-- id=66
UPDATE `products` SET `price_1_4` = '10912.00', `price_5_9` = '9592.00', `price_10_19` = '8008.00', `price_20_plus` = '8008.00', `price_per_meter` = '6424.00' WHERE `id` = 66;
-- id=67
UPDATE `products` SET `price_1_4` = '10912.00', `price_5_9` = '9592.00', `price_10_19` = '8008.00', `price_20_plus` = '8008.00', `price_per_meter` = '6424.00' WHERE `id` = 67;
-- id=68
UPDATE `products` SET `price_1_4` = '10912.00', `price_5_9` = '9592.00', `price_10_19` = '8008.00', `price_20_plus` = '8008.00', `price_per_meter` = '6424.00' WHERE `id` = 68;
-- id=69
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 69;
-- id=70
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 70;
-- id=71
UPDATE `products` SET `price_1_4` = '14608.00', `price_5_9` = '12848.00', `price_10_19` = '10736.00', `price_20_plus` = '10736.00', `price_per_meter` = '8624.00' WHERE `id` = 71;
-- id=72
UPDATE `products` SET `price_1_4` = '12408.00', `price_5_9` = '11000.00', `price_10_19` = '9152.00', `price_20_plus` = '9152.00', `price_per_meter` = '7304.00' WHERE `id` = 72;
-- id=73
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 73;
-- id=74
UPDATE `products` SET `price_1_4` = '3150.00', `price_5_9` = '3150.00', `price_20_plus` = '3937.50', `price_per_meter` = '3150.00' WHERE `id` = 74;
-- id=75
UPDATE `products` SET `price_1_4` = '5456.00', `price_5_9` = '4840.00', `price_10_19` = '4048.00', `price_20_plus` = '4048.00', `price_per_meter` = '3168.00' WHERE `id` = 75;
-- id=76
UPDATE `products` SET `price_1_4` = '5456.00', `price_5_9` = '4840.00', `price_10_19` = '4048.00', `price_20_plus` = '4048.00', `price_per_meter` = '3168.00' WHERE `id` = 76;
-- id=77
UPDATE `products` SET `price_1_4` = '5456.00', `price_5_9` = '4840.00', `price_10_19` = '4048.00', `price_20_plus` = '4048.00', `price_per_meter` = '3168.00' WHERE `id` = 77;
-- id=78
UPDATE `products` SET `price_1_4` = '5456.00', `price_5_9` = '4840.00', `price_10_19` = '4048.00', `price_20_plus` = '4048.00', `price_per_meter` = '3168.00' WHERE `id` = 78;
-- id=79
UPDATE `products` SET `price_1_4` = '5456.00', `price_5_9` = '4840.00', `price_10_19` = '4048.00', `price_20_plus` = '4048.00', `price_per_meter` = '3168.00' WHERE `id` = 79;
-- id=80
UPDATE `products` SET `price_1_4` = '91872.00', `price_5_9` = '91872.00', `price_10_19` = '91872.00', `price_20_plus` = '91872.00', `price_per_meter` = '2464.00' WHERE `id` = 80;
-- id=81
UPDATE `products` SET `price_1_4` = '117128.00', `price_5_9` = '88.00', `price_10_19` = '88.00', `price_20_plus` = '88.00', `price_per_meter` = '3080.00' WHERE `id` = 81;
-- id=82
UPDATE `products` SET `price_1_4` = '85888.00', `price_5_9` = '85888.00', `price_10_19` = '85888.00', `price_20_plus` = '85888.00', `price_per_meter` = '2288.00' WHERE `id` = 82;
-- id=83
UPDATE `products` SET `price_1_4` = '101728.00', `price_5_9` = '101728.00', `price_10_19` = '101728.00', `price_20_plus` = '101728.00', `price_per_meter` = '2728.00' WHERE `id` = 83;
-- id=84
UPDATE `products` SET `price_1_4` = '35640.00', `price_5_9` = '31856.00', `price_10_19` = '28160.00', `price_20_plus` = '28160.00', `price_per_meter` = '2728.00' WHERE `id` = 84;
-- id=85
UPDATE `products` SET `price_1_4` = '32736.00', `price_5_9` = '29304.00', `price_10_19` = '25872.00', `price_20_plus` = '25872.00', `price_per_meter` = '2552.00' WHERE `id` = 85;
-- id=86
UPDATE `products` SET `price_1_4` = '35640.00', `price_5_9` = '31856.00', `price_10_19` = '28160.00', `price_20_plus` = '28160.00', `price_per_meter` = '2728.00' WHERE `id` = 86;
-- id=87
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 87;
-- id=88
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 88;
-- id=89
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 89;
-- id=90
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 90;
-- id=91
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 91;
-- id=92
UPDATE `products` SET `price_1_4` = '157432.00', `price_5_9` = '157432.00', `price_10_19` = '157432.00', `price_20_plus` = '157432.00', `price_per_meter` = '2992.00' WHERE `id` = 92;
-- id=93
UPDATE `products` SET `price_1_4` = '46552.00', `price_5_9` = '46552.00', `price_10_19` = '46552.00', `price_20_plus` = '46552.00', `price_per_meter` = '880.00' WHERE `id` = 93;
-- id=94
UPDATE `products` SET `price_1_4` = '49632.00', `price_5_9` = '44440.00', `price_10_19` = '39248.00', `price_20_plus` = '39248.00', `price_per_meter` = '3872.00' WHERE `id` = 94;
-- id=95
UPDATE `products` SET `price_1_4` = '53504.00', `price_5_9` = '47872.00', `price_10_19` = '42240.00', `price_20_plus` = '42240.00', `price_per_meter` = '4136.00' WHERE `id` = 95;
-- id=96
UPDATE `products` SET `price_1_4` = '61160.00', `price_5_9` = '54736.00', `price_10_19` = '48312.00', `price_20_plus` = '48312.00', `price_per_meter` = '4752.00' WHERE `id` = 96;
-- id=97
UPDATE `products` SET `price_1_4` = '52184.00', `price_5_9` = '46640.00', `price_10_19` = '41184.00', `price_20_plus` = '41184.00', `price_per_meter` = '4048.00' WHERE `id` = 97;
-- id=98
UPDATE `products` SET `price_1_4` = '52184.00', `price_5_9` = '46640.00', `price_10_19` = '41184.00', `price_20_plus` = '41184.00', `price_per_meter` = '4048.00' WHERE `id` = 98;
-- id=99
UPDATE `products` SET `price_1_4` = '56848.00', `price_5_9` = '50864.00', `price_10_19` = '44880.00', `price_20_plus` = '44880.00', `price_per_meter` = '4400.00' WHERE `id` = 99;
-- id=100
UPDATE `products` SET `price_1_4` = '58520.00', `price_5_9` = '52360.00', `price_10_19` = '46200.00', `price_20_plus` = '46200.00', `price_per_meter` = '4488.00' WHERE `id` = 100;
-- id=101
UPDATE `products` SET `price_1_4` = '62656.00', `price_5_9` = '58784.00', `price_10_19` = '5104.00', `price_20_plus` = '5104.00', `price_per_meter` = '5192.00' WHERE `id` = 101;
-- id=102
UPDATE `products` SET `price_1_4` = '77704.00', `price_5_9` = '72864.00', `price_10_19` = '352.00', `price_20_plus` = '352.00', `price_per_meter` = '6512.00' WHERE `id` = 102;
-- id=103
UPDATE `products` SET `price_1_4` = '48048.00', `price_5_9` = '45056.00', `price_10_19` = '1848.00', `price_20_plus` = '1848.00', `price_per_meter` = '3960.00' WHERE `id` = 103;
-- id=104
UPDATE `products` SET `price_1_4` = '50160.00', `price_5_9` = '46992.00', `price_10_19` = '968.00', `price_20_plus` = '968.00', `price_per_meter` = '4136.00' WHERE `id` = 104;
-- id=105
UPDATE `products` SET `price_1_4` = '60620.00', `price_5_9` = '56831.25', `price_10_19` = '56831.25', `price_20_plus` = '56831.25', `price_per_meter` = '50516.67' WHERE `id` = 105;
-- id=106
UPDATE `products` SET `price_1_4` = '60620.00', `price_5_9` = '56831.25', `price_10_19` = '56831.25', `price_20_plus` = '56831.25', `price_per_meter` = '50516.67' WHERE `id` = 106;
-- id=107
UPDATE `products` SET `price_1_4` = '20416.00', `price_5_9` = '19184.00', `price_10_19` = '264.00', `price_20_plus` = '264.00', `price_per_meter` = '1672.00' WHERE `id` = 107;
-- id=108
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 108;
-- id=109
UPDATE `products` SET `price_per_meter` = '88.00' WHERE `id` = 109;
-- id=110
UPDATE `products` SET `price_per_meter` = '88.00' WHERE `id` = 110;
-- id=111
UPDATE `products` SET `price_per_meter` = '88.00' WHERE `id` = 111;
-- id=112
UPDATE `products` SET `price_per_meter` = '88.00' WHERE `id` = 112;
-- id=113
UPDATE `products` SET `price_per_meter` = '88.00' WHERE `id` = 113;
-- id=114
UPDATE `products` SET `price_per_meter` = '176.00' WHERE `id` = 114;
-- id=115
UPDATE `products` SET `price_per_meter` = '1232.00' WHERE `id` = 115;
-- id=116
UPDATE `products` SET `price_1_4` = '52800.00', `price_5_9` = '52800.00', `price_10_19` = '44000.00', `price_20_plus` = '44000.00' WHERE `id` = 116;
-- id=117
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '70400.00', `price_10_19` = '61600.00', `price_20_plus` = '61600.00' WHERE `id` = 117;
-- id=118
UPDATE `products` SET `price_1_4` = '74800.00', `price_5_9` = '74800.00', `price_10_19` = '66000.00', `price_20_plus` = '66000.00' WHERE `id` = 118;
-- id=119
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '70400.00', `price_10_19` = '61600.00', `price_20_plus` = '61600.00' WHERE `id` = 119;
-- id=120
UPDATE `products` SET `price_1_4` = '49632.00', `price_5_9` = '44440.00', `price_10_19` = '39248.00', `price_20_plus` = '39248.00', `price_per_meter` = '3872.00' WHERE `id` = 120;
-- id=121
UPDATE `products` SET `price_1_4` = '53504.00', `price_5_9` = '47872.00', `price_10_19` = '42240.00', `price_20_plus` = '42240.00', `price_per_meter` = '4136.00' WHERE `id` = 121;
-- id=122
UPDATE `products` SET `price_1_4` = '52184.00', `price_5_9` = '46640.00', `price_10_19` = '41184.00', `price_20_plus` = '41184.00', `price_per_meter` = '4048.00' WHERE `id` = 122;
-- id=123
UPDATE `products` SET `price_1_4` = '35640.00', `price_5_9` = '31856.00', `price_10_19` = '28160.00', `price_20_plus` = '28160.00', `price_per_meter` = '2728.00' WHERE `id` = 123;
-- id=124
UPDATE `products` SET `price_1_4` = '72776.00', `price_5_9` = '65120.00', `price_10_19` = '57464.00', `price_20_plus` = '57464.00', `price_per_meter` = '5632.00' WHERE `id` = 124;
-- id=125
UPDATE `products` SET `price_1_4` = '72776.00', `price_5_9` = '65120.00', `price_10_19` = '57464.00', `price_20_plus` = '57464.00', `price_per_meter` = '5632.00' WHERE `id` = 125;
-- id=126
UPDATE `products` SET `price_1_4` = '72776.00', `price_5_9` = '65120.00', `price_10_19` = '57464.00', `price_20_plus` = '57464.00', `price_per_meter` = '5632.00' WHERE `id` = 126;
-- id=127
UPDATE `products` SET `price_1_4` = '45848.00', `price_5_9` = '41008.00', `price_10_19` = '36168.00', `price_20_plus` = '36168.00', `price_per_meter` = '3520.00' WHERE `id` = 127;
-- id=128
UPDATE `products` SET `price_1_4` = '26752.00', `price_5_9` = '23936.00', `price_10_19` = '21120.00', `price_20_plus` = '21120.00', `price_per_meter` = '2024.00' WHERE `id` = 128;
-- id=129
UPDATE `products` SET `price_1_4` = '26752.00', `price_5_9` = '23936.00', `price_10_19` = '21120.00', `price_20_plus` = '21120.00', `price_per_meter` = '2024.00' WHERE `id` = 129;
-- id=130
UPDATE `products` SET `price_1_4` = '26752.00', `price_5_9` = '23936.00', `price_10_19` = '21120.00', `price_20_plus` = '21120.00', `price_per_meter` = '2024.00' WHERE `id` = 130;
-- id=131
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '26400.00', `price_20_plus` = '26400.00', `price_per_meter` = '17600.00' WHERE `id` = 131;
-- id=132
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '26400.00', `price_20_plus` = '26400.00', `price_per_meter` = '17600.00' WHERE `id` = 132;
-- id=133
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 133;
-- id=134
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 134;
-- id=135
UPDATE `products` SET `price_1_4` = '147400.00', `price_5_9` = '135608.00', `price_10_19` = '4400.00', `price_20_plus` = '4400.00', `price_per_meter` = '11000.00' WHERE `id` = 135;
-- id=136
UPDATE `products` SET `price_1_4` = '5104.00', `price_5_9` = '5104.00', `price_10_19` = '12760.00', `price_20_plus` = '12760.00', `price_per_meter` = '5544.00' WHERE `id` = 136;
-- id=137
UPDATE `products` SET `price_1_4` = '184800.00', `price_5_9` = '170016.00', `price_10_19` = '264.00', `price_20_plus` = '264.00', `price_per_meter` = '13816.00' WHERE `id` = 137;
-- id=138
UPDATE `products` SET `price_1_4` = '169400.00', `price_5_9` = '155848.00', `price_10_19` = '3344.00', `price_20_plus` = '3344.00', `price_per_meter` = '12672.00' WHERE `id` = 138;
-- id=139
UPDATE `products` SET `price_1_4` = '234520.00', `price_5_9` = '215776.00', `price_10_19` = '215776.00', `price_20_plus` = '215776.00', `price_per_meter` = '17512.00' WHERE `id` = 139;
-- id=140
UPDATE `products` SET `price_1_4` = '184800.00', `price_5_9` = '170016.00', `price_10_19` = '352.00', `price_20_plus` = '352.00', `price_per_meter` = '13816.00' WHERE `id` = 140;
-- id=142
UPDATE `products` SET `price_1_4` = '147400.00', `price_5_9` = '135608.00', `price_10_19` = '176.00', `price_20_plus` = '176.00', `price_per_meter` = '11000.00' WHERE `id` = 142;
-- id=143
UPDATE `products` SET `price_1_4` = '192808.00', `price_5_9` = '177408.00', `price_10_19` = '352.00', `price_20_plus` = '352.00', `price_per_meter` = '14432.00' WHERE `id` = 143;
-- id=144
UPDATE `products` SET `price_1_4` = '109736.00', `price_5_9` = '101904.00', `price_10_19` = '101904.00', `price_20_plus` = '101904.00', `price_per_meter` = '6512.00' WHERE `id` = 144;
-- id=145
UPDATE `products` SET `price_1_4` = '97680.00', `price_5_9` = '90728.00', `price_10_19` = '90728.00', `price_20_plus` = '90728.00', `price_per_meter` = '7656.00' WHERE `id` = 145;
-- id=146
UPDATE `products` SET `price_1_4` = '120384.00', `price_5_9` = '111760.00', `price_10_19` = '111760.00', `price_20_plus` = '111760.00', `price_per_meter` = '7128.00' WHERE `id` = 146;
-- id=147
UPDATE `products` SET `price_1_4` = '191576.00', `price_5_9` = '177936.00', `price_10_19` = '177936.00', `price_20_plus` = '177936.00', `price_per_meter` = '15048.00' WHERE `id` = 147;
-- id=148
UPDATE `products` SET `price_1_4` = '163240.00', `price_5_9` = '151624.00', `price_10_19` = '151624.00', `price_20_plus` = '151624.00', `price_per_meter` = '9592.00' WHERE `id` = 148;
-- id=149
UPDATE `products` SET `price_1_4` = '65208.00', `price_5_9` = '65208.00', `price_10_19` = '65208.00', `price_20_plus` = '65208.00', `price_per_meter` = '3432.00' WHERE `id` = 149;
-- id=150
UPDATE `products` SET `price_1_4` = '123288.00', `price_5_9` = '123288.00', `price_10_19` = '123288.00', `price_20_plus` = '123288.00', `price_per_meter` = '6512.00' WHERE `id` = 150;
-- id=151
UPDATE `products` SET `price_1_4` = '173888.00', `price_5_9` = '173888.00', `price_10_19` = '173888.00', `price_20_plus` = '173888.00', `price_per_meter` = '9152.00' WHERE `id` = 151;
-- id=152
UPDATE `products` SET `price_1_4` = '286000.00', `price_5_9` = '286000.00', `price_10_19` = '286000.00', `price_20_plus` = '286000.00', `price_per_meter` = '15136.00' WHERE `id` = 152;
-- id=153
UPDATE `products` SET `price_1_4` = '52272.00', `price_5_9` = '352.00', `price_10_19` = '352.00', `price_20_plus` = '352.00', `price_per_meter` = '2728.00' WHERE `id` = 153;
-- id=154
UPDATE `products` SET `price_1_4` = '53240.00', `price_5_9` = '53240.00', `price_10_19` = '53240.00', `price_20_plus` = '53240.00', `price_per_meter` = '2816.00' WHERE `id` = 154;
-- id=155
UPDATE `products` SET `price_1_4` = '53240.00', `price_5_9` = '53240.00', `price_10_19` = '53240.00', `price_20_plus` = '53240.00', `price_per_meter` = '2816.00' WHERE `id` = 155;
-- id=156
UPDATE `products` SET `price_1_4` = '49720.00', `price_5_9` = '49720.00', `price_10_19` = '49720.00', `price_20_plus` = '49720.00', `price_per_meter` = '2200.00' WHERE `id` = 156;
-- id=157
UPDATE `products` SET `price_1_4` = '109736.00', `price_5_9` = '101904.00', `price_10_19` = '101904.00', `price_20_plus` = '101904.00', `price_per_meter` = '6512.00' WHERE `id` = 157;
-- id=158
UPDATE `products` SET `price_1_4` = '97680.00', `price_5_9` = '90728.00', `price_10_19` = '90728.00', `price_20_plus` = '90728.00', `price_per_meter` = '7656.00' WHERE `id` = 158;
-- id=159
UPDATE `products` SET `price_1_4` = '120384.00', `price_5_9` = '111760.00', `price_10_19` = '111760.00', `price_20_plus` = '111760.00', `price_per_meter` = '7128.00' WHERE `id` = 159;
-- id=160
UPDATE `products` SET `price_1_4` = '191576.00', `price_5_9` = '177936.00', `price_10_19` = '177936.00', `price_20_plus` = '177936.00', `price_per_meter` = '15048.00' WHERE `id` = 160;
-- id=161
UPDATE `products` SET `price_1_4` = '163240.00', `price_5_9` = '151624.00', `price_10_19` = '151624.00', `price_20_plus` = '151624.00', `price_per_meter` = '9592.00' WHERE `id` = 161;
-- id=162
UPDATE `products` SET `price_1_4` = '674.63', `price_5_9` = '674.63', `price_20_plus` = '674.63', `price_per_meter` = '674.63' WHERE `id` = 162;
-- id=163
UPDATE `products` SET `price_1_4` = '1350.13', `price_5_9` = '1350.13', `price_20_plus` = '1350.13', `price_per_meter` = '1350.13' WHERE `id` = 163;
-- id=164
UPDATE `products` SET `price_1_4` = '780.50', `price_5_9` = '780.50', `price_20_plus` = '780.50', `price_per_meter` = '780.50' WHERE `id` = 164;
-- id=165
UPDATE `products` SET `price_1_4` = '254.63', `price_5_9` = '254.63', `price_20_plus` = '254.63', `price_per_meter` = '254.63' WHERE `id` = 165;
-- id=166
UPDATE `products` SET `price_1_4` = '11880.00', `price_5_9` = '11880.00', `price_10_19` = '11880.00', `price_20_plus` = '11880.00', `price_per_meter` = '1232.00' WHERE `id` = 166;
-- id=167
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '60720.00', `price_10_19` = '60720.00', `price_20_plus` = '60720.00', `price_per_meter` = '1760.00' WHERE `id` = 167;
-- id=168
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '60720.00', `price_10_19` = '60720.00', `price_20_plus` = '60720.00', `price_per_meter` = '1760.00' WHERE `id` = 168;
-- id=169
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '60720.00', `price_10_19` = '60720.00', `price_20_plus` = '60720.00', `price_per_meter` = '1760.00' WHERE `id` = 169;
-- id=170
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '60720.00', `price_10_19` = '60720.00', `price_20_plus` = '60720.00', `price_per_meter` = '1760.00' WHERE `id` = 170;
-- id=171
UPDATE `products` SET `price_1_4` = '25375.00', `price_5_9` = '25375.00', `price_20_plus` = '26250.00', `price_per_meter` = '25375.00' WHERE `id` = 171;
-- id=172
UPDATE `products` SET `price_1_4` = '437.50', `price_5_9` = '0.00', `price_20_plus` = '17500.00', `price_per_meter` = '0.00' WHERE `id` = 172;
-- id=173
UPDATE `products` SET `price_1_4` = '66000.00', `price_5_9` = '66000.00', `price_10_19` = '66000.00', `price_20_plus` = '66000.00', `price_per_meter` = '66000.00' WHERE `id` = 173;
-- id=174
UPDATE `products` SET `price_1_4` = '79200.00', `price_5_9` = '79200.00', `price_10_19` = '79200.00', `price_20_plus` = '79200.00', `price_per_meter` = '79200.00' WHERE `id` = 174;
-- id=175
UPDATE `products` SET `price_1_4` = '352.00', `price_5_9` = '352.00', `price_10_19` = '352.00', `price_20_plus` = '352.00', `price_per_meter` = '35200.00' WHERE `id` = 175;
-- id=176
UPDATE `products` SET `price_per_meter` = '26400.00' WHERE `id` = 176;
-- id=177
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '35200.00', `price_10_19` = '35200.00', `price_20_plus` = '35200.00', `price_per_meter` = '35200.00' WHERE `id` = 177;
-- id=178
UPDATE `products` SET `price_1_4` = '26400.00', `price_5_9` = '26400.00', `price_10_19` = '26400.00', `price_20_plus` = '26400.00', `price_per_meter` = '26400.00' WHERE `id` = 178;
-- id=179
UPDATE `products` SET `price_1_4` = '13200.00', `price_5_9` = '13200.00', `price_10_19` = '13200.00', `price_20_plus` = '13200.00', `price_per_meter` = '13200.00' WHERE `id` = 179;
-- id=180
UPDATE `products` SET `price_1_4` = '57200.00', `price_5_9` = '57200.00', `price_10_19` = '57200.00', `price_20_plus` = '57200.00', `price_per_meter` = '57200.00' WHERE `id` = 180;
-- id=181
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '17600.00', `price_10_19` = '17600.00', `price_20_plus` = '17600.00' WHERE `id` = 181;
-- id=182
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '17600.00', `price_10_19` = '17600.00', `price_20_plus` = '17600.00' WHERE `id` = 182;
-- id=183
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '17600.00', `price_10_19` = '17600.00', `price_20_plus` = '17600.00' WHERE `id` = 183;
-- id=184
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 184;
-- id=185
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '17600.00', `price_10_19` = '17600.00', `price_20_plus` = '17600.00' WHERE `id` = 185;
-- id=186
UPDATE `products` SET `price_1_4` = '176.00', `price_5_9` = '176.00', `price_10_19` = '176.00', `price_20_plus` = '176.00', `price_per_meter` = '52800.00' WHERE `id` = 186;
-- id=187
UPDATE `products` SET `price_1_4` = '176.00', `price_5_9` = '176.00', `price_10_19` = '176.00', `price_20_plus` = '176.00', `price_per_meter` = '52800.00' WHERE `id` = 187;
-- id=188
UPDATE `products` SET `price_1_4` = '13200.00', `price_5_9` = '13200.00', `price_10_19` = '13200.00', `price_20_plus` = '13200.00' WHERE `id` = 188;
-- id=189
UPDATE `products` SET `price_1_4` = '13200.00', `price_5_9` = '13200.00', `price_10_19` = '13200.00', `price_20_plus` = '13200.00' WHERE `id` = 189;
-- id=190
UPDATE `products` SET `price_1_4` = '13200.00', `price_5_9` = '13200.00', `price_10_19` = '13200.00', `price_20_plus` = '13200.00' WHERE `id` = 190;
-- id=191
UPDATE `products` SET `price_1_4` = '9064.00', `price_5_9` = '7480.00', `price_10_19` = '3872.00', `price_20_plus` = '3872.00', `price_per_meter` = '528.00' WHERE `id` = 191;
-- id=192
UPDATE `products` SET `price_1_4` = '12320.00', `price_5_9` = '10120.00', `price_10_19` = '5280.00', `price_20_plus` = '5280.00', `price_per_meter` = '704.00' WHERE `id` = 192;
-- id=193
UPDATE `products` SET `price_1_4` = '22968.00', `price_5_9` = '18920.00', `price_10_19` = '9856.00', `price_20_plus` = '9856.00', `price_per_meter` = '1408.00' WHERE `id` = 193;
-- id=194
UPDATE `products` SET `price_1_4` = '35288.00', `price_5_9` = '29040.00', `price_10_19` = '15136.00', `price_20_plus` = '15136.00', `price_per_meter` = '2112.00' WHERE `id` = 194;
-- id=195
UPDATE `products` SET `price_1_4` = '21208.00', `price_5_9` = '17424.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '1232.00' WHERE `id` = 195;
-- id=196
UPDATE `products` SET `price_1_4` = '28248.00', `price_5_9` = '23144.00', `price_10_19` = '12056.00', `price_20_plus` = '12056.00', `price_per_meter` = '1672.00' WHERE `id` = 196;
-- id=197
UPDATE `products` SET `price_1_4` = '41800.00', `price_5_9` = '34320.00', `price_10_19` = '17952.00', `price_20_plus` = '17952.00', `price_per_meter` = '2464.00' WHERE `id` = 197;
-- id=198
UPDATE `products` SET `price_1_4` = '22264.00', `price_5_9` = '18216.00', `price_10_19` = '13464.00', `price_20_plus` = '13464.00', `price_per_meter` = '1320.00' WHERE `id` = 198;
-- id=199
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8272.00', `price_10_19` = '6072.00', `price_20_plus` = '6072.00', `price_per_meter` = '616.00' WHERE `id` = 199;
-- id=200
UPDATE `products` SET `price_1_4` = '11352.00', `price_5_9` = '9328.00', `price_10_19` = '6952.00', `price_20_plus` = '6952.00', `price_per_meter` = '704.00' WHERE `id` = 200;
-- id=201
UPDATE `products` SET `price_1_4` = '12760.00', `price_5_9` = '10472.00', `price_10_19` = '7744.00', `price_20_plus` = '7744.00', `price_per_meter` = '792.00' WHERE `id` = 201;
-- id=202
UPDATE `products` SET `price_1_4` = '18128.00', `price_5_9` = '14960.00', `price_10_19` = '11000.00', `price_20_plus` = '11000.00', `price_per_meter` = '1056.00' WHERE `id` = 202;
-- id=203
UPDATE `products` SET `price_1_4` = '25784.00', `price_5_9` = '21120.00', `price_10_19` = '15664.00', `price_20_plus` = '15664.00', `price_per_meter` = '1496.00' WHERE `id` = 203;
-- id=204
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '28952.00', `price_10_19` = '21384.00', `price_20_plus` = '21384.00', `price_per_meter` = '2112.00' WHERE `id` = 204;
-- id=205
UPDATE `products` SET `price_1_4` = '48752.00', `price_5_9` = '40040.00', `price_10_19` = '29656.00', `price_20_plus` = '29656.00', `price_per_meter` = '2904.00' WHERE `id` = 205;
-- id=206
UPDATE `products` SET `price_1_4` = '32560.00', `price_5_9` = '26752.00', `price_10_19` = '19712.00', `price_20_plus` = '19712.00', `price_per_meter` = '1936.00' WHERE `id` = 206;
-- id=207
UPDATE `products` SET `price_1_4` = '29832.00', `price_5_9` = '24464.00', `price_10_19` = '18128.00', `price_20_plus` = '18128.00', `price_per_meter` = '1760.00' WHERE `id` = 207;
-- id=208
UPDATE `products` SET `price_1_4` = '46112.00', `price_5_9` = '37840.00', `price_10_19` = '27984.00', `price_20_plus` = '27984.00', `price_per_meter` = '2728.00' WHERE `id` = 208;
-- id=209
UPDATE `products` SET `price_1_4` = '57464.00', `price_5_9` = '47168.00', `price_10_19` = '34848.00', `price_20_plus` = '34848.00', `price_per_meter` = '3432.00' WHERE `id` = 209;
-- id=210
UPDATE `products` SET `price_1_4` = '57464.00', `price_5_9` = '47168.00', `price_10_19` = '34848.00', `price_20_plus` = '34848.00', `price_per_meter` = '3432.00' WHERE `id` = 210;
-- id=211
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 211;
-- id=212
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 212;
-- id=213
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 213;
-- id=214
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 214;
-- id=215
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 215;
-- id=216
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 216;
-- id=217
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 217;
-- id=218
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 218;
-- id=219
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 219;
-- id=220
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 220;
-- id=221
UPDATE `products` SET `price_1_4` = '54208.00', `price_5_9` = '44528.00', `price_10_19` = '32912.00', `price_20_plus` = '32912.00', `price_per_meter` = '3256.00' WHERE `id` = 221;
-- id=222
UPDATE `products` SET `price_1_4` = '20328.00', `price_5_9` = '16720.00', `price_10_19` = '12320.00', `price_20_plus` = '12320.00', `price_per_meter` = '1232.00' WHERE `id` = 222;
-- id=223
UPDATE `products` SET `price_1_4` = '19536.00', `price_5_9` = '16016.00', `price_10_19` = '11880.00', `price_20_plus` = '11880.00', `price_per_meter` = '1144.00' WHERE `id` = 223;
-- id=224
UPDATE `products` SET `price_1_4` = '14080.00', `price_5_9` = '11616.00', `price_10_19` = '8536.00', `price_20_plus` = '8536.00', `price_per_meter` = '880.00' WHERE `id` = 224;
-- id=225
UPDATE `products` SET `price_1_4` = '27632.00', `price_5_9` = '22704.00', `price_10_19` = '16808.00', `price_20_plus` = '16808.00', `price_per_meter` = '792.00' WHERE `id` = 225;
-- id=226
UPDATE `products` SET `price_1_4` = '17072.00', `price_5_9` = '13992.00', `price_10_19` = '10384.00', `price_20_plus` = '10384.00', `price_per_meter` = '1056.00' WHERE `id` = 226;
-- id=227
UPDATE `products` SET `price_1_4` = '33088.00', `price_5_9` = '27192.00', `price_10_19` = '20064.00', `price_20_plus` = '20064.00', `price_per_meter` = '968.00' WHERE `id` = 227;
-- id=228
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 228;
-- id=229
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '14432.00', `price_10_19` = '10736.00', `price_20_plus` = '10736.00', `price_per_meter` = '1056.00' WHERE `id` = 229;
-- id=230
UPDATE `products` SET `price_1_4` = '34672.00', `price_5_9` = '28512.00', `price_10_19` = '21032.00', `price_20_plus` = '21032.00', `price_per_meter` = '1056.00' WHERE `id` = 230;
-- id=231
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 231;
-- id=232
UPDATE `products` SET `price_1_4` = '27104.00', `price_5_9` = '22264.00', `price_10_19` = '16456.00', `price_20_plus` = '16456.00', `price_per_meter` = '1584.00' WHERE `id` = 232;
-- id=233
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 233;
-- id=234
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '14432.00', `price_10_19` = '10736.00', `price_20_plus` = '10736.00', `price_per_meter` = '1056.00' WHERE `id` = 234;
-- id=235
UPDATE `products` SET `price_1_4` = '34672.00', `price_5_9` = '28512.00', `price_10_19` = '21032.00', `price_20_plus` = '21032.00', `price_per_meter` = '1056.00' WHERE `id` = 235;
-- id=236
UPDATE `products` SET `price_1_4` = '22264.00', `price_5_9` = '18216.00', `price_10_19` = '13464.00', `price_20_plus` = '13464.00', `price_per_meter` = '1320.00' WHERE `id` = 236;
-- id=237
UPDATE `products` SET `price_1_4` = '14080.00', `price_5_9` = '11616.00', `price_10_19` = '8536.00', `price_20_plus` = '8536.00', `price_per_meter` = '880.00' WHERE `id` = 237;
-- id=238
UPDATE `products` SET `price_1_4` = '27632.00', `price_5_9` = '22704.00', `price_10_19` = '16808.00', `price_20_plus` = '16808.00', `price_per_meter` = '792.00' WHERE `id` = 238;
-- id=239
UPDATE `products` SET `price_1_4` = '17072.00', `price_5_9` = '13992.00', `price_10_19` = '10384.00', `price_20_plus` = '10384.00', `price_per_meter` = '1056.00' WHERE `id` = 239;
-- id=240
UPDATE `products` SET `price_1_4` = '17072.00', `price_5_9` = '13992.00', `price_10_19` = '10384.00', `price_20_plus` = '10384.00', `price_per_meter` = '1056.00' WHERE `id` = 240;
-- id=241
UPDATE `products` SET `price_1_4` = '33088.00', `price_5_9` = '27192.00', `price_10_19` = '20064.00', `price_20_plus` = '20064.00', `price_per_meter` = '968.00' WHERE `id` = 241;
-- id=242
UPDATE `products` SET `price_1_4` = '14080.00', `price_5_9` = '11616.00', `price_10_19` = '8536.00', `price_20_plus` = '8536.00', `price_per_meter` = '880.00' WHERE `id` = 242;
-- id=243
UPDATE `products` SET `price_1_4` = '17072.00', `price_5_9` = '13992.00', `price_10_19` = '10384.00', `price_20_plus` = '10384.00', `price_per_meter` = '1056.00' WHERE `id` = 243;
-- id=244
UPDATE `products` SET `price_1_4` = '14080.00', `price_5_9` = '11616.00', `price_10_19` = '8536.00', `price_20_plus` = '8536.00', `price_per_meter` = '880.00' WHERE `id` = 244;
-- id=245
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 245;
-- id=246
UPDATE `products` SET `price_1_4` = '12232.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '14872.00' WHERE `id` = 246;
-- id=247
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 247;
-- id=248
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 248;
-- id=249
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '440.00' WHERE `id` = 249;
-- id=250
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 250;
-- id=251
UPDATE `products` SET `price_1_4` = '19536.00', `price_5_9` = '16016.00', `price_10_19` = '11880.00', `price_20_plus` = '11880.00', `price_per_meter` = '1144.00' WHERE `id` = 251;
-- id=252
UPDATE `products` SET `price_1_4` = '16808.00', `price_5_9` = '13816.00', `price_10_19` = '10208.00', `price_20_plus` = '10208.00', `price_per_meter` = '968.00' WHERE `id` = 252;
-- id=253
UPDATE `products` SET `price_1_4` = '16808.00', `price_5_9` = '13816.00', `price_10_19` = '10208.00', `price_20_plus` = '10208.00', `price_per_meter` = '968.00' WHERE `id` = 253;
-- id=254
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 254;
-- id=255
UPDATE `products` SET `price_1_4` = '25784.00', `price_5_9` = '21120.00', `price_10_19` = '15664.00', `price_20_plus` = '15664.00', `price_per_meter` = '792.00' WHERE `id` = 255;
-- id=256
UPDATE `products` SET `price_1_4` = '25784.00', `price_5_9` = '21120.00', `price_10_19` = '15664.00', `price_20_plus` = '15664.00', `price_per_meter` = '792.00' WHERE `id` = 256;
-- id=257
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 257;
-- id=258
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00', `price_per_meter` = '880.00' WHERE `id` = 258;
-- id=259
UPDATE `products` SET `price_1_4` = '21120.00', `price_5_9` = '17336.00', `price_10_19` = '12848.00', `price_20_plus` = '12848.00', `price_per_meter` = '616.00' WHERE `id` = 259;
-- id=260
UPDATE `products` SET `price_1_4` = '11352.00', `price_5_9` = '9328.00', `price_10_19` = '6952.00', `price_20_plus` = '6952.00', `price_per_meter` = '704.00' WHERE `id` = 260;
-- id=261
UPDATE `products` SET `price_1_4` = '18216.00', `price_5_9` = '18216.00', `price_10_19` = '13464.00', `price_20_plus` = '13464.00', `price_per_meter` = '22264.00' WHERE `id` = 261;
-- id=262
UPDATE `products` SET `price_1_4` = '14080.00', `price_5_9` = '11616.00', `price_10_19` = '8536.00', `price_20_plus` = '8536.00', `price_per_meter` = '880.00' WHERE `id` = 262;
-- id=263
UPDATE `products` SET `price_1_4` = '22704.00', `price_5_9` = '22704.00', `price_10_19` = '16808.00', `price_20_plus` = '16808.00', `price_per_meter` = '27632.00' WHERE `id` = 263;
-- id=264
UPDATE `products` SET `price_1_4` = '15488.00', `price_5_9` = '12672.00', `price_10_19` = '9416.00', `price_20_plus` = '9416.00', `price_per_meter` = '880.00' WHERE `id` = 264;
-- id=265
UPDATE `products` SET `price_1_4` = '24904.00', `price_5_9` = '24904.00', `price_10_19` = '18392.00', `price_20_plus` = '18392.00', `price_per_meter` = '30360.00' WHERE `id` = 265;
-- id=266
UPDATE `products` SET `price_1_4` = '15488.00', `price_5_9` = '12672.00', `price_10_19` = '9416.00', `price_20_plus` = '9416.00', `price_per_meter` = '880.00' WHERE `id` = 266;
-- id=267
UPDATE `products` SET `price_1_4` = '24904.00', `price_5_9` = '24904.00', `price_10_19` = '18392.00', `price_20_plus` = '18392.00', `price_per_meter` = '30360.00' WHERE `id` = 267;
-- id=268
UPDATE `products` SET `price_1_4` = '15488.00', `price_5_9` = '12672.00', `price_10_19` = '9416.00', `price_20_plus` = '9416.00', `price_per_meter` = '880.00' WHERE `id` = 268;
-- id=269
UPDATE `products` SET `price_1_4` = '24904.00', `price_5_9` = '24904.00', `price_10_19` = '18392.00', `price_20_plus` = '18392.00', `price_per_meter` = '30360.00' WHERE `id` = 269;
-- id=270
UPDATE `products` SET `price_1_4` = '15488.00', `price_5_9` = '12672.00', `price_10_19` = '9416.00', `price_20_plus` = '9416.00', `price_per_meter` = '880.00' WHERE `id` = 270;
-- id=271
UPDATE `products` SET `price_1_4` = '24904.00', `price_5_9` = '24904.00', `price_10_19` = '18392.00', `price_20_plus` = '18392.00', `price_per_meter` = '30360.00' WHERE `id` = 271;
-- id=272
UPDATE `products` SET `price_1_4` = '15488.00', `price_5_9` = '12672.00', `price_10_19` = '9416.00', `price_20_plus` = '9416.00', `price_per_meter` = '880.00' WHERE `id` = 272;
-- id=273
UPDATE `products` SET `price_1_4` = '19536.00', `price_5_9` = '16016.00', `price_10_19` = '11880.00', `price_20_plus` = '11880.00', `price_per_meter` = '1144.00' WHERE `id` = 273;
-- id=274
UPDATE `products` SET `price_1_4` = '19536.00', `price_5_9` = '16016.00', `price_10_19` = '11880.00', `price_20_plus` = '11880.00', `price_per_meter` = '1144.00' WHERE `id` = 274;
-- id=275
UPDATE `products` SET `price_1_4` = '88.00', `price_5_9` = '88.00', `price_10_19` = '88.00', `price_20_plus` = '88.00' WHERE `id` = 275;
-- id=276
UPDATE `products` SET `price_1_4` = '88.00', `price_5_9` = '88.00', `price_10_19` = '88.00', `price_20_plus` = '88.00' WHERE `id` = 276;
-- id=278
UPDATE `products` SET `price_1_4` = '176.00', `price_5_9` = '176.00', `price_10_19` = '176.00', `price_20_plus` = '176.00' WHERE `id` = 278;
-- id=279
UPDATE `products` SET `price_1_4` = '88.00', `price_5_9` = '88.00', `price_10_19` = '88.00', `price_20_plus` = '88.00' WHERE `id` = 279;
-- id=280
UPDATE `products` SET `price_1_4` = '88.00', `price_5_9` = '88.00', `price_10_19` = '88.00', `price_20_plus` = '88.00' WHERE `id` = 280;
-- id=281
UPDATE `products` SET `price_1_4` = '88.00', `price_5_9` = '88.00', `price_10_19` = '88.00', `price_20_plus` = '88.00' WHERE `id` = 281;
-- id=282
UPDATE `products` SET `price_1_4` = '352.00', `price_5_9` = '352.00', `price_10_19` = '352.00', `price_20_plus` = '352.00' WHERE `id` = 282;
-- id=283
UPDATE `products` SET `price_1_4` = '616.00', `price_5_9` = '616.00', `price_10_19` = '616.00', `price_20_plus` = '616.00' WHERE `id` = 283;
-- id=284
UPDATE `products` SET `price_1_4` = '176.00', `price_5_9` = '176.00', `price_10_19` = '176.00', `price_20_plus` = '176.00' WHERE `id` = 284;
-- id=285
UPDATE `products` SET `price_1_4` = '264.00', `price_5_9` = '264.00', `price_10_19` = '264.00', `price_20_plus` = '264.00' WHERE `id` = 285;
-- id=286
UPDATE `products` SET `price_1_4` = '88.00', `price_5_9` = '88.00', `price_10_19` = '88.00', `price_20_plus` = '88.00' WHERE `id` = 286;
-- id=287
UPDATE `products` SET `price_1_4` = '88.00', `price_5_9` = '88.00', `price_10_19` = '88.00', `price_20_plus` = '88.00' WHERE `id` = 287;
-- id=292 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '332.50', `price_5_9` = '332.50', `price_10_19` = '332.50', `price_20_plus` = '332.50', `price_per_meter` = '332.50' WHERE `id` = 292;
-- id=293 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '300.00', `price_5_9` = '300.00', `price_10_19` = '300.00', `price_20_plus` = '300.00', `price_per_meter` = '300.00' WHERE `id` = 293;
-- id=294 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '122.50', `price_5_9` = '122.50', `price_10_19` = '122.50', `price_20_plus` = '122.50', `price_per_meter` = '122.50' WHERE `id` = 294;
-- id=295 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '122.50', `price_5_9` = '122.50', `price_10_19` = '122.50', `price_20_plus` = '122.50', `price_per_meter` = '122.50' WHERE `id` = 295;
-- id=296 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '472.50', `price_5_9` = '472.50', `price_10_19` = '472.50', `price_20_plus` = '472.50', `price_per_meter` = '472.50' WHERE `id` = 296;
-- id=297 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '490.00', `price_5_9` = '490.00', `price_10_19` = '490.00', `price_20_plus` = '490.00', `price_per_meter` = '490.00' WHERE `id` = 297;
-- id=298 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '691.25', `price_5_9` = '691.25', `price_10_19` = '691.25', `price_20_plus` = '691.25', `price_per_meter` = '691.25' WHERE `id` = 298;
-- id=299 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '376.25', `price_5_9` = '376.25', `price_10_19` = '376.25', `price_20_plus` = '376.25', `price_per_meter` = '376.25' WHERE `id` = 299;
-- id=300 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '350.00', `price_5_9` = '350.00', `price_10_19` = '350.00', `price_20_plus` = '350.00', `price_per_meter` = '350.00' WHERE `id` = 300;
-- id=301 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '210.00', `price_5_9` = '210.00', `price_10_19` = '210.00', `price_20_plus` = '210.00', `price_per_meter` = '210.00' WHERE `id` = 301;
-- id=302 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '201.25', `price_5_9` = '201.25', `price_10_19` = '201.25', `price_20_plus` = '201.25', `price_per_meter` = '201.25' WHERE `id` = 302;
-- id=303 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '201.25', `price_5_9` = '201.25', `price_10_19` = '201.25', `price_20_plus` = '201.25', `price_per_meter` = '201.25' WHERE `id` = 303;
-- id=304 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '600.00', `price_5_9` = '600.00', `price_10_19` = '600.00', `price_20_plus` = '600.00', `price_per_meter` = '600.00' WHERE `id` = 304;
-- id=305 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '551.25', `price_5_9` = '551.25', `price_10_19` = '551.25', `price_20_plus` = '551.25', `price_per_meter` = '551.25' WHERE `id` = 305;
-- id=306 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '700.00', `price_5_9` = '700.00', `price_10_19` = '700.00', `price_20_plus` = '700.00', `price_per_meter` = '700.00' WHERE `id` = 306;
-- id=307 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '330.00', `price_5_9` = '330.00', `price_10_19` = '330.00', `price_20_plus` = '330.00', `price_per_meter` = '330.00' WHERE `id` = 307;
-- id=308 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '253.75', `price_5_9` = '253.75', `price_10_19` = '253.75', `price_20_plus` = '253.75', `price_per_meter` = '253.75' WHERE `id` = 308;
-- id=309 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '148.75', `price_5_9` = '148.75', `price_10_19` = '148.75', `price_20_plus` = '148.75', `price_per_meter` = '148.75' WHERE `id` = 309;
-- id=310 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '61.25', `price_5_9` = '61.25', `price_10_19` = '61.25', `price_20_plus` = '61.25', `price_per_meter` = '61.25' WHERE `id` = 310;
-- id=311 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '717.50', `price_5_9` = '717.50', `price_10_19` = '717.50', `price_20_plus` = '717.50', `price_per_meter` = '717.50' WHERE `id` = 311;
-- id=312 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '796.25', `price_5_9` = '796.25', `price_10_19` = '796.25', `price_20_plus` = '796.25', `price_per_meter` = '796.25' WHERE `id` = 312;
-- id=313 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '400.00', `price_5_9` = '400.00', `price_10_19` = '400.00', `price_20_plus` = '400.00', `price_per_meter` = '400.00' WHERE `id` = 313;
-- id=314 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26.25', `price_5_9` = '26.25', `price_10_19` = '26.25', `price_20_plus` = '26.25', `price_per_meter` = '26.25' WHERE `id` = 314;
-- id=315 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '52.50', `price_5_9` = '52.50', `price_10_19` = '52.50', `price_20_plus` = '52.50', `price_per_meter` = '52.50' WHERE `id` = 315;
-- id=316 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '105.00', `price_5_9` = '105.00', `price_10_19` = '105.00', `price_20_plus` = '105.00', `price_per_meter` = '105.00' WHERE `id` = 316;
-- id=317 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '70.00', `price_5_9` = '70.00', `price_10_19` = '70.00', `price_20_plus` = '70.00', `price_per_meter` = '70.00' WHERE `id` = 317;
-- id=318 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '200.00', `price_5_9` = '200.00', `price_10_19` = '200.00', `price_20_plus` = '200.00', `price_per_meter` = '200.00' WHERE `id` = 318;
-- id=319 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17841.25', `price_5_9` = '17841.25', `price_10_19` = '17841.25', `price_20_plus` = '17841.25', `price_per_meter` = '17841.25' WHERE `id` = 319;
-- id=320 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '735.00', `price_5_9` = '735.00', `price_10_19` = '735.00', `price_20_plus` = '735.00', `price_per_meter` = '735.00' WHERE `id` = 320;
-- id=321 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '971.25', `price_5_9` = '971.25', `price_10_19` = '971.25', `price_20_plus` = '971.25', `price_per_meter` = '971.25' WHERE `id` = 321;
-- id=322 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '2000.00', `price_5_9` = '2000.00', `price_10_19` = '2000.00', `price_20_plus` = '2000.00', `price_per_meter` = '2000.00' WHERE `id` = 322;
-- id=323 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3500.00', `price_5_9` = '3500.00', `price_10_19` = '3500.00', `price_20_plus` = '3500.00', `price_per_meter` = '3500.00' WHERE `id` = 323;
-- id=324 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '113.75', `price_5_9` = '113.75', `price_10_19` = '113.75', `price_20_plus` = '113.75', `price_per_meter` = '113.75' WHERE `id` = 324;
-- id=325 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '131.25', `price_5_9` = '131.25', `price_10_19` = '131.25', `price_20_plus` = '131.25', `price_per_meter` = '131.25' WHERE `id` = 325;
-- id=326 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '183.75', `price_5_9` = '183.75', `price_10_19` = '183.75', `price_20_plus` = '183.75', `price_per_meter` = '183.75' WHERE `id` = 326;
-- id=327 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '52.50', `price_5_9` = '52.50', `price_10_19` = '52.50', `price_20_plus` = '52.50', `price_per_meter` = '52.50' WHERE `id` = 327;
-- id=328 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '52.50', `price_5_9` = '52.50', `price_10_19` = '52.50', `price_20_plus` = '52.50', `price_per_meter` = '52.50' WHERE `id` = 328;
-- id=329 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1000.00', `price_5_9` = '1000.00', `price_10_19` = '1000.00', `price_20_plus` = '1000.00', `price_per_meter` = '1000.00' WHERE `id` = 329;
-- id=330 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5235125.00', `price_5_9` = '5235125.00', `price_10_19` = '5235125.00', `price_20_plus` = '5235125.00', `price_per_meter` = '5235125.00' WHERE `id` = 330;
-- id=331 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '525.00', `price_5_9` = '525.00', `price_10_19` = '525.00', `price_20_plus` = '525.00', `price_per_meter` = '525.00' WHERE `id` = 331;
-- id=332 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '96.25', `price_5_9` = '96.25', `price_10_19` = '96.25', `price_20_plus` = '96.25', `price_per_meter` = '96.25' WHERE `id` = 332;
-- id=333 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '175.00', `price_5_9` = '175.00', `price_10_19` = '175.00', `price_20_plus` = '175.00', `price_per_meter` = '175.00' WHERE `id` = 333;
-- id=334 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '131.25', `price_5_9` = '131.25', `price_10_19` = '131.25', `price_20_plus` = '131.25', `price_per_meter` = '131.25' WHERE `id` = 334;
-- id=335 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '201.25', `price_5_9` = '201.25', `price_10_19` = '201.25', `price_20_plus` = '201.25', `price_per_meter` = '201.25' WHERE `id` = 335;
-- id=336 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '192.50', `price_5_9` = '192.50', `price_10_19` = '192.50', `price_20_plus` = '192.50', `price_per_meter` = '192.50' WHERE `id` = 336;
-- id=337 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '166.25', `price_5_9` = '166.25', `price_10_19` = '166.25', `price_20_plus` = '166.25', `price_per_meter` = '166.25' WHERE `id` = 337;
-- id=338 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '183.75', `price_5_9` = '183.75', `price_10_19` = '183.75', `price_20_plus` = '183.75', `price_per_meter` = '183.75' WHERE `id` = 338;
-- id=339 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '183.75', `price_5_9` = '183.75', `price_10_19` = '183.75', `price_20_plus` = '183.75', `price_per_meter` = '183.75' WHERE `id` = 339;
-- id=340 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '183.75', `price_5_9` = '183.75', `price_10_19` = '183.75', `price_20_plus` = '183.75', `price_per_meter` = '183.75' WHERE `id` = 340;
-- id=341 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '201.25', `price_5_9` = '201.25', `price_10_19` = '201.25', `price_20_plus` = '201.25', `price_per_meter` = '201.25' WHERE `id` = 341;
-- id=342 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '218.75', `price_5_9` = '218.75', `price_10_19` = '218.75', `price_20_plus` = '218.75', `price_per_meter` = '218.75' WHERE `id` = 342;
-- id=343 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '210.00', `price_5_9` = '210.00', `price_10_19` = '210.00', `price_20_plus` = '210.00', `price_per_meter` = '210.00' WHERE `id` = 343;
-- id=344 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '192.50', `price_5_9` = '192.50', `price_10_19` = '192.50', `price_20_plus` = '192.50', `price_per_meter` = '192.50' WHERE `id` = 344;
-- id=345 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '218.75', `price_5_9` = '218.75', `price_10_19` = '218.75', `price_20_plus` = '218.75', `price_per_meter` = '218.75' WHERE `id` = 345;
-- id=346 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '192.50', `price_5_9` = '192.50', `price_10_19` = '192.50', `price_20_plus` = '192.50', `price_per_meter` = '192.50' WHERE `id` = 346;
-- id=347 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '157.50', `price_5_9` = '157.50', `price_10_19` = '157.50', `price_20_plus` = '157.50', `price_per_meter` = '157.50' WHERE `id` = 347;
-- id=348 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '61.25', `price_5_9` = '61.25', `price_10_19` = '61.25', `price_20_plus` = '61.25', `price_per_meter` = '61.25' WHERE `id` = 348;
-- id=349 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35.00', `price_5_9` = '35.00', `price_10_19` = '35.00', `price_20_plus` = '35.00', `price_per_meter` = '35.00' WHERE `id` = 349;
-- id=350 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '96.25', `price_5_9` = '96.25', `price_10_19` = '96.25', `price_20_plus` = '96.25', `price_per_meter` = '96.25' WHERE `id` = 350;
-- id=351 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '96.25', `price_5_9` = '96.25', `price_10_19` = '96.25', `price_20_plus` = '96.25', `price_per_meter` = '96.25' WHERE `id` = 351;
-- id=352 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1100.00', `price_5_9` = '1100.00', `price_10_19` = '1100.00', `price_20_plus` = '1100.00', `price_per_meter` = '1100.00' WHERE `id` = 352;
-- id=353 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1320.00', `price_5_9` = '1320.00', `price_10_19` = '1320.00', `price_20_plus` = '1320.00', `price_per_meter` = '1320.00' WHERE `id` = 353;
-- id=354 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '150.00', `price_5_9` = '150.00', `price_10_19` = '150.00', `price_20_plus` = '150.00', `price_per_meter` = '150.00' WHERE `id` = 354;
-- id=355 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '150.00', `price_5_9` = '150.00', `price_10_19` = '150.00', `price_20_plus` = '150.00', `price_per_meter` = '150.00' WHERE `id` = 355;
-- id=356 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '150.00', `price_5_9` = '150.00', `price_10_19` = '150.00', `price_20_plus` = '150.00', `price_per_meter` = '150.00' WHERE `id` = 356;
-- id=357 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '166.25', `price_5_9` = '166.25', `price_10_19` = '166.25', `price_20_plus` = '166.25', `price_per_meter` = '166.25' WHERE `id` = 357;
-- id=358 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '113.75', `price_5_9` = '113.75', `price_10_19` = '113.75', `price_20_plus` = '113.75', `price_per_meter` = '113.75' WHERE `id` = 358;
-- id=359 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '402.50', `price_5_9` = '402.50', `price_10_19` = '402.50', `price_20_plus` = '402.50', `price_per_meter` = '402.50' WHERE `id` = 359;
-- id=360 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '78.75', `price_5_9` = '78.75', `price_10_19` = '78.75', `price_20_plus` = '78.75', `price_per_meter` = '78.75' WHERE `id` = 360;
-- id=361 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1330.00', `price_5_9` = '1330.00', `price_10_19` = '1330.00', `price_20_plus` = '1330.00', `price_per_meter` = '1330.00' WHERE `id` = 361;
-- id=362 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '800.00', `price_5_9` = '800.00', `price_10_19` = '800.00', `price_20_plus` = '800.00', `price_per_meter` = '800.00' WHERE `id` = 362;
-- id=363 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '8.75', `price_5_9` = '8.75', `price_10_19` = '8.75', `price_20_plus` = '8.75', `price_per_meter` = '8.75' WHERE `id` = 363;
-- id=364 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1700.00', `price_5_9` = '1700.00', `price_10_19` = '1700.00', `price_20_plus` = '1700.00', `price_per_meter` = '1700.00' WHERE `id` = 364;
-- id=365 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '52.50', `price_5_9` = '52.50', `price_10_19` = '52.50', `price_20_plus` = '52.50', `price_per_meter` = '52.50' WHERE `id` = 365;
-- id=366 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '96.25', `price_5_9` = '96.25', `price_10_19` = '96.25', `price_20_plus` = '96.25', `price_per_meter` = '96.25' WHERE `id` = 366;
-- id=367 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '96.25', `price_5_9` = '96.25', `price_10_19` = '96.25', `price_20_plus` = '96.25', `price_per_meter` = '96.25' WHERE `id` = 367;
-- id=368 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '70.00', `price_5_9` = '70.00', `price_10_19` = '70.00', `price_20_plus` = '70.00', `price_per_meter` = '70.00' WHERE `id` = 368;
-- id=369 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '70.00', `price_5_9` = '70.00', `price_10_19` = '70.00', `price_20_plus` = '70.00', `price_per_meter` = '70.00' WHERE `id` = 369;
-- id=370 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '70.00', `price_5_9` = '70.00', `price_10_19` = '70.00', `price_20_plus` = '70.00', `price_per_meter` = '70.00' WHERE `id` = 370;
-- id=371 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26.25', `price_5_9` = '26.25', `price_10_19` = '26.25', `price_20_plus` = '26.25', `price_per_meter` = '26.25' WHERE `id` = 371;
-- id=372 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '52.50', `price_5_9` = '52.50', `price_10_19` = '52.50', `price_20_plus` = '52.50', `price_per_meter` = '52.50' WHERE `id` = 372;
-- id=373 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35.00', `price_5_9` = '35.00', `price_10_19` = '35.00', `price_20_plus` = '35.00', `price_per_meter` = '35.00' WHERE `id` = 373;
-- id=374 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35.00', `price_5_9` = '35.00', `price_10_19` = '35.00', `price_20_plus` = '35.00', `price_per_meter` = '35.00' WHERE `id` = 374;
-- id=375 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35.00', `price_5_9` = '35.00', `price_10_19` = '35.00', `price_20_plus` = '35.00', `price_per_meter` = '35.00' WHERE `id` = 375;
-- id=376 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35.00', `price_5_9` = '35.00', `price_10_19` = '35.00', `price_20_plus` = '35.00', `price_per_meter` = '35.00' WHERE `id` = 376;
-- id=377 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35.00', `price_5_9` = '35.00', `price_10_19` = '35.00', `price_20_plus` = '35.00', `price_per_meter` = '35.00' WHERE `id` = 377;
-- id=378 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35.00', `price_5_9` = '35.00', `price_10_19` = '35.00', `price_20_plus` = '35.00', `price_per_meter` = '35.00' WHERE `id` = 378;
-- id=379 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '96.25', `price_5_9` = '96.25', `price_10_19` = '96.25', `price_20_plus` = '96.25', `price_per_meter` = '96.25' WHERE `id` = 379;
-- id=380 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '96.25', `price_5_9` = '96.25', `price_10_19` = '96.25', `price_20_plus` = '96.25', `price_per_meter` = '96.25' WHERE `id` = 380;
-- id=381 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '96.25', `price_5_9` = '96.25', `price_10_19` = '96.25', `price_20_plus` = '96.25', `price_per_meter` = '96.25' WHERE `id` = 381;
-- id=382 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '131.25', `price_5_9` = '131.25', `price_10_19` = '131.25', `price_20_plus` = '131.25', `price_per_meter` = '131.25' WHERE `id` = 382;
-- id=383 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '131.25', `price_5_9` = '131.25', `price_10_19` = '131.25', `price_20_plus` = '131.25', `price_per_meter` = '131.25' WHERE `id` = 383;
-- id=384 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26.25', `price_5_9` = '26.25', `price_10_19` = '26.25', `price_20_plus` = '26.25', `price_per_meter` = '26.25' WHERE `id` = 384;
-- id=385 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35.00', `price_5_9` = '35.00', `price_10_19` = '35.00', `price_20_plus` = '35.00', `price_per_meter` = '35.00' WHERE `id` = 385;
-- id=386 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35.00', `price_5_9` = '35.00', `price_10_19` = '35.00', `price_20_plus` = '35.00', `price_per_meter` = '35.00' WHERE `id` = 386;
-- id=387 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35.00', `price_5_9` = '35.00', `price_10_19` = '35.00', `price_20_plus` = '35.00', `price_per_meter` = '35.00' WHERE `id` = 387;
-- id=388 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '140.00', `price_5_9` = '140.00', `price_10_19` = '140.00', `price_20_plus` = '140.00', `price_per_meter` = '140.00' WHERE `id` = 388;
-- id=389 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '78.75', `price_5_9` = '78.75', `price_10_19` = '78.75', `price_20_plus` = '78.75', `price_per_meter` = '78.75' WHERE `id` = 389;
-- id=390 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '78.75', `price_5_9` = '78.75', `price_10_19` = '78.75', `price_20_plus` = '78.75', `price_per_meter` = '78.75' WHERE `id` = 390;
-- id=391 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '323.75', `price_5_9` = '323.75', `price_10_19` = '323.75', `price_20_plus` = '323.75', `price_per_meter` = '323.75' WHERE `id` = 391;
-- id=392 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '78.75', `price_5_9` = '78.75', `price_10_19` = '78.75', `price_20_plus` = '78.75', `price_per_meter` = '78.75' WHERE `id` = 392;
-- id=393 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1600.00', `price_5_9` = '1600.00', `price_10_19` = '1600.00', `price_20_plus` = '1600.00', `price_per_meter` = '1600.00' WHERE `id` = 393;
-- id=394 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1200.00', `price_5_9` = '1200.00', `price_10_19` = '1200.00', `price_20_plus` = '1200.00', `price_per_meter` = '1200.00' WHERE `id` = 394;
-- id=395 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1200.00', `price_5_9` = '1200.00', `price_10_19` = '1200.00', `price_20_plus` = '1200.00', `price_per_meter` = '1200.00' WHERE `id` = 395;
-- id=396 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '78.75', `price_5_9` = '78.75', `price_10_19` = '78.75', `price_20_plus` = '78.75', `price_per_meter` = '78.75' WHERE `id` = 396;
-- id=397 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '78.75', `price_5_9` = '78.75', `price_10_19` = '78.75', `price_20_plus` = '78.75', `price_per_meter` = '78.75' WHERE `id` = 397;
-- id=398 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '78.75', `price_5_9` = '78.75', `price_10_19` = '78.75', `price_20_plus` = '78.75', `price_per_meter` = '78.75' WHERE `id` = 398;
-- id=399 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '262.50', `price_5_9` = '262.50', `price_10_19` = '262.50', `price_20_plus` = '262.50', `price_per_meter` = '262.50' WHERE `id` = 399;
-- id=400 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '200.00', `price_5_9` = '200.00', `price_10_19` = '200.00', `price_20_plus` = '200.00', `price_per_meter` = '200.00' WHERE `id` = 400;
-- id=401 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '200.00', `price_5_9` = '200.00', `price_10_19` = '200.00', `price_20_plus` = '200.00', `price_per_meter` = '200.00' WHERE `id` = 401;
-- id=402 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '270.00', `price_5_9` = '270.00', `price_10_19` = '270.00', `price_20_plus` = '270.00', `price_per_meter` = '270.00' WHERE `id` = 402;
-- id=403 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '52.50', `price_5_9` = '52.50', `price_10_19` = '52.50', `price_20_plus` = '52.50', `price_per_meter` = '52.50' WHERE `id` = 403;
-- id=404 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '43.75', `price_5_9` = '43.75', `price_10_19` = '43.75', `price_20_plus` = '43.75', `price_per_meter` = '43.75' WHERE `id` = 404;
-- id=405 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '280.00', `price_5_9` = '280.00', `price_10_19` = '280.00', `price_20_plus` = '280.00', `price_per_meter` = '280.00' WHERE `id` = 405;
-- id=406 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '4445.00', `price_5_9` = '4445.00', `price_10_19` = '4445.00', `price_20_plus` = '4445.00', `price_per_meter` = '4445.00' WHERE `id` = 406;
-- id=407 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3762.50', `price_5_9` = '3762.50', `price_10_19` = '3762.50', `price_20_plus` = '3762.50', `price_per_meter` = '3762.50' WHERE `id` = 407;
-- id=408 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1723.75', `price_5_9` = '1723.75', `price_10_19` = '1723.75', `price_20_plus` = '1723.75', `price_per_meter` = '1723.75' WHERE `id` = 408;
-- id=409 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '2563.75', `price_5_9` = '2563.75', `price_10_19` = '2563.75', `price_20_plus` = '2563.75', `price_per_meter` = '2563.75' WHERE `id` = 409;
-- id=410 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '218.75', `price_5_9` = '218.75', `price_10_19` = '218.75', `price_20_plus` = '218.75', `price_per_meter` = '218.75' WHERE `id` = 410;
-- id=411 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '236.25', `price_5_9` = '236.25', `price_10_19` = '236.25', `price_20_plus` = '236.25', `price_per_meter` = '236.25' WHERE `id` = 411;
-- id=412 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26.25', `price_5_9` = '26.25', `price_10_19` = '26.25', `price_20_plus` = '26.25', `price_per_meter` = '26.25' WHERE `id` = 412;
-- id=413 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '122.50', `price_5_9` = '122.50', `price_10_19` = '122.50', `price_20_plus` = '122.50', `price_per_meter` = '122.50' WHERE `id` = 413;
-- id=414 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '131.25', `price_5_9` = '131.25', `price_10_19` = '131.25', `price_20_plus` = '131.25', `price_per_meter` = '131.25' WHERE `id` = 414;
-- id=415 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26.25', `price_5_9` = '26.25', `price_10_19` = '26.25', `price_20_plus` = '26.25', `price_per_meter` = '26.25' WHERE `id` = 415;
-- id=416 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '236.25', `price_5_9` = '236.25', `price_10_19` = '236.25', `price_20_plus` = '236.25', `price_per_meter` = '236.25' WHERE `id` = 416;
-- id=417 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '122.50', `price_5_9` = '122.50', `price_10_19` = '122.50', `price_20_plus` = '122.50', `price_per_meter` = '122.50' WHERE `id` = 417;
-- id=418 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1200.00', `price_5_9` = '1200.00', `price_10_19` = '1200.00', `price_20_plus` = '1200.00', `price_per_meter` = '1200.00' WHERE `id` = 418;
-- id=419 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1951.25', `price_5_9` = '1951.25', `price_10_19` = '1951.25', `price_20_plus` = '1951.25', `price_per_meter` = '1951.25' WHERE `id` = 419;
-- id=420 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1863.75', `price_5_9` = '1863.75', `price_10_19` = '1863.75', `price_20_plus` = '1863.75', `price_per_meter` = '1863.75' WHERE `id` = 420;
-- id=421 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '70.00', `price_5_9` = '70.00', `price_10_19` = '70.00', `price_20_plus` = '70.00', `price_per_meter` = '70.00' WHERE `id` = 421;
-- id=422 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26.25', `price_5_9` = '26.25', `price_10_19` = '26.25', `price_20_plus` = '26.25', `price_per_meter` = '26.25' WHERE `id` = 422;
-- id=423 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26.25', `price_5_9` = '26.25', `price_10_19` = '26.25', `price_20_plus` = '26.25', `price_per_meter` = '26.25' WHERE `id` = 423;
-- id=424 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26.25', `price_5_9` = '26.25', `price_10_19` = '26.25', `price_20_plus` = '26.25', `price_per_meter` = '26.25' WHERE `id` = 424;
-- id=425 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1500.00', `price_5_9` = '1500.00', `price_10_19` = '1500.00', `price_20_plus` = '1500.00', `price_per_meter` = '1500.00' WHERE `id` = 425;
-- id=426 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '105.00', `price_5_9` = '105.00', `price_10_19` = '105.00', `price_20_plus` = '105.00', `price_per_meter` = '105.00' WHERE `id` = 426;
-- id=427 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '105.00', `price_5_9` = '105.00', `price_10_19` = '105.00', `price_20_plus` = '105.00', `price_per_meter` = '105.00' WHERE `id` = 427;
-- id=428 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '586.25', `price_5_9` = '586.25', `price_10_19` = '586.25', `price_20_plus` = '586.25', `price_per_meter` = '586.25' WHERE `id` = 428;
-- id=429 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '428.75', `price_5_9` = '428.75', `price_10_19` = '428.75', `price_20_plus` = '428.75', `price_per_meter` = '428.75' WHERE `id` = 429;
-- id=430 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '300.00', `price_5_9` = '300.00', `price_10_19` = '300.00', `price_20_plus` = '300.00', `price_per_meter` = '300.00' WHERE `id` = 430;
-- id=431 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '323.75', `price_5_9` = '323.75', `price_10_19` = '323.75', `price_20_plus` = '323.75', `price_per_meter` = '323.75' WHERE `id` = 431;
-- id=432 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '236.25', `price_5_9` = '236.25', `price_10_19` = '236.25', `price_20_plus` = '236.25', `price_per_meter` = '236.25' WHERE `id` = 432;
-- id=433 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '1000.00', `price_5_9` = '1000.00', `price_10_19` = '1000.00', `price_20_plus` = '1000.00', `price_per_meter` = '1000.00' WHERE `id` = 433;
-- id=434 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '700.00', `price_5_9` = '700.00', `price_10_19` = '700.00', `price_20_plus` = '700.00', `price_per_meter` = '700.00' WHERE `id` = 434;
-- id=435 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '300.00', `price_5_9` = '300.00', `price_10_19` = '300.00', `price_20_plus` = '300.00', `price_per_meter` = '300.00' WHERE `id` = 435;
-- id=436 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '300.00', `price_5_9` = '300.00', `price_10_19` = '300.00', `price_20_plus` = '300.00', `price_per_meter` = '300.00' WHERE `id` = 436;
-- id=437 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '300.00', `price_5_9` = '300.00', `price_10_19` = '300.00', `price_20_plus` = '300.00', `price_per_meter` = '300.00' WHERE `id` = 437;
-- id=438 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '300.00', `price_5_9` = '300.00', `price_10_19` = '300.00', `price_20_plus` = '300.00', `price_per_meter` = '300.00' WHERE `id` = 438;
-- id=746 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '46551.90', `price_5_9` = '46551.90', `price_10_19` = '46551.90', `price_20_plus` = '46551.90', `price_per_meter` = '1551.73' WHERE `id` = 746;
-- id=747 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '82280.10', `price_5_9` = '82280.10', `price_10_19` = '82280.10', `price_20_plus` = '82280.10', `price_per_meter` = '2742.67' WHERE `id` = 747;
-- id=748 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '46551.90', `price_5_9` = '46551.90', `price_10_19` = '46551.90', `price_20_plus` = '46551.90', `price_per_meter` = '1551.73' WHERE `id` = 748;
-- id=749 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '84656.10', `price_5_9` = '84656.10', `price_10_19` = '84656.10', `price_20_plus` = '84656.10', `price_per_meter` = '2821.87' WHERE `id` = 749;
-- id=750 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '80696.10', `price_5_9` = '80696.10', `price_10_19` = '80696.10', `price_20_plus` = '80696.10', `price_per_meter` = '2689.87' WHERE `id` = 750;
-- id=751 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '80696.10', `price_5_9` = '80696.10', `price_10_19` = '80696.10', `price_20_plus` = '80696.10', `price_per_meter` = '2689.87' WHERE `id` = 751;
-- id=752 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 752;
-- id=753 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 753;
-- id=754 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 754;
-- id=755 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 755;
-- id=756 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 756;
-- id=757 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 757;
-- id=758 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '27456.00', `price_5_9` = '27456.00', `price_10_19` = '27456.00', `price_20_plus` = '27456.00', `price_per_meter` = '915.20' WHERE `id` = 758;
-- id=759 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '27456.00', `price_5_9` = '27456.00', `price_10_19` = '27456.00', `price_20_plus` = '27456.00', `price_per_meter` = '915.20' WHERE `id` = 759;
-- id=760 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '27456.00', `price_5_9` = '27456.00', `price_10_19` = '27456.00', `price_20_plus` = '27456.00', `price_per_meter` = '915.20' WHERE `id` = 760;
-- id=761 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '82280.10', `price_5_9` = '82280.10', `price_10_19` = '82280.10', `price_20_plus` = '82280.10', `price_per_meter` = '2742.67' WHERE `id` = 761;
-- id=762 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '49280.10', `price_5_9` = '49280.10', `price_10_19` = '49280.10', `price_20_plus` = '49280.10', `price_per_meter` = '1642.67' WHERE `id` = 762;
-- id=763 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '82280.10', `price_5_9` = '82280.10', `price_10_19` = '82280.10', `price_20_plus` = '82280.10', `price_per_meter` = '2742.67' WHERE `id` = 763;
-- id=764 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '98736.00', `price_5_9` = '98736.00', `price_10_19` = '98736.00', `price_20_plus` = '98736.00', `price_per_meter` = '3291.20' WHERE `id` = 764;
-- id=765 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 765;
-- id=766 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 766;
-- id=767 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 767;
-- id=768 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 768;
-- id=769 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 769;
-- id=770 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 770;
-- id=771 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 771;
-- id=772 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 772;
-- id=773 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 773;
-- id=774 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 774;
-- id=775 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 775;
-- id=776 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 776;
-- id=777 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 777;
-- id=778 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 778;
-- id=779 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '12936.00', `price_5_9` = '12936.00', `price_10_19` = '12936.00', `price_20_plus` = '12936.00', `price_per_meter` = '431.20' WHERE `id` = 779;
-- id=780 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '14256.00', `price_5_9` = '14256.00', `price_10_19` = '14256.00', `price_20_plus` = '14256.00', `price_per_meter` = '475.20' WHERE `id` = 780;
-- id=781 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '14256.00', `price_5_9` = '14256.00', `price_10_19` = '14256.00', `price_20_plus` = '14256.00', `price_per_meter` = '475.20' WHERE `id` = 781;
-- id=782 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6423.90', `price_5_9` = '6423.90', `price_10_19` = '6423.90', `price_20_plus` = '6423.90', `price_per_meter` = '214.13' WHERE `id` = 782;
-- id=783 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6423.90', `price_5_9` = '6423.90', `price_10_19` = '6423.90', `price_20_plus` = '6423.90', `price_per_meter` = '214.13' WHERE `id` = 783;
-- id=784 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6423.90', `price_5_9` = '6423.90', `price_10_19` = '6423.90', `price_20_plus` = '6423.90', `price_per_meter` = '214.13' WHERE `id` = 784;
-- id=785 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6423.90', `price_5_9` = '6423.90', `price_10_19` = '6423.90', `price_20_plus` = '6423.90', `price_per_meter` = '214.13' WHERE `id` = 785;
-- id=786 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17599.95', `price_5_9` = '17599.95', `price_10_19` = '17599.95', `price_20_plus` = '17599.95', `price_per_meter` = '1173.33' WHERE `id` = 786;
-- id=787 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17599.95', `price_5_9` = '17599.95', `price_10_19` = '17599.95', `price_20_plus` = '17599.95', `price_per_meter` = '1173.33' WHERE `id` = 787;
-- id=788 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '8624.10', `price_5_9` = '8624.10', `price_10_19` = '8624.10', `price_20_plus` = '8624.10', `price_per_meter` = '287.47' WHERE `id` = 788;
-- id=789 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7304.10', `price_5_9` = '7304.10', `price_10_19` = '7304.10', `price_20_plus` = '7304.10', `price_per_meter` = '243.47' WHERE `id` = 789;
-- id=790 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3168.00', `price_5_9` = '3168.00', `price_10_19` = '3168.00', `price_20_plus` = '3168.00', `price_per_meter` = '105.60' WHERE `id` = 790;
-- id=791 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3168.00', `price_5_9` = '3168.00', `price_10_19` = '3168.00', `price_20_plus` = '3168.00', `price_per_meter` = '105.60' WHERE `id` = 791;
-- id=792 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3168.00', `price_5_9` = '3168.00', `price_10_19` = '3168.00', `price_20_plus` = '3168.00', `price_per_meter` = '105.60' WHERE `id` = 792;
-- id=793 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3168.00', `price_5_9` = '3168.00', `price_10_19` = '3168.00', `price_20_plus` = '3168.00', `price_per_meter` = '105.60' WHERE `id` = 793;
-- id=794 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3168.00', `price_5_9` = '3168.00', `price_10_19` = '3168.00', `price_20_plus` = '3168.00', `price_per_meter` = '105.60' WHERE `id` = 794;
-- id=795 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '57464.10', `price_5_9` = '57464.10', `price_10_19` = '57464.10', `price_20_plus` = '57464.10', `price_per_meter` = '1915.47' WHERE `id` = 795;
-- id=796 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '70928.10', `price_5_9` = '70928.10', `price_10_19` = '70928.10', `price_20_plus` = '70928.10', `price_per_meter` = '2364.27' WHERE `id` = 796;
-- id=797 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35199.90', `price_5_9` = '35199.90', `price_10_19` = '35199.90', `price_20_plus` = '35199.90', `price_per_meter` = '1173.33' WHERE `id` = 797;
-- id=798 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '57200.10', `price_5_9` = '57200.10', `price_10_19` = '57200.10', `price_20_plus` = '57200.10', `price_per_meter` = '1906.67' WHERE `id` = 798;
-- id=799 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17600.10', `price_5_9` = '17600.10', `price_10_19` = '17600.10', `price_20_plus` = '17600.10', `price_per_meter` = '586.67' WHERE `id` = 799;
-- id=800 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17600.10', `price_5_9` = '17600.10', `price_10_19` = '17600.10', `price_20_plus` = '17600.10', `price_per_meter` = '586.67' WHERE `id` = 800;
-- id=801 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '13200.00', `price_5_9` = '13200.00', `price_10_19` = '13200.00', `price_20_plus` = '13200.00', `price_per_meter` = '440.00' WHERE `id` = 801;
-- id=802 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3255.90', `price_5_9` = '3255.90', `price_10_19` = '3255.90', `price_20_plus` = '3255.90', `price_per_meter` = '108.53' WHERE `id` = 802;
-- id=803 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '4400.10', `price_5_9` = '4400.10', `price_10_19` = '4400.10', `price_20_plus` = '4400.10', `price_per_meter` = '146.67' WHERE `id` = 803;
-- id=804 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '8184.00', `price_5_9` = '8184.00', `price_10_19` = '8184.00', `price_20_plus` = '8184.00', `price_per_meter` = '272.80' WHERE `id` = 804;
-- id=805 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '12584.10', `price_5_9` = '12584.10', `price_10_19` = '12584.10', `price_20_plus` = '12584.10', `price_per_meter` = '419.47' WHERE `id` = 805;
-- id=806 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7568.10', `price_5_9` = '7568.10', `price_10_19` = '7568.10', `price_20_plus` = '7568.10', `price_per_meter` = '252.27' WHERE `id` = 806;
-- id=807 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10119.90', `price_5_9` = '10119.90', `price_10_19` = '10119.90', `price_20_plus` = '10119.90', `price_per_meter` = '337.33' WHERE `id` = 807;
-- id=808 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7920.00', `price_5_9` = '7920.00', `price_10_19` = '7920.00', `price_20_plus` = '7920.00', `price_per_meter` = '264.00' WHERE `id` = 808;
-- id=809 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3608.10', `price_5_9` = '3608.10', `price_10_19` = '3608.10', `price_20_plus` = '3608.10', `price_per_meter` = '120.27' WHERE `id` = 809;
-- id=810 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '4047.90', `price_5_9` = '4047.90', `price_10_19` = '4047.90', `price_20_plus` = '4047.90', `price_per_meter` = '134.93' WHERE `id` = 810;
-- id=811 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '4575.90', `price_5_9` = '4575.90', `price_10_19` = '4575.90', `price_20_plus` = '4575.90', `price_per_meter` = '152.53' WHERE `id` = 811;
-- id=812 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9240.00', `price_5_9` = '9240.00', `price_10_19` = '9240.00', `price_20_plus` = '9240.00', `price_per_meter` = '308.00' WHERE `id` = 812;
-- id=813 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '12584.10', `price_5_9` = '12584.10', `price_10_19` = '12584.10', `price_20_plus` = '12584.10', `price_per_meter` = '419.47' WHERE `id` = 813;
-- id=814 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17424.00', `price_5_9` = '17424.00', `price_10_19` = '17424.00', `price_20_plus` = '17424.00', `price_per_meter` = '580.80' WHERE `id` = 814;
-- id=815 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '11616.00', `price_5_9` = '11616.00', `price_10_19` = '11616.00', `price_20_plus` = '11616.00', `price_per_meter` = '387.20' WHERE `id` = 815;
-- id=816 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10647.90', `price_5_9` = '10647.90', `price_10_19` = '10647.90', `price_20_plus` = '10647.90', `price_per_meter` = '354.93' WHERE `id` = 816;
-- id=817 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '16455.90', `price_5_9` = '16455.90', `price_10_19` = '16455.90', `price_20_plus` = '16455.90', `price_per_meter` = '548.53' WHERE `id` = 817;
-- id=818 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '20504.10', `price_5_9` = '20504.10', `price_10_19` = '20504.10', `price_20_plus` = '20504.10', `price_per_meter` = '683.47' WHERE `id` = 818;
-- id=819 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '20504.10', `price_5_9` = '20504.10', `price_10_19` = '20504.10', `price_20_plus` = '20504.10', `price_per_meter` = '683.47' WHERE `id` = 819;
-- id=820 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 820;
-- id=821 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 821;
-- id=822 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 822;
-- id=823 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 823;
-- id=824 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 824;
-- id=825 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 825;
-- id=826 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 826;
-- id=827 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 827;
-- id=828 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 828;
-- id=829 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '19359.90', `price_5_9` = '19359.90', `price_10_19` = '19359.90', `price_20_plus` = '19359.90', `price_per_meter` = '645.33' WHERE `id` = 829;
-- id=830 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7304.10', `price_5_9` = '7304.10', `price_10_19` = '7304.10', `price_20_plus` = '7304.10', `price_per_meter` = '243.47' WHERE `id` = 830;
-- id=831 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6951.90', `price_5_9` = '6951.90', `price_10_19` = '6951.90', `price_20_plus` = '6951.90', `price_per_meter` = '231.73' WHERE `id` = 831;
-- id=832 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5016.00', `price_5_9` = '5016.00', `price_10_19` = '5016.00', `price_20_plus` = '5016.00', `price_per_meter` = '167.20' WHERE `id` = 832;
-- id=833 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9856.20', `price_5_9` = '9856.20', `price_10_19` = '9856.20', `price_20_plus` = '9856.20', `price_per_meter` = '164.27' WHERE `id` = 833;
-- id=834 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6072.00', `price_5_9` = '6072.00', `price_10_19` = '6072.00', `price_20_plus` = '6072.00', `price_per_meter` = '202.40' WHERE `id` = 834;
-- id=835 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '11791.80', `price_5_9` = '11791.80', `price_10_19` = '11791.80', `price_20_plus` = '11791.80', `price_per_meter` = '196.53' WHERE `id` = 835;
-- id=836 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 836;
-- id=837 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6336.00', `price_5_9` = '6336.00', `price_10_19` = '6336.00', `price_20_plus` = '6336.00', `price_per_meter` = '211.20' WHERE `id` = 837;
-- id=838 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '12408.00', `price_5_9` = '12408.00', `price_10_19` = '12408.00', `price_20_plus` = '12408.00', `price_per_meter` = '206.80' WHERE `id` = 838;
-- id=839 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 839;
-- id=840 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9680.10', `price_5_9` = '9680.10', `price_10_19` = '9680.10', `price_20_plus` = '9680.10', `price_per_meter` = '322.67' WHERE `id` = 840;
-- id=841 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 841;
-- id=842 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6336.00', `price_5_9` = '6336.00', `price_10_19` = '6336.00', `price_20_plus` = '6336.00', `price_per_meter` = '211.20' WHERE `id` = 842;
-- id=843 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '12408.00', `price_5_9` = '12408.00', `price_10_19` = '12408.00', `price_20_plus` = '12408.00', `price_per_meter` = '206.80' WHERE `id` = 843;
-- id=844 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7920.00', `price_5_9` = '7920.00', `price_10_19` = '7920.00', `price_20_plus` = '7920.00', `price_per_meter` = '264.00' WHERE `id` = 844;
-- id=845 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5016.00', `price_5_9` = '5016.00', `price_10_19` = '5016.00', `price_20_plus` = '5016.00', `price_per_meter` = '167.20' WHERE `id` = 845;
-- id=846 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9856.20', `price_5_9` = '9856.20', `price_10_19` = '9856.20', `price_20_plus` = '9856.20', `price_per_meter` = '164.27' WHERE `id` = 846;
-- id=847 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '11791.80', `price_5_9` = '11791.80', `price_10_19` = '11791.80', `price_20_plus` = '11791.80', `price_per_meter` = '196.53' WHERE `id` = 847;
-- id=848 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5016.00', `price_5_9` = '5016.00', `price_10_19` = '5016.00', `price_20_plus` = '5016.00', `price_per_meter` = '167.20' WHERE `id` = 848;
-- id=849 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6072.00', `price_5_9` = '6072.00', `price_10_19` = '6072.00', `price_20_plus` = '6072.00', `price_per_meter` = '202.40' WHERE `id` = 849;
-- id=850 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5016.00', `price_5_9` = '5016.00', `price_10_19` = '5016.00', `price_20_plus` = '5016.00', `price_per_meter` = '167.20' WHERE `id` = 850;
-- id=851 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 851;
-- id=852 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5368.20', `price_5_9` = '5368.20', `price_10_19` = '5368.20', `price_20_plus` = '5368.20', `price_per_meter` = '89.47' WHERE `id` = 852;
-- id=853 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 853;
-- id=854 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 854;
-- id=855 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5368.20', `price_5_9` = '5368.20', `price_10_19` = '5368.20', `price_20_plus` = '5368.20', `price_per_meter` = '89.47' WHERE `id` = 855;
-- id=856 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 856;
-- id=857 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6951.90', `price_5_9` = '6951.90', `price_10_19` = '6951.90', `price_20_plus` = '6951.90', `price_per_meter` = '231.73' WHERE `id` = 857;
-- id=858 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5984.10', `price_5_9` = '5984.10', `price_10_19` = '5984.10', `price_20_plus` = '5984.10', `price_per_meter` = '199.47' WHERE `id` = 858;
-- id=859 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5984.10', `price_5_9` = '5984.10', `price_10_19` = '5984.10', `price_20_plus` = '5984.10', `price_per_meter` = '199.47' WHERE `id` = 859;
-- id=860 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9240.00', `price_5_9` = '9240.00', `price_10_19` = '9240.00', `price_20_plus` = '9240.00', `price_per_meter` = '154.00' WHERE `id` = 860;
-- id=861 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9240.00', `price_5_9` = '9240.00', `price_10_19` = '9240.00', `price_20_plus` = '9240.00', `price_per_meter` = '154.00' WHERE `id` = 861;
-- id=862 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 862;
-- id=863 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7567.80', `price_5_9` = '7567.80', `price_10_19` = '7567.80', `price_20_plus` = '7567.80', `price_per_meter` = '126.13' WHERE `id` = 863;
-- id=864 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '4047.90', `price_5_9` = '4047.90', `price_10_19` = '4047.90', `price_20_plus` = '4047.90', `price_per_meter` = '134.93' WHERE `id` = 864;
-- id=865 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7920.00', `price_5_9` = '7920.00', `price_10_19` = '7920.00', `price_20_plus` = '7920.00', `price_per_meter` = '132.00' WHERE `id` = 865;
-- id=866 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5016.00', `price_5_9` = '5016.00', `price_10_19` = '5016.00', `price_20_plus` = '5016.00', `price_per_meter` = '167.20' WHERE `id` = 866;
-- id=867 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9856.20', `price_5_9` = '9856.20', `price_10_19` = '9856.20', `price_20_plus` = '9856.20', `price_per_meter` = '164.27' WHERE `id` = 867;
-- id=868 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5544.00', `price_5_9` = '5544.00', `price_10_19` = '5544.00', `price_20_plus` = '5544.00', `price_per_meter` = '184.80' WHERE `id` = 868;
-- id=869 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10824.00', `price_5_9` = '10824.00', `price_10_19` = '10824.00', `price_20_plus` = '10824.00', `price_per_meter` = '180.40' WHERE `id` = 869;
-- id=870 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5544.00', `price_5_9` = '5544.00', `price_10_19` = '5544.00', `price_20_plus` = '5544.00', `price_per_meter` = '184.80' WHERE `id` = 870;
-- id=871 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10824.00', `price_5_9` = '10824.00', `price_10_19` = '10824.00', `price_20_plus` = '10824.00', `price_per_meter` = '180.40' WHERE `id` = 871;
-- id=872 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5544.00', `price_5_9` = '5544.00', `price_10_19` = '5544.00', `price_20_plus` = '5544.00', `price_per_meter` = '184.80' WHERE `id` = 872;
-- id=873 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10824.00', `price_5_9` = '10824.00', `price_10_19` = '10824.00', `price_20_plus` = '10824.00', `price_per_meter` = '180.40' WHERE `id` = 873;
-- id=874 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5544.00', `price_5_9` = '5544.00', `price_10_19` = '5544.00', `price_20_plus` = '5544.00', `price_per_meter` = '184.80' WHERE `id` = 874;
-- id=875 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10824.00', `price_5_9` = '10824.00', `price_10_19` = '10824.00', `price_20_plus` = '10824.00', `price_per_meter` = '180.40' WHERE `id` = 875;
-- id=876 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5544.00', `price_5_9` = '5544.00', `price_10_19` = '5544.00', `price_20_plus` = '5544.00', `price_per_meter` = '184.80' WHERE `id` = 876;
-- id=877 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6951.90', `price_5_9` = '6951.90', `price_10_19` = '6951.90', `price_20_plus` = '6951.90', `price_per_meter` = '231.73' WHERE `id` = 877;
-- id=878 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6951.90', `price_5_9` = '6951.90', `price_10_19` = '6951.90', `price_20_plus` = '6951.90', `price_per_meter` = '231.73' WHERE `id` = 878;
-- id=879 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '57199.95', `price_5_9` = '57199.95', `price_10_19` = '57199.95', `price_20_plus` = '57199.95', `price_per_meter` = '3813.33' WHERE `id` = 879;
-- id=880 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '79200.00', `price_5_9` = '79200.00', `price_10_19` = '79200.00', `price_20_plus` = '79200.00', `price_per_meter` = '5280.00' WHERE `id` = 880;
-- id=881 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '83599.95', `price_5_9` = '83599.95', `price_10_19` = '83599.95', `price_20_plus` = '83599.95', `price_per_meter` = '5573.33' WHERE `id` = 881;
-- id=882 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '79200.00', `price_5_9` = '79200.00', `price_10_19` = '79200.00', `price_20_plus` = '79200.00', `price_per_meter` = '5280.00' WHERE `id` = 882;
-- id=883 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26136.00', `price_5_9` = '26136.00', `price_10_19` = '26136.00', `price_20_plus` = '26136.00', `price_per_meter` = '1742.40' WHERE `id` = 883;
-- id=884 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '28159.95', `price_5_9` = '28159.95', `price_10_19` = '28159.95', `price_20_plus` = '28159.95', `price_per_meter` = '1877.33' WHERE `id` = 884;
-- id=885 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '32208.00', `price_5_9` = '32208.00', `price_10_19` = '32208.00', `price_20_plus` = '32208.00', `price_per_meter` = '2147.20' WHERE `id` = 885;
-- id=886 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '27456.00', `price_5_9` = '27456.00', `price_10_19` = '27456.00', `price_20_plus` = '27456.00', `price_per_meter` = '1830.40' WHERE `id` = 886;
-- id=887 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '27456.00', `price_5_9` = '27456.00', `price_10_19` = '27456.00', `price_20_plus` = '27456.00', `price_per_meter` = '1830.40' WHERE `id` = 887;
-- id=888 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '30799.95', `price_5_9` = '30799.95', `price_10_19` = '30799.95', `price_20_plus` = '30799.95', `price_per_meter` = '2053.33' WHERE `id` = 888;
-- id=889 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17248.05', `price_5_9` = '17248.05', `price_10_19` = '17248.05', `price_20_plus` = '17248.05', `price_per_meter` = '1149.87' WHERE `id` = 889;
-- id=890 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26136.00', `price_5_9` = '26136.00', `price_10_19` = '26136.00', `price_20_plus` = '26136.00', `price_per_meter` = '1742.40' WHERE `id` = 890;
-- id=891 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '28159.95', `price_5_9` = '28159.95', `price_10_19` = '28159.95', `price_20_plus` = '28159.95', `price_per_meter` = '1877.33' WHERE `id` = 891;
-- id=892 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '38280.00', `price_5_9` = '38280.00', `price_10_19` = '38280.00', `price_20_plus` = '38280.00', `price_per_meter` = '2552.00' WHERE `id` = 892;
-- id=893 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '38280.00', `price_5_9` = '38280.00', `price_10_19` = '38280.00', `price_20_plus` = '38280.00', `price_per_meter` = '2552.00' WHERE `id` = 893;
-- id=894 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '38280.00', `price_5_9` = '38280.00', `price_10_19` = '38280.00', `price_20_plus` = '38280.00', `price_per_meter` = '2552.00' WHERE `id` = 894;
-- id=895 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '24112.05', `price_5_9` = '24112.05', `price_10_19` = '24112.05', `price_20_plus` = '24112.05', `price_per_meter` = '1607.47' WHERE `id` = 895;
-- id=896 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '14080.05', `price_5_9` = '14080.05', `price_10_19` = '14080.05', `price_20_plus` = '14080.05', `price_per_meter` = '938.67' WHERE `id` = 896;
-- id=897 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '14080.05', `price_5_9` = '14080.05', `price_10_19` = '14080.05', `price_20_plus` = '14080.05', `price_per_meter` = '938.67' WHERE `id` = 897;
-- id=898 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '14080.05', `price_5_9` = '14080.05', `price_10_19` = '14080.05', `price_20_plus` = '14080.05', `price_per_meter` = '938.67' WHERE `id` = 898;
-- id=899 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '46551.90', `price_5_9` = '46551.90', `price_10_19` = '46551.90', `price_20_plus` = '46551.90', `price_per_meter` = '1551.73' WHERE `id` = 899;
-- id=900 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '46551.90', `price_5_9` = '46551.90', `price_10_19` = '46551.90', `price_20_plus` = '46551.90', `price_per_meter` = '1551.73' WHERE `id` = 900;
-- id=901 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '46551.90', `price_5_9` = '46551.90', `price_10_19` = '46551.90', `price_20_plus` = '46551.90', `price_per_meter` = '1551.73' WHERE `id` = 901;
-- id=902 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '84656.10', `price_5_9` = '84656.10', `price_10_19` = '84656.10', `price_20_plus` = '84656.10', `price_per_meter` = '2821.87' WHERE `id` = 902;
-- id=903 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '80696.10', `price_5_9` = '80696.10', `price_10_19` = '80696.10', `price_20_plus` = '80696.10', `price_per_meter` = '2689.87' WHERE `id` = 903;
-- id=904 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '80696.10', `price_5_9` = '80696.10', `price_10_19` = '80696.10', `price_20_plus` = '80696.10', `price_per_meter` = '2689.87' WHERE `id` = 904;
-- id=905 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 905;
-- id=906 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 906;
-- id=907 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 907;
-- id=908 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 908;
-- id=909 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 909;
-- id=910 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '23144.10', `price_5_9` = '23144.10', `price_10_19` = '23144.10', `price_20_plus` = '23144.10', `price_per_meter` = '771.47' WHERE `id` = 910;
-- id=911 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '27456.00', `price_5_9` = '27456.00', `price_10_19` = '27456.00', `price_20_plus` = '27456.00', `price_per_meter` = '915.20' WHERE `id` = 911;
-- id=912 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '27456.00', `price_5_9` = '27456.00', `price_10_19` = '27456.00', `price_20_plus` = '27456.00', `price_per_meter` = '915.20' WHERE `id` = 912;
-- id=913 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '27456.00', `price_5_9` = '27456.00', `price_10_19` = '27456.00', `price_20_plus` = '27456.00', `price_per_meter` = '915.20' WHERE `id` = 913;
-- id=914 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '49280.10', `price_5_9` = '49280.10', `price_10_19` = '49280.10', `price_20_plus` = '49280.10', `price_per_meter` = '1642.67' WHERE `id` = 914;
-- id=915 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '82280.10', `price_5_9` = '82280.10', `price_10_19` = '82280.10', `price_20_plus` = '82280.10', `price_per_meter` = '2742.67' WHERE `id` = 915;
-- id=916 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '98736.00', `price_5_9` = '98736.00', `price_10_19` = '98736.00', `price_20_plus` = '98736.00', `price_per_meter` = '3291.20' WHERE `id` = 916;
-- id=917 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 917;
-- id=918 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 918;
-- id=919 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 919;
-- id=920 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 920;
-- id=921 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 921;
-- id=922 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 922;
-- id=923 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5895.90', `price_5_9` = '5895.90', `price_10_19` = '5895.90', `price_20_plus` = '5895.90', `price_per_meter` = '196.53' WHERE `id` = 923;
-- id=924 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 924;
-- id=925 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 925;
-- id=926 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 926;
-- id=927 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 927;
-- id=928 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 928;
-- id=929 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 929;
-- id=930 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6776.10', `price_5_9` = '6776.10', `price_10_19` = '6776.10', `price_20_plus` = '6776.10', `price_per_meter` = '225.87' WHERE `id` = 930;
-- id=931 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '12936.00', `price_5_9` = '12936.00', `price_10_19` = '12936.00', `price_20_plus` = '12936.00', `price_per_meter` = '431.20' WHERE `id` = 931;
-- id=932 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '14256.00', `price_5_9` = '14256.00', `price_10_19` = '14256.00', `price_20_plus` = '14256.00', `price_per_meter` = '475.20' WHERE `id` = 932;
-- id=933 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '14256.00', `price_5_9` = '14256.00', `price_10_19` = '14256.00', `price_20_plus` = '14256.00', `price_per_meter` = '475.20' WHERE `id` = 933;
-- id=934 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6423.90', `price_5_9` = '6423.90', `price_10_19` = '6423.90', `price_20_plus` = '6423.90', `price_per_meter` = '214.13' WHERE `id` = 934;
-- id=935 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6423.90', `price_5_9` = '6423.90', `price_10_19` = '6423.90', `price_20_plus` = '6423.90', `price_per_meter` = '214.13' WHERE `id` = 935;
-- id=936 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6423.90', `price_5_9` = '6423.90', `price_10_19` = '6423.90', `price_20_plus` = '6423.90', `price_per_meter` = '214.13' WHERE `id` = 936;
-- id=937 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6423.90', `price_5_9` = '6423.90', `price_10_19` = '6423.90', `price_20_plus` = '6423.90', `price_per_meter` = '214.13' WHERE `id` = 937;
-- id=938 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17599.95', `price_5_9` = '17599.95', `price_10_19` = '17599.95', `price_20_plus` = '17599.95', `price_per_meter` = '1173.33' WHERE `id` = 938;
-- id=939 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17599.95', `price_5_9` = '17599.95', `price_10_19` = '17599.95', `price_20_plus` = '17599.95', `price_per_meter` = '1173.33' WHERE `id` = 939;
-- id=940 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '8624.10', `price_5_9` = '8624.10', `price_10_19` = '8624.10', `price_20_plus` = '8624.10', `price_per_meter` = '287.47' WHERE `id` = 940;
-- id=941 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7304.10', `price_5_9` = '7304.10', `price_10_19` = '7304.10', `price_20_plus` = '7304.10', `price_per_meter` = '243.47' WHERE `id` = 941;
-- id=942 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3168.00', `price_5_9` = '3168.00', `price_10_19` = '3168.00', `price_20_plus` = '3168.00', `price_per_meter` = '105.60' WHERE `id` = 942;
-- id=943 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3168.00', `price_5_9` = '3168.00', `price_10_19` = '3168.00', `price_20_plus` = '3168.00', `price_per_meter` = '105.60' WHERE `id` = 943;
-- id=944 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3168.00', `price_5_9` = '3168.00', `price_10_19` = '3168.00', `price_20_plus` = '3168.00', `price_per_meter` = '105.60' WHERE `id` = 944;
-- id=945 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3168.00', `price_5_9` = '3168.00', `price_10_19` = '3168.00', `price_20_plus` = '3168.00', `price_per_meter` = '105.60' WHERE `id` = 945;
-- id=946 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3168.00', `price_5_9` = '3168.00', `price_10_19` = '3168.00', `price_20_plus` = '3168.00', `price_per_meter` = '105.60' WHERE `id` = 946;
-- id=947 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '57464.10', `price_5_9` = '57464.10', `price_10_19` = '57464.10', `price_20_plus` = '57464.10', `price_per_meter` = '1915.47' WHERE `id` = 947;
-- id=948 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '70928.10', `price_5_9` = '70928.10', `price_10_19` = '70928.10', `price_20_plus` = '70928.10', `price_per_meter` = '2364.27' WHERE `id` = 948;
-- id=949 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '35199.90', `price_5_9` = '35199.90', `price_10_19` = '35199.90', `price_20_plus` = '35199.90', `price_per_meter` = '1173.33' WHERE `id` = 949;
-- id=950 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '57200.10', `price_5_9` = '57200.10', `price_10_19` = '57200.10', `price_20_plus` = '57200.10', `price_per_meter` = '1906.67' WHERE `id` = 950;
-- id=951 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17600.10', `price_5_9` = '17600.10', `price_10_19` = '17600.10', `price_20_plus` = '17600.10', `price_per_meter` = '586.67' WHERE `id` = 951;
-- id=952 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17600.10', `price_5_9` = '17600.10', `price_10_19` = '17600.10', `price_20_plus` = '17600.10', `price_per_meter` = '586.67' WHERE `id` = 952;
-- id=953 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '13200.00', `price_5_9` = '13200.00', `price_10_19` = '13200.00', `price_20_plus` = '13200.00', `price_per_meter` = '440.00' WHERE `id` = 953;
-- id=954 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3255.90', `price_5_9` = '3255.90', `price_10_19` = '3255.90', `price_20_plus` = '3255.90', `price_per_meter` = '108.53' WHERE `id` = 954;
-- id=955 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '4400.10', `price_5_9` = '4400.10', `price_10_19` = '4400.10', `price_20_plus` = '4400.10', `price_per_meter` = '146.67' WHERE `id` = 955;
-- id=956 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '8184.00', `price_5_9` = '8184.00', `price_10_19` = '8184.00', `price_20_plus` = '8184.00', `price_per_meter` = '272.80' WHERE `id` = 956;
-- id=957 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '12584.10', `price_5_9` = '12584.10', `price_10_19` = '12584.10', `price_20_plus` = '12584.10', `price_per_meter` = '419.47' WHERE `id` = 957;
-- id=958 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7568.10', `price_5_9` = '7568.10', `price_10_19` = '7568.10', `price_20_plus` = '7568.10', `price_per_meter` = '252.27' WHERE `id` = 958;
-- id=959 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10119.90', `price_5_9` = '10119.90', `price_10_19` = '10119.90', `price_20_plus` = '10119.90', `price_per_meter` = '337.33' WHERE `id` = 959;
-- id=960 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7920.00', `price_5_9` = '7920.00', `price_10_19` = '7920.00', `price_20_plus` = '7920.00', `price_per_meter` = '264.00' WHERE `id` = 960;
-- id=961 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '3608.10', `price_5_9` = '3608.10', `price_10_19` = '3608.10', `price_20_plus` = '3608.10', `price_per_meter` = '120.27' WHERE `id` = 961;
-- id=962 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '4047.90', `price_5_9` = '4047.90', `price_10_19` = '4047.90', `price_20_plus` = '4047.90', `price_per_meter` = '134.93' WHERE `id` = 962;
-- id=963 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '4575.90', `price_5_9` = '4575.90', `price_10_19` = '4575.90', `price_20_plus` = '4575.90', `price_per_meter` = '152.53' WHERE `id` = 963;
-- id=964 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9240.00', `price_5_9` = '9240.00', `price_10_19` = '9240.00', `price_20_plus` = '9240.00', `price_per_meter` = '308.00' WHERE `id` = 964;
-- id=965 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '12584.10', `price_5_9` = '12584.10', `price_10_19` = '12584.10', `price_20_plus` = '12584.10', `price_per_meter` = '419.47' WHERE `id` = 965;
-- id=966 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17424.00', `price_5_9` = '17424.00', `price_10_19` = '17424.00', `price_20_plus` = '17424.00', `price_per_meter` = '580.80' WHERE `id` = 966;
-- id=967 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '11616.00', `price_5_9` = '11616.00', `price_10_19` = '11616.00', `price_20_plus` = '11616.00', `price_per_meter` = '387.20' WHERE `id` = 967;
-- id=968 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10647.90', `price_5_9` = '10647.90', `price_10_19` = '10647.90', `price_20_plus` = '10647.90', `price_per_meter` = '354.93' WHERE `id` = 968;
-- id=969 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '16455.90', `price_5_9` = '16455.90', `price_10_19` = '16455.90', `price_20_plus` = '16455.90', `price_per_meter` = '548.53' WHERE `id` = 969;
-- id=970 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '20504.10', `price_5_9` = '20504.10', `price_10_19` = '20504.10', `price_20_plus` = '20504.10', `price_per_meter` = '683.47' WHERE `id` = 970;
-- id=971 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '20504.10', `price_5_9` = '20504.10', `price_10_19` = '20504.10', `price_20_plus` = '20504.10', `price_per_meter` = '683.47' WHERE `id` = 971;
-- id=972 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 972;
-- id=973 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 973;
-- id=974 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 974;
-- id=975 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 975;
-- id=976 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 976;
-- id=977 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 977;
-- id=978 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 978;
-- id=979 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 979;
-- id=980 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 980;
-- id=981 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '19359.90', `price_5_9` = '19359.90', `price_10_19` = '19359.90', `price_20_plus` = '19359.90', `price_per_meter` = '645.33' WHERE `id` = 981;
-- id=982 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7304.10', `price_5_9` = '7304.10', `price_10_19` = '7304.10', `price_20_plus` = '7304.10', `price_per_meter` = '243.47' WHERE `id` = 982;
-- id=983 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6951.90', `price_5_9` = '6951.90', `price_10_19` = '6951.90', `price_20_plus` = '6951.90', `price_per_meter` = '231.73' WHERE `id` = 983;
-- id=984 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5016.00', `price_5_9` = '5016.00', `price_10_19` = '5016.00', `price_20_plus` = '5016.00', `price_per_meter` = '167.20' WHERE `id` = 984;
-- id=985 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9856.20', `price_5_9` = '9856.20', `price_10_19` = '9856.20', `price_20_plus` = '9856.20', `price_per_meter` = '164.27' WHERE `id` = 985;
-- id=986 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6072.00', `price_5_9` = '6072.00', `price_10_19` = '6072.00', `price_20_plus` = '6072.00', `price_per_meter` = '202.40' WHERE `id` = 986;
-- id=987 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '11791.80', `price_5_9` = '11791.80', `price_10_19` = '11791.80', `price_20_plus` = '11791.80', `price_per_meter` = '196.53' WHERE `id` = 987;
-- id=988 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 988;
-- id=989 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6336.00', `price_5_9` = '6336.00', `price_10_19` = '6336.00', `price_20_plus` = '6336.00', `price_per_meter` = '211.20' WHERE `id` = 989;
-- id=990 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '12408.00', `price_5_9` = '12408.00', `price_10_19` = '12408.00', `price_20_plus` = '12408.00', `price_per_meter` = '206.80' WHERE `id` = 990;
-- id=991 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 991;
-- id=992 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9680.10', `price_5_9` = '9680.10', `price_10_19` = '9680.10', `price_20_plus` = '9680.10', `price_per_meter` = '322.67' WHERE `id` = 992;
-- id=993 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 993;
-- id=994 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6336.00', `price_5_9` = '6336.00', `price_10_19` = '6336.00', `price_20_plus` = '6336.00', `price_per_meter` = '211.20' WHERE `id` = 994;
-- id=995 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '12408.00', `price_5_9` = '12408.00', `price_10_19` = '12408.00', `price_20_plus` = '12408.00', `price_per_meter` = '206.80' WHERE `id` = 995;
-- id=996 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7920.00', `price_5_9` = '7920.00', `price_10_19` = '7920.00', `price_20_plus` = '7920.00', `price_per_meter` = '264.00' WHERE `id` = 996;
-- id=997 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5016.00', `price_5_9` = '5016.00', `price_10_19` = '5016.00', `price_20_plus` = '5016.00', `price_per_meter` = '167.20' WHERE `id` = 997;
-- id=998 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9856.20', `price_5_9` = '9856.20', `price_10_19` = '9856.20', `price_20_plus` = '9856.20', `price_per_meter` = '164.27' WHERE `id` = 998;
-- id=999 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '11791.80', `price_5_9` = '11791.80', `price_10_19` = '11791.80', `price_20_plus` = '11791.80', `price_per_meter` = '196.53' WHERE `id` = 999;
-- id=1000 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5016.00', `price_5_9` = '5016.00', `price_10_19` = '5016.00', `price_20_plus` = '5016.00', `price_per_meter` = '167.20' WHERE `id` = 1000;
-- id=1001 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6072.00', `price_5_9` = '6072.00', `price_10_19` = '6072.00', `price_20_plus` = '6072.00', `price_per_meter` = '202.40' WHERE `id` = 1001;
-- id=1002 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5016.00', `price_5_9` = '5016.00', `price_10_19` = '5016.00', `price_20_plus` = '5016.00', `price_per_meter` = '167.20' WHERE `id` = 1002;
-- id=1003 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 1003;
-- id=1004 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5368.20', `price_5_9` = '5368.20', `price_10_19` = '5368.20', `price_20_plus` = '5368.20', `price_per_meter` = '89.47' WHERE `id` = 1004;
-- id=1005 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 1005;
-- id=1006 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 1006;
-- id=1007 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5368.20', `price_5_9` = '5368.20', `price_10_19` = '5368.20', `price_20_plus` = '5368.20', `price_per_meter` = '89.47' WHERE `id` = 1007;
-- id=1008 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 1008;
-- id=1009 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6951.90', `price_5_9` = '6951.90', `price_10_19` = '6951.90', `price_20_plus` = '6951.90', `price_per_meter` = '231.73' WHERE `id` = 1009;
-- id=1010 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5984.10', `price_5_9` = '5984.10', `price_10_19` = '5984.10', `price_20_plus` = '5984.10', `price_per_meter` = '199.47' WHERE `id` = 1010;
-- id=1011 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5984.10', `price_5_9` = '5984.10', `price_10_19` = '5984.10', `price_20_plus` = '5984.10', `price_per_meter` = '199.47' WHERE `id` = 1011;
-- id=1012 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9240.00', `price_5_9` = '9240.00', `price_10_19` = '9240.00', `price_20_plus` = '9240.00', `price_per_meter` = '154.00' WHERE `id` = 1012;
-- id=1013 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9240.00', `price_5_9` = '9240.00', `price_10_19` = '9240.00', `price_20_plus` = '9240.00', `price_per_meter` = '154.00' WHERE `id` = 1013;
-- id=1014 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5367.90', `price_5_9` = '5367.90', `price_10_19` = '5367.90', `price_20_plus` = '5367.90', `price_per_meter` = '178.93' WHERE `id` = 1014;
-- id=1015 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7567.80', `price_5_9` = '7567.80', `price_10_19` = '7567.80', `price_20_plus` = '7567.80', `price_per_meter` = '126.13' WHERE `id` = 1015;
-- id=1016 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '4047.90', `price_5_9` = '4047.90', `price_10_19` = '4047.90', `price_20_plus` = '4047.90', `price_per_meter` = '134.93' WHERE `id` = 1016;
-- id=1017 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '7920.00', `price_5_9` = '7920.00', `price_10_19` = '7920.00', `price_20_plus` = '7920.00', `price_per_meter` = '132.00' WHERE `id` = 1017;
-- id=1018 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5016.00', `price_5_9` = '5016.00', `price_10_19` = '5016.00', `price_20_plus` = '5016.00', `price_per_meter` = '167.20' WHERE `id` = 1018;
-- id=1019 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '9856.20', `price_5_9` = '9856.20', `price_10_19` = '9856.20', `price_20_plus` = '9856.20', `price_per_meter` = '164.27' WHERE `id` = 1019;
-- id=1020 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5544.00', `price_5_9` = '5544.00', `price_10_19` = '5544.00', `price_20_plus` = '5544.00', `price_per_meter` = '184.80' WHERE `id` = 1020;
-- id=1021 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10824.00', `price_5_9` = '10824.00', `price_10_19` = '10824.00', `price_20_plus` = '10824.00', `price_per_meter` = '180.40' WHERE `id` = 1021;
-- id=1022 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5544.00', `price_5_9` = '5544.00', `price_10_19` = '5544.00', `price_20_plus` = '5544.00', `price_per_meter` = '184.80' WHERE `id` = 1022;
-- id=1023 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10824.00', `price_5_9` = '10824.00', `price_10_19` = '10824.00', `price_20_plus` = '10824.00', `price_per_meter` = '180.40' WHERE `id` = 1023;
-- id=1024 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5544.00', `price_5_9` = '5544.00', `price_10_19` = '5544.00', `price_20_plus` = '5544.00', `price_per_meter` = '184.80' WHERE `id` = 1024;
-- id=1025 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10824.00', `price_5_9` = '10824.00', `price_10_19` = '10824.00', `price_20_plus` = '10824.00', `price_per_meter` = '180.40' WHERE `id` = 1025;
-- id=1026 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5544.00', `price_5_9` = '5544.00', `price_10_19` = '5544.00', `price_20_plus` = '5544.00', `price_per_meter` = '184.80' WHERE `id` = 1026;
-- id=1027 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '10824.00', `price_5_9` = '10824.00', `price_10_19` = '10824.00', `price_20_plus` = '10824.00', `price_per_meter` = '180.40' WHERE `id` = 1027;
-- id=1028 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '5544.00', `price_5_9` = '5544.00', `price_10_19` = '5544.00', `price_20_plus` = '5544.00', `price_per_meter` = '184.80' WHERE `id` = 1028;
-- id=1029 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6951.90', `price_5_9` = '6951.90', `price_10_19` = '6951.90', `price_20_plus` = '6951.90', `price_per_meter` = '231.73' WHERE `id` = 1029;
-- id=1030 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '6951.90', `price_5_9` = '6951.90', `price_10_19` = '6951.90', `price_20_plus` = '6951.90', `price_per_meter` = '231.73' WHERE `id` = 1030;
-- id=1031 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '57199.95', `price_5_9` = '57199.95', `price_10_19` = '57199.95', `price_20_plus` = '57199.95', `price_per_meter` = '3813.33' WHERE `id` = 1031;
-- id=1032 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '79200.00', `price_5_9` = '79200.00', `price_10_19` = '79200.00', `price_20_plus` = '79200.00', `price_per_meter` = '5280.00' WHERE `id` = 1032;
-- id=1033 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '83599.95', `price_5_9` = '83599.95', `price_10_19` = '83599.95', `price_20_plus` = '83599.95', `price_per_meter` = '5573.33' WHERE `id` = 1033;
-- id=1034 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '79200.00', `price_5_9` = '79200.00', `price_10_19` = '79200.00', `price_20_plus` = '79200.00', `price_per_meter` = '5280.00' WHERE `id` = 1034;
-- id=1035 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26136.00', `price_5_9` = '26136.00', `price_10_19` = '26136.00', `price_20_plus` = '26136.00', `price_per_meter` = '1742.40' WHERE `id` = 1035;
-- id=1036 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '28159.95', `price_5_9` = '28159.95', `price_10_19` = '28159.95', `price_20_plus` = '28159.95', `price_per_meter` = '1877.33' WHERE `id` = 1036;
-- id=1037 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '32208.00', `price_5_9` = '32208.00', `price_10_19` = '32208.00', `price_20_plus` = '32208.00', `price_per_meter` = '2147.20' WHERE `id` = 1037;
-- id=1038 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '27456.00', `price_5_9` = '27456.00', `price_10_19` = '27456.00', `price_20_plus` = '27456.00', `price_per_meter` = '1830.40' WHERE `id` = 1038;
-- id=1039 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '27456.00', `price_5_9` = '27456.00', `price_10_19` = '27456.00', `price_20_plus` = '27456.00', `price_per_meter` = '1830.40' WHERE `id` = 1039;
-- id=1040 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '30799.95', `price_5_9` = '30799.95', `price_10_19` = '30799.95', `price_20_plus` = '30799.95', `price_per_meter` = '2053.33' WHERE `id` = 1040;
-- id=1041 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '17248.05', `price_5_9` = '17248.05', `price_10_19` = '17248.05', `price_20_plus` = '17248.05', `price_per_meter` = '1149.87' WHERE `id` = 1041;
-- id=1042 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '26136.00', `price_5_9` = '26136.00', `price_10_19` = '26136.00', `price_20_plus` = '26136.00', `price_per_meter` = '1742.40' WHERE `id` = 1042;
-- id=1043 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '28159.95', `price_5_9` = '28159.95', `price_10_19` = '28159.95', `price_20_plus` = '28159.95', `price_per_meter` = '1877.33' WHERE `id` = 1043;
-- id=1044 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '38280.00', `price_5_9` = '38280.00', `price_10_19` = '38280.00', `price_20_plus` = '38280.00', `price_per_meter` = '2552.00' WHERE `id` = 1044;
-- id=1045 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '38280.00', `price_5_9` = '38280.00', `price_10_19` = '38280.00', `price_20_plus` = '38280.00', `price_per_meter` = '2552.00' WHERE `id` = 1045;
-- id=1046 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '38280.00', `price_5_9` = '38280.00', `price_10_19` = '38280.00', `price_20_plus` = '38280.00', `price_per_meter` = '2552.00' WHERE `id` = 1046;
-- id=1047 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '24112.05', `price_5_9` = '24112.05', `price_10_19` = '24112.05', `price_20_plus` = '24112.05', `price_per_meter` = '1607.47' WHERE `id` = 1047;
-- id=1048 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '14080.05', `price_5_9` = '14080.05', `price_10_19` = '14080.05', `price_20_plus` = '14080.05', `price_per_meter` = '938.67' WHERE `id` = 1048;
-- id=1049 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '14080.05', `price_5_9` = '14080.05', `price_10_19` = '14080.05', `price_20_plus` = '14080.05', `price_per_meter` = '938.67' WHERE `id` = 1049;
-- id=1050 — fallback tiers = price_per_meter×roll из products.sql
UPDATE `products` SET `price_1_4` = '14080.05', `price_5_9` = '14080.05', `price_10_19` = '14080.05', `price_20_plus` = '14080.05', `price_per_meter` = '938.67' WHERE `id` = 1050;
COMMIT;
