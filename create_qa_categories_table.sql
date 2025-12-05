-- Create QA Categories table
CREATE TABLE IF NOT EXISTS `qa_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_categories` varchar(50) NOT NULL,
  `categories` varchar(255) NOT NULL,
  `status` enum('A','N') NOT NULL DEFAULT 'A' COMMENT 'A = Aktif, N = Non-Aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qa_categories_kode_categories_unique` (`kode_categories`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data
INSERT INTO `qa_categories` (`kode_categories`, `categories`, `status`, `created_at`, `updated_at`) VALUES
('QA001', 'Food Safety', 'A', NOW(), NOW()),
('QA002', 'Hygiene & Sanitation', 'A', NOW(), NOW()),
('QA003', 'Quality Control', 'A', NOW(), NOW()),
('QA004', 'Equipment Maintenance', 'A', NOW(), NOW()),
('QA005', 'Staff Training', 'A', NOW(), NOW());
