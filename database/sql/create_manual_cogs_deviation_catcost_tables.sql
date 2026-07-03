-- Manual COGS, Deviation & Catcost — buat tabel (tanpa migration Laravel)
-- Jalankan manual sekali di MySQL production/staging

CREATE TABLE IF NOT EXISTS `manual_cogs_deviation_catcost` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `month` TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    `year` SMALLINT UNSIGNED NOT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `mcdc_period_unique` (`year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `manual_cogs_deviation_catcost_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `manual_cogs_deviation_catcost_id` BIGINT UNSIGNED NOT NULL,
    `outlet_id` BIGINT UNSIGNED NOT NULL,
    `cogs_value` DECIMAL(18, 2) NOT NULL DEFAULT 0,
    `cogs_percent` DECIMAL(10, 4) NOT NULL DEFAULT 0,
    `deviation_value` DECIMAL(18, 2) NOT NULL DEFAULT 0,
    `deviation_percent` DECIMAL(10, 4) NOT NULL DEFAULT 0,
    `catcost_value` DECIMAL(18, 2) NOT NULL DEFAULT 0,
    `catcost_percent` DECIMAL(10, 4) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `mcdc_items_header_outlet_unique` (`manual_cogs_deviation_catcost_id`, `outlet_id`),
    KEY `mcdc_items_outlet_id_index` (`outlet_id`),
    CONSTRAINT `mcdc_items_header_id_foreign`
        FOREIGN KEY (`manual_cogs_deviation_catcost_id`) REFERENCES `manual_cogs_deviation_catcost` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
