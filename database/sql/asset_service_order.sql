-- Asset Service Order Tables
-- Track asset items sent out for service/repair to vendors

CREATE TABLE IF NOT EXISTS `asset_service_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(50) NOT NULL,
    `date` DATE NOT NULL,
    `outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK to tbl_data_outlet.id_outlet',
    `warehouse_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK to warehouse_outlets.id',
    `supplier_id` INT UNSIGNED NOT NULL COMMENT 'FK to suppliers.id',
    `description` TEXT NOT NULL COMMENT 'Service description/reason',
    `estimated_cost` DECIMAL(20,2) NOT NULL DEFAULT 0,
    `actual_cost` DECIMAL(20,2) NOT NULL DEFAULT 0,
    `status` ENUM('waiting_approval','in_service','partially_returned','returned','rejected') NOT NULL DEFAULT 'waiting_approval',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `sent_date` DATE NULL COMMENT 'Date items were sent to service',
    `return_date` DATE NULL COMMENT 'Date all items returned',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_service_order_number` (`number`),
    KEY `idx_status` (`status`),
    KEY `idx_outlet` (`outlet_id`),
    KEY `idx_warehouse` (`warehouse_outlet_id`),
    KEY `idx_supplier` (`supplier_id`),
    KEY `idx_date` (`date`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_service_order_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service_order_id` BIGINT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `qty_out` DECIMAL(15,4) NOT NULL DEFAULT 0 COMMENT 'Qty sent to service',
    `qty_returned` DECIMAL(15,4) NOT NULL DEFAULT 0 COMMENT 'Qty returned from service',
    `unit` VARCHAR(100) NOT NULL COMMENT 'Selected unit name string',
    `note` TEXT NULL,
    `return_date` DATE NULL,
    `return_note` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_service_order_id` (`service_order_id`),
    KEY `idx_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_service_order_approval_flows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service_order_id` BIGINT UNSIGNED NOT NULL,
    `approver_id` BIGINT UNSIGNED NOT NULL,
    `approval_level` INT NOT NULL DEFAULT 1,
    `status` ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    `approved_at` DATETIME NULL,
    `rejected_at` DATETIME NULL,
    `comments` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_service_order_id` (`service_order_id`),
    KEY `idx_approver` (`approver_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
