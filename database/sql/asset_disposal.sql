-- Asset Disposal Tables
-- Track asset items disposed (discarded or sold)

CREATE TABLE IF NOT EXISTS `asset_disposals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(50) NOT NULL,
    `date` DATE NOT NULL,
    `outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK to tbl_data_outlet.id_outlet',
    `warehouse_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK to warehouse_outlets.id',
    `type` ENUM('discard','sold') NOT NULL DEFAULT 'discard',
    `description` TEXT NOT NULL,
    `buyer_name` VARCHAR(255) NULL COMMENT 'Nama pembeli, for sold type',
    `buyer_contact` VARCHAR(255) NULL COMMENT 'Kontak pembeli, for sold type',
    `total_sale_price` DECIMAL(20,2) NOT NULL DEFAULT 0,
    `status` ENUM('waiting_approval','approved','rejected') NOT NULL DEFAULT 'waiting_approval',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_disposal_number` (`number`),
    KEY `idx_status` (`status`),
    KEY `idx_type` (`type`),
    KEY `idx_outlet` (`outlet_id`),
    KEY `idx_warehouse` (`warehouse_outlet_id`),
    KEY `idx_date` (`date`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_disposal_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `disposal_id` BIGINT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `qty` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `unit` VARCHAR(100) NOT NULL,
    `sale_price` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'Harga jual per item, 0 for discard',
    `note` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_disposal_id` (`disposal_id`),
    KEY `idx_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_disposal_photos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `disposal_id` BIGINT UNSIGNED NOT NULL,
    `photo_path` VARCHAR(500) NOT NULL,
    `caption` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_disposal_id` (`disposal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_disposal_approval_flows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `disposal_id` BIGINT UNSIGNED NOT NULL,
    `approver_id` BIGINT UNSIGNED NOT NULL,
    `approval_level` INT NOT NULL DEFAULT 1,
    `status` ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    `approved_at` DATETIME NULL,
    `rejected_at` DATETIME NULL,
    `comments` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_disposal_id` (`disposal_id`),
    KEY `idx_approver` (`approver_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
