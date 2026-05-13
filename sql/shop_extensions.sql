-- Дополнительные таблицы магазина (дублирует логику includes/shop_db.php для ручного импорта).
-- Если колонки в `orders` или `catalog` уже есть, соответствующие ALTER пропустите.

CREATE TABLE IF NOT EXISTS `shop_category` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(64) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_shop_category_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_review` (
  `review_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `rating` tinyint NOT NULL DEFAULT '5',
  `body` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  KEY `idx_shop_review_status` (`status`),
  KEY `idx_shop_review_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` tinyint unsigned NOT NULL DEFAULT '1',
  `phone` varchar(80) NOT NULL DEFAULT '+7 (999) 123-45-67',
  `email` varchar(255) NOT NULL DEFAULT 'info@sportnutrition.local',
  `address_line` varchar(255) NOT NULL DEFAULT 'ул. Спортивная, д. 1, Москва',
  `telegram_url` varchar(512) NOT NULL DEFAULT 'https://t.me',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `site_settings` (`id`) VALUES (1);

-- При необходимости (один раз, если колонок ещё нет):
-- ALTER TABLE `catalog` ADD COLUMN `category_id` int DEFAULT NULL;
-- ALTER TABLE `orders` ADD COLUMN `admin_comment` text NULL;
-- ALTER TABLE `orders` ADD COLUMN `items_note` varchar(500) NULL;
-- ALTER TABLE `orders` ADD COLUMN `customer_request` text NULL;
