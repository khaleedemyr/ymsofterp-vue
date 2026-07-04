-- New Product Development Plan & Report — eksekusi manual sekali di MySQL

CREATE TABLE IF NOT EXISTS `npd_plan_reports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(50) NOT NULL,
    `report_month` DATE NOT NULL COMMENT 'First day of report month (YYYY-MM-01)',
    `outlet_id` INT UNSIGNED NOT NULL,
    `outlet_name` VARCHAR(255) NOT NULL,
    `status` ENUM('draft', 'submitted', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'draft',
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `npd_plan_reports_number_unique` (`number`),
    KEY `npd_plan_reports_report_month_index` (`report_month`),
    KEY `npd_plan_reports_outlet_id_index` (`outlet_id`),
    KEY `npd_plan_reports_status_index` (`status`),
    KEY `npd_plan_reports_created_by_index` (`created_by`),
    KEY `npd_plan_reports_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `npd_plan_report_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id` BIGINT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `product_name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(255) NULL,
    `development_date` DATE NULL,
    `purpose` ENUM('enhancement', 'new_product', 'adjustment') NOT NULL DEFAULT 'new_product',
    `proposed_launch_date` DATE NULL,
    `proposed_launch_area_outlet` VARCHAR(255) NULL,
    `fb_cost` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `selling_price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `npd_plan_report_items_report_id_index` (`report_id`),
    CONSTRAINT `npd_plan_report_items_report_id_foreign`
        FOREIGN KEY (`report_id`) REFERENCES `npd_plan_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `npd_plan_report_approval_flows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id` BIGINT UNSIGNED NOT NULL,
    `approver_id` BIGINT UNSIGNED NOT NULL,
    `approval_level` INT UNSIGNED NOT NULL DEFAULT 1,
    `status` ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    `rejected_at` TIMESTAMP NULL DEFAULT NULL,
    `comments` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `npd_plan_report_approval_flows_report_id_index` (`report_id`),
    KEY `npd_plan_report_approval_flows_approver_id_index` (`approver_id`),
    CONSTRAINT `npd_plan_report_approval_flows_report_id_foreign`
        FOREIGN KEY (`report_id`) REFERENCES `npd_plan_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
