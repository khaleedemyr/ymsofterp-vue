-- Asset Inventory Serial + RFID NTAG support
-- Jalankan manual di database ERP.

-- Flag per item asset: apakah item ini dilacak per nomor seri
ALTER TABLE `asset_inventory_items`
  ADD COLUMN `track_serial` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = unit fisik dilacak nomor seri / RFID' AFTER `large_unit_id`;

CREATE TABLE IF NOT EXISTS `asset_inventory_serials` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `serial_number` VARCHAR(50) NOT NULL,
  `tag_uid` VARCHAR(32) NULL COMMENT 'UID chip NTAG (hex uppercase, tanpa spasi)',
  `inventory_item_id` BIGINT UNSIGNED NOT NULL,
  `item_id` BIGINT UNSIGNED NOT NULL,
  `owner_outlet_id` INT UNSIGNED NOT NULL,
  `outlet_id` INT UNSIGNED NULL COMMENT 'Lokasi fisik outlet',
  `warehouse_outlet_id` BIGINT UNSIGNED NULL,
  `unit_level` ENUM('small','medium','large') NOT NULL DEFAULT 'small',
  `status` ENUM('available','in_transfer','in_service','disposed','lost','replaced') NOT NULL DEFAULT 'available',
  `source_type` VARCHAR(50) NULL COMMENT 'retroactive_tag, asset_good_receive, dll',
  `source_id` BIGINT UNSIGNED NULL,
  `cost_small` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `cost_medium` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `cost_large` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `tagged_at` TIMESTAMP NULL,
  `tagged_by` BIGINT UNSIGNED NULL,
  `replaced_by_serial_id` BIGINT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_asset_serial_number` (`serial_number`),
  UNIQUE KEY `uk_asset_serial_tag_uid` (`tag_uid`),
  KEY `idx_asset_serial_item_owner_wh` (`inventory_item_id`, `owner_outlet_id`, `warehouse_outlet_id`),
  KEY `idx_asset_serial_status` (`status`),
  KEY `idx_asset_serial_item` (`item_id`),
  CONSTRAINT `asset_inventory_serials_inventory_item_fk`
    FOREIGN KEY (`inventory_item_id`) REFERENCES `asset_inventory_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_serial_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `serial_id` BIGINT UNSIGNED NOT NULL,
  `movement_type` VARCHAR(50) NOT NULL COMMENT 'tagged, transfer_out, transfer_in, service_out, dll',
  `reference_type` VARCHAR(50) NULL,
  `reference_id` BIGINT UNSIGNED NULL,
  `from_owner_outlet_id` INT UNSIGNED NULL,
  `from_warehouse_outlet_id` BIGINT UNSIGNED NULL,
  `to_owner_outlet_id` INT UNSIGNED NULL,
  `to_warehouse_outlet_id` BIGINT UNSIGNED NULL,
  `moved_by` BIGINT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_asset_serial_mov_serial` (`serial_id`),
  KEY `idx_asset_serial_mov_ref` (`reference_type`, `reference_id`),
  CONSTRAINT `asset_serial_movements_serial_fk`
    FOREIGN KEY (`serial_id`) REFERENCES `asset_inventory_serials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menu ERP
INSERT INTO `erp_menu` (`code`, `name`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'asset_serial',
    'Asset Serial / RFID',
    251,
    '/asset-serials',
    'fa-solid fa-nfc-symbol',
    NOW(), NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @asset_serial_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'asset_serial' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@asset_serial_menu_id, 'view',   'asset_serial_view',   NOW(), NOW()),
(@asset_serial_menu_id, 'create', 'asset_serial_create', NOW(), NOW()),
(@asset_serial_menu_id, 'update', 'asset_serial_edit',   NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
