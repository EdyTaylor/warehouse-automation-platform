-- Только розничная цена за метр: price_per_meter.
-- price_1_4 … price_20_plus, закуп и purchase_delivered_per_meter не трогаем.
-- Источник: только products_full_import_prices_usd_x88.sql (литерал price_per_meter как в LLumar-файле).
-- Применение: phpMyAdmin → SQL или mysql CLI на своей БД.
-- Regenerate: node example/new/build_sales_tiers_only_sql.js --price-per-meter-only [--price-per-meter-fill-from-dump]
-- Rows: 217 UPDATE statements.
-- Generated: 2026-05-04T16:29:57.091Z

SET NAMES utf8mb4;
START TRANSACTION;

-- id=26
UPDATE `products` SET `price_per_meter` = '5280.00' WHERE `id` = 26;
-- id=27
UPDATE `products` SET `price_per_meter` = '5280.00' WHERE `id` = 27;
-- id=28
UPDATE `products` SET `price_per_meter` = '5280.00' WHERE `id` = 28;
-- id=29
UPDATE `products` SET `price_per_meter` = '2288.00' WHERE `id` = 29;
-- id=30
UPDATE `products` SET `price_per_meter` = '2288.00' WHERE `id` = 30;
-- id=31
UPDATE `products` SET `price_per_meter` = '2288.00' WHERE `id` = 31;
-- id=32
UPDATE `products` SET `price_per_meter` = '2288.00' WHERE `id` = 32;
-- id=33
UPDATE `products` SET `price_per_meter` = '2288.00' WHERE `id` = 33;
-- id=34
UPDATE `products` SET `price_per_meter` = '2288.00' WHERE `id` = 34;
-- id=35
UPDATE `products` SET `price_per_meter` = '2728.00' WHERE `id` = 35;
-- id=36
UPDATE `products` SET `price_per_meter` = '2728.00' WHERE `id` = 36;
-- id=37
UPDATE `products` SET `price_per_meter` = '2728.00' WHERE `id` = 37;
-- id=38
UPDATE `products` SET `price_per_meter` = '2728.00' WHERE `id` = 38;
-- id=39
UPDATE `products` SET `price_per_meter` = '2728.00' WHERE `id` = 39;
-- id=40
UPDATE `products` SET `price_per_meter` = '5280.00' WHERE `id` = 40;
-- id=41
UPDATE `products` SET `price_per_meter` = '3520.00' WHERE `id` = 41;
-- id=42
UPDATE `products` SET `price_per_meter` = '5280.00' WHERE `id` = 42;
-- id=43
UPDATE `products` SET `price_per_meter` = '3520.00' WHERE `id` = 43;
-- id=44
UPDATE `products` SET `price_per_meter` = '5280.00' WHERE `id` = 44;
-- id=45
UPDATE `products` SET `price_per_meter` = '7920.00' WHERE `id` = 45;
-- id=46
UPDATE `products` SET `price_per_meter` = '704.00' WHERE `id` = 46;
-- id=47
UPDATE `products` SET `price_per_meter` = '704.00' WHERE `id` = 47;
-- id=48
UPDATE `products` SET `price_per_meter` = '704.00' WHERE `id` = 48;
-- id=49
UPDATE `products` SET `price_per_meter` = '704.00' WHERE `id` = 49;
-- id=50
UPDATE `products` SET `price_per_meter` = '704.00' WHERE `id` = 50;
-- id=51
UPDATE `products` SET `price_per_meter` = '704.00' WHERE `id` = 51;
-- id=52
UPDATE `products` SET `price_per_meter` = '704.00' WHERE `id` = 52;
-- id=53
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 53;
-- id=54
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 54;
-- id=55
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 55;
-- id=56
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 56;
-- id=57
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 57;
-- id=58
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 58;
-- id=59
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 59;
-- id=60
UPDATE `products` SET `price_per_meter` = '1496.00' WHERE `id` = 60;
-- id=61
UPDATE `products` SET `price_per_meter` = '1672.00' WHERE `id` = 61;
-- id=62
UPDATE `products` SET `price_per_meter` = '1672.00' WHERE `id` = 62;
-- id=63
UPDATE `products` SET `price_per_meter` = '1672.00' WHERE `id` = 63;
-- id=64
UPDATE `products` SET `price_per_meter` = '6424.00' WHERE `id` = 64;
-- id=65
UPDATE `products` SET `price_per_meter` = '6424.00' WHERE `id` = 65;
-- id=66
UPDATE `products` SET `price_per_meter` = '6424.00' WHERE `id` = 66;
-- id=67
UPDATE `products` SET `price_per_meter` = '6424.00' WHERE `id` = 67;
-- id=68
UPDATE `products` SET `price_per_meter` = '6424.00' WHERE `id` = 68;
-- id=71
UPDATE `products` SET `price_per_meter` = '8624.00' WHERE `id` = 71;
-- id=72
UPDATE `products` SET `price_per_meter` = '7304.00' WHERE `id` = 72;
-- id=75
UPDATE `products` SET `price_per_meter` = '3168.00' WHERE `id` = 75;
-- id=76
UPDATE `products` SET `price_per_meter` = '3168.00' WHERE `id` = 76;
-- id=77
UPDATE `products` SET `price_per_meter` = '3168.00' WHERE `id` = 77;
-- id=78
UPDATE `products` SET `price_per_meter` = '3168.00' WHERE `id` = 78;
-- id=79
UPDATE `products` SET `price_per_meter` = '3168.00' WHERE `id` = 79;
-- id=80
UPDATE `products` SET `price_per_meter` = '2464.00' WHERE `id` = 80;
-- id=81
UPDATE `products` SET `price_per_meter` = '3080.00' WHERE `id` = 81;
-- id=82
UPDATE `products` SET `price_per_meter` = '2288.00' WHERE `id` = 82;
-- id=83
UPDATE `products` SET `price_per_meter` = '2728.00' WHERE `id` = 83;
-- id=84
UPDATE `products` SET `price_per_meter` = '2728.00' WHERE `id` = 84;
-- id=85
UPDATE `products` SET `price_per_meter` = '2552.00' WHERE `id` = 85;
-- id=86
UPDATE `products` SET `price_per_meter` = '2728.00' WHERE `id` = 86;
-- id=92
UPDATE `products` SET `price_per_meter` = '2992.00' WHERE `id` = 92;
-- id=93
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 93;
-- id=94
UPDATE `products` SET `price_per_meter` = '3872.00' WHERE `id` = 94;
-- id=95
UPDATE `products` SET `price_per_meter` = '4136.00' WHERE `id` = 95;
-- id=96
UPDATE `products` SET `price_per_meter` = '4752.00' WHERE `id` = 96;
-- id=97
UPDATE `products` SET `price_per_meter` = '4048.00' WHERE `id` = 97;
-- id=98
UPDATE `products` SET `price_per_meter` = '4048.00' WHERE `id` = 98;
-- id=99
UPDATE `products` SET `price_per_meter` = '4400.00' WHERE `id` = 99;
-- id=100
UPDATE `products` SET `price_per_meter` = '4488.00' WHERE `id` = 100;
-- id=101
UPDATE `products` SET `price_per_meter` = '5192.00' WHERE `id` = 101;
-- id=102
UPDATE `products` SET `price_per_meter` = '6512.00' WHERE `id` = 102;
-- id=103
UPDATE `products` SET `price_per_meter` = '3960.00' WHERE `id` = 103;
-- id=104
UPDATE `products` SET `price_per_meter` = '4136.00' WHERE `id` = 104;
-- id=107
UPDATE `products` SET `price_per_meter` = '1672.00' WHERE `id` = 107;
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
-- id=120
UPDATE `products` SET `price_per_meter` = '3872.00' WHERE `id` = 120;
-- id=121
UPDATE `products` SET `price_per_meter` = '4136.00' WHERE `id` = 121;
-- id=122
UPDATE `products` SET `price_per_meter` = '4048.00' WHERE `id` = 122;
-- id=123
UPDATE `products` SET `price_per_meter` = '2728.00' WHERE `id` = 123;
-- id=124
UPDATE `products` SET `price_per_meter` = '5632.00' WHERE `id` = 124;
-- id=125
UPDATE `products` SET `price_per_meter` = '5632.00' WHERE `id` = 125;
-- id=126
UPDATE `products` SET `price_per_meter` = '5632.00' WHERE `id` = 126;
-- id=127
UPDATE `products` SET `price_per_meter` = '3520.00' WHERE `id` = 127;
-- id=128
UPDATE `products` SET `price_per_meter` = '2024.00' WHERE `id` = 128;
-- id=129
UPDATE `products` SET `price_per_meter` = '2024.00' WHERE `id` = 129;
-- id=130
UPDATE `products` SET `price_per_meter` = '2024.00' WHERE `id` = 130;
-- id=131
UPDATE `products` SET `price_per_meter` = '17600.00' WHERE `id` = 131;
-- id=132
UPDATE `products` SET `price_per_meter` = '17600.00' WHERE `id` = 132;
-- id=135
UPDATE `products` SET `price_per_meter` = '11000.00' WHERE `id` = 135;
-- id=136
UPDATE `products` SET `price_per_meter` = '5544.00' WHERE `id` = 136;
-- id=137
UPDATE `products` SET `price_per_meter` = '13816.00' WHERE `id` = 137;
-- id=138
UPDATE `products` SET `price_per_meter` = '12672.00' WHERE `id` = 138;
-- id=139
UPDATE `products` SET `price_per_meter` = '17512.00' WHERE `id` = 139;
-- id=140
UPDATE `products` SET `price_per_meter` = '13816.00' WHERE `id` = 140;
-- id=142
UPDATE `products` SET `price_per_meter` = '11000.00' WHERE `id` = 142;
-- id=143
UPDATE `products` SET `price_per_meter` = '14432.00' WHERE `id` = 143;
-- id=144
UPDATE `products` SET `price_per_meter` = '6512.00' WHERE `id` = 144;
-- id=145
UPDATE `products` SET `price_per_meter` = '7656.00' WHERE `id` = 145;
-- id=146
UPDATE `products` SET `price_per_meter` = '7128.00' WHERE `id` = 146;
-- id=147
UPDATE `products` SET `price_per_meter` = '15048.00' WHERE `id` = 147;
-- id=148
UPDATE `products` SET `price_per_meter` = '9592.00' WHERE `id` = 148;
-- id=149
UPDATE `products` SET `price_per_meter` = '3432.00' WHERE `id` = 149;
-- id=150
UPDATE `products` SET `price_per_meter` = '6512.00' WHERE `id` = 150;
-- id=151
UPDATE `products` SET `price_per_meter` = '9152.00' WHERE `id` = 151;
-- id=152
UPDATE `products` SET `price_per_meter` = '15136.00' WHERE `id` = 152;
-- id=153
UPDATE `products` SET `price_per_meter` = '2728.00' WHERE `id` = 153;
-- id=154
UPDATE `products` SET `price_per_meter` = '2816.00' WHERE `id` = 154;
-- id=155
UPDATE `products` SET `price_per_meter` = '2816.00' WHERE `id` = 155;
-- id=156
UPDATE `products` SET `price_per_meter` = '2200.00' WHERE `id` = 156;
-- id=157
UPDATE `products` SET `price_per_meter` = '6512.00' WHERE `id` = 157;
-- id=158
UPDATE `products` SET `price_per_meter` = '7656.00' WHERE `id` = 158;
-- id=159
UPDATE `products` SET `price_per_meter` = '7128.00' WHERE `id` = 159;
-- id=160
UPDATE `products` SET `price_per_meter` = '15048.00' WHERE `id` = 160;
-- id=161
UPDATE `products` SET `price_per_meter` = '9592.00' WHERE `id` = 161;
-- id=166
UPDATE `products` SET `price_per_meter` = '1232.00' WHERE `id` = 166;
-- id=167
UPDATE `products` SET `price_per_meter` = '1760.00' WHERE `id` = 167;
-- id=168
UPDATE `products` SET `price_per_meter` = '1760.00' WHERE `id` = 168;
-- id=169
UPDATE `products` SET `price_per_meter` = '1760.00' WHERE `id` = 169;
-- id=170
UPDATE `products` SET `price_per_meter` = '1760.00' WHERE `id` = 170;
-- id=173
UPDATE `products` SET `price_per_meter` = '66000.00' WHERE `id` = 173;
-- id=174
UPDATE `products` SET `price_per_meter` = '79200.00' WHERE `id` = 174;
-- id=175
UPDATE `products` SET `price_per_meter` = '35200.00' WHERE `id` = 175;
-- id=176
UPDATE `products` SET `price_per_meter` = '26400.00' WHERE `id` = 176;
-- id=177
UPDATE `products` SET `price_per_meter` = '35200.00' WHERE `id` = 177;
-- id=178
UPDATE `products` SET `price_per_meter` = '26400.00' WHERE `id` = 178;
-- id=179
UPDATE `products` SET `price_per_meter` = '13200.00' WHERE `id` = 179;
-- id=180
UPDATE `products` SET `price_per_meter` = '57200.00' WHERE `id` = 180;
-- id=186
UPDATE `products` SET `price_per_meter` = '52800.00' WHERE `id` = 186;
-- id=187
UPDATE `products` SET `price_per_meter` = '52800.00' WHERE `id` = 187;
-- id=191
UPDATE `products` SET `price_per_meter` = '528.00' WHERE `id` = 191;
-- id=192
UPDATE `products` SET `price_per_meter` = '704.00' WHERE `id` = 192;
-- id=193
UPDATE `products` SET `price_per_meter` = '1408.00' WHERE `id` = 193;
-- id=194
UPDATE `products` SET `price_per_meter` = '2112.00' WHERE `id` = 194;
-- id=195
UPDATE `products` SET `price_per_meter` = '1232.00' WHERE `id` = 195;
-- id=196
UPDATE `products` SET `price_per_meter` = '1672.00' WHERE `id` = 196;
-- id=197
UPDATE `products` SET `price_per_meter` = '2464.00' WHERE `id` = 197;
-- id=198
UPDATE `products` SET `price_per_meter` = '1320.00' WHERE `id` = 198;
-- id=199
UPDATE `products` SET `price_per_meter` = '616.00' WHERE `id` = 199;
-- id=200
UPDATE `products` SET `price_per_meter` = '704.00' WHERE `id` = 200;
-- id=201
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 201;
-- id=202
UPDATE `products` SET `price_per_meter` = '1056.00' WHERE `id` = 202;
-- id=203
UPDATE `products` SET `price_per_meter` = '1496.00' WHERE `id` = 203;
-- id=204
UPDATE `products` SET `price_per_meter` = '2112.00' WHERE `id` = 204;
-- id=205
UPDATE `products` SET `price_per_meter` = '2904.00' WHERE `id` = 205;
-- id=206
UPDATE `products` SET `price_per_meter` = '1936.00' WHERE `id` = 206;
-- id=207
UPDATE `products` SET `price_per_meter` = '1760.00' WHERE `id` = 207;
-- id=208
UPDATE `products` SET `price_per_meter` = '2728.00' WHERE `id` = 208;
-- id=209
UPDATE `products` SET `price_per_meter` = '3432.00' WHERE `id` = 209;
-- id=210
UPDATE `products` SET `price_per_meter` = '3432.00' WHERE `id` = 210;
-- id=211
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 211;
-- id=212
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 212;
-- id=213
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 213;
-- id=214
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 214;
-- id=215
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 215;
-- id=216
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 216;
-- id=217
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 217;
-- id=218
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 218;
-- id=219
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 219;
-- id=220
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 220;
-- id=221
UPDATE `products` SET `price_per_meter` = '3256.00' WHERE `id` = 221;
-- id=222
UPDATE `products` SET `price_per_meter` = '1232.00' WHERE `id` = 222;
-- id=223
UPDATE `products` SET `price_per_meter` = '1144.00' WHERE `id` = 223;
-- id=224
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 224;
-- id=225
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 225;
-- id=226
UPDATE `products` SET `price_per_meter` = '1056.00' WHERE `id` = 226;
-- id=227
UPDATE `products` SET `price_per_meter` = '968.00' WHERE `id` = 227;
-- id=228
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 228;
-- id=229
UPDATE `products` SET `price_per_meter` = '1056.00' WHERE `id` = 229;
-- id=230
UPDATE `products` SET `price_per_meter` = '1056.00' WHERE `id` = 230;
-- id=231
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 231;
-- id=232
UPDATE `products` SET `price_per_meter` = '1584.00' WHERE `id` = 232;
-- id=233
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 233;
-- id=234
UPDATE `products` SET `price_per_meter` = '1056.00' WHERE `id` = 234;
-- id=235
UPDATE `products` SET `price_per_meter` = '1056.00' WHERE `id` = 235;
-- id=236
UPDATE `products` SET `price_per_meter` = '1320.00' WHERE `id` = 236;
-- id=237
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 237;
-- id=238
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 238;
-- id=239
UPDATE `products` SET `price_per_meter` = '1056.00' WHERE `id` = 239;
-- id=240
UPDATE `products` SET `price_per_meter` = '1056.00' WHERE `id` = 240;
-- id=241
UPDATE `products` SET `price_per_meter` = '968.00' WHERE `id` = 241;
-- id=242
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 242;
-- id=243
UPDATE `products` SET `price_per_meter` = '1056.00' WHERE `id` = 243;
-- id=244
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 244;
-- id=245
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 245;
-- id=246
UPDATE `products` SET `price_per_meter` = '14872.00' WHERE `id` = 246;
-- id=247
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 247;
-- id=248
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 248;
-- id=249
UPDATE `products` SET `price_per_meter` = '440.00' WHERE `id` = 249;
-- id=250
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 250;
-- id=251
UPDATE `products` SET `price_per_meter` = '1144.00' WHERE `id` = 251;
-- id=252
UPDATE `products` SET `price_per_meter` = '968.00' WHERE `id` = 252;
-- id=253
UPDATE `products` SET `price_per_meter` = '968.00' WHERE `id` = 253;
-- id=254
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 254;
-- id=255
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 255;
-- id=256
UPDATE `products` SET `price_per_meter` = '792.00' WHERE `id` = 256;
-- id=257
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 257;
-- id=258
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 258;
-- id=259
UPDATE `products` SET `price_per_meter` = '616.00' WHERE `id` = 259;
-- id=260
UPDATE `products` SET `price_per_meter` = '704.00' WHERE `id` = 260;
-- id=261
UPDATE `products` SET `price_per_meter` = '22264.00' WHERE `id` = 261;
-- id=262
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 262;
-- id=263
UPDATE `products` SET `price_per_meter` = '27632.00' WHERE `id` = 263;
-- id=264
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 264;
-- id=265
UPDATE `products` SET `price_per_meter` = '30360.00' WHERE `id` = 265;
-- id=266
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 266;
-- id=267
UPDATE `products` SET `price_per_meter` = '30360.00' WHERE `id` = 267;
-- id=268
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 268;
-- id=269
UPDATE `products` SET `price_per_meter` = '30360.00' WHERE `id` = 269;
-- id=270
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 270;
-- id=271
UPDATE `products` SET `price_per_meter` = '30360.00' WHERE `id` = 271;
-- id=272
UPDATE `products` SET `price_per_meter` = '880.00' WHERE `id` = 272;
-- id=273
UPDATE `products` SET `price_per_meter` = '1144.00' WHERE `id` = 273;
-- id=274
UPDATE `products` SET `price_per_meter` = '1144.00' WHERE `id` = 274;
COMMIT;
