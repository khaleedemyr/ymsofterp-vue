-- Asset Manual Monthly Lost & Breakage — buat tabel (tanpa migration Laravel)
-- Jalankan manual sekali di MySQL production/staging

CREATE TABLE IF NOT EXISTS `asset_manual_monthly_lost_breakage` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `month` TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    `year` SMALLINT UNSIGNED NOT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ammlb_period_unique` (`year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_manual_monthly_lost_breakage_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `asset_manual_monthly_lost_breakage_id` BIGINT UNSIGNED NOT NULL,
    `outlet_id` BIGINT UNSIGNED NOT NULL,
    `lost_breakage_value` DECIMAL(18, 2) NOT NULL DEFAULT 0,
    `lost_breakage_percent` DECIMAL(10, 4) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ammlb_items_header_outlet_unique` (`asset_manual_monthly_lost_breakage_id`, `outlet_id`),
    KEY `ammlb_items_outlet_id_index` (`outlet_id`),
    CONSTRAINT `ammlb_items_header_id_foreign`
        FOREIGN KEY (`asset_manual_monthly_lost_breakage_id`) REFERENCES `asset_manual_monthly_lost_breakage` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
