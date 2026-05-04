-- Продажа (только рулоны): закупку, доставку, purchase_delivered_per_meter не трогаем.
-- По умолчанию обновляются только price_1_4 … price_20_plus (цена за метр — отдельно в приложении/приходе).
-- Чтобы включить price_per_meter из LLumar как раньше: node build_sales_tiers_only_sql.js --with-meter
-- Источник LLumar: products_full_import_prices_usd_x88.sql (node build_products_prices_usd_x88.js);
-- пустые J/K → хвост ступени как последняя заданная колонка H–K.
-- Остальные id — из products.sql; = NULL не пишем.
-- products.php: пустой POST числовых полей не перезаписывает значение через COALESCE(?, column).
-- Regenerate: node example/new/build_sales_tiers_only_sql.js [--with-meter]
-- Rows: 240 UPDATE statements.
-- Generated: 2026-05-04T14:59:03.099Z

SET NAMES utf8mb4;
START TRANSACTION;

-- id=26
UPDATE `products` SET `price_1_4` = '127600.00', `price_5_9` = '110880.00', `price_10_19` = '110880.00', `price_20_plus` = '110880.00' WHERE `id` = 26;
-- id=27
UPDATE `products` SET `price_1_4` = '121440.00', `price_5_9` = '105600.00', `price_10_19` = '105600.00', `price_20_plus` = '105600.00' WHERE `id` = 27;
-- id=28
UPDATE `products` SET `price_1_4` = '121440.00', `price_5_9` = '105600.00', `price_10_19` = '105600.00', `price_20_plus` = '105600.00' WHERE `id` = 28;
-- id=29
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00' WHERE `id` = 29;
-- id=30
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00' WHERE `id` = 30;
-- id=31
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00' WHERE `id` = 31;
-- id=32
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00' WHERE `id` = 32;
-- id=33
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00' WHERE `id` = 33;
-- id=34
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '30800.00', `price_20_plus` = '30800.00' WHERE `id` = 34;
-- id=35
UPDATE `products` SET `price_1_4` = '41360.00', `price_5_9` = '36080.00', `price_10_19` = '36080.00', `price_20_plus` = '36080.00' WHERE `id` = 35;
-- id=36
UPDATE `products` SET `price_1_4` = '41360.00', `price_5_9` = '36080.00', `price_10_19` = '36080.00', `price_20_plus` = '36080.00' WHERE `id` = 36;
-- id=37
UPDATE `products` SET `price_1_4` = '41360.00', `price_5_9` = '36080.00', `price_10_19` = '36080.00', `price_20_plus` = '36080.00' WHERE `id` = 37;
-- id=38
UPDATE `products` SET `price_1_4` = '41360.00', `price_5_9` = '36080.00', `price_10_19` = '36080.00', `price_20_plus` = '36080.00' WHERE `id` = 38;
-- id=39
UPDATE `products` SET `price_1_4` = '41360.00', `price_5_9` = '36080.00', `price_10_19` = '36080.00', `price_20_plus` = '36080.00' WHERE `id` = 39;
-- id=40
UPDATE `products` SET `price_1_4` = '124080.00', `price_5_9` = '107360.00', `price_10_19` = '107360.00', `price_20_plus` = '107360.00' WHERE `id` = 40;
-- id=41
UPDATE `products` SET `price_1_4` = '74800.00', `price_5_9` = '64240.00', `price_10_19` = '64240.00', `price_20_plus` = '64240.00' WHERE `id` = 41;
-- id=42
UPDATE `products` SET `price_1_4` = '124080.00', `price_5_9` = '107360.00', `price_10_19` = '107360.00', `price_20_plus` = '107360.00' WHERE `id` = 42;
-- id=43
UPDATE `products` SET `price_1_4` = '74800.00', `price_5_9` = '64240.00', `price_10_19` = '64240.00', `price_20_plus` = '64240.00' WHERE `id` = 43;
-- id=44
UPDATE `products` SET `price_1_4` = '124080.00', `price_5_9` = '107360.00', `price_10_19` = '107360.00', `price_20_plus` = '107360.00' WHERE `id` = 44;
-- id=45
UPDATE `products` SET `price_1_4` = '148720.00', `price_5_9` = '128480.00', `price_10_19` = '128480.00', `price_20_plus` = '128480.00' WHERE `id` = 45;
-- id=46
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00' WHERE `id` = 46;
-- id=47
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00' WHERE `id` = 47;
-- id=48
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00' WHERE `id` = 48;
-- id=49
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00' WHERE `id` = 49;
-- id=50
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00' WHERE `id` = 50;
-- id=51
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00' WHERE `id` = 51;
-- id=52
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8888.00', `price_10_19` = '7392.00', `price_20_plus` = '7392.00' WHERE `id` = 52;
-- id=53
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00' WHERE `id` = 53;
-- id=54
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00' WHERE `id` = 54;
-- id=55
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00' WHERE `id` = 55;
-- id=56
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00' WHERE `id` = 56;
-- id=57
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00' WHERE `id` = 57;
-- id=58
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00' WHERE `id` = 58;
-- id=59
UPDATE `products` SET `price_1_4` = '11528.00', `price_5_9` = '10208.00', `price_10_19` = '8448.00', `price_20_plus` = '8448.00' WHERE `id` = 59;
-- id=60
UPDATE `products` SET `price_1_4` = '22000.00', `price_5_9` = '19448.00', `price_10_19` = '16192.00', `price_20_plus` = '16192.00' WHERE `id` = 60;
-- id=61
UPDATE `products` SET `price_1_4` = '24200.00', `price_5_9` = '21384.00', `price_10_19` = '17864.00', `price_20_plus` = '17864.00' WHERE `id` = 61;
-- id=62
UPDATE `products` SET `price_1_4` = '24200.00', `price_5_9` = '21384.00', `price_10_19` = '17864.00', `price_20_plus` = '17864.00' WHERE `id` = 62;
-- id=63
UPDATE `products` SET `price_1_4` = '24200.00', `price_5_9` = '21384.00', `price_10_19` = '17864.00', `price_20_plus` = '17864.00' WHERE `id` = 63;
-- id=64
UPDATE `products` SET `price_1_4` = '10912.00', `price_5_9` = '9592.00', `price_10_19` = '8008.00', `price_20_plus` = '8008.00' WHERE `id` = 64;
-- id=65
UPDATE `products` SET `price_1_4` = '10912.00', `price_5_9` = '9592.00', `price_10_19` = '8008.00', `price_20_plus` = '8008.00' WHERE `id` = 65;
-- id=66
UPDATE `products` SET `price_1_4` = '10912.00', `price_5_9` = '9592.00', `price_10_19` = '8008.00', `price_20_plus` = '8008.00' WHERE `id` = 66;
-- id=67
UPDATE `products` SET `price_1_4` = '10912.00', `price_5_9` = '9592.00', `price_10_19` = '8008.00', `price_20_plus` = '8008.00' WHERE `id` = 67;
-- id=68
UPDATE `products` SET `price_1_4` = '10912.00', `price_5_9` = '9592.00', `price_10_19` = '8008.00', `price_20_plus` = '8008.00' WHERE `id` = 68;
-- id=71
UPDATE `products` SET `price_1_4` = '14608.00', `price_5_9` = '12848.00', `price_10_19` = '10736.00', `price_20_plus` = '10736.00' WHERE `id` = 71;
-- id=72
UPDATE `products` SET `price_1_4` = '12408.00', `price_5_9` = '11000.00', `price_10_19` = '9152.00', `price_20_plus` = '9152.00' WHERE `id` = 72;
-- id=74
UPDATE `products` SET `price_1_4` = '3150.00', `price_5_9` = '3150.00', `price_20_plus` = '3937.50' WHERE `id` = 74;
-- id=75
UPDATE `products` SET `price_1_4` = '5456.00', `price_5_9` = '4840.00', `price_10_19` = '4048.00', `price_20_plus` = '4048.00' WHERE `id` = 75;
-- id=76
UPDATE `products` SET `price_1_4` = '5456.00', `price_5_9` = '4840.00', `price_10_19` = '4048.00', `price_20_plus` = '4048.00' WHERE `id` = 76;
-- id=77
UPDATE `products` SET `price_1_4` = '5456.00', `price_5_9` = '4840.00', `price_10_19` = '4048.00', `price_20_plus` = '4048.00' WHERE `id` = 77;
-- id=78
UPDATE `products` SET `price_1_4` = '5456.00', `price_5_9` = '4840.00', `price_10_19` = '4048.00', `price_20_plus` = '4048.00' WHERE `id` = 78;
-- id=79
UPDATE `products` SET `price_1_4` = '5456.00', `price_5_9` = '4840.00', `price_10_19` = '4048.00', `price_20_plus` = '4048.00' WHERE `id` = 79;
-- id=80
UPDATE `products` SET `price_1_4` = '91872.00', `price_5_9` = '91872.00', `price_10_19` = '91872.00', `price_20_plus` = '91872.00' WHERE `id` = 80;
-- id=81
UPDATE `products` SET `price_1_4` = '117128.00', `price_5_9` = '88.00', `price_10_19` = '88.00', `price_20_plus` = '88.00' WHERE `id` = 81;
-- id=82
UPDATE `products` SET `price_1_4` = '85888.00', `price_5_9` = '85888.00', `price_10_19` = '85888.00', `price_20_plus` = '85888.00' WHERE `id` = 82;
-- id=83
UPDATE `products` SET `price_1_4` = '101728.00', `price_5_9` = '101728.00', `price_10_19` = '101728.00', `price_20_plus` = '101728.00' WHERE `id` = 83;
-- id=84
UPDATE `products` SET `price_1_4` = '35640.00', `price_5_9` = '31856.00', `price_10_19` = '28160.00', `price_20_plus` = '28160.00' WHERE `id` = 84;
-- id=85
UPDATE `products` SET `price_1_4` = '32736.00', `price_5_9` = '29304.00', `price_10_19` = '25872.00', `price_20_plus` = '25872.00' WHERE `id` = 85;
-- id=86
UPDATE `products` SET `price_1_4` = '35640.00', `price_5_9` = '31856.00', `price_10_19` = '28160.00', `price_20_plus` = '28160.00' WHERE `id` = 86;
-- id=92
UPDATE `products` SET `price_1_4` = '157432.00', `price_5_9` = '157432.00', `price_10_19` = '157432.00', `price_20_plus` = '157432.00' WHERE `id` = 92;
-- id=93
UPDATE `products` SET `price_1_4` = '46552.00', `price_5_9` = '46552.00', `price_10_19` = '46552.00', `price_20_plus` = '46552.00' WHERE `id` = 93;
-- id=94
UPDATE `products` SET `price_1_4` = '49632.00', `price_5_9` = '44440.00', `price_10_19` = '39248.00', `price_20_plus` = '39248.00' WHERE `id` = 94;
-- id=95
UPDATE `products` SET `price_1_4` = '53504.00', `price_5_9` = '47872.00', `price_10_19` = '42240.00', `price_20_plus` = '42240.00' WHERE `id` = 95;
-- id=96
UPDATE `products` SET `price_1_4` = '61160.00', `price_5_9` = '54736.00', `price_10_19` = '48312.00', `price_20_plus` = '48312.00' WHERE `id` = 96;
-- id=97
UPDATE `products` SET `price_1_4` = '52184.00', `price_5_9` = '46640.00', `price_10_19` = '41184.00', `price_20_plus` = '41184.00' WHERE `id` = 97;
-- id=98
UPDATE `products` SET `price_1_4` = '52184.00', `price_5_9` = '46640.00', `price_10_19` = '41184.00', `price_20_plus` = '41184.00' WHERE `id` = 98;
-- id=99
UPDATE `products` SET `price_1_4` = '56848.00', `price_5_9` = '50864.00', `price_10_19` = '44880.00', `price_20_plus` = '44880.00' WHERE `id` = 99;
-- id=100
UPDATE `products` SET `price_1_4` = '58520.00', `price_5_9` = '52360.00', `price_10_19` = '46200.00', `price_20_plus` = '46200.00' WHERE `id` = 100;
-- id=101
UPDATE `products` SET `price_1_4` = '62656.00', `price_5_9` = '58784.00', `price_10_19` = '5104.00', `price_20_plus` = '5104.00' WHERE `id` = 101;
-- id=102
UPDATE `products` SET `price_1_4` = '77704.00', `price_5_9` = '72864.00', `price_10_19` = '352.00', `price_20_plus` = '352.00' WHERE `id` = 102;
-- id=103
UPDATE `products` SET `price_1_4` = '48048.00', `price_5_9` = '45056.00', `price_10_19` = '1848.00', `price_20_plus` = '1848.00' WHERE `id` = 103;
-- id=104
UPDATE `products` SET `price_1_4` = '50160.00', `price_5_9` = '46992.00', `price_10_19` = '968.00', `price_20_plus` = '968.00' WHERE `id` = 104;
-- id=105
UPDATE `products` SET `price_1_4` = '60620.00', `price_5_9` = '56831.25', `price_10_19` = '56831.25', `price_20_plus` = '56831.25' WHERE `id` = 105;
-- id=106
UPDATE `products` SET `price_1_4` = '60620.00', `price_5_9` = '56831.25', `price_10_19` = '56831.25', `price_20_plus` = '56831.25' WHERE `id` = 106;
-- id=107
UPDATE `products` SET `price_1_4` = '20416.00', `price_5_9` = '19184.00', `price_10_19` = '264.00', `price_20_plus` = '264.00' WHERE `id` = 107;
-- id=116
UPDATE `products` SET `price_1_4` = '52800.00', `price_5_9` = '52800.00', `price_10_19` = '44000.00', `price_20_plus` = '44000.00' WHERE `id` = 116;
-- id=117
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '70400.00', `price_10_19` = '61600.00', `price_20_plus` = '61600.00' WHERE `id` = 117;
-- id=118
UPDATE `products` SET `price_1_4` = '74800.00', `price_5_9` = '74800.00', `price_10_19` = '66000.00', `price_20_plus` = '66000.00' WHERE `id` = 118;
-- id=119
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '70400.00', `price_10_19` = '61600.00', `price_20_plus` = '61600.00' WHERE `id` = 119;
-- id=120
UPDATE `products` SET `price_1_4` = '49632.00', `price_5_9` = '44440.00', `price_10_19` = '39248.00', `price_20_plus` = '39248.00' WHERE `id` = 120;
-- id=121
UPDATE `products` SET `price_1_4` = '53504.00', `price_5_9` = '47872.00', `price_10_19` = '42240.00', `price_20_plus` = '42240.00' WHERE `id` = 121;
-- id=122
UPDATE `products` SET `price_1_4` = '52184.00', `price_5_9` = '46640.00', `price_10_19` = '41184.00', `price_20_plus` = '41184.00' WHERE `id` = 122;
-- id=123
UPDATE `products` SET `price_1_4` = '35640.00', `price_5_9` = '31856.00', `price_10_19` = '28160.00', `price_20_plus` = '28160.00' WHERE `id` = 123;
-- id=124
UPDATE `products` SET `price_1_4` = '72776.00', `price_5_9` = '65120.00', `price_10_19` = '57464.00', `price_20_plus` = '57464.00' WHERE `id` = 124;
-- id=125
UPDATE `products` SET `price_1_4` = '72776.00', `price_5_9` = '65120.00', `price_10_19` = '57464.00', `price_20_plus` = '57464.00' WHERE `id` = 125;
-- id=126
UPDATE `products` SET `price_1_4` = '72776.00', `price_5_9` = '65120.00', `price_10_19` = '57464.00', `price_20_plus` = '57464.00' WHERE `id` = 126;
-- id=127
UPDATE `products` SET `price_1_4` = '45848.00', `price_5_9` = '41008.00', `price_10_19` = '36168.00', `price_20_plus` = '36168.00' WHERE `id` = 127;
-- id=128
UPDATE `products` SET `price_1_4` = '26752.00', `price_5_9` = '23936.00', `price_10_19` = '21120.00', `price_20_plus` = '21120.00' WHERE `id` = 128;
-- id=129
UPDATE `products` SET `price_1_4` = '26752.00', `price_5_9` = '23936.00', `price_10_19` = '21120.00', `price_20_plus` = '21120.00' WHERE `id` = 129;
-- id=130
UPDATE `products` SET `price_1_4` = '26752.00', `price_5_9` = '23936.00', `price_10_19` = '21120.00', `price_20_plus` = '21120.00' WHERE `id` = 130;
-- id=131
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '26400.00', `price_20_plus` = '26400.00' WHERE `id` = 131;
-- id=132
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '30800.00', `price_10_19` = '26400.00', `price_20_plus` = '26400.00' WHERE `id` = 132;
-- id=135
UPDATE `products` SET `price_1_4` = '147400.00', `price_5_9` = '135608.00', `price_10_19` = '4400.00', `price_20_plus` = '4400.00' WHERE `id` = 135;
-- id=136
UPDATE `products` SET `price_1_4` = '5104.00', `price_5_9` = '5104.00', `price_10_19` = '12760.00', `price_20_plus` = '12760.00' WHERE `id` = 136;
-- id=137
UPDATE `products` SET `price_1_4` = '184800.00', `price_5_9` = '170016.00', `price_10_19` = '264.00', `price_20_plus` = '264.00' WHERE `id` = 137;
-- id=138
UPDATE `products` SET `price_1_4` = '169400.00', `price_5_9` = '155848.00', `price_10_19` = '3344.00', `price_20_plus` = '3344.00' WHERE `id` = 138;
-- id=139
UPDATE `products` SET `price_1_4` = '234520.00', `price_5_9` = '215776.00', `price_10_19` = '215776.00', `price_20_plus` = '215776.00' WHERE `id` = 139;
-- id=140
UPDATE `products` SET `price_1_4` = '184800.00', `price_5_9` = '170016.00', `price_10_19` = '352.00', `price_20_plus` = '352.00' WHERE `id` = 140;
-- id=142
UPDATE `products` SET `price_1_4` = '147400.00', `price_5_9` = '135608.00', `price_10_19` = '176.00', `price_20_plus` = '176.00' WHERE `id` = 142;
-- id=143
UPDATE `products` SET `price_1_4` = '192808.00', `price_5_9` = '177408.00', `price_10_19` = '352.00', `price_20_plus` = '352.00' WHERE `id` = 143;
-- id=144
UPDATE `products` SET `price_1_4` = '109736.00', `price_5_9` = '101904.00', `price_10_19` = '101904.00', `price_20_plus` = '101904.00' WHERE `id` = 144;
-- id=145
UPDATE `products` SET `price_1_4` = '97680.00', `price_5_9` = '90728.00', `price_10_19` = '90728.00', `price_20_plus` = '90728.00' WHERE `id` = 145;
-- id=146
UPDATE `products` SET `price_1_4` = '120384.00', `price_5_9` = '111760.00', `price_10_19` = '111760.00', `price_20_plus` = '111760.00' WHERE `id` = 146;
-- id=147
UPDATE `products` SET `price_1_4` = '191576.00', `price_5_9` = '177936.00', `price_10_19` = '177936.00', `price_20_plus` = '177936.00' WHERE `id` = 147;
-- id=148
UPDATE `products` SET `price_1_4` = '163240.00', `price_5_9` = '151624.00', `price_10_19` = '151624.00', `price_20_plus` = '151624.00' WHERE `id` = 148;
-- id=149
UPDATE `products` SET `price_1_4` = '65208.00', `price_5_9` = '65208.00', `price_10_19` = '65208.00', `price_20_plus` = '65208.00' WHERE `id` = 149;
-- id=150
UPDATE `products` SET `price_1_4` = '123288.00', `price_5_9` = '123288.00', `price_10_19` = '123288.00', `price_20_plus` = '123288.00' WHERE `id` = 150;
-- id=151
UPDATE `products` SET `price_1_4` = '173888.00', `price_5_9` = '173888.00', `price_10_19` = '173888.00', `price_20_plus` = '173888.00' WHERE `id` = 151;
-- id=152
UPDATE `products` SET `price_1_4` = '286000.00', `price_5_9` = '286000.00', `price_10_19` = '286000.00', `price_20_plus` = '286000.00' WHERE `id` = 152;
-- id=153
UPDATE `products` SET `price_1_4` = '52272.00', `price_5_9` = '352.00', `price_10_19` = '352.00', `price_20_plus` = '352.00' WHERE `id` = 153;
-- id=154
UPDATE `products` SET `price_1_4` = '53240.00', `price_5_9` = '53240.00', `price_10_19` = '53240.00', `price_20_plus` = '53240.00' WHERE `id` = 154;
-- id=155
UPDATE `products` SET `price_1_4` = '53240.00', `price_5_9` = '53240.00', `price_10_19` = '53240.00', `price_20_plus` = '53240.00' WHERE `id` = 155;
-- id=156
UPDATE `products` SET `price_1_4` = '49720.00', `price_5_9` = '49720.00', `price_10_19` = '49720.00', `price_20_plus` = '49720.00' WHERE `id` = 156;
-- id=157
UPDATE `products` SET `price_1_4` = '109736.00', `price_5_9` = '101904.00', `price_10_19` = '101904.00', `price_20_plus` = '101904.00' WHERE `id` = 157;
-- id=158
UPDATE `products` SET `price_1_4` = '97680.00', `price_5_9` = '90728.00', `price_10_19` = '90728.00', `price_20_plus` = '90728.00' WHERE `id` = 158;
-- id=159
UPDATE `products` SET `price_1_4` = '120384.00', `price_5_9` = '111760.00', `price_10_19` = '111760.00', `price_20_plus` = '111760.00' WHERE `id` = 159;
-- id=160
UPDATE `products` SET `price_1_4` = '191576.00', `price_5_9` = '177936.00', `price_10_19` = '177936.00', `price_20_plus` = '177936.00' WHERE `id` = 160;
-- id=161
UPDATE `products` SET `price_1_4` = '163240.00', `price_5_9` = '151624.00', `price_10_19` = '151624.00', `price_20_plus` = '151624.00' WHERE `id` = 161;
-- id=162
UPDATE `products` SET `price_1_4` = '674.63', `price_5_9` = '674.63', `price_20_plus` = '674.63' WHERE `id` = 162;
-- id=163
UPDATE `products` SET `price_1_4` = '1350.13', `price_5_9` = '1350.13', `price_20_plus` = '1350.13' WHERE `id` = 163;
-- id=164
UPDATE `products` SET `price_1_4` = '780.50', `price_5_9` = '780.50', `price_20_plus` = '780.50' WHERE `id` = 164;
-- id=165
UPDATE `products` SET `price_1_4` = '254.63', `price_5_9` = '254.63', `price_20_plus` = '254.63' WHERE `id` = 165;
-- id=166
UPDATE `products` SET `price_1_4` = '11880.00', `price_5_9` = '11880.00', `price_10_19` = '11880.00', `price_20_plus` = '11880.00' WHERE `id` = 166;
-- id=167
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '60720.00', `price_10_19` = '60720.00', `price_20_plus` = '60720.00' WHERE `id` = 167;
-- id=168
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '60720.00', `price_10_19` = '60720.00', `price_20_plus` = '60720.00' WHERE `id` = 168;
-- id=169
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '60720.00', `price_10_19` = '60720.00', `price_20_plus` = '60720.00' WHERE `id` = 169;
-- id=170
UPDATE `products` SET `price_1_4` = '70400.00', `price_5_9` = '60720.00', `price_10_19` = '60720.00', `price_20_plus` = '60720.00' WHERE `id` = 170;
-- id=171
UPDATE `products` SET `price_1_4` = '25375.00', `price_5_9` = '25375.00', `price_20_plus` = '26250.00' WHERE `id` = 171;
-- id=172
UPDATE `products` SET `price_1_4` = '437.50', `price_5_9` = '0.00', `price_20_plus` = '17500.00' WHERE `id` = 172;
-- id=173
UPDATE `products` SET `price_1_4` = '66000.00', `price_5_9` = '66000.00', `price_10_19` = '66000.00', `price_20_plus` = '66000.00' WHERE `id` = 173;
-- id=174
UPDATE `products` SET `price_1_4` = '79200.00', `price_5_9` = '79200.00', `price_10_19` = '79200.00', `price_20_plus` = '79200.00' WHERE `id` = 174;
-- id=175
UPDATE `products` SET `price_1_4` = '352.00', `price_5_9` = '352.00', `price_10_19` = '352.00', `price_20_plus` = '352.00' WHERE `id` = 175;
-- id=177
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '35200.00', `price_10_19` = '35200.00', `price_20_plus` = '35200.00' WHERE `id` = 177;
-- id=178
UPDATE `products` SET `price_1_4` = '26400.00', `price_5_9` = '26400.00', `price_10_19` = '26400.00', `price_20_plus` = '26400.00' WHERE `id` = 178;
-- id=179
UPDATE `products` SET `price_1_4` = '13200.00', `price_5_9` = '13200.00', `price_10_19` = '13200.00', `price_20_plus` = '13200.00' WHERE `id` = 179;
-- id=180
UPDATE `products` SET `price_1_4` = '57200.00', `price_5_9` = '57200.00', `price_10_19` = '57200.00', `price_20_plus` = '57200.00' WHERE `id` = 180;
-- id=181
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '17600.00', `price_10_19` = '17600.00', `price_20_plus` = '17600.00' WHERE `id` = 181;
-- id=182
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '17600.00', `price_10_19` = '17600.00', `price_20_plus` = '17600.00' WHERE `id` = 182;
-- id=183
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '17600.00', `price_10_19` = '17600.00', `price_20_plus` = '17600.00' WHERE `id` = 183;
-- id=185
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '17600.00', `price_10_19` = '17600.00', `price_20_plus` = '17600.00' WHERE `id` = 185;
-- id=186
UPDATE `products` SET `price_1_4` = '176.00', `price_5_9` = '176.00', `price_10_19` = '176.00', `price_20_plus` = '176.00' WHERE `id` = 186;
-- id=187
UPDATE `products` SET `price_1_4` = '176.00', `price_5_9` = '176.00', `price_10_19` = '176.00', `price_20_plus` = '176.00' WHERE `id` = 187;
-- id=188
UPDATE `products` SET `price_1_4` = '13200.00', `price_5_9` = '13200.00', `price_10_19` = '13200.00', `price_20_plus` = '13200.00' WHERE `id` = 188;
-- id=189
UPDATE `products` SET `price_1_4` = '13200.00', `price_5_9` = '13200.00', `price_10_19` = '13200.00', `price_20_plus` = '13200.00' WHERE `id` = 189;
-- id=190
UPDATE `products` SET `price_1_4` = '13200.00', `price_5_9` = '13200.00', `price_10_19` = '13200.00', `price_20_plus` = '13200.00' WHERE `id` = 190;
-- id=191
UPDATE `products` SET `price_1_4` = '9064.00', `price_5_9` = '7480.00', `price_10_19` = '3872.00', `price_20_plus` = '3872.00' WHERE `id` = 191;
-- id=192
UPDATE `products` SET `price_1_4` = '12320.00', `price_5_9` = '10120.00', `price_10_19` = '5280.00', `price_20_plus` = '5280.00' WHERE `id` = 192;
-- id=193
UPDATE `products` SET `price_1_4` = '22968.00', `price_5_9` = '18920.00', `price_10_19` = '9856.00', `price_20_plus` = '9856.00' WHERE `id` = 193;
-- id=194
UPDATE `products` SET `price_1_4` = '35288.00', `price_5_9` = '29040.00', `price_10_19` = '15136.00', `price_20_plus` = '15136.00' WHERE `id` = 194;
-- id=195
UPDATE `products` SET `price_1_4` = '21208.00', `price_5_9` = '17424.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 195;
-- id=196
UPDATE `products` SET `price_1_4` = '28248.00', `price_5_9` = '23144.00', `price_10_19` = '12056.00', `price_20_plus` = '12056.00' WHERE `id` = 196;
-- id=197
UPDATE `products` SET `price_1_4` = '41800.00', `price_5_9` = '34320.00', `price_10_19` = '17952.00', `price_20_plus` = '17952.00' WHERE `id` = 197;
-- id=198
UPDATE `products` SET `price_1_4` = '22264.00', `price_5_9` = '18216.00', `price_10_19` = '13464.00', `price_20_plus` = '13464.00' WHERE `id` = 198;
-- id=199
UPDATE `products` SET `price_1_4` = '10032.00', `price_5_9` = '8272.00', `price_10_19` = '6072.00', `price_20_plus` = '6072.00' WHERE `id` = 199;
-- id=200
UPDATE `products` SET `price_1_4` = '11352.00', `price_5_9` = '9328.00', `price_10_19` = '6952.00', `price_20_plus` = '6952.00' WHERE `id` = 200;
-- id=201
UPDATE `products` SET `price_1_4` = '12760.00', `price_5_9` = '10472.00', `price_10_19` = '7744.00', `price_20_plus` = '7744.00' WHERE `id` = 201;
-- id=202
UPDATE `products` SET `price_1_4` = '18128.00', `price_5_9` = '14960.00', `price_10_19` = '11000.00', `price_20_plus` = '11000.00' WHERE `id` = 202;
-- id=203
UPDATE `products` SET `price_1_4` = '25784.00', `price_5_9` = '21120.00', `price_10_19` = '15664.00', `price_20_plus` = '15664.00' WHERE `id` = 203;
-- id=204
UPDATE `products` SET `price_1_4` = '35200.00', `price_5_9` = '28952.00', `price_10_19` = '21384.00', `price_20_plus` = '21384.00' WHERE `id` = 204;
-- id=205
UPDATE `products` SET `price_1_4` = '48752.00', `price_5_9` = '40040.00', `price_10_19` = '29656.00', `price_20_plus` = '29656.00' WHERE `id` = 205;
-- id=206
UPDATE `products` SET `price_1_4` = '32560.00', `price_5_9` = '26752.00', `price_10_19` = '19712.00', `price_20_plus` = '19712.00' WHERE `id` = 206;
-- id=207
UPDATE `products` SET `price_1_4` = '29832.00', `price_5_9` = '24464.00', `price_10_19` = '18128.00', `price_20_plus` = '18128.00' WHERE `id` = 207;
-- id=208
UPDATE `products` SET `price_1_4` = '46112.00', `price_5_9` = '37840.00', `price_10_19` = '27984.00', `price_20_plus` = '27984.00' WHERE `id` = 208;
-- id=209
UPDATE `products` SET `price_1_4` = '57464.00', `price_5_9` = '47168.00', `price_10_19` = '34848.00', `price_20_plus` = '34848.00' WHERE `id` = 209;
-- id=210
UPDATE `products` SET `price_1_4` = '57464.00', `price_5_9` = '47168.00', `price_10_19` = '34848.00', `price_20_plus` = '34848.00' WHERE `id` = 210;
-- id=211
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 211;
-- id=212
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 212;
-- id=213
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 213;
-- id=214
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 214;
-- id=215
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 215;
-- id=216
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 216;
-- id=217
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 217;
-- id=218
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 218;
-- id=219
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 219;
-- id=220
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 220;
-- id=221
UPDATE `products` SET `price_1_4` = '54208.00', `price_5_9` = '44528.00', `price_10_19` = '32912.00', `price_20_plus` = '32912.00' WHERE `id` = 221;
-- id=222
UPDATE `products` SET `price_1_4` = '20328.00', `price_5_9` = '16720.00', `price_10_19` = '12320.00', `price_20_plus` = '12320.00' WHERE `id` = 222;
-- id=223
UPDATE `products` SET `price_1_4` = '19536.00', `price_5_9` = '16016.00', `price_10_19` = '11880.00', `price_20_plus` = '11880.00' WHERE `id` = 223;
-- id=224
UPDATE `products` SET `price_1_4` = '14080.00', `price_5_9` = '11616.00', `price_10_19` = '8536.00', `price_20_plus` = '8536.00' WHERE `id` = 224;
-- id=225
UPDATE `products` SET `price_1_4` = '27632.00', `price_5_9` = '22704.00', `price_10_19` = '16808.00', `price_20_plus` = '16808.00' WHERE `id` = 225;
-- id=226
UPDATE `products` SET `price_1_4` = '17072.00', `price_5_9` = '13992.00', `price_10_19` = '10384.00', `price_20_plus` = '10384.00' WHERE `id` = 226;
-- id=227
UPDATE `products` SET `price_1_4` = '33088.00', `price_5_9` = '27192.00', `price_10_19` = '20064.00', `price_20_plus` = '20064.00' WHERE `id` = 227;
-- id=228
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 228;
-- id=229
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '14432.00', `price_10_19` = '10736.00', `price_20_plus` = '10736.00' WHERE `id` = 229;
-- id=230
UPDATE `products` SET `price_1_4` = '34672.00', `price_5_9` = '28512.00', `price_10_19` = '21032.00', `price_20_plus` = '21032.00' WHERE `id` = 230;
-- id=231
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 231;
-- id=232
UPDATE `products` SET `price_1_4` = '27104.00', `price_5_9` = '22264.00', `price_10_19` = '16456.00', `price_20_plus` = '16456.00' WHERE `id` = 232;
-- id=233
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 233;
-- id=234
UPDATE `products` SET `price_1_4` = '17600.00', `price_5_9` = '14432.00', `price_10_19` = '10736.00', `price_20_plus` = '10736.00' WHERE `id` = 234;
-- id=235
UPDATE `products` SET `price_1_4` = '34672.00', `price_5_9` = '28512.00', `price_10_19` = '21032.00', `price_20_plus` = '21032.00' WHERE `id` = 235;
-- id=236
UPDATE `products` SET `price_1_4` = '22264.00', `price_5_9` = '18216.00', `price_10_19` = '13464.00', `price_20_plus` = '13464.00' WHERE `id` = 236;
-- id=237
UPDATE `products` SET `price_1_4` = '14080.00', `price_5_9` = '11616.00', `price_10_19` = '8536.00', `price_20_plus` = '8536.00' WHERE `id` = 237;
-- id=238
UPDATE `products` SET `price_1_4` = '27632.00', `price_5_9` = '22704.00', `price_10_19` = '16808.00', `price_20_plus` = '16808.00' WHERE `id` = 238;
-- id=239
UPDATE `products` SET `price_1_4` = '17072.00', `price_5_9` = '13992.00', `price_10_19` = '10384.00', `price_20_plus` = '10384.00' WHERE `id` = 239;
-- id=240
UPDATE `products` SET `price_1_4` = '17072.00', `price_5_9` = '13992.00', `price_10_19` = '10384.00', `price_20_plus` = '10384.00' WHERE `id` = 240;
-- id=241
UPDATE `products` SET `price_1_4` = '33088.00', `price_5_9` = '27192.00', `price_10_19` = '20064.00', `price_20_plus` = '20064.00' WHERE `id` = 241;
-- id=242
UPDATE `products` SET `price_1_4` = '14080.00', `price_5_9` = '11616.00', `price_10_19` = '8536.00', `price_20_plus` = '8536.00' WHERE `id` = 242;
-- id=243
UPDATE `products` SET `price_1_4` = '17072.00', `price_5_9` = '13992.00', `price_10_19` = '10384.00', `price_20_plus` = '10384.00' WHERE `id` = 243;
-- id=244
UPDATE `products` SET `price_1_4` = '14080.00', `price_5_9` = '11616.00', `price_10_19` = '8536.00', `price_20_plus` = '8536.00' WHERE `id` = 244;
-- id=245
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 245;
-- id=246
UPDATE `products` SET `price_1_4` = '12232.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 246;
-- id=247
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 247;
-- id=248
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 248;
-- id=249
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 249;
-- id=250
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 250;
-- id=251
UPDATE `products` SET `price_1_4` = '19536.00', `price_5_9` = '16016.00', `price_10_19` = '11880.00', `price_20_plus` = '11880.00' WHERE `id` = 251;
-- id=252
UPDATE `products` SET `price_1_4` = '16808.00', `price_5_9` = '13816.00', `price_10_19` = '10208.00', `price_20_plus` = '10208.00' WHERE `id` = 252;
-- id=253
UPDATE `products` SET `price_1_4` = '16808.00', `price_5_9` = '13816.00', `price_10_19` = '10208.00', `price_20_plus` = '10208.00' WHERE `id` = 253;
-- id=254
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 254;
-- id=255
UPDATE `products` SET `price_1_4` = '25784.00', `price_5_9` = '21120.00', `price_10_19` = '15664.00', `price_20_plus` = '15664.00' WHERE `id` = 255;
-- id=256
UPDATE `products` SET `price_1_4` = '25784.00', `price_5_9` = '21120.00', `price_10_19` = '15664.00', `price_20_plus` = '15664.00' WHERE `id` = 256;
-- id=257
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 257;
-- id=258
UPDATE `products` SET `price_1_4` = '14872.00', `price_5_9` = '12232.00', `price_10_19` = '9064.00', `price_20_plus` = '9064.00' WHERE `id` = 258;
-- id=259
UPDATE `products` SET `price_1_4` = '21120.00', `price_5_9` = '17336.00', `price_10_19` = '12848.00', `price_20_plus` = '12848.00' WHERE `id` = 259;
-- id=260
UPDATE `products` SET `price_1_4` = '11352.00', `price_5_9` = '9328.00', `price_10_19` = '6952.00', `price_20_plus` = '6952.00' WHERE `id` = 260;
-- id=261
UPDATE `products` SET `price_1_4` = '18216.00', `price_5_9` = '18216.00', `price_10_19` = '13464.00', `price_20_plus` = '13464.00' WHERE `id` = 261;
-- id=262
UPDATE `products` SET `price_1_4` = '14080.00', `price_5_9` = '11616.00', `price_10_19` = '8536.00', `price_20_plus` = '8536.00' WHERE `id` = 262;
-- id=263
UPDATE `products` SET `price_1_4` = '22704.00', `price_5_9` = '22704.00', `price_10_19` = '16808.00', `price_20_plus` = '16808.00' WHERE `id` = 263;
-- id=264
UPDATE `products` SET `price_1_4` = '15488.00', `price_5_9` = '12672.00', `price_10_19` = '9416.00', `price_20_plus` = '9416.00' WHERE `id` = 264;
-- id=265
UPDATE `products` SET `price_1_4` = '24904.00', `price_5_9` = '24904.00', `price_10_19` = '18392.00', `price_20_plus` = '18392.00' WHERE `id` = 265;
-- id=266
UPDATE `products` SET `price_1_4` = '15488.00', `price_5_9` = '12672.00', `price_10_19` = '9416.00', `price_20_plus` = '9416.00' WHERE `id` = 266;
-- id=267
UPDATE `products` SET `price_1_4` = '24904.00', `price_5_9` = '24904.00', `price_10_19` = '18392.00', `price_20_plus` = '18392.00' WHERE `id` = 267;
-- id=268
UPDATE `products` SET `price_1_4` = '15488.00', `price_5_9` = '12672.00', `price_10_19` = '9416.00', `price_20_plus` = '9416.00' WHERE `id` = 268;
-- id=269
UPDATE `products` SET `price_1_4` = '24904.00', `price_5_9` = '24904.00', `price_10_19` = '18392.00', `price_20_plus` = '18392.00' WHERE `id` = 269;
-- id=270
UPDATE `products` SET `price_1_4` = '15488.00', `price_5_9` = '12672.00', `price_10_19` = '9416.00', `price_20_plus` = '9416.00' WHERE `id` = 270;
-- id=271
UPDATE `products` SET `price_1_4` = '24904.00', `price_5_9` = '24904.00', `price_10_19` = '18392.00', `price_20_plus` = '18392.00' WHERE `id` = 271;
-- id=272
UPDATE `products` SET `price_1_4` = '15488.00', `price_5_9` = '12672.00', `price_10_19` = '9416.00', `price_20_plus` = '9416.00' WHERE `id` = 272;
-- id=273
UPDATE `products` SET `price_1_4` = '19536.00', `price_5_9` = '16016.00', `price_10_19` = '11880.00', `price_20_plus` = '11880.00' WHERE `id` = 273;
-- id=274
UPDATE `products` SET `price_1_4` = '19536.00', `price_5_9` = '16016.00', `price_10_19` = '11880.00', `price_20_plus` = '11880.00' WHERE `id` = 274;
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
COMMIT;
