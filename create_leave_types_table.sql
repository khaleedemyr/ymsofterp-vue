-- Create leave_types table
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `max_days` int(11) NOT NULL DEFAULT 0,
  `requires_document` tinyint(1) NOT NULL DEFAULT 0,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample leave types
INSERT INTO `leave_types` (`name`, `max_days`, `requires_document`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
('Sakit', 0, 1, 'Izin sakit dengan surat dokter', 1, NOW(), NOW()),
('Cuti Tahunan', 12, 0, 'Cuti tahunan karyawan', 1, NOW(), NOW()),
('Izin Pribadi', 3, 0, 'Izin untuk keperluan pribadi', 1, NOW(), NOW()),
('Cuti Melahirkan', 90, 1, 'Cuti melahirkan dengan surat dokter', 1, NOW(), NOW()),
('Cuti Menikah', 3, 1, 'Cuti menikah dengan undangan', 1, NOW(), NOW()),
('Izin Darurat', 1, 0, 'Izin untuk keperluan darurat', 1, NOW(), NOW());
