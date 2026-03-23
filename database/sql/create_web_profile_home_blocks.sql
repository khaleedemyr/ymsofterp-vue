-- Company profile home page blocks (text / video + caption)
-- Jalankan manual di MySQL jika belum ada tabelnya.

CREATE TABLE IF NOT EXISTS `web_profile_home_blocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sort_order` int NOT NULL DEFAULT 0,
  `block_type` varchar(20) NOT NULL COMMENT 'text, video',
  `title` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `bg_variant` varchar(20) NOT NULL DEFAULT 'dark' COMMENT 'dark, light, video_dark',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `web_profile_home_blocks_sort_order_index` (`sort_order`),
  KEY `web_profile_home_blocks_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
