-- Query untuk menambahkan struktur header dan kolom status/number untuk Outlet WIP Production
-- Jangan migration, query saja

-- 1. Buat tabel header untuk mengelompokkan multiple productions
CREATE TABLE IF NOT EXISTS `outlet_wip_production_headers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(255) DEFAULT NULL,
  `production_date` date NOT NULL,
  `batch_number` varchar(255) DEFAULT NULL,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `warehouse_outlet_id` bigint(20) unsigned NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('DRAFT','SUBMITTED','PROCESSED') DEFAULT 'DRAFT',
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `outlet_wip_production_headers_outlet_id_foreign` (`outlet_id`),
  KEY `outlet_wip_production_headers_warehouse_outlet_id_foreign` (`warehouse_outlet_id`),
  KEY `outlet_wip_production_headers_created_by_foreign` (`created_by`),
  KEY `outlet_wip_production_headers_status` (`status`),
  CONSTRAINT `outlet_wip_production_headers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `outlet_wip_production_headers_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`),
  CONSTRAINT `outlet_wip_production_headers_warehouse_outlet_id_foreign` FOREIGN KEY (`warehouse_outlet_id`) REFERENCES `warehouse_outlets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tambahkan kolom header_id ke tabel outlet_wip_productions (jika belum ada)
ALTER TABLE `outlet_wip_productions` 
ADD COLUMN IF NOT EXISTS `header_id` bigint(20) unsigned NULL AFTER `id`,
ADD INDEX IF NOT EXISTS `outlet_wip_productions_header_id_foreign` (`header_id`);

-- 3. Tambahkan foreign key untuk header_id (jika belum ada)
ALTER TABLE `outlet_wip_productions`
ADD CONSTRAINT IF NOT EXISTS `outlet_wip_productions_header_id_foreign` 
FOREIGN KEY (`header_id`) REFERENCES `outlet_wip_production_headers` (`id`) ON DELETE CASCADE;

