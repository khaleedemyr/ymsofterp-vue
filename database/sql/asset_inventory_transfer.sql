-- Asset Inventory Transfer Tables
-- Transfer asset inventory stock between outlet/warehouse locations

CREATE TABLE IF NOT EXISTS `asset_inventory_transfers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transfer_number` VARCHAR(50) NOT NULL,
    `transfer_date` DATE NOT NULL,
    `outlet_id` INT UNSIGNED NOT NULL COMMENT 'Creator outlet',
    `warehouse_outlet_from_id` INT UNSIGNED NOT NULL,
    `warehouse_outlet_to_id` INT UNSIGNED NOT NULL,
    `status` ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `approval_by` BIGINT UNSIGNED NULL,
    `approval_at` DATETIME NULL,
    `approval_notes` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_transfer_number` (`transfer_number`),
    KEY `idx_status` (`status`),
    KEY `idx_outlet` (`outlet_id`),
    KEY `idx_from` (`warehouse_outlet_from_id`),
    KEY `idx_to` (`warehouse_outlet_to_id`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_transfer_date` (`transfer_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_inventory_transfer_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `asset_inventory_transfer_id` BIGINT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `unit_id` INT UNSIGNED NULL,
    `qty` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `qty_small` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `qty_medium` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `qty_large` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `note` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_transfer_id` (`asset_inventory_transfer_id`),
    KEY `idx_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_inventory_transfer_approval_flows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `asset_inventory_transfer_id` BIGINT UNSIGNED NOT NULL,
    `approver_id` BIGINT UNSIGNED NOT NULL,
    `approval_level` INT NOT NULL DEFAULT 1,
    `status` ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    `approved_at` DATETIME NULL,
    `rejected_at` DATETIME NULL,
    `comments` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_transfer_id` (`asset_inventory_transfer_id`),
    KEY `idx_approver` (`approver_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
