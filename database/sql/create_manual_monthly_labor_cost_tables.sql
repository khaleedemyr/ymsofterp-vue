-- Manual Monthly Labor Cost — buat tabel (tanpa migration Laravel)
-- Jalankan manual sekali di MySQL production/staging

CREATE TABLE IF NOT EXISTS `manual_monthly_labor_cost` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `month` TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    `year` SMALLINT UNSIGNED NOT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `mmlc_period_unique` (`year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `manual_monthly_labor_cost_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `manual_monthly_labor_cost_id` BIGINT UNSIGNED NOT NULL,
    `outlet_id` BIGINT UNSIGNED NOT NULL,
    `labor_cost_value` DECIMAL(18, 2) NOT NULL DEFAULT 0,
    `labor_cost_percent` DECIMAL(10, 4) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `mmlc_items_header_outlet_unique` (`manual_monthly_labor_cost_id`, `outlet_id`),
    KEY `mmlc_items_outlet_id_index` (`outlet_id`),
    CONSTRAINT `mmlc_items_header_id_foreign`
        FOREIGN KEY (`manual_monthly_labor_cost_id`) REFERENCES `manual_monthly_labor_cost` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
