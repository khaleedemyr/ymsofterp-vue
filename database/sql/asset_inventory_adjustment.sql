-- Asset Inventory Adjustment Tables
-- Adjust asset inventory stock (in/out) with approval flow

CREATE TABLE IF NOT EXISTS `asset_inventory_adjustments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(50) NOT NULL,
    `date` DATE NOT NULL,
    `outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK to tbl_data_outlet.id_outlet',
    `warehouse_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK to warehouse_outlets.id',
    `type` ENUM('in','out') NOT NULL COMMENT 'in=stock increase, out=stock decrease',
    `reason` TEXT NOT NULL,
    `status` ENUM('waiting_approval','approved','rejected') NOT NULL DEFAULT 'waiting_approval',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_adjustment_number` (`number`),
    KEY `idx_status` (`status`),
    KEY `idx_outlet` (`outlet_id`),
    KEY `idx_warehouse` (`warehouse_outlet_id`),
    KEY `idx_type` (`type`),
    KEY `idx_date` (`date`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_inventory_adjustment_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `adjustment_id` BIGINT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `qty` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `unit` VARCHAR(100) NOT NULL COMMENT 'Selected unit name string',
    `note` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_adjustment_id` (`adjustment_id`),
    KEY `idx_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_inventory_adjustment_approval_flows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `adjustment_id` BIGINT UNSIGNED NOT NULL,
    `approver_id` BIGINT UNSIGNED NOT NULL,
    `approval_level` INT NOT NULL DEFAULT 1,
    `status` ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    `approved_at` DATETIME NULL,
    `rejected_at` DATETIME NULL,
    `comments` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_adjustment_id` (`adjustment_id`),
    KEY `idx_approver` (`approver_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
