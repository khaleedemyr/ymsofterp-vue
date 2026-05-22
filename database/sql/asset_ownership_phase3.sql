-- Asset ownership — fase 3: Lost & Breakage + Transfer Kepemilikan
-- Jalankan setelah asset_ownership_phase2.sql

START TRANSACTION;

-- Lost & Breakage header
ALTER TABLE `lost_breakage_headers`
  ADD COLUMN `owner_outlet_id` INT UNSIGNED NULL COMMENT 'FK tbl_data_outlet — pemilik aset yang hilang/rusak' AFTER `date`;

ALTER TABLE `lost_breakage_headers`
  ADD COLUMN `warehouse_outlet_id` INT UNSIGNED NULL COMMENT 'FK warehouse_outlets — lokasi gudang' AFTER `owner_outlet_id`;

UPDATE `lost_breakage_headers` SET `owner_outlet_id` = `outlet_id` WHERE `owner_outlet_id` IS NULL;

ALTER TABLE `lost_breakage_headers`
  MODIFY COLUMN `owner_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — pemilik aset';

ALTER TABLE `lost_breakage_headers`
  MODIFY COLUMN `outlet_id` INT NOT NULL COMMENT 'FK tbl_data_outlet — lokasi fisik (outlet)';

ALTER TABLE `lost_breakage_headers`
  ADD KEY `idx_lbh_owner` (`owner_outlet_id`);

ALTER TABLE `lost_breakage_headers`
  ADD KEY `idx_lbh_warehouse` (`warehouse_outlet_id`);

-- Transfer kepemilikan (stok tetap di gudang yang sama, pemilik berubah)
CREATE TABLE IF NOT EXISTS `asset_owner_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transfer_date` date NOT NULL,
  `owner_outlet_from_id` int unsigned NOT NULL COMMENT 'Pemilik asal',
  `owner_outlet_to_id` int unsigned NOT NULL COMMENT 'Pemilik tujuan',
  `warehouse_outlet_id` int unsigned NOT NULL COMMENT 'Gudang (lokasi fisik tidak berubah)',
  `outlet_id` int unsigned NOT NULL COMMENT 'Outlet lokasi gudang',
  `status` enum('draft','submitted','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `approval_by` bigint unsigned DEFAULT NULL,
  `approval_at` datetime DEFAULT NULL,
  `approval_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_owner_transfers_number_unique` (`transfer_number`),
  KEY `idx_aot_owner_from` (`owner_outlet_from_id`),
  KEY `idx_aot_owner_to` (`owner_outlet_to_id`),
  KEY `idx_aot_wh` (`warehouse_outlet_id`),
  KEY `idx_aot_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_owner_transfer_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_owner_transfer_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned DEFAULT NULL,
  `qty` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `qty_small` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `qty_medium` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `qty_large` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aoti_transfer` (`asset_owner_transfer_id`),
  CONSTRAINT `asset_owner_transfer_items_transfer_fk`
    FOREIGN KEY (`asset_owner_transfer_id`) REFERENCES `asset_owner_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_owner_transfer_approval_flows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_owner_transfer_id` bigint unsigned NOT NULL,
  `approver_id` bigint unsigned NOT NULL,
  `approval_level` int NOT NULL DEFAULT 1,
  `status` enum('PENDING','APPROVED','REJECTED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `comments` text COLLATE utf8mb4_unicode_ci,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aotaf_transfer` (`asset_owner_transfer_id`),
  CONSTRAINT `asset_owner_transfer_approval_flows_transfer_fk`
    FOREIGN KEY (`asset_owner_transfer_id`) REFERENCES `asset_owner_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
