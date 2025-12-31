-- Query CREATE TABLE untuk Web Profile Banners
-- Jalankan query ini untuk membuat table banners

CREATE TABLE IF NOT EXISTS `web_profile_banners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `background_image` varchar(255) NOT NULL COMMENT 'Path ke background image',
  `content_image` varchar(255) DEFAULT NULL COMMENT 'Path ke content image (gambar makanan, dll)',
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Urutan tampil (0 = pertama)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status aktif (1=aktif, 0=nonaktif)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

