-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: MySQL-8.4:3306
-- Время создания: Май 13 2026 г., 23:01
-- Версия сервера: 8.4.7
-- Версия PHP: 8.5.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `db`
--

-- --------------------------------------------------------

--
-- Структура таблицы `catalog`
--

CREATE TABLE `catalog` (
  `product_id` int NOT NULL,
  `owner_user_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `stock_qty` int DEFAULT '0',
  `image` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `catalog`
--

INSERT INTO `catalog` (`product_id`, `owner_user_id`, `category_id`, `title`, `description`, `price`, `stock_qty`, `image`, `created_at`) VALUES
(6, NULL, NULL, 'Протеин', 'Протеин', 2000.00, 10, 'img_6a048a41abbf78.82171479.png', '2026-05-13 22:27:13');

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `order_id` int UNSIGNED NOT NULL,
  `user_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user`
--

CREATE TABLE `user` (
  `user_id` int NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `login` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('user','admin','moderator') COLLATE utf8mb4_general_ci DEFAULT 'user',
  `status` enum('active','blocked') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `remember_token` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `user`
--

INSERT INTO `user` (`user_id`, `username`, `login`, `password`, `email`, `role`, `status`, `phone`, `birthdate`, `remember_token`, `token_expires`, `avatar`) VALUES
(1, 'Щербаков Владислав', 'shcherbakov', '$2y$12$bvYaw/plnR6yTaBPuqgZPuJTb5lXWq/ovSiGw/Owo9HuFyl3sj.hG', 'vladisch20002@gmail.com', 'user', 'active', NULL, NULL, NULL, NULL, NULL),
(2, 'Жигмитов Ринчин', 'rinchin', '$2y$12$TQDLgqN5r1lOq1o3qnViE.hiR7gatSUcDjgSStdGElrEYLi38LL8O', 'rinchin2006@gmail.com', 'user', 'active', NULL, NULL, NULL, NULL, NULL),
(19, 'Влад', 'vlad', '$2y$12$wCMa/DJ/vLFNm2fGDcMwFO2krVf2G1JgTAbu55E2exuhgk95RC2hi', 'vlad@mail.ru', 'admin', 'active', '+79833371218', '2002-09-11', NULL, NULL, 'avatar_19_1778480536.jpg'),
(20, 'Антон', 'anton', '$2y$12$jS7wasCEpUaspp6wiru01.AAtonyN5F/9Vj7ZKzqkMgmstaOeag6.', 'anton@mail.ru', 'user', 'active', NULL, NULL, NULL, NULL, NULL),
(21, 'Даша', 'dasha', '$2y$12$oVv6rXYJ.iWFEZqoY.zTSO86ScKUdbq1RGfqvp2yidx38k5VvS7da', 'dasha@mail.ru', 'user', 'active', NULL, NULL, NULL, NULL, NULL);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `catalog`
--
ALTER TABLE `catalog`
  ADD PRIMARY KEY (`product_id`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_orders_user_id` (`user_id`);

--
-- Индексы таблицы `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `catalog`
--
ALTER TABLE `catalog`
  MODIFY `product_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
