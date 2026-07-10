-- Manual Monthly Google Review Rating — buat tabel (tanpa migration Laravel)
-- Jalankan manual sekali di MySQL production/staging

CREATE TABLE IF NOT EXISTS `manual_monthly_google_review` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `month` TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    `year` SMALLINT UNSIGNED NOT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `mmgr_period_unique` (`year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `manual_monthly_google_review_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `manual_monthly_google_review_id` BIGINT UNSIGNED NOT NULL,
    `outlet_id` BIGINT UNSIGNED NOT NULL,
    `rating` DECIMAL(3, 2) NOT NULL DEFAULT 0 COMMENT 'Google review rating 1.00 - 5.00',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `mmgr_items_header_outlet_unique` (`manual_monthly_google_review_id`, `outlet_id`),
    KEY `mmgr_items_outlet_id_index` (`outlet_id`),
    CONSTRAINT `mmgr_items_header_id_foreign`
        FOREIGN KEY (`manual_monthly_google_review_id`) REFERENCES `manual_monthly_google_review` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
