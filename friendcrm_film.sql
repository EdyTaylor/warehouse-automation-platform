-- phpMyAdmin SQL Dump
-- version 4.9.7
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Апр 27 2026 г., 12:34
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
(4, 'TEST', '15.00', '60000.00', '65000.00', '90000.00', '0.00', '0.00', '80000.00', '8000.00', NULL, NULL);

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
(18, 2, '', 3, 8000, 24000, '2026-03-30 16:18:03', 101, 'https://friendcrm.beget.tech/test', 0, NULL),
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

--
-- Индексы сохранённых таблиц
--

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
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `deals`
--
ALTER TABLE `deals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
