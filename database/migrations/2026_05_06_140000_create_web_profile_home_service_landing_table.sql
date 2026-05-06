-- Home Service Landing (website) — jalankan manual di MySQL/MariaDB.
-- Jika versi DB tidak punya tipe JSON, ganti `json` menjadi `longtext` untuk kedua kolom.

CREATE TABLE IF NOT EXISTS `web_profile_home_service_landing` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hero_title` varchar(255) DEFAULT NULL,
  `hero_subtitle` text,
  `content_blocks` json DEFAULT NULL COMMENT 'array blok: title, body, video_url, text_on_left',
  `collage_images` json DEFAULT NULL COMMENT 'array path relatif storage public',
  `gallery_card_image` varchar(255) DEFAULT NULL,
  `gallery_card_label` varchar(255) DEFAULT NULL,
  `gallery_card_url` varchar(2048) DEFAULT NULL,
  `menu_card_image` varchar(255) DEFAULT NULL,
  `menu_card_label` varchar(255) DEFAULT NULL,
  `menu_card_url` varchar(2048) DEFAULT NULL,
  `cta_label` varchar(255) DEFAULT NULL,
  `cta_url` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
