-- Web Profile: landing page per outlet (Justus Kunest web)
-- Eksekusi manual sekali di MySQL

CREATE TABLE IF NOT EXISTS `web_profile_outlet_landings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `outlet_id` INT UNSIGNED NOT NULL,
    `slug` VARCHAR(191) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `outlet_subtitle` VARCHAR(255) NULL COMMENT 'Contoh: ALAM SUTERA',
    `headline` VARCHAR(500) NULL,
    `intro_paragraph` TEXT NULL,
    `secondary_paragraph` TEXT NULL,
    `hero_image` VARCHAR(500) NULL,
    `gallery_images` JSON NULL,
    `logo_override` VARCHAR(500) NULL,
    `address_override` TEXT NULL COMMENT 'Deprecated — alamat diambil dari tbl_data_outlet.lokasi',
    `map_url` VARCHAR(2048) NULL COMMENT 'Deprecated — map URL dari tbl_data_outlet lat/long',
    `book_now_label` VARCHAR(100) NULL DEFAULT 'BOOK NOW',
    `see_map_label` VARCHAR(100) NULL DEFAULT 'SEE MAP',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `web_profile_outlet_landings_outlet_id_unique` (`outlet_id`),
    UNIQUE KEY `web_profile_outlet_landings_slug_unique` (`slug`),
    KEY `web_profile_outlet_landings_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
