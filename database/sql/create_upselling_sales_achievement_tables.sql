-- Upselling Sales Achievement — buat tabel (tanpa migration Laravel)
-- Jalankan manual sekali di MySQL production/staging

CREATE TABLE IF NOT EXISTS `upselling_sales_achievements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `outlet_id` BIGINT UNSIGNED NOT NULL,
    `month` TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    `year` SMALLINT UNSIGNED NOT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `usa_outlet_month_year_unique` (`outlet_id`, `month`, `year`),
    KEY `upselling_sales_achievements_outlet_id_index` (`outlet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `upselling_sales_achievement_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `achievement_id` BIGINT UNSIGNED NOT NULL,
    `item_id` BIGINT UNSIGNED NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `category_label` VARCHAR(255) NULL,
    `average_check` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `cover` INT UNSIGNED NOT NULL DEFAULT 0,
    `fb_revenue` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `upselling_sales_achievement_items_achievement_id_item_id_index` (`achievement_id`, `item_id`),
    CONSTRAINT `upselling_sales_achievement_items_achievement_id_foreign`
        FOREIGN KEY (`achievement_id`) REFERENCES `upselling_sales_achievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
