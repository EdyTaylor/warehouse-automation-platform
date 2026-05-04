-- Только розничная цена за метр: price_per_meter.
-- price_1_4 … price_20_plus, закуп и purchase_delivered_per_meter не трогаем.
-- Источник: products_full_import_prices_usd_x88.sql + недостающие id из products.sql (колонка цены за м в дампе).
-- Применение: phpMyAdmin → SQL или mysql CLI на своей БД.
-- Regenerate: node example/new/build_sales_tiers_only_sql.js --price-per-meter-only
-- Rows: 689 UPDATE statements.
-- Generated: 2026-05-04T16:14:29.238Z

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
-- id=69
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 69;
-- id=70
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 70;
-- id=71
UPDATE `products` SET `price_per_meter` = '8624.00' WHERE `id` = 71;
-- id=72
UPDATE `products` SET `price_per_meter` = '7304.00' WHERE `id` = 72;
-- id=73
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 73;
-- id=74
UPDATE `products` SET `price_per_meter` = '3150.00' WHERE `id` = 74;
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
-- id=105
UPDATE `products` SET `price_per_meter` = '50516.67' WHERE `id` = 105;
-- id=106
UPDATE `products` SET `price_per_meter` = '50516.67' WHERE `id` = 106;
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
-- id=133
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 133;
-- id=134
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 134;
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
-- id=162
UPDATE `products` SET `price_per_meter` = '674.63' WHERE `id` = 162;
-- id=163
UPDATE `products` SET `price_per_meter` = '1350.13' WHERE `id` = 163;
-- id=164
UPDATE `products` SET `price_per_meter` = '780.50' WHERE `id` = 164;
-- id=165
UPDATE `products` SET `price_per_meter` = '254.63' WHERE `id` = 165;
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
-- id=171
UPDATE `products` SET `price_per_meter` = '25375.00' WHERE `id` = 171;
-- id=172
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 172;
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
-- id=184
UPDATE `products` SET `price_per_meter` = '0.00' WHERE `id` = 184;
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
-- id=292
UPDATE `products` SET `price_per_meter` = '332.50' WHERE `id` = 292;
-- id=293
UPDATE `products` SET `price_per_meter` = '300.00' WHERE `id` = 293;
-- id=294
UPDATE `products` SET `price_per_meter` = '122.50' WHERE `id` = 294;
-- id=295
UPDATE `products` SET `price_per_meter` = '122.50' WHERE `id` = 295;
-- id=296
UPDATE `products` SET `price_per_meter` = '472.50' WHERE `id` = 296;
-- id=297
UPDATE `products` SET `price_per_meter` = '490.00' WHERE `id` = 297;
-- id=298
UPDATE `products` SET `price_per_meter` = '691.25' WHERE `id` = 298;
-- id=299
UPDATE `products` SET `price_per_meter` = '376.25' WHERE `id` = 299;
-- id=300
UPDATE `products` SET `price_per_meter` = '350.00' WHERE `id` = 300;
-- id=301
UPDATE `products` SET `price_per_meter` = '210.00' WHERE `id` = 301;
-- id=302
UPDATE `products` SET `price_per_meter` = '201.25' WHERE `id` = 302;
-- id=303
UPDATE `products` SET `price_per_meter` = '201.25' WHERE `id` = 303;
-- id=304
UPDATE `products` SET `price_per_meter` = '600.00' WHERE `id` = 304;
-- id=305
UPDATE `products` SET `price_per_meter` = '551.25' WHERE `id` = 305;
-- id=306
UPDATE `products` SET `price_per_meter` = '700.00' WHERE `id` = 306;
-- id=307
UPDATE `products` SET `price_per_meter` = '330.00' WHERE `id` = 307;
-- id=308
UPDATE `products` SET `price_per_meter` = '253.75' WHERE `id` = 308;
-- id=309
UPDATE `products` SET `price_per_meter` = '148.75' WHERE `id` = 309;
-- id=310
UPDATE `products` SET `price_per_meter` = '61.25' WHERE `id` = 310;
-- id=311
UPDATE `products` SET `price_per_meter` = '717.50' WHERE `id` = 311;
-- id=312
UPDATE `products` SET `price_per_meter` = '796.25' WHERE `id` = 312;
-- id=313
UPDATE `products` SET `price_per_meter` = '400.00' WHERE `id` = 313;
-- id=314
UPDATE `products` SET `price_per_meter` = '26.25' WHERE `id` = 314;
-- id=315
UPDATE `products` SET `price_per_meter` = '52.50' WHERE `id` = 315;
-- id=316
UPDATE `products` SET `price_per_meter` = '105.00' WHERE `id` = 316;
-- id=317
UPDATE `products` SET `price_per_meter` = '70.00' WHERE `id` = 317;
-- id=318
UPDATE `products` SET `price_per_meter` = '200.00' WHERE `id` = 318;
-- id=319
UPDATE `products` SET `price_per_meter` = '17841.25' WHERE `id` = 319;
-- id=320
UPDATE `products` SET `price_per_meter` = '735.00' WHERE `id` = 320;
-- id=321
UPDATE `products` SET `price_per_meter` = '971.25' WHERE `id` = 321;
-- id=322
UPDATE `products` SET `price_per_meter` = '2000.00' WHERE `id` = 322;
-- id=323
UPDATE `products` SET `price_per_meter` = '3500.00' WHERE `id` = 323;
-- id=324
UPDATE `products` SET `price_per_meter` = '113.75' WHERE `id` = 324;
-- id=325
UPDATE `products` SET `price_per_meter` = '131.25' WHERE `id` = 325;
-- id=326
UPDATE `products` SET `price_per_meter` = '183.75' WHERE `id` = 326;
-- id=327
UPDATE `products` SET `price_per_meter` = '52.50' WHERE `id` = 327;
-- id=328
UPDATE `products` SET `price_per_meter` = '52.50' WHERE `id` = 328;
-- id=329
UPDATE `products` SET `price_per_meter` = '1000.00' WHERE `id` = 329;
-- id=330
UPDATE `products` SET `price_per_meter` = '5235125.00' WHERE `id` = 330;
-- id=331
UPDATE `products` SET `price_per_meter` = '525.00' WHERE `id` = 331;
-- id=332
UPDATE `products` SET `price_per_meter` = '96.25' WHERE `id` = 332;
-- id=333
UPDATE `products` SET `price_per_meter` = '175.00' WHERE `id` = 333;
-- id=334
UPDATE `products` SET `price_per_meter` = '131.25' WHERE `id` = 334;
-- id=335
UPDATE `products` SET `price_per_meter` = '201.25' WHERE `id` = 335;
-- id=336
UPDATE `products` SET `price_per_meter` = '192.50' WHERE `id` = 336;
-- id=337
UPDATE `products` SET `price_per_meter` = '166.25' WHERE `id` = 337;
-- id=338
UPDATE `products` SET `price_per_meter` = '183.75' WHERE `id` = 338;
-- id=339
UPDATE `products` SET `price_per_meter` = '183.75' WHERE `id` = 339;
-- id=340
UPDATE `products` SET `price_per_meter` = '183.75' WHERE `id` = 340;
-- id=341
UPDATE `products` SET `price_per_meter` = '201.25' WHERE `id` = 341;
-- id=342
UPDATE `products` SET `price_per_meter` = '218.75' WHERE `id` = 342;
-- id=343
UPDATE `products` SET `price_per_meter` = '210.00' WHERE `id` = 343;
-- id=344
UPDATE `products` SET `price_per_meter` = '192.50' WHERE `id` = 344;
-- id=345
UPDATE `products` SET `price_per_meter` = '218.75' WHERE `id` = 345;
-- id=346
UPDATE `products` SET `price_per_meter` = '192.50' WHERE `id` = 346;
-- id=347
UPDATE `products` SET `price_per_meter` = '157.50' WHERE `id` = 347;
-- id=348
UPDATE `products` SET `price_per_meter` = '61.25' WHERE `id` = 348;
-- id=349
UPDATE `products` SET `price_per_meter` = '35.00' WHERE `id` = 349;
-- id=350
UPDATE `products` SET `price_per_meter` = '96.25' WHERE `id` = 350;
-- id=351
UPDATE `products` SET `price_per_meter` = '96.25' WHERE `id` = 351;
-- id=352
UPDATE `products` SET `price_per_meter` = '1100.00' WHERE `id` = 352;
-- id=353
UPDATE `products` SET `price_per_meter` = '1320.00' WHERE `id` = 353;
-- id=354
UPDATE `products` SET `price_per_meter` = '150.00' WHERE `id` = 354;
-- id=355
UPDATE `products` SET `price_per_meter` = '150.00' WHERE `id` = 355;
-- id=356
UPDATE `products` SET `price_per_meter` = '150.00' WHERE `id` = 356;
-- id=357
UPDATE `products` SET `price_per_meter` = '166.25' WHERE `id` = 357;
-- id=358
UPDATE `products` SET `price_per_meter` = '113.75' WHERE `id` = 358;
-- id=359
UPDATE `products` SET `price_per_meter` = '402.50' WHERE `id` = 359;
-- id=360
UPDATE `products` SET `price_per_meter` = '78.75' WHERE `id` = 360;
-- id=361
UPDATE `products` SET `price_per_meter` = '1330.00' WHERE `id` = 361;
-- id=362
UPDATE `products` SET `price_per_meter` = '800.00' WHERE `id` = 362;
-- id=363
UPDATE `products` SET `price_per_meter` = '8.75' WHERE `id` = 363;
-- id=364
UPDATE `products` SET `price_per_meter` = '1700.00' WHERE `id` = 364;
-- id=365
UPDATE `products` SET `price_per_meter` = '52.50' WHERE `id` = 365;
-- id=366
UPDATE `products` SET `price_per_meter` = '96.25' WHERE `id` = 366;
-- id=367
UPDATE `products` SET `price_per_meter` = '96.25' WHERE `id` = 367;
-- id=368
UPDATE `products` SET `price_per_meter` = '70.00' WHERE `id` = 368;
-- id=369
UPDATE `products` SET `price_per_meter` = '70.00' WHERE `id` = 369;
-- id=370
UPDATE `products` SET `price_per_meter` = '70.00' WHERE `id` = 370;
-- id=371
UPDATE `products` SET `price_per_meter` = '26.25' WHERE `id` = 371;
-- id=372
UPDATE `products` SET `price_per_meter` = '52.50' WHERE `id` = 372;
-- id=373
UPDATE `products` SET `price_per_meter` = '35.00' WHERE `id` = 373;
-- id=374
UPDATE `products` SET `price_per_meter` = '35.00' WHERE `id` = 374;
-- id=375
UPDATE `products` SET `price_per_meter` = '35.00' WHERE `id` = 375;
-- id=376
UPDATE `products` SET `price_per_meter` = '35.00' WHERE `id` = 376;
-- id=377
UPDATE `products` SET `price_per_meter` = '35.00' WHERE `id` = 377;
-- id=378
UPDATE `products` SET `price_per_meter` = '35.00' WHERE `id` = 378;
-- id=379
UPDATE `products` SET `price_per_meter` = '96.25' WHERE `id` = 379;
-- id=380
UPDATE `products` SET `price_per_meter` = '96.25' WHERE `id` = 380;
-- id=381
UPDATE `products` SET `price_per_meter` = '96.25' WHERE `id` = 381;
-- id=382
UPDATE `products` SET `price_per_meter` = '131.25' WHERE `id` = 382;
-- id=383
UPDATE `products` SET `price_per_meter` = '131.25' WHERE `id` = 383;
-- id=384
UPDATE `products` SET `price_per_meter` = '26.25' WHERE `id` = 384;
-- id=385
UPDATE `products` SET `price_per_meter` = '35.00' WHERE `id` = 385;
-- id=386
UPDATE `products` SET `price_per_meter` = '35.00' WHERE `id` = 386;
-- id=387
UPDATE `products` SET `price_per_meter` = '35.00' WHERE `id` = 387;
-- id=388
UPDATE `products` SET `price_per_meter` = '140.00' WHERE `id` = 388;
-- id=389
UPDATE `products` SET `price_per_meter` = '78.75' WHERE `id` = 389;
-- id=390
UPDATE `products` SET `price_per_meter` = '78.75' WHERE `id` = 390;
-- id=391
UPDATE `products` SET `price_per_meter` = '323.75' WHERE `id` = 391;
-- id=392
UPDATE `products` SET `price_per_meter` = '78.75' WHERE `id` = 392;
-- id=393
UPDATE `products` SET `price_per_meter` = '1600.00' WHERE `id` = 393;
-- id=394
UPDATE `products` SET `price_per_meter` = '1200.00' WHERE `id` = 394;
-- id=395
UPDATE `products` SET `price_per_meter` = '1200.00' WHERE `id` = 395;
-- id=396
UPDATE `products` SET `price_per_meter` = '78.75' WHERE `id` = 396;
-- id=397
UPDATE `products` SET `price_per_meter` = '78.75' WHERE `id` = 397;
-- id=398
UPDATE `products` SET `price_per_meter` = '78.75' WHERE `id` = 398;
-- id=399
UPDATE `products` SET `price_per_meter` = '262.50' WHERE `id` = 399;
-- id=400
UPDATE `products` SET `price_per_meter` = '200.00' WHERE `id` = 400;
-- id=401
UPDATE `products` SET `price_per_meter` = '200.00' WHERE `id` = 401;
-- id=402
UPDATE `products` SET `price_per_meter` = '270.00' WHERE `id` = 402;
-- id=403
UPDATE `products` SET `price_per_meter` = '52.50' WHERE `id` = 403;
-- id=404
UPDATE `products` SET `price_per_meter` = '43.75' WHERE `id` = 404;
-- id=405
UPDATE `products` SET `price_per_meter` = '280.00' WHERE `id` = 405;
-- id=406
UPDATE `products` SET `price_per_meter` = '4445.00' WHERE `id` = 406;
-- id=407
UPDATE `products` SET `price_per_meter` = '3762.50' WHERE `id` = 407;
-- id=408
UPDATE `products` SET `price_per_meter` = '1723.75' WHERE `id` = 408;
-- id=409
UPDATE `products` SET `price_per_meter` = '2563.75' WHERE `id` = 409;
-- id=410
UPDATE `products` SET `price_per_meter` = '218.75' WHERE `id` = 410;
-- id=411
UPDATE `products` SET `price_per_meter` = '236.25' WHERE `id` = 411;
-- id=412
UPDATE `products` SET `price_per_meter` = '26.25' WHERE `id` = 412;
-- id=413
UPDATE `products` SET `price_per_meter` = '122.50' WHERE `id` = 413;
-- id=414
UPDATE `products` SET `price_per_meter` = '131.25' WHERE `id` = 414;
-- id=415
UPDATE `products` SET `price_per_meter` = '26.25' WHERE `id` = 415;
-- id=416
UPDATE `products` SET `price_per_meter` = '236.25' WHERE `id` = 416;
-- id=417
UPDATE `products` SET `price_per_meter` = '122.50' WHERE `id` = 417;
-- id=418
UPDATE `products` SET `price_per_meter` = '1200.00' WHERE `id` = 418;
-- id=419
UPDATE `products` SET `price_per_meter` = '1951.25' WHERE `id` = 419;
-- id=420
UPDATE `products` SET `price_per_meter` = '1863.75' WHERE `id` = 420;
-- id=421
UPDATE `products` SET `price_per_meter` = '70.00' WHERE `id` = 421;
-- id=422
UPDATE `products` SET `price_per_meter` = '26.25' WHERE `id` = 422;
-- id=423
UPDATE `products` SET `price_per_meter` = '26.25' WHERE `id` = 423;
-- id=424
UPDATE `products` SET `price_per_meter` = '26.25' WHERE `id` = 424;
-- id=425
UPDATE `products` SET `price_per_meter` = '1500.00' WHERE `id` = 425;
-- id=426
UPDATE `products` SET `price_per_meter` = '105.00' WHERE `id` = 426;
-- id=427
UPDATE `products` SET `price_per_meter` = '105.00' WHERE `id` = 427;
-- id=428
UPDATE `products` SET `price_per_meter` = '586.25' WHERE `id` = 428;
-- id=429
UPDATE `products` SET `price_per_meter` = '428.75' WHERE `id` = 429;
-- id=430
UPDATE `products` SET `price_per_meter` = '300.00' WHERE `id` = 430;
-- id=431
UPDATE `products` SET `price_per_meter` = '323.75' WHERE `id` = 431;
-- id=432
UPDATE `products` SET `price_per_meter` = '236.25' WHERE `id` = 432;
-- id=433
UPDATE `products` SET `price_per_meter` = '1000.00' WHERE `id` = 433;
-- id=434
UPDATE `products` SET `price_per_meter` = '700.00' WHERE `id` = 434;
-- id=435
UPDATE `products` SET `price_per_meter` = '300.00' WHERE `id` = 435;
-- id=436
UPDATE `products` SET `price_per_meter` = '300.00' WHERE `id` = 436;
-- id=437
UPDATE `products` SET `price_per_meter` = '300.00' WHERE `id` = 437;
-- id=438
UPDATE `products` SET `price_per_meter` = '300.00' WHERE `id` = 438;
-- id=746
UPDATE `products` SET `price_per_meter` = '1551.73' WHERE `id` = 746;
-- id=747
UPDATE `products` SET `price_per_meter` = '2742.67' WHERE `id` = 747;
-- id=748
UPDATE `products` SET `price_per_meter` = '1551.73' WHERE `id` = 748;
-- id=749
UPDATE `products` SET `price_per_meter` = '2821.87' WHERE `id` = 749;
-- id=750
UPDATE `products` SET `price_per_meter` = '2689.87' WHERE `id` = 750;
-- id=751
UPDATE `products` SET `price_per_meter` = '2689.87' WHERE `id` = 751;
-- id=752
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 752;
-- id=753
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 753;
-- id=754
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 754;
-- id=755
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 755;
-- id=756
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 756;
-- id=757
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 757;
-- id=758
UPDATE `products` SET `price_per_meter` = '915.20' WHERE `id` = 758;
-- id=759
UPDATE `products` SET `price_per_meter` = '915.20' WHERE `id` = 759;
-- id=760
UPDATE `products` SET `price_per_meter` = '915.20' WHERE `id` = 760;
-- id=761
UPDATE `products` SET `price_per_meter` = '2742.67' WHERE `id` = 761;
-- id=762
UPDATE `products` SET `price_per_meter` = '1642.67' WHERE `id` = 762;
-- id=763
UPDATE `products` SET `price_per_meter` = '2742.67' WHERE `id` = 763;
-- id=764
UPDATE `products` SET `price_per_meter` = '3291.20' WHERE `id` = 764;
-- id=765
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 765;
-- id=766
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 766;
-- id=767
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 767;
-- id=768
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 768;
-- id=769
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 769;
-- id=770
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 770;
-- id=771
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 771;
-- id=772
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 772;
-- id=773
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 773;
-- id=774
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 774;
-- id=775
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 775;
-- id=776
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 776;
-- id=777
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 777;
-- id=778
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 778;
-- id=779
UPDATE `products` SET `price_per_meter` = '431.20' WHERE `id` = 779;
-- id=780
UPDATE `products` SET `price_per_meter` = '475.20' WHERE `id` = 780;
-- id=781
UPDATE `products` SET `price_per_meter` = '475.20' WHERE `id` = 781;
-- id=782
UPDATE `products` SET `price_per_meter` = '214.13' WHERE `id` = 782;
-- id=783
UPDATE `products` SET `price_per_meter` = '214.13' WHERE `id` = 783;
-- id=784
UPDATE `products` SET `price_per_meter` = '214.13' WHERE `id` = 784;
-- id=785
UPDATE `products` SET `price_per_meter` = '214.13' WHERE `id` = 785;
-- id=786
UPDATE `products` SET `price_per_meter` = '1173.33' WHERE `id` = 786;
-- id=787
UPDATE `products` SET `price_per_meter` = '1173.33' WHERE `id` = 787;
-- id=788
UPDATE `products` SET `price_per_meter` = '287.47' WHERE `id` = 788;
-- id=789
UPDATE `products` SET `price_per_meter` = '243.47' WHERE `id` = 789;
-- id=790
UPDATE `products` SET `price_per_meter` = '105.60' WHERE `id` = 790;
-- id=791
UPDATE `products` SET `price_per_meter` = '105.60' WHERE `id` = 791;
-- id=792
UPDATE `products` SET `price_per_meter` = '105.60' WHERE `id` = 792;
-- id=793
UPDATE `products` SET `price_per_meter` = '105.60' WHERE `id` = 793;
-- id=794
UPDATE `products` SET `price_per_meter` = '105.60' WHERE `id` = 794;
-- id=795
UPDATE `products` SET `price_per_meter` = '1915.47' WHERE `id` = 795;
-- id=796
UPDATE `products` SET `price_per_meter` = '2364.27' WHERE `id` = 796;
-- id=797
UPDATE `products` SET `price_per_meter` = '1173.33' WHERE `id` = 797;
-- id=798
UPDATE `products` SET `price_per_meter` = '1906.67' WHERE `id` = 798;
-- id=799
UPDATE `products` SET `price_per_meter` = '586.67' WHERE `id` = 799;
-- id=800
UPDATE `products` SET `price_per_meter` = '586.67' WHERE `id` = 800;
-- id=801
UPDATE `products` SET `price_per_meter` = '440.00' WHERE `id` = 801;
-- id=802
UPDATE `products` SET `price_per_meter` = '108.53' WHERE `id` = 802;
-- id=803
UPDATE `products` SET `price_per_meter` = '146.67' WHERE `id` = 803;
-- id=804
UPDATE `products` SET `price_per_meter` = '272.80' WHERE `id` = 804;
-- id=805
UPDATE `products` SET `price_per_meter` = '419.47' WHERE `id` = 805;
-- id=806
UPDATE `products` SET `price_per_meter` = '252.27' WHERE `id` = 806;
-- id=807
UPDATE `products` SET `price_per_meter` = '337.33' WHERE `id` = 807;
-- id=808
UPDATE `products` SET `price_per_meter` = '264.00' WHERE `id` = 808;
-- id=809
UPDATE `products` SET `price_per_meter` = '120.27' WHERE `id` = 809;
-- id=810
UPDATE `products` SET `price_per_meter` = '134.93' WHERE `id` = 810;
-- id=811
UPDATE `products` SET `price_per_meter` = '152.53' WHERE `id` = 811;
-- id=812
UPDATE `products` SET `price_per_meter` = '308.00' WHERE `id` = 812;
-- id=813
UPDATE `products` SET `price_per_meter` = '419.47' WHERE `id` = 813;
-- id=814
UPDATE `products` SET `price_per_meter` = '580.80' WHERE `id` = 814;
-- id=815
UPDATE `products` SET `price_per_meter` = '387.20' WHERE `id` = 815;
-- id=816
UPDATE `products` SET `price_per_meter` = '354.93' WHERE `id` = 816;
-- id=817
UPDATE `products` SET `price_per_meter` = '548.53' WHERE `id` = 817;
-- id=818
UPDATE `products` SET `price_per_meter` = '683.47' WHERE `id` = 818;
-- id=819
UPDATE `products` SET `price_per_meter` = '683.47' WHERE `id` = 819;
-- id=820
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 820;
-- id=821
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 821;
-- id=822
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 822;
-- id=823
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 823;
-- id=824
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 824;
-- id=825
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 825;
-- id=826
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 826;
-- id=827
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 827;
-- id=828
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 828;
-- id=829
UPDATE `products` SET `price_per_meter` = '645.33' WHERE `id` = 829;
-- id=830
UPDATE `products` SET `price_per_meter` = '243.47' WHERE `id` = 830;
-- id=831
UPDATE `products` SET `price_per_meter` = '231.73' WHERE `id` = 831;
-- id=832
UPDATE `products` SET `price_per_meter` = '167.20' WHERE `id` = 832;
-- id=833
UPDATE `products` SET `price_per_meter` = '164.27' WHERE `id` = 833;
-- id=834
UPDATE `products` SET `price_per_meter` = '202.40' WHERE `id` = 834;
-- id=835
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 835;
-- id=836
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 836;
-- id=837
UPDATE `products` SET `price_per_meter` = '211.20' WHERE `id` = 837;
-- id=838
UPDATE `products` SET `price_per_meter` = '206.80' WHERE `id` = 838;
-- id=839
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 839;
-- id=840
UPDATE `products` SET `price_per_meter` = '322.67' WHERE `id` = 840;
-- id=841
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 841;
-- id=842
UPDATE `products` SET `price_per_meter` = '211.20' WHERE `id` = 842;
-- id=843
UPDATE `products` SET `price_per_meter` = '206.80' WHERE `id` = 843;
-- id=844
UPDATE `products` SET `price_per_meter` = '264.00' WHERE `id` = 844;
-- id=845
UPDATE `products` SET `price_per_meter` = '167.20' WHERE `id` = 845;
-- id=846
UPDATE `products` SET `price_per_meter` = '164.27' WHERE `id` = 846;
-- id=847
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 847;
-- id=848
UPDATE `products` SET `price_per_meter` = '167.20' WHERE `id` = 848;
-- id=849
UPDATE `products` SET `price_per_meter` = '202.40' WHERE `id` = 849;
-- id=850
UPDATE `products` SET `price_per_meter` = '167.20' WHERE `id` = 850;
-- id=851
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 851;
-- id=852
UPDATE `products` SET `price_per_meter` = '89.47' WHERE `id` = 852;
-- id=853
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 853;
-- id=854
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 854;
-- id=855
UPDATE `products` SET `price_per_meter` = '89.47' WHERE `id` = 855;
-- id=856
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 856;
-- id=857
UPDATE `products` SET `price_per_meter` = '231.73' WHERE `id` = 857;
-- id=858
UPDATE `products` SET `price_per_meter` = '199.47' WHERE `id` = 858;
-- id=859
UPDATE `products` SET `price_per_meter` = '199.47' WHERE `id` = 859;
-- id=860
UPDATE `products` SET `price_per_meter` = '154.00' WHERE `id` = 860;
-- id=861
UPDATE `products` SET `price_per_meter` = '154.00' WHERE `id` = 861;
-- id=862
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 862;
-- id=863
UPDATE `products` SET `price_per_meter` = '126.13' WHERE `id` = 863;
-- id=864
UPDATE `products` SET `price_per_meter` = '134.93' WHERE `id` = 864;
-- id=865
UPDATE `products` SET `price_per_meter` = '132.00' WHERE `id` = 865;
-- id=866
UPDATE `products` SET `price_per_meter` = '167.20' WHERE `id` = 866;
-- id=867
UPDATE `products` SET `price_per_meter` = '164.27' WHERE `id` = 867;
-- id=868
UPDATE `products` SET `price_per_meter` = '184.80' WHERE `id` = 868;
-- id=869
UPDATE `products` SET `price_per_meter` = '180.40' WHERE `id` = 869;
-- id=870
UPDATE `products` SET `price_per_meter` = '184.80' WHERE `id` = 870;
-- id=871
UPDATE `products` SET `price_per_meter` = '180.40' WHERE `id` = 871;
-- id=872
UPDATE `products` SET `price_per_meter` = '184.80' WHERE `id` = 872;
-- id=873
UPDATE `products` SET `price_per_meter` = '180.40' WHERE `id` = 873;
-- id=874
UPDATE `products` SET `price_per_meter` = '184.80' WHERE `id` = 874;
-- id=875
UPDATE `products` SET `price_per_meter` = '180.40' WHERE `id` = 875;
-- id=876
UPDATE `products` SET `price_per_meter` = '184.80' WHERE `id` = 876;
-- id=877
UPDATE `products` SET `price_per_meter` = '231.73' WHERE `id` = 877;
-- id=878
UPDATE `products` SET `price_per_meter` = '231.73' WHERE `id` = 878;
-- id=879
UPDATE `products` SET `price_per_meter` = '3813.33' WHERE `id` = 879;
-- id=880
UPDATE `products` SET `price_per_meter` = '5280.00' WHERE `id` = 880;
-- id=881
UPDATE `products` SET `price_per_meter` = '5573.33' WHERE `id` = 881;
-- id=882
UPDATE `products` SET `price_per_meter` = '5280.00' WHERE `id` = 882;
-- id=883
UPDATE `products` SET `price_per_meter` = '1742.40' WHERE `id` = 883;
-- id=884
UPDATE `products` SET `price_per_meter` = '1877.33' WHERE `id` = 884;
-- id=885
UPDATE `products` SET `price_per_meter` = '2147.20' WHERE `id` = 885;
-- id=886
UPDATE `products` SET `price_per_meter` = '1830.40' WHERE `id` = 886;
-- id=887
UPDATE `products` SET `price_per_meter` = '1830.40' WHERE `id` = 887;
-- id=888
UPDATE `products` SET `price_per_meter` = '2053.33' WHERE `id` = 888;
-- id=889
UPDATE `products` SET `price_per_meter` = '1149.87' WHERE `id` = 889;
-- id=890
UPDATE `products` SET `price_per_meter` = '1742.40' WHERE `id` = 890;
-- id=891
UPDATE `products` SET `price_per_meter` = '1877.33' WHERE `id` = 891;
-- id=892
UPDATE `products` SET `price_per_meter` = '2552.00' WHERE `id` = 892;
-- id=893
UPDATE `products` SET `price_per_meter` = '2552.00' WHERE `id` = 893;
-- id=894
UPDATE `products` SET `price_per_meter` = '2552.00' WHERE `id` = 894;
-- id=895
UPDATE `products` SET `price_per_meter` = '1607.47' WHERE `id` = 895;
-- id=896
UPDATE `products` SET `price_per_meter` = '938.67' WHERE `id` = 896;
-- id=897
UPDATE `products` SET `price_per_meter` = '938.67' WHERE `id` = 897;
-- id=898
UPDATE `products` SET `price_per_meter` = '938.67' WHERE `id` = 898;
-- id=899
UPDATE `products` SET `price_per_meter` = '1551.73' WHERE `id` = 899;
-- id=900
UPDATE `products` SET `price_per_meter` = '1551.73' WHERE `id` = 900;
-- id=901
UPDATE `products` SET `price_per_meter` = '1551.73' WHERE `id` = 901;
-- id=902
UPDATE `products` SET `price_per_meter` = '2821.87' WHERE `id` = 902;
-- id=903
UPDATE `products` SET `price_per_meter` = '2689.87' WHERE `id` = 903;
-- id=904
UPDATE `products` SET `price_per_meter` = '2689.87' WHERE `id` = 904;
-- id=905
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 905;
-- id=906
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 906;
-- id=907
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 907;
-- id=908
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 908;
-- id=909
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 909;
-- id=910
UPDATE `products` SET `price_per_meter` = '771.47' WHERE `id` = 910;
-- id=911
UPDATE `products` SET `price_per_meter` = '915.20' WHERE `id` = 911;
-- id=912
UPDATE `products` SET `price_per_meter` = '915.20' WHERE `id` = 912;
-- id=913
UPDATE `products` SET `price_per_meter` = '915.20' WHERE `id` = 913;
-- id=914
UPDATE `products` SET `price_per_meter` = '1642.67' WHERE `id` = 914;
-- id=915
UPDATE `products` SET `price_per_meter` = '2742.67' WHERE `id` = 915;
-- id=916
UPDATE `products` SET `price_per_meter` = '3291.20' WHERE `id` = 916;
-- id=917
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 917;
-- id=918
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 918;
-- id=919
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 919;
-- id=920
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 920;
-- id=921
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 921;
-- id=922
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 922;
-- id=923
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 923;
-- id=924
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 924;
-- id=925
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 925;
-- id=926
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 926;
-- id=927
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 927;
-- id=928
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 928;
-- id=929
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 929;
-- id=930
UPDATE `products` SET `price_per_meter` = '225.87' WHERE `id` = 930;
-- id=931
UPDATE `products` SET `price_per_meter` = '431.20' WHERE `id` = 931;
-- id=932
UPDATE `products` SET `price_per_meter` = '475.20' WHERE `id` = 932;
-- id=933
UPDATE `products` SET `price_per_meter` = '475.20' WHERE `id` = 933;
-- id=934
UPDATE `products` SET `price_per_meter` = '214.13' WHERE `id` = 934;
-- id=935
UPDATE `products` SET `price_per_meter` = '214.13' WHERE `id` = 935;
-- id=936
UPDATE `products` SET `price_per_meter` = '214.13' WHERE `id` = 936;
-- id=937
UPDATE `products` SET `price_per_meter` = '214.13' WHERE `id` = 937;
-- id=938
UPDATE `products` SET `price_per_meter` = '1173.33' WHERE `id` = 938;
-- id=939
UPDATE `products` SET `price_per_meter` = '1173.33' WHERE `id` = 939;
-- id=940
UPDATE `products` SET `price_per_meter` = '287.47' WHERE `id` = 940;
-- id=941
UPDATE `products` SET `price_per_meter` = '243.47' WHERE `id` = 941;
-- id=942
UPDATE `products` SET `price_per_meter` = '105.60' WHERE `id` = 942;
-- id=943
UPDATE `products` SET `price_per_meter` = '105.60' WHERE `id` = 943;
-- id=944
UPDATE `products` SET `price_per_meter` = '105.60' WHERE `id` = 944;
-- id=945
UPDATE `products` SET `price_per_meter` = '105.60' WHERE `id` = 945;
-- id=946
UPDATE `products` SET `price_per_meter` = '105.60' WHERE `id` = 946;
-- id=947
UPDATE `products` SET `price_per_meter` = '1915.47' WHERE `id` = 947;
-- id=948
UPDATE `products` SET `price_per_meter` = '2364.27' WHERE `id` = 948;
-- id=949
UPDATE `products` SET `price_per_meter` = '1173.33' WHERE `id` = 949;
-- id=950
UPDATE `products` SET `price_per_meter` = '1906.67' WHERE `id` = 950;
-- id=951
UPDATE `products` SET `price_per_meter` = '586.67' WHERE `id` = 951;
-- id=952
UPDATE `products` SET `price_per_meter` = '586.67' WHERE `id` = 952;
-- id=953
UPDATE `products` SET `price_per_meter` = '440.00' WHERE `id` = 953;
-- id=954
UPDATE `products` SET `price_per_meter` = '108.53' WHERE `id` = 954;
-- id=955
UPDATE `products` SET `price_per_meter` = '146.67' WHERE `id` = 955;
-- id=956
UPDATE `products` SET `price_per_meter` = '272.80' WHERE `id` = 956;
-- id=957
UPDATE `products` SET `price_per_meter` = '419.47' WHERE `id` = 957;
-- id=958
UPDATE `products` SET `price_per_meter` = '252.27' WHERE `id` = 958;
-- id=959
UPDATE `products` SET `price_per_meter` = '337.33' WHERE `id` = 959;
-- id=960
UPDATE `products` SET `price_per_meter` = '264.00' WHERE `id` = 960;
-- id=961
UPDATE `products` SET `price_per_meter` = '120.27' WHERE `id` = 961;
-- id=962
UPDATE `products` SET `price_per_meter` = '134.93' WHERE `id` = 962;
-- id=963
UPDATE `products` SET `price_per_meter` = '152.53' WHERE `id` = 963;
-- id=964
UPDATE `products` SET `price_per_meter` = '308.00' WHERE `id` = 964;
-- id=965
UPDATE `products` SET `price_per_meter` = '419.47' WHERE `id` = 965;
-- id=966
UPDATE `products` SET `price_per_meter` = '580.80' WHERE `id` = 966;
-- id=967
UPDATE `products` SET `price_per_meter` = '387.20' WHERE `id` = 967;
-- id=968
UPDATE `products` SET `price_per_meter` = '354.93' WHERE `id` = 968;
-- id=969
UPDATE `products` SET `price_per_meter` = '548.53' WHERE `id` = 969;
-- id=970
UPDATE `products` SET `price_per_meter` = '683.47' WHERE `id` = 970;
-- id=971
UPDATE `products` SET `price_per_meter` = '683.47' WHERE `id` = 971;
-- id=972
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 972;
-- id=973
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 973;
-- id=974
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 974;
-- id=975
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 975;
-- id=976
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 976;
-- id=977
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 977;
-- id=978
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 978;
-- id=979
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 979;
-- id=980
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 980;
-- id=981
UPDATE `products` SET `price_per_meter` = '645.33' WHERE `id` = 981;
-- id=982
UPDATE `products` SET `price_per_meter` = '243.47' WHERE `id` = 982;
-- id=983
UPDATE `products` SET `price_per_meter` = '231.73' WHERE `id` = 983;
-- id=984
UPDATE `products` SET `price_per_meter` = '167.20' WHERE `id` = 984;
-- id=985
UPDATE `products` SET `price_per_meter` = '164.27' WHERE `id` = 985;
-- id=986
UPDATE `products` SET `price_per_meter` = '202.40' WHERE `id` = 986;
-- id=987
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 987;
-- id=988
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 988;
-- id=989
UPDATE `products` SET `price_per_meter` = '211.20' WHERE `id` = 989;
-- id=990
UPDATE `products` SET `price_per_meter` = '206.80' WHERE `id` = 990;
-- id=991
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 991;
-- id=992
UPDATE `products` SET `price_per_meter` = '322.67' WHERE `id` = 992;
-- id=993
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 993;
-- id=994
UPDATE `products` SET `price_per_meter` = '211.20' WHERE `id` = 994;
-- id=995
UPDATE `products` SET `price_per_meter` = '206.80' WHERE `id` = 995;
-- id=996
UPDATE `products` SET `price_per_meter` = '264.00' WHERE `id` = 996;
-- id=997
UPDATE `products` SET `price_per_meter` = '167.20' WHERE `id` = 997;
-- id=998
UPDATE `products` SET `price_per_meter` = '164.27' WHERE `id` = 998;
-- id=999
UPDATE `products` SET `price_per_meter` = '196.53' WHERE `id` = 999;
-- id=1000
UPDATE `products` SET `price_per_meter` = '167.20' WHERE `id` = 1000;
-- id=1001
UPDATE `products` SET `price_per_meter` = '202.40' WHERE `id` = 1001;
-- id=1002
UPDATE `products` SET `price_per_meter` = '167.20' WHERE `id` = 1002;
-- id=1003
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 1003;
-- id=1004
UPDATE `products` SET `price_per_meter` = '89.47' WHERE `id` = 1004;
-- id=1005
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 1005;
-- id=1006
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 1006;
-- id=1007
UPDATE `products` SET `price_per_meter` = '89.47' WHERE `id` = 1007;
-- id=1008
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 1008;
-- id=1009
UPDATE `products` SET `price_per_meter` = '231.73' WHERE `id` = 1009;
-- id=1010
UPDATE `products` SET `price_per_meter` = '199.47' WHERE `id` = 1010;
-- id=1011
UPDATE `products` SET `price_per_meter` = '199.47' WHERE `id` = 1011;
-- id=1012
UPDATE `products` SET `price_per_meter` = '154.00' WHERE `id` = 1012;
-- id=1013
UPDATE `products` SET `price_per_meter` = '154.00' WHERE `id` = 1013;
-- id=1014
UPDATE `products` SET `price_per_meter` = '178.93' WHERE `id` = 1014;
-- id=1015
UPDATE `products` SET `price_per_meter` = '126.13' WHERE `id` = 1015;
-- id=1016
UPDATE `products` SET `price_per_meter` = '134.93' WHERE `id` = 1016;
-- id=1017
UPDATE `products` SET `price_per_meter` = '132.00' WHERE `id` = 1017;
-- id=1018
UPDATE `products` SET `price_per_meter` = '167.20' WHERE `id` = 1018;
-- id=1019
UPDATE `products` SET `price_per_meter` = '164.27' WHERE `id` = 1019;
-- id=1020
UPDATE `products` SET `price_per_meter` = '184.80' WHERE `id` = 1020;
-- id=1021
UPDATE `products` SET `price_per_meter` = '180.40' WHERE `id` = 1021;
-- id=1022
UPDATE `products` SET `price_per_meter` = '184.80' WHERE `id` = 1022;
-- id=1023
UPDATE `products` SET `price_per_meter` = '180.40' WHERE `id` = 1023;
-- id=1024
UPDATE `products` SET `price_per_meter` = '184.80' WHERE `id` = 1024;
-- id=1025
UPDATE `products` SET `price_per_meter` = '180.40' WHERE `id` = 1025;
-- id=1026
UPDATE `products` SET `price_per_meter` = '184.80' WHERE `id` = 1026;
-- id=1027
UPDATE `products` SET `price_per_meter` = '180.40' WHERE `id` = 1027;
-- id=1028
UPDATE `products` SET `price_per_meter` = '184.80' WHERE `id` = 1028;
-- id=1029
UPDATE `products` SET `price_per_meter` = '231.73' WHERE `id` = 1029;
-- id=1030
UPDATE `products` SET `price_per_meter` = '231.73' WHERE `id` = 1030;
-- id=1031
UPDATE `products` SET `price_per_meter` = '3813.33' WHERE `id` = 1031;
-- id=1032
UPDATE `products` SET `price_per_meter` = '5280.00' WHERE `id` = 1032;
-- id=1033
UPDATE `products` SET `price_per_meter` = '5573.33' WHERE `id` = 1033;
-- id=1034
UPDATE `products` SET `price_per_meter` = '5280.00' WHERE `id` = 1034;
-- id=1035
UPDATE `products` SET `price_per_meter` = '1742.40' WHERE `id` = 1035;
-- id=1036
UPDATE `products` SET `price_per_meter` = '1877.33' WHERE `id` = 1036;
-- id=1037
UPDATE `products` SET `price_per_meter` = '2147.20' WHERE `id` = 1037;
-- id=1038
UPDATE `products` SET `price_per_meter` = '1830.40' WHERE `id` = 1038;
-- id=1039
UPDATE `products` SET `price_per_meter` = '1830.40' WHERE `id` = 1039;
-- id=1040
UPDATE `products` SET `price_per_meter` = '2053.33' WHERE `id` = 1040;
-- id=1041
UPDATE `products` SET `price_per_meter` = '1149.87' WHERE `id` = 1041;
-- id=1042
UPDATE `products` SET `price_per_meter` = '1742.40' WHERE `id` = 1042;
-- id=1043
UPDATE `products` SET `price_per_meter` = '1877.33' WHERE `id` = 1043;
-- id=1044
UPDATE `products` SET `price_per_meter` = '2552.00' WHERE `id` = 1044;
-- id=1045
UPDATE `products` SET `price_per_meter` = '2552.00' WHERE `id` = 1045;
-- id=1046
UPDATE `products` SET `price_per_meter` = '2552.00' WHERE `id` = 1046;
-- id=1047
UPDATE `products` SET `price_per_meter` = '1607.47' WHERE `id` = 1047;
-- id=1048
UPDATE `products` SET `price_per_meter` = '938.67' WHERE `id` = 1048;
-- id=1049
UPDATE `products` SET `price_per_meter` = '938.67' WHERE `id` = 1049;
-- id=1050
UPDATE `products` SET `price_per_meter` = '938.67' WHERE `id` = 1050;
COMMIT;
