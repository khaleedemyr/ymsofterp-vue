-- Create table for Member Apps Members
CREATE TABLE IF NOT EXISTS `member_apps_members` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL UNIQUE,
  `nama_lengkap` varchar(255) NOT NULL,
  `mobile_phone` varchar(20) NOT NULL UNIQUE,
  `tanggal_lahir` date NOT NULL,
  `jenis_kelamin` enum('L', 'P') NOT NULL COMMENT 'L=Laki-laki, P=Perempuan',
  `pekerjaan_id` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `pin` varchar(255) NOT NULL COMMENT 'Hashed PIN',
  `password` varchar(255) NOT NULL COMMENT 'Hashed Password',
  `is_exclusive_member` tinyint(1) NOT NULL DEFAULT 0,
  `member_level` enum('Silver', 'Loyal', 'Elite', 'Prestige') NOT NULL DEFAULT 'Silver',
  `total_spending` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total spending dalam 12 bulan terakhir',
  `just_points` int(11) NOT NULL DEFAULT 0 COMMENT 'Total Just Points yang dimiliki',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `mobile_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`),
  UNIQUE KEY `unique_mobile_phone` (`mobile_phone`),
  KEY `idx_mobile_phone` (`mobile_phone`),
  KEY `idx_email` (`email`),
  KEY `idx_member_level` (`member_level`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_pekerjaan_id` (`pekerjaan_id`),
  CONSTRAINT `fk_member_pekerjaan` FOREIGN KEY (`pekerjaan_id`) REFERENCES `member_apps_occupations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for Member Apps Occupations (Master Data)
CREATE TABLE IF NOT EXISTS `member_apps_occupations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL UNIQUE,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default occupations
INSERT INTO `member_apps_occupations` (`name`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
('PNS', 1, 1, NOW(), NOW()),
('Pegawai Swasta', 1, 2, NOW(), NOW()),
('Pelajar/Mahasiswa', 1, 3, NOW(), NOW()),
('Ibu Rumah Tangga', 1, 4, NOW(), NOW()),
('TNI/Polri', 1, 5, NOW(), NOW()),
('Wirausaha', 1, 6, NOW(), NOW());

