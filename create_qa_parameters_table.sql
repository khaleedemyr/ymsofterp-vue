-- Create QA Parameters table
CREATE TABLE IF NOT EXISTS `qa_parameters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_parameter` varchar(50) NOT NULL,
  `parameter` varchar(255) NOT NULL,
  `status` enum('A','N') NOT NULL DEFAULT 'A' COMMENT 'A = Aktif, N = Non-Aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qa_parameters_kode_parameter_unique` (`kode_parameter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data
INSERT INTO `qa_parameters` (`kode_parameter`, `parameter`, `status`, `created_at`, `updated_at`) VALUES
('QP001', 'Temperature Control', 'A', NOW(), NOW()),
('QP002', 'Hygiene Standards', 'A', NOW(), NOW()),
('QP003', 'Quality Check', 'A', NOW(), NOW()),
('QP004', 'Safety Protocols', 'A', NOW(), NOW()),
('QP005', 'Performance Metrics', 'A', NOW(), NOW());
