-- Query untuk membuat table jurnal (input jurnal)
CREATE TABLE IF NOT EXISTS `jurnal` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `no_jurnal` varchar(50) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` text,
  `coa_debit_id` bigint(20) UNSIGNED NOT NULL,
  `coa_kredit_id` bigint(20) UNSIGNED NOT NULL,
  `jumlah_debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `jumlah_kredit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID outlet untuk jurnal ini',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'Jenis referensi (jurnal, purchase_order, dll)',
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID dari referensi',
  `status` enum('draft','posted','cancelled') DEFAULT 'draft',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jurnal_no_jurnal_unique` (`no_jurnal`),
  KEY `jurnal_coa_debit_id_foreign` (`coa_debit_id`),
  KEY `jurnal_coa_kredit_id_foreign` (`coa_kredit_id`),
  KEY `jurnal_created_by_foreign` (`created_by`),
  KEY `jurnal_outlet_id_foreign` (`outlet_id`),
  KEY `jurnal_reference` (`reference_type`,`reference_id`),
  KEY `jurnal_tanggal` (`tanggal`),
  KEY `jurnal_status` (`status`),
  CONSTRAINT `jurnal_coa_debit_id_foreign` FOREIGN KEY (`coa_debit_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `jurnal_coa_kredit_id_foreign` FOREIGN KEY (`coa_kredit_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `jurnal_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jurnal_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Query untuk membuat table jurnal_global (semua penjurnalan dari semua menu)
CREATE TABLE IF NOT EXISTS `jurnal_global` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `no_jurnal` varchar(50) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` text,
  `coa_debit_id` bigint(20) UNSIGNED NOT NULL,
  `coa_kredit_id` bigint(20) UNSIGNED NOT NULL,
  `jumlah_debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `jumlah_kredit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `outlet_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID outlet untuk jurnal ini',
  `source_module` varchar(50) NOT NULL COMMENT 'Module sumber (jurnal, purchase_order, sales, dll)',
  `source_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID dari source module',
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('draft','posted','cancelled') DEFAULT 'draft',
  `posted_at` timestamp NULL DEFAULT NULL,
  `posted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jurnal_global_no_jurnal` (`no_jurnal`),
  KEY `jurnal_global_coa_debit_id_foreign` (`coa_debit_id`),
  KEY `jurnal_global_coa_kredit_id_foreign` (`coa_kredit_id`),
  KEY `jurnal_global_created_by_foreign` (`created_by`),
  KEY `jurnal_global_posted_by_foreign` (`posted_by`),
  KEY `jurnal_global_outlet_id_foreign` (`outlet_id`),
  KEY `jurnal_global_source` (`source_module`,`source_id`),
  KEY `jurnal_global_reference` (`reference_type`,`reference_id`),
  KEY `jurnal_global_tanggal` (`tanggal`),
  KEY `jurnal_global_status` (`status`),
  CONSTRAINT `jurnal_global_coa_debit_id_foreign` FOREIGN KEY (`coa_debit_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `jurnal_global_coa_kredit_id_foreign` FOREIGN KEY (`coa_kredit_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `jurnal_global_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jurnal_global_posted_by_foreign` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jurnal_global_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Query ALTER untuk menambahkan outlet_id jika table sudah ada (untuk update table yang sudah dibuat sebelumnya)
-- Jalankan query ini jika table jurnal sudah ada dan belum ada kolom outlet_id
-- ALTER TABLE `jurnal` 
-- ADD COLUMN `outlet_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID outlet untuk jurnal ini' AFTER `jumlah_kredit`,
-- ADD INDEX `jurnal_outlet_id_foreign` (`outlet_id`),
-- ADD CONSTRAINT `jurnal_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE RESTRICT;

-- ALTER TABLE `jurnal_global` 
-- ADD COLUMN `outlet_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID outlet untuk jurnal ini' AFTER `jumlah_kredit`,
-- ADD INDEX `jurnal_global_outlet_id_foreign` (`outlet_id`),
-- ADD CONSTRAINT `jurnal_global_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE RESTRICT;

-- Query insert untuk erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Jurnal', 'jurnal', 5, '/jurnal', 'fa-solid fa-book', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Query insert untuk erp_permission (mengambil menu_id dari insert di atas)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    m.id as menu_id,
    'view' as action,
    'jurnal_view' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu` m
WHERE m.code = 'jurnal' AND m.parent_id = 5
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    m.id as menu_id,
    'create' as action,
    'jurnal_create' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu` m
WHERE m.code = 'jurnal' AND m.parent_id = 5
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    m.id as menu_id,
    'update' as action,
    'jurnal_update' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu` m
WHERE m.code = 'jurnal' AND m.parent_id = 5
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    m.id as menu_id,
    'delete' as action,
    'jurnal_delete' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu` m
WHERE m.code = 'jurnal' AND m.parent_id = 5
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

