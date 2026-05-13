<?php

declare(strict_types=1);

/**
 * Схема магазина и контакты сайта: идемпотентные CREATE / ALTER и чтение настроек для шаблонов.
 */

function shop_table_exists(mysqli $link, string $table): bool
{
    $t = $link->real_escape_string($table);
    $r = $link->query("SHOW TABLES LIKE '" . $t . "'");
    $ok = $r && $r->num_rows > 0;
    if ($r) {
        $r->close();
    }
    return $ok;
}

function shop_column_exists(mysqli $link, string $table, string $column): bool
{
    $t = $link->real_escape_string($table);
    $c = $link->real_escape_string($column);
    $r = $link->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $t . "' AND COLUMN_NAME = '" . $c . "' LIMIT 1"
    );
    $ok = $r && $r->num_rows > 0;
    if ($r) {
        $r->close();
    }
    return $ok;
}

function shop_ensure_orders_table(mysqli $link): void
{
    $sql = "CREATE TABLE IF NOT EXISTS `orders` (
        `order_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `status` VARCHAR(32) NOT NULL DEFAULT 'new',
        `admin_comment` TEXT NULL,
        `items_note` VARCHAR(500) NULL,
        `customer_request` TEXT NULL,
        PRIMARY KEY (`order_id`),
        KEY `idx_orders_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    try {
        $link->query($sql);
    } catch (mysqli_sql_exception) {
        // ignore
    }
    if (!shop_table_exists($link, 'orders')) {
        return;
    }
    $alters = [
        'admin_comment' => 'ADD COLUMN `admin_comment` TEXT NULL',
        'items_note' => 'ADD COLUMN `items_note` VARCHAR(500) NULL',
        'customer_request' => 'ADD COLUMN `customer_request` TEXT NULL',
    ];
    foreach ($alters as $col => $ddl) {
        if (!shop_column_exists($link, 'orders', $col)) {
            try {
                $link->query('ALTER TABLE `orders` ' . $ddl);
            } catch (mysqli_sql_exception) {
                // ignore
            }
        }
    }
}

function shop_ensure_category_table(mysqli $link): void
{
    $sql = "CREATE TABLE IF NOT EXISTS `shop_category` (
        `category_id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(120) NOT NULL,
        `slug` VARCHAR(64) NOT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`category_id`),
        UNIQUE KEY `uq_shop_category_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    try {
        $link->query($sql);
    } catch (mysqli_sql_exception) {
        return;
    }
    $cnt = $link->query('SELECT COUNT(*) AS c FROM shop_category');
    if (!$cnt) {
        return;
    }
    $n = (int) ($cnt->fetch_assoc()['c'] ?? 0);
    $cnt->close();
    if ($n > 0) {
        return;
    }
    $rows = [
        ['Протеины', 'protein', 1],
        ['Витамины', 'vitamins', 2],
        ['Аминокислоты', 'amino', 3],
        ['Комплекты', 'sets', 4],
    ];
    $stmt = $link->prepare('INSERT INTO shop_category (name, slug, sort_order) VALUES (?, ?, ?)');
    foreach ($rows as $r) {
        $stmt->bind_param('ssi', $r[0], $r[1], $r[2]);
        try {
            $stmt->execute();
        } catch (mysqli_sql_exception) {
            // ignore duplicate
        }
    }
    $stmt->close();
}

function shop_ensure_review_table(mysqli $link): void
{
    $sql = "CREATE TABLE IF NOT EXISTS `shop_review` (
        `review_id` INT NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `product_id` INT NULL,
        `rating` TINYINT NOT NULL DEFAULT 5,
        `body` TEXT NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`review_id`),
        KEY `idx_shop_review_status` (`status`),
        KEY `idx_shop_review_product` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    try {
        $link->query($sql);
    } catch (mysqli_sql_exception) {
        // ignore
    }
}

function shop_ensure_site_settings_table(mysqli $link): void
{
    $sql = "CREATE TABLE IF NOT EXISTS `site_settings` (
        `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
        `phone` VARCHAR(80) NOT NULL DEFAULT '+7 (999) 123-45-67',
        `email` VARCHAR(255) NOT NULL DEFAULT 'info@sportnutrition.local',
        `address_line` VARCHAR(255) NOT NULL DEFAULT 'ул. Спортивная, д. 1, Москва',
        `telegram_url` VARCHAR(512) NOT NULL DEFAULT 'https://t.me',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    try {
        $link->query($sql);
    } catch (mysqli_sql_exception) {
        return;
    }
    try {
        $link->query("INSERT IGNORE INTO site_settings (id) VALUES (1)");
    } catch (mysqli_sql_exception) {
        // ignore
    }
}

function shop_ensure_catalog_category_id(mysqli $link): void
{
    if (!shop_table_exists($link, 'catalog')) {
        return;
    }
    if (!shop_column_exists($link, 'catalog', 'category_id')) {
        try {
            $link->query('ALTER TABLE `catalog` ADD COLUMN `category_id` INT NULL');
        } catch (mysqli_sql_exception) {
            // ignore
        }
    }
}

function shop_ensure_schema(mysqli $link): void
{
    shop_ensure_catalog_category_id($link);
    shop_ensure_orders_table($link);
    shop_ensure_category_table($link);
    shop_ensure_review_table($link);
    shop_ensure_site_settings_table($link);
}

/** Контакты и соцсети для футера / главной (строка id=1). */
function shop_site_settings(mysqli $link): array
{
    $defaults = [
        'phone' => '+7 (999) 123-45-67',
        'email' => 'info@sportnutrition.local',
        'address_line' => 'ул. Спортивная, д. 1, Москва',
        'telegram_url' => 'https://t.me',
    ];
    if (!shop_table_exists($link, 'site_settings')) {
        return $defaults;
    }
    $r = $link->query('SELECT phone, email, address_line, telegram_url FROM site_settings WHERE id = 1 LIMIT 1');
    if (!$r || $r->num_rows === 0) {
        if ($r) {
            $r->close();
        }
        return $defaults;
    }
    $row = $r->fetch_assoc();
    $r->close();
    return array_merge($defaults, array_filter($row, static fn ($v) => $v !== null && $v !== ''));
}
