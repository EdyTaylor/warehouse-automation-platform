-- phpMyAdmin SQL Dump
-- version 4.9.7
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Апр 27 2026 г., 15:10
-- Версия сервера: 8.0.34-26-beget-1-1
-- Версия PHP: 5.6.40

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `friendcrm_film`
--

-- --------------------------------------------------------

--
-- Структура таблицы `b24_sale_lines`
--
-- Создание: Апр 27 2026 г., 10:18
--

DROP TABLE IF EXISTS `b24_sale_lines`;
CREATE TABLE `b24_sale_lines` (
  `id` int NOT NULL,
  `request_id` int NOT NULL,
  `b24_product_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity_m` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_per_unit` decimal(10,2) DEFAULT NULL,
  `status` enum('new','in_progress','completed') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `b24_sale_line_cuts`
--
-- Создание: Апр 27 2026 г., 10:18
--

DROP TABLE IF EXISTS `b24_sale_line_cuts`;
CREATE TABLE `b24_sale_line_cuts` (
  `id` int NOT NULL,
  `line_id` int NOT NULL,
  `roll_id` int NOT NULL,
  `meters` decimal(10,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `b24_sale_requests`
--
-- Создание: Апр 27 2026 г., 10:18
--

DROP TABLE IF EXISTS `b24_sale_requests`;
CREATE TABLE `b24_sale_requests` (
  `id` int NOT NULL,
  `b24_deal_id` int NOT NULL,
  `deal_name` varchar(255) DEFAULT NULL,
  `responsible` varchar(255) DEFAULT NULL,
  `status` enum('new','in_progress','completed','cancelled') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `deals`
--
-- Создание: Мар 30 2026 г., 09:12
--

DROP TABLE IF EXISTS `deals`;
CREATE TABLE `deals` (
  `id` int NOT NULL,
  `b24_deal_id` int DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--
-- Создание: Мар 30 2026 г., 09:12
-- Последнее обновление: Апр 27 2026 г., 11:26
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `roll_length` decimal(10,2) NOT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `delivery_price` decimal(10,2) DEFAULT NULL,
  `price_1_4` decimal(10,2) DEFAULT NULL,
  `price_5_9` decimal(10,2) DEFAULT NULL,
  `price_10_19` decimal(10,2) DEFAULT NULL,
  `price_20_plus` decimal(10,2) DEFAULT NULL,
  `price_per_meter` decimal(10,2) DEFAULT NULL,
  `base_roll_price` decimal(10,2) DEFAULT NULL,
  `b24_product_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `name`, `roll_length`, `purchase_price`, `delivery_price`, `price_1_4`, `price_5_9`, `price_10_19`, `price_20_plus`, `price_per_meter`, `base_roll_price`, `b24_product_id`) VALUES
(2, 'IRXTM 05 CH SR HPR', '30.00', '64750.00', '84175.00', '126875.00', '0.00', '0.00', '110250.00', '8056.00', NULL, NULL),
(3, 'IRXTM 15 CH SR HPR', '30.00', '61687.50', '80237.50', '120750.00', '0.00', '0.00', '105000.00', '8056.00', NULL, NULL),
(4, 'TEST', '15.00', '60000.00', '65000.00', '90000.00', '0.00', '0.00', '80000.00', '8000.00', NULL, NULL),
(5, 'Поклейка защитной пленки', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 45),
(6, 'Тонировка стёкол', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 47),
(7, 'Шумоизоляция', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 49),
(8, 'Полировка', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 51),
(9, 'Химчистка', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 53),
(10, 'Антихром', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 55),
(11, 'Шумоизоляция', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 57),
(12, 'Шиномонтаж+шины+диски', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 59),
(13, 'Малярка', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 61),
(14, 'Кузовные работы', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 63),
(15, 'Тюнинг', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 65),
(16, 'Прошивка', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 67),
(17, 'IT оборудование', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 69),
(18, 'Звук', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 71),
(19, 'Обвесы', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 73),
(20, 'Оклейка салона', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 75),
(21, 'Подсветка', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 77),
(22, 'Перешив сидений', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 79),
(23, 'Перешив руля', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 81),
(24, 'Перешив салона', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 83),
(25, 'Тонировочные пленки', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 85),
(26, 'IRXTM 05 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 89),
(27, 'IRXTM 15 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 93),
(28, 'IRXTM 35 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 97),
(29, 'ATС 05 CH SR HPR ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 101),
(30, 'ATС 15 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 105),
(31, 'ATС 20 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 109),
(32, 'ATС 35 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 113),
(33, 'ATС 50 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 117),
(34, 'ATС 70 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 121),
(35, 'ATR 05 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 125),
(36, 'ATR 15 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 129),
(37, 'ATR 20 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 133),
(38, 'ATR 35 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 137),
(39, 'ATR 50 CH SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 141),
(40, 'AIR 75 IR SR HPR - 1,52 х 30 м', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 145),
(41, 'AIR 75 IR SR HPR    0,9 х 30 м', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 149),
(42, 'AIR 80 BLSRHPR - 1,52 х 30 м', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 153),
(43, 'AIR 80 BLSRHPR  0,90 х 30 м', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 157),
(44, 'AIR 90 СLSRHPR  1,52Х 30 м', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 161),
(45, 'AIR 90 СLSRHPR  1,83Х 30 м', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 165),
(46, 'Carbon CBS ST BK 0330', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 169),
(47, 'Carbon CBS ST BK 0530', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 173),
(48, 'Carbon CBS ST BK 1530', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 177),
(49, 'Carbon CBS ST BK 2030', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 181),
(50, 'Carbon CBS ST BK 3530', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 185),
(51, 'Carbon CBS ST BK 5030', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 189),
(52, 'Carbon CBS ST BK 7030', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 193),
(53, 'HP 03 Black SR PS', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 197),
(54, 'HP 05 Black SR PS', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 201),
(55, 'HP 15 Black SR PS', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 205),
(56, 'HP 20 Black SR PS', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 209),
(57, 'HP 35 Black SR PS', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 213),
(58, 'HP 50 Black SR PS', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 217),
(59, 'HP 70 Black SR PS', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 221),
(60, 'Ceramic PIR8070', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 225),
(61, 'Super IR 7080 BLUE', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 229),
(62, 'Super IR 7080 GREEN', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 233),
(63, 'Super IR 7080 BLUE    NEW+', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 237),
(64, 'LUXFIL CARBON NANO CERAMIC 0590', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 241),
(65, 'LUXFIL CARBON NANO CERAMIC 1590', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 245),
(66, 'LUXFIL CARBON NANO CERAMIC 3590', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 249),
(67, 'LUXFIL CARBON NANO CERAMIC 5090', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 253),
(68, 'LUXFIL CARBON NANO CERAMIC 7090', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 257),
(69, 'LUXFIL PPF 05', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 261),
(70, 'LUXFIL PPF 15', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 265),
(71, 'LUXFIL CERAMIC IR7590', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 269),
(72, 'LUXFIL AIR90   Blue', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 273),
(73, 'LUXFIL BLACK 03', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 277),
(74, 'LUXFIL BLACK 05', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 281),
(75, 'LUXFIL BLACK 15', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 285),
(76, 'LUXFIL BLACK 35', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 289),
(77, 'LUXFIL BLACK 50', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 293),
(78, 'LUXFIL BLACK 70', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 297),
(79, 'LUXFIL BLACK 80', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 301),
(80, 'КПМФ черный глянец', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 305),
(81, 'КПМФ черная структурная', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 309),
(82, 'КПМФ прозрачная (салон)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 313),
(83, 'КПМФ Матт (салон)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 317),
(84, 'Medium (средний топ)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 321),
(85, 'Soft (мягкий топ)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 325),
(86, 'Satin (матт - сати)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 329),
(87, 'Stek Smoke', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 333),
(88, 'Stek Storm', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 337),
(89, 'Stek Shade', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 341),
(90, 'Stek Dyno Flex TOP', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 345),
(91, 'Stek DYNO matte', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 349),
(92, 'ORACAL 970 черный глянец', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 353),
(93, 'ORACAL 8300 полупрозрач глянец', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 357),
(94, 'Mono PPF (мягкий топ)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 361),
(95, 'Mono PRO  (твердый топ)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 365),
(96, 'MONO TPU PPF Plus (мягкий топ)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 369),
(97, 'Mono PPF Matte', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 373),
(98, 'MONO TPU Satin', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 377),
(99, 'Mono TPU Black Gloss', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 381),
(100, 'MONO TPU Black Satin', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 385),
(101, 'Premium', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 389),
(102, 'Premium Matte', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 393),
(103, 'Xside', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 397),
(104, 'Satin', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 401),
(105, 'Риджит (ridgit)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 405),
(106, 'Хард Глосс (hard gloss)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 409),
(107, 'Hybrid Black', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 413),
(108, 'LUXFIL PPF TPH MATTE', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 417),
(109, 'LUXFIL PPF TPH GLOSS', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 421),
(110, 'LUXFIL PPF TPU GLOSS A', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 425),
(111, 'LUXFIL PPF TPU GLOSS S', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 429),
(112, 'LUXFIL PPF TPU GLOSS SS', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 433),
(113, 'LUXFIL PPF TPU MATTE', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 437),
(114, 'LUXFIL  TPH  А10', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 441),
(115, 'LUXFIL   TPU  C1', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 445),
(116, 'Nexfil PPF Clear', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 449),
(117, 'Luxfil Proffesional = Mapro', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 453),
(118, 'Luxfil Matt = Mapro', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 457),
(119, 'G-Suit', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 461),
(120, 'Luxfil ClearShield', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 465),
(121, 'Luxfil ClearShield Hard', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 469),
(122, 'LUXFIL PPF ClearShield Matt ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 473),
(123, 'Luxfil ClearShield Light', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 477),
(124, 'RAN  T', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 481),
(125, 'RAN  M', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 485),
(126, 'RAN   H', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 489),
(127, 'Luxfil PPF  Hydrophobic 3M Glue 7,5ml', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 493),
(128, 'Luxfil PPF Generation 1 TPU-S 7,5ml', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 497),
(129, 'Luxfil PPF Generation 2 TPU-SW 7,5ml', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 501),
(130, 'Luxfil PPF Generation 3 TPU-SS 7,5ml', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 505),
(131, 'LUXFIL PPF  Tint 05 limited edition', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 509),
(132, 'LUXFIL PPF  Tint 15 limited edition  ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 513),
(133, 'LUXFIL TPU windsheld Ultra HD', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 517),
(134, 'LUXFIL TPU windsheld HD', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 521),
(135, 'LLumar PPF GLOSS', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 525),
(136, 'LLumar PPF GLOSS Edge (торцы дверей)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 529),
(137, 'LLumar Valor', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 533),
(138, 'LLumar Platinum 1,52', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 537),
(139, 'LLumar Platinum 1,83', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 541),
(140, 'LLumar Platinum Matt 1,83', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 545),
(141, 'LLumar Platinum Matt 1,52', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 549),
(142, 'LLumar Matt', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 553),
(143, 'LLumar PPF 11.5MIL CLEAR CAP', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 557),
(144, 'Hexis BodyFence Х 1,52 ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 561),
(145, 'Hexis BodyFence Х Fast 1,52', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 565),
(146, 'Hexis BodyFence M, X Matt, X Satin', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 569),
(147, 'Hexis BodyFence Wide 1,83', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 573),
(148, 'Hexis BodyFence Black 1,52', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 577),
(149, 'Hexis HX 20000 (цветные глянец / мат / сатин) ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 581),
(150, 'Hexis HX 30000 (цветные глянец / мат / сатин)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 585),
(151, 'Hexis HX 30000 (карбоны, кожа, браш)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 589),
(152, 'Hexis HX 30000 (хромы глянец / сатин) ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 593),
(153, 'Hexis HX 20890B (черный глянец)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 597),
(154, 'Hexis HX 20890B (черный сатин)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 601),
(155, 'Hexis HX20890B (черный матт)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 605),
(156, 'Hexis HXR150BGR черная структурная', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 609),
(157, 'BodyFence Х 1,52 ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 613),
(158, 'BodyFence Х Fast 1,52', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 617),
(159, 'BodyFence M, X Matt, X Satin', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 621),
(160, 'BodyFence Wide 1,83', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 625),
(161, 'BodyFence Black 1,52', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 629),
(162, 'OLFA Лезвия SAB - 10B', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 633),
(163, 'OLFA Лезвия ABB - 50B', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 637),
(164, 'OLFA Ножи OL-SAC-1 ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 641),
(165, 'OLFA Ножи OL-S', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 645),
(166, 'Праймер Pro Bond', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 649),
(167, 'LLumar SA 05 CH SR PS 4', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 653),
(168, 'LLumar SA 15 CH SR PS 4', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 657),
(169, 'LLumar SA 35 CH SR PS 4', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 661),
(170, 'LLumar SA 50 CH SR PS 4', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 665),
(171, 'LLumar LA 20 S SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 669),
(172, 'LLumar ATT 05 S SR HPR 2', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 673),
(173, 'LLumar S CL SR PS 8 (1,52)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 677),
(174, 'LLumar S CL SR PS 8 (1,83)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 681),
(175, 'LLumar S CL SR PS 4 (1,83)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 685),
(176, 'LLumar S CL SR PS 4 (1,524)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 689),
(177, 'LLumar AU 85 UV SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 693),
(178, 'LLumar N 1020 SR PS 4', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 697),
(179, 'LLumar N1020 SR CDF', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 701),
(180, 'LLumar S SI 20 SR PS 4', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 705),
(181, 'LLumar NRM SFD SR HPR горошек', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 709),
(182, 'LLumar FROSTED SPARKLE', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 713),
(183, 'LLumar RM PS 2 Зерк', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 717),
(184, 'LLumar NR M B PS 2 бронз', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 721),
(185, 'LLumar NR M PS 2 Белая', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 725),
(186, 'LLumar LE 35 SR CDF', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 729),
(187, 'LLumar LE 50', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 733),
(188, 'LLumar R 35 SR HPR', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 737),
(189, 'LLumar R 20 SR CDF 1,52', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 741),
(190, 'LLumar R 20 SR CDF 1,83', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 745),
(191, 'LUXFIL SAFETY 2 light', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 749),
(192, 'LUXFIL SAFETY 4  light', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 753),
(193, 'LUXFIL SAFETY 8  light', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 757),
(194, 'LUXFIL SAFETY 12  light', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 761),
(195, 'LUXFIL SAFETY 4 (1,83)  light', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 765),
(196, 'LUXFIL SAFETY 8 (1,83)  light', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 769),
(197, 'LUXFIL SAFETY 12 (1,83)  light', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 773),
(198, 'Luxfil Silver Safety 4 mil 15%', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 777),
(199, 'Luxfil Safety 2 mil(1,524 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 781),
(200, 'Luxfil Safety 2 mil(1,83 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 785),
(201, 'Luxfil Safety 4 mil(1,524х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 789),
(202, 'Luxfil Safety 4 mil (1,83х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 793),
(203, 'Luxfil Safety 7 mil(1,524 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 797),
(204, 'Luxfil Safety 7 mil (1,83х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 801),
(205, 'Luxfil Safety 12 mil (1,83)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 805),
(206, 'Luxfil Safety 12 mil (1,524)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 809),
(207, 'Luxfil Safety 11 mil (1,524)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 813),
(208, 'Luxfil Safety 11 mil (1,83)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 817),
(209, 'Luxfil Safety Ceramic Super IR 7080 Green', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 821),
(210, 'Luxfil Safety Ceramic Super IR 7080 BLUE', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 825),
(211, 'Luxfil R GREY 10', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 829),
(212, 'Luxfil R ORANGE 10', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 833),
(213, 'Luxfil R RED 10', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 837),
(214, 'Luxfil R PINK 10', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 841),
(215, 'Luxfil R GREEN 10', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 845),
(216, 'Luxfil R BLUE 10', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 849),
(217, 'Luxfil R GOLD 10', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 853),
(218, 'Luxfil HP BRONZE 30', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 857),
(219, 'Luxfil HP BLUE 30', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 861),
(220, 'Luxfil HP GREEN 30', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 865),
(221, 'Luxfil Ceramic 90', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 869),
(222, 'Luxfil Illision(1,524 х30м) ext наружная', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 873),
(223, 'Luxfil Illision(1,524 х30м) int внутрен', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 877),
(224, 'Luxfil R Silver 05(1,524х 30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 881),
(225, 'Luxfil R Silver 05(1,524х 60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 885),
(226, 'Luxfil R Silver 05(1,83х 30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 893),
(227, 'Luxfil R Silver 05(1,83х 60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 897),
(228, 'Luxfil Silver 05 EXT 2 mil (1,525 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 901),
(229, 'Luxfil Silver 05 EXT 2 mil (1,83 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 905),
(230, 'Luxfil Silver 05 EXT 2 mil (1,83 х60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 909),
(231, 'Luxfil Silver 05 EXT 4 mil (1,52 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 913),
(232, 'Luxfil Silver 15 EXT 3 mil (1,525 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 917),
(233, 'Luxfil Silver 15 EXT 2 mil (1,525 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 921),
(234, 'Luxfil Silver 15 EXT 2 mil (1,83 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 925),
(235, 'Luxfil Silver 15 EXT 2 mil (1,83 х60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 929),
(236, 'Luxfil Silver 15 EXT 4 mil(1,525 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 933),
(237, 'Luxfil R silver 15(1,524х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 937),
(238, 'Luxfil R  silver 15(1,525х 60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 941),
(239, 'Luxfil R silver 15(1,83х30м) ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 945),
(240, 'Luxfil R silver 15(1,83х30м) ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 949),
(241, 'Luxfil R silver 15(1,83х 60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 953),
(242, 'Luxfil R silver 35 (1,525 х30м) ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 957),
(243, 'Luxfil R silver 35 (1,83 х30м) ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 961),
(244, 'Luxfil R silver 50 (1,525 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 965),
(245, 'Luxfil silver bronze 20 (1,524 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 969),
(246, 'Luxfil silver bronze 20 (1,524 х60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 973),
(247, 'Luxfil silver bronze 10 (1,524 х30м) ', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 977),
(248, 'Luxfil Silver Blue P', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 981),
(249, 'Luxfil Silver Blue 20 (1,524 х60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 985),
(250, 'Luxfil Silver Blue 20 (1,524 х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 989),
(251, 'Luxfil Silver Out (1,52 x 30 m)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 993),
(252, 'Luxfil Black Out(1,524  х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 997),
(253, 'Luxfil White Out(1,524  х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1001),
(254, 'Luxfil Deco 18 (горош)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1005),
(255, 'Luxfil Deco 1 (1,524  х60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1009),
(256, 'Luxfil Deco 17 (1,524  х60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1013),
(257, 'Luxfil Deco INT UV 003(1,525 х60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1017),
(258, 'Luxfil Deco 22(1,524  х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1021),
(259, 'LUXFIL DECO 12 (60м) Китай', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1025),
(260, 'Luxfil White Matte мат белая(1,524х30м п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1029),
(261, 'Luxfil White Matte мат белая(1,524х60м п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1033),
(262, 'Luxfil White Matte мат белая(1,83х30м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1037),
(263, 'Luxfil White Matte мат белая(1,83х60м)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1041),
(264, 'Luxfil Red Matte(1,524х30м п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1045),
(265, 'Luxfil Red Matte(1,524х60м п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1049),
(266, 'Luxfil Bronze Matte(1,524х30м п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1053),
(267, 'Luxfil Blue matte(1,524х60м п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1057),
(268, 'Luxfil Blue matte(1,524х30м п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1061),
(269, 'Luxfil Green matte(1,524х60м п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1065),
(270, 'Luxfil Green matte(1,524х30м п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1069),
(271, 'Luxfil GREY MATTE(1,524х60 м п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1073),
(272, 'Luxfil GREY MATTE(1,524х30 м п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1077),
(273, 'Luxfil Silver matte (1,524х30м  п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1081),
(274, 'Luxfil Silver bronze matte (1,524х30м  п)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1085),
(275, 'ASWF 4mil Silver 20 (1,83)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1089),
(276, 'ASWF 8mil Silver 20 (1,83)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1093),
(277, 'ASWF Natur 20 (1,83 )', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1097),
(278, 'ASWF Sky 10 (1,524)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1101),
(279, 'ASWF Sky 10 (1,83)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1105),
(280, 'ASWF Sky 20 (1,524)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1109),
(281, 'ASWF Sky 20 (1,83)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1113),
(282, 'ASWF Illision(1,83)', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1117),
(283, 'ASWF Bronze Matte', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1121),
(284, 'ASWF Grey Matte', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1125),
(285, 'ASWF Silver Matte', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1129),
(286, 'ASWF DLR 45', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1133),
(287, 'ASWF DLR 75', '30.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, 1137);

-- --------------------------------------------------------

--
-- Структура таблицы `rolls`
--
-- Создание: Апр 27 2026 г., 09:33
--

DROP TABLE IF EXISTS `rolls`;
CREATE TABLE `rolls` (
  `id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `original_length` decimal(10,2) DEFAULT NULL,
  `current_length` decimal(10,2) DEFAULT NULL,
  `reserved_length` decimal(10,2) NOT NULL DEFAULT '0.00',
  `min_full_length` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `reserved` tinyint DEFAULT '0',
  `deal_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `rolls`
--

INSERT INTO `rolls` (`id`, `product_id`, `original_length`, `current_length`, `reserved_length`, `min_full_length`, `status`, `reserved`, `deal_id`) VALUES
(126, 2, '30.00', '0.00', '0.00', '0.00', 'written_off', 0, NULL),
(127, 2, '30.00', '0.00', '0.00', '0.00', 'waste', 0, NULL),
(128, 2, '30.00', '0.00', '0.00', '0.00', 'waste', 0, NULL),
(129, 2, '30.00', '0.00', '0.00', '0.00', 'waste', 0, NULL),
(130, 2, '30.00', '0.00', '0.00', '0.00', 'waste', 0, NULL),
(131, 2, '30.00', '0.00', '0.00', '0.00', 'waste', 0, NULL),
(132, 2, '30.00', '0.00', '0.00', '0.00', 'waste', 0, NULL),
(133, 2, '30.00', '0.00', '0.00', '0.00', 'waste', 0, NULL),
(134, 2, '30.00', '0.00', '0.00', '0.00', 'waste', 0, NULL),
(135, 2, '30.00', '0.00', '0.00', '0.00', 'waste', 0, NULL),
(136, 2, '30.00', '0.00', '0.00', '0.00', 'waste', 0, NULL),
(137, 2, '30.00', '15.00', '0.00', '0.00', 'active', 0, NULL),
(138, 2, '30.00', '30.00', '0.00', '0.00', 'active', 0, NULL),
(139, 4, '15.00', '0.00', '0.00', '0.10', 'sold', 0, NULL),
(140, 4, '15.00', '0.00', '0.00', '0.10', 'sold', 0, NULL),
(141, 4, '15.00', '0.00', '0.00', '0.10', 'sold', 0, NULL),
(142, 4, '15.00', '0.00', '0.00', '0.10', 'sold', 0, NULL),
(143, 4, '15.00', '0.00', '0.00', '0.10', 'sold', 0, NULL),
(144, 4, '15.00', '0.00', '0.00', '0.10', 'sold', 0, NULL),
(145, 4, '15.00', '0.00', '0.00', '0.10', 'waste', 0, NULL),
(146, 4, '15.00', '0.00', '0.00', '0.10', 'waste', 0, NULL),
(147, 4, '15.00', '0.00', '0.00', '0.10', 'waste', 0, NULL),
(148, 4, '15.00', '0.00', '0.00', '0.10', 'waste', 0, NULL),
(149, 4, '15.00', '0.00', '0.00', '0.10', 'waste', 0, NULL),
(150, 4, '15.00', '0.00', '0.00', '0.10', 'waste', 0, NULL),
(151, 4, '15.00', '1.00', '0.00', '0.10', 'active', 0, NULL),
(152, 4, '15.00', '15.00', '0.00', '0.10', 'active', 0, NULL),
(153, 4, '15.00', '15.00', '0.00', '0.10', 'active', 0, NULL),
(154, 4, '15.00', '15.00', '0.00', '0.10', 'active', 0, NULL),
(155, 4, '15.00', '15.00', '0.00', '0.10', 'active', 0, NULL),
(156, 4, '15.00', '15.00', '0.00', '0.10', 'active', 0, NULL),
(157, 4, '15.00', '15.00', '0.00', '0.10', 'active', 0, NULL),
(158, 4, '15.00', '15.00', '0.00', '0.10', 'active', 0, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `sales`
--
-- Создание: Мар 30 2026 г., 13:34
--

DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `type` enum('roll','meter','writeoff') NOT NULL,
  `quantity` float DEFAULT NULL,
  `price_per_unit` float DEFAULT NULL,
  `total` float DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `deal_id` int DEFAULT NULL,
  `deal_url` text,
  `reserved` tinyint DEFAULT '0',
  `responsible` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `sales`
--

INSERT INTO `sales` (`id`, `product_id`, `type`, `quantity`, `price_per_unit`, `total`, `created_at`, `deal_id`, `deal_url`, `reserved`, `responsible`) VALUES
(18, 2, '', 3, 8000, 24000, '2026-03-30 16:18:03', 101, 'https://friendcrm.beget.tech/LLumar/test', 0, NULL),
(19, 2, '', 2, 8000, 16000, '2026-03-30 16:45:36', 102, 'http://...', 0, 'Иван'),
(20, 2, 'meter', 23, 8056, 185288, '2026-03-30 17:09:24', NULL, NULL, 0, NULL),
(21, 2, 'meter', 34, 8056, 273904, '2026-03-30 18:33:12', NULL, NULL, 0, NULL),
(22, 2, 'meter', 35, 8056, 281960, '2026-03-30 18:33:28', NULL, NULL, 0, NULL),
(23, 2, 'meter', 31, 8056, 249736, '2026-03-30 18:33:43', NULL, NULL, 0, NULL),
(24, 2, 'meter', 27, 8056, 217512, '2026-03-30 18:33:53', NULL, NULL, 0, NULL),
(25, 2, 'meter', 33, 8056, 265848, '2026-03-30 18:34:49', NULL, NULL, 0, NULL),
(26, 2, 'meter', 7, 8056, 56392, '2026-03-30 18:35:00', NULL, NULL, 0, NULL),
(27, 2, 'meter', 13, 8056, 104728, '2026-03-30 18:35:08', NULL, NULL, 0, NULL),
(28, 2, 'meter', 7, 8056, 56392, '2026-03-30 18:35:15', NULL, NULL, 0, NULL),
(29, 2, 'meter', 37, 8056, 298072, '2026-03-30 18:44:22', NULL, NULL, 0, NULL),
(30, 2, '', 34, 500, 17000, '2026-03-30 19:13:03', 123, 'https://bitrix/deal/123', 0, 'Менеджер'),
(31, 2, '', 34, 500, 17000, '2026-03-30 19:13:59', 123, 'https://bitrix/deal/123', 0, 'Менеджер'),
(32, 2, '', 34, 500, 17000, '2026-03-30 19:27:54', 123, 'https://bitrix/deal/123', 0, 'Менеджер'),
(33, 2, '', 34, 500, 17000, '2026-03-30 19:54:17', 123, 'https://bitrix/deal/123', 0, 'Менеджер'),
(34, 2, '', 34, 500, 17000, '2026-03-30 19:57:15', 123, 'https://bitrix/deal/123', 0, 'Менеджер'),
(35, 4, 'roll', 6, 80000, 480000, '2026-03-30 20:04:41', NULL, NULL, 0, NULL),
(36, 4, 'meter', 15, 8000, 120000, '2026-03-30 20:05:14', NULL, NULL, 0, NULL),
(37, 4, 'meter', 15, 8000, 120000, '2026-04-01 19:59:06', NULL, NULL, 0, NULL),
(38, 4, '', 34, 500, 17000, '2026-04-01 20:00:09', 123, 'https://bitrix/deal/123', 0, 'Менеджер'),
(39, 4, 'meter', 15, 8000, 120000, '2026-04-01 20:01:06', NULL, NULL, 0, NULL),
(40, 4, 'meter', 7, 8000, 56000, '2026-04-17 13:21:59', NULL, NULL, 0, NULL),
(41, 4, 'meter', 18, 8000, 144000, '2026-04-17 13:22:14', NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `stock_movements`
--
-- Создание: Апр 27 2026 г., 09:55
--

DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `roll_id` int DEFAULT NULL,
  `movement_type` enum('receipt','reserve','reserve_release','sale_meter','sale_roll','writeoff','adjustment') NOT NULL,
  `quantity_m` decimal(10,2) NOT NULL DEFAULT '0.00',
  `quantity_rolls` int NOT NULL DEFAULT '0',
  `price_per_unit` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `deal_id` int DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `bitrix_status` enum('pending','sent','error') NOT NULL DEFAULT 'pending',
  `bitrix_response` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `b24_sale_lines`
--
ALTER TABLE `b24_sale_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_b24_sale_lines_request` (`request_id`),
  ADD KEY `idx_b24_sale_lines_product` (`product_id`);

--
-- Индексы таблицы `b24_sale_line_cuts`
--
ALTER TABLE `b24_sale_line_cuts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_b24_sale_line_cuts_line` (`line_id`),
  ADD KEY `idx_b24_sale_line_cuts_roll` (`roll_id`);

--
-- Индексы таблицы `b24_sale_requests`
--
ALTER TABLE `b24_sale_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_b24_sale_requests_deal` (`b24_deal_id`);

--
-- Индексы таблицы `deals`
--
ALTER TABLE `deals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `b24_deal_id` (`b24_deal_id`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `rolls`
--
ALTER TABLE `rolls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rolls_product_reserved` (`product_id`,`reserved`,`status`),
  ADD KEY `idx_rolls_deal` (`deal_id`);

--
-- Индексы таблицы `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_movements_product` (`product_id`),
  ADD KEY `idx_stock_movements_deal` (`deal_id`),
  ADD KEY `idx_stock_movements_bitrix_status` (`bitrix_status`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `b24_sale_lines`
--
ALTER TABLE `b24_sale_lines`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `b24_sale_line_cuts`
--
ALTER TABLE `b24_sale_line_cuts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `b24_sale_requests`
--
ALTER TABLE `b24_sale_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `deals`
--
ALTER TABLE `deals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=288;

--
-- AUTO_INCREMENT для таблицы `rolls`
--
ALTER TABLE `rolls`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT для таблицы `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT для таблицы `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
