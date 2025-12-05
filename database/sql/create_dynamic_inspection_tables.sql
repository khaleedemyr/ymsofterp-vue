-- Dynamic Inspection System Tables
-- Created: 2025-10-01

-- 1. Tabel untuk subject inspection (Employee Grooming, Employee Facilities, dll)
CREATE TABLE `inspection_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Nama subject (e.g., "Employee Grooming", "Employee Facilities")',
  `description` text DEFAULT NULL COMMENT 'Deskripsi subject',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status aktif',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Urutan tampil',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel untuk item-item dalam setiap subject (Uniform, Nametag, dll)
CREATE TABLE `inspection_subject_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inspection_subject_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Nama item (e.g., "Uniform", "Nametag", "Grooming")',
  `description` text DEFAULT NULL COMMENT 'Deskripsi item',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status aktif',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Urutan tampil',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_subject_active_sort` (`inspection_subject_id`, `is_active`, `sort_order`),
  CONSTRAINT `fk_inspection_subject_items_subject` FOREIGN KEY (`inspection_subject_id`) REFERENCES `inspection_subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel untuk header inspection
CREATE TABLE `dynamic_inspections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inspection_number` varchar(255) NOT NULL COMMENT 'Nomor inspection',
  `outlet_id` bigint(20) unsigned NOT NULL COMMENT 'Outlet yang di-inspect',
  `pic_name` varchar(255) NOT NULL COMMENT 'Nama PIC (creator)',
  `pic_position` varchar(255) NOT NULL COMMENT 'Jabatan PIC',
  `pic_division` varchar(255) NOT NULL COMMENT 'Divisi PIC',
  `inspection_date` date NOT NULL COMMENT 'Tanggal inspection',
  `status` enum('draft','completed') NOT NULL DEFAULT 'draft' COMMENT 'Status inspection',
  `general_notes` text DEFAULT NULL COMMENT 'Catatan umum',
  `outlet_leader` varchar(255) DEFAULT NULL COMMENT 'Nama outlet leader',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'Creator',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inspection_number` (`inspection_number`),
  KEY `idx_outlet_date` (`outlet_id`, `inspection_date`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_dynamic_inspections_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`),
  CONSTRAINT `fk_dynamic_inspections_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel untuk detail inspection (checkbox, notes, dokumentasi)
CREATE TABLE `dynamic_inspection_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dynamic_inspection_id` bigint(20) unsigned NOT NULL,
  `inspection_subject_id` bigint(20) unsigned NOT NULL COMMENT 'Subject yang dipilih',
  `inspection_subject_item_id` bigint(20) unsigned NOT NULL COMMENT 'Item yang di-inspect',
  `is_checked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Status checkbox',
  `notes` text DEFAULT NULL COMMENT 'Notes untuk item ini',
  `documentation_paths` json DEFAULT NULL COMMENT 'Path dokumentasi (array)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inspection_subject` (`dynamic_inspection_id`, `inspection_subject_id`),
  KEY `idx_subject_item` (`inspection_subject_id`, `inspection_subject_item_id`),
  CONSTRAINT `fk_dynamic_inspection_details_inspection` FOREIGN KEY (`dynamic_inspection_id`) REFERENCES `dynamic_inspections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dynamic_inspection_details_subject` FOREIGN KEY (`inspection_subject_id`) REFERENCES `inspection_subjects` (`id`),
  CONSTRAINT `fk_dynamic_inspection_details_item` FOREIGN KEY (`inspection_subject_item_id`) REFERENCES `inspection_subject_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data untuk inspection subjects
INSERT INTO `inspection_subjects` (`name`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
('Employee Grooming', 'Pemeriksaan grooming dan penampilan karyawan', 1, 1, NOW(), NOW()),
('Employee Facilities', 'Pemeriksaan fasilitas karyawan', 1, 2, NOW(), NOW()),
('Staff Area Cleanliness', 'Pemeriksaan kebersihan area staff', 1, 3, NOW(), NOW()),
('P3K (Kitchen Area & Cashier)', 'Pemeriksaan kotak P3K di area kitchen dan cashier', 1, 4, NOW(), NOW()),
('Sign', 'Pemeriksaan signage dan papan informasi', 1, 5, NOW(), NOW()),
('Absensi', 'Pemeriksaan absensi karyawan', 1, 6, NOW(), NOW());

-- Insert sample data untuk inspection subject items
-- Employee Grooming items
INSERT INTO `inspection_subject_items` (`inspection_subject_id`, `name`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Uniform', 'Pemeriksaan seragam karyawan', 1, 1, NOW(), NOW()),
(1, 'Nametag', 'Pemeriksaan name tag karyawan', 1, 2, NOW(), NOW()),
(1, 'Grooming', 'Pemeriksaan grooming dan penampilan', 1, 3, NOW(), NOW());

-- Employee Facilities items
INSERT INTO `inspection_subject_items` (`inspection_subject_id`, `name`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(2, 'Office', 'Pemeriksaan area office', 1, 1, NOW(), NOW()),
(2, 'Locker', 'Pemeriksaan loker karyawan', 1, 2, NOW(), NOW()),
(2, 'Key Locker', 'Pemeriksaan loker kunci', 1, 3, NOW(), NOW()),
(2, 'Employee Meals Utensil', 'Pemeriksaan alat makan karyawan', 1, 4, NOW(), NOW()),
(2, 'Shoes Rack', 'Pemeriksaan rak sepatu', 1, 5, NOW(), NOW()),
(2, 'Mushola', 'Pemeriksaan mushola', 1, 6, NOW(), NOW()),
(2, 'APAR expired date', 'Pemeriksaan tanggal expired APAR', 1, 7, NOW(), NOW()),
(2, 'Toilet', 'Pemeriksaan toilet', 1, 8, NOW(), NOW());

-- Staff Area Cleanliness items
INSERT INTO `inspection_subject_items` (`inspection_subject_id`, `name`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(3, 'General Cleanliness', 'Pemeriksaan kebersihan umum area staff', 1, 1, NOW(), NOW());

-- P3K items
INSERT INTO `inspection_subject_items` (`inspection_subject_id`, `name`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(4, 'Betadine', 'Pemeriksaan ketersediaan betadine', 1, 1, NOW(), NOW()),
(4, 'Kain Kassa (Gauze)', 'Pemeriksaan ketersediaan kain kassa', 1, 2, NOW(), NOW()),
(4, 'Plester Gulung (Rolled Plaster/Bandage)', 'Pemeriksaan ketersediaan plester gulung', 1, 3, NOW(), NOW()),
(4, 'Plester Satuan (Individual Plaster/Band-Aid)', 'Pemeriksaan ketersediaan plester satuan', 1, 4, NOW(), NOW()),
(4, 'Bioplacentone', 'Pemeriksaan ketersediaan bioplacentone', 1, 5, NOW(), NOW()),
(4, 'Alkohol 70%', 'Pemeriksaan ketersediaan alkohol 70%', 1, 6, NOW(), NOW());

-- Sign items
INSERT INTO `inspection_subject_items` (`inspection_subject_id`, `name`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(5, 'No Smoking', 'Pemeriksaan papan No Smoking', 1, 1, NOW(), NOW()),
(5, 'Jalur Evakuasi (Evacuation Route)', 'Pemeriksaan papan jalur evakuasi', 1, 2, NOW(), NOW()),
(5, 'Assembly Point', 'Pemeriksaan papan assembly point', 1, 3, NOW(), NOW()),
(5, 'Dilarang makan di area kerja', 'Pemeriksaan papan larangan makan', 1, 4, NOW(), NOW()),
(5, 'Visi Misi Core Value', 'Pemeriksaan papan visi misi', 1, 5, NOW(), NOW()),
(5, 'Anti Narkoba', 'Pemeriksaan papan anti narkoba', 1, 6, NOW(), NOW()),
(5, 'Jagalah Kebersihan', 'Pemeriksaan papan jagalah kebersihan', 1, 7, NOW(), NOW()),
(5, 'Cuci Tangan', 'Pemeriksaan papan cuci tangan', 1, 8, NOW(), NOW()),
(5, 'Taste All Food Before Serving', 'Pemeriksaan papan taste all food', 1, 9, NOW(), NOW()),
(5, 'Others', 'Pemeriksaan signage lainnya', 1, 10, NOW(), NOW());

-- Absensi items
INSERT INTO `inspection_subject_items` (`inspection_subject_id`, `name`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(6, 'Attendance Check', 'Pemeriksaan absensi karyawan', 1, 1, NOW(), NOW());
