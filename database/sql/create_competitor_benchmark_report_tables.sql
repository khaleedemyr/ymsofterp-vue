-- Competitor Benchmark Report — eksekusi manual sekali di MySQL
-- Tanpa approval & tanpa outlet: report langsung disimpan dengan status approved

CREATE TABLE IF NOT EXISTS `competitor_benchmark_reports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(50) NOT NULL,
    `report_month` DATE NOT NULL COMMENT 'First day of report month (YYYY-MM-01)',
    `outlet_id` INT UNSIGNED NULL,
    `outlet_name` VARCHAR(255) NULL,
    `pics` TEXT NULL COMMENT 'JSON array [{id,name,jabatan}] PIC users',
    `status` ENUM('draft', 'submitted', 'approved', 'rejected', 'requires_revision', 'cancelled') NOT NULL DEFAULT 'approved',
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `competitor_benchmark_reports_number_unique` (`number`),
    KEY `competitor_benchmark_reports_report_month_index` (`report_month`),
    KEY `competitor_benchmark_reports_created_by_index` (`created_by`),
    KEY `competitor_benchmark_reports_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `competitor_benchmark_report_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id` BIGINT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `brand_restaurant_visited` VARCHAR(255) NOT NULL,
    `location` VARCHAR(255) NULL,
    `visit_date` DATE NULL,
    `product_benchmark` TEXT NULL,
    `service_benchmark` TEXT NULL,
    `pricing_benchmark` TEXT NULL,
    `operational_benchmark` TEXT NULL,
    `market_positioning_benchmark` TEXT NULL,
    `summary_report` TEXT NULL,
    `development_action_plan` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `competitor_benchmark_report_items_report_id_index` (`report_id`),
    CONSTRAINT `competitor_benchmark_report_items_report_id_foreign`
        FOREIGN KEY (`report_id`) REFERENCES `competitor_benchmark_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
