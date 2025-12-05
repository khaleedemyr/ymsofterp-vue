-- Create departemens table
CREATE TABLE IF NOT EXISTS `departemens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama_departemen` varchar(255) NOT NULL COMMENT 'Nama departemen',
  `kode_departemen` varchar(50) NOT NULL COMMENT 'Kode departemen',
  `deskripsi` text DEFAULT NULL COMMENT 'Deskripsi departemen',
  `status` enum('A','N') DEFAULT 'A' COMMENT 'Status: A=Aktif, N=Non-Aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departemens_kode_departemen_unique` (`kode_departemen`),
  KEY `departemens_status_index` (`status`),
  KEY `departemens_nama_departemen_index` (`nama_departemen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel master departemen';

-- Create areas table
CREATE TABLE IF NOT EXISTS `areas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama_area` varchar(255) NOT NULL COMMENT 'Nama area',
  `kode_area` varchar(50) NOT NULL COMMENT 'Kode area',
  `departemen_id` bigint(20) unsigned NOT NULL COMMENT 'ID departemen',
  `deskripsi` text DEFAULT NULL COMMENT 'Deskripsi area',
  `status` enum('A','N') DEFAULT 'A' COMMENT 'Status: A=Aktif, N=Non-Aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `areas_kode_area_unique` (`kode_area`),
  KEY `areas_departemen_id_foreign` (`departemen_id`),
  KEY `areas_status_index` (`status`),
  KEY `areas_nama_area_index` (`nama_area`),
  CONSTRAINT `areas_departemen_id_foreign` FOREIGN KEY (`departemen_id`) REFERENCES `departemens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel master area';

-- Insert sample data for departemens
INSERT INTO `departemens` (`nama_departemen`, `kode_departemen`, `deskripsi`, `status`, `created_at`, `updated_at`) VALUES
('Human Resources', 'HR', 'Departemen yang mengelola sumber daya manusia', 'A', NOW(), NOW()),
('Finance', 'FIN', 'Departemen yang mengelola keuangan perusahaan', 'A', NOW(), NOW()),
('Operations', 'OPS', 'Departemen yang mengelola operasional perusahaan', 'A', NOW(), NOW()),
('Marketing', 'MKT', 'Departemen yang mengelola pemasaran dan promosi', 'A', NOW(), NOW()),
('IT Support', 'IT', 'Departemen yang mengelola teknologi informasi', 'A', NOW(), NOW());

-- Insert sample data for areas
INSERT INTO `areas` (`nama_area`, `kode_area`, `departemen_id`, `deskripsi`, `status`, `created_at`, `updated_at`) VALUES
('Recruitment', 'REC', 1, 'Area yang menangani rekrutmen karyawan', 'A', NOW(), NOW()),
('Training & Development', 'TND', 1, 'Area yang menangani pelatihan dan pengembangan', 'A', NOW(), NOW()),
('Payroll', 'PAY', 2, 'Area yang menangani penggajian', 'A', NOW(), NOW()),
('Accounting', 'ACC', 2, 'Area yang menangani akuntansi', 'A', NOW(), NOW()),
('Production', 'PROD', 3, 'Area yang menangani produksi', 'A', NOW(), NOW()),
('Quality Control', 'QC', 3, 'Area yang menangani kontrol kualitas', 'A', NOW(), NOW()),
('Digital Marketing', 'DIGMKT', 4, 'Area yang menangani pemasaran digital', 'A', NOW(), NOW()),
('Brand Management', 'BRAND', 4, 'Area yang menangani manajemen merek', 'A', NOW(), NOW()),
('System Administration', 'SYSADM', 5, 'Area yang menangani administrasi sistem', 'A', NOW(), NOW()),
('Network Support', 'NETSUP', 5, 'Area yang menangani dukungan jaringan', 'A', NOW(), NOW());
