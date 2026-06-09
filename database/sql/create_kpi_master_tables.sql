-- =====================================================
-- KPI Master Data — tables (jalankan sekali di MySQL)
-- Fase 1: Parameter Catalog, Key Strategy, Template KPI
-- =====================================================

START TRANSACTION;

-- 1. Parameter Catalog
CREATE TABLE IF NOT EXISTS `kpi_parameters` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `source_type` ENUM('erp', 'manual', 'hybrid') NOT NULL DEFAULT 'manual',
    `scope_type` ENUM('outlet', 'employee', 'division') NOT NULL DEFAULT 'outlet',
    `data_type` ENUM('decimal', 'integer', 'percent', 'hours', 'text') NOT NULL DEFAULT 'decimal',
    `description` TEXT NULL,
    `is_shared` TINYINT(1) NOT NULL DEFAULT 1,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `kpi_parameters_code_unique` (`code`),
    KEY `kpi_parameters_status_index` (`status`),
    KEY `kpi_parameters_source_type_index` (`source_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ERP mapping config per parameter (erp / hybrid)
CREATE TABLE IF NOT EXISTS `kpi_parameter_erp_mappings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kpi_parameter_id` BIGINT UNSIGNED NOT NULL,
    `resolver_key` VARCHAR(100) NOT NULL COMMENT 'e.g. daily_revenue_forecast, outlet_analyzer_payroll',
    `static_filters` JSON NULL,
    `dynamic_filter_bindings` JSON NULL COMMENT 'e.g. {"outlet_id":"evaluation.outlet_id","month":"evaluation.period_month"}',
    `aggregation` VARCHAR(50) NULL DEFAULT 'sum',
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `kpi_parameter_erp_mappings_parameter_unique` (`kpi_parameter_id`),
    CONSTRAINT `kpi_parameter_erp_mappings_parameter_fk`
        FOREIGN KEY (`kpi_parameter_id`) REFERENCES `kpi_parameters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Key Strategy master (reusable)
CREATE TABLE IF NOT EXISTS `kpi_key_strategies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `kpi_key_strategies_code_unique` (`code`),
    KEY `kpi_key_strategies_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. KPI Template header
CREATE TABLE IF NOT EXISTS `kpi_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `version` INT NOT NULL DEFAULT 1,
    `template_status` ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
    `scoring_rules` JSON NULL COMMENT '{"exceeding_min":100,"meeting_min":85,"below_max":85}',
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `kpi_templates_code_unique` (`code`),
    KEY `kpi_templates_template_status_index` (`template_status`),
    KEY `kpi_templates_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bridging jabatan ↔ template
CREATE TABLE IF NOT EXISTS `kpi_template_positions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kpi_template_id` BIGINT UNSIGNED NOT NULL,
    `id_jabatan` INT NOT NULL COMMENT 'FK tbl_data_jabatan.id_jabatan',
    `effective_from` DATE NULL,
    `effective_to` DATE NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `kpi_template_positions_template_index` (`kpi_template_id`),
    KEY `kpi_template_positions_jabatan_index` (`id_jabatan`),
    CONSTRAINT `kpi_template_positions_template_fk`
        FOREIGN KEY (`kpi_template_id`) REFERENCES `kpi_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Key Strategy instance per template (dengan bobot)
CREATE TABLE IF NOT EXISTS `kpi_template_strategies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kpi_template_id` BIGINT UNSIGNED NOT NULL,
    `kpi_key_strategy_id` BIGINT UNSIGNED NOT NULL,
    `weight_percent` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `kpi_template_strategies_template_index` (`kpi_template_id`),
    KEY `kpi_template_strategies_key_strategy_index` (`kpi_key_strategy_id`),
    CONSTRAINT `kpi_template_strategies_template_fk`
        FOREIGN KEY (`kpi_template_id`) REFERENCES `kpi_templates` (`id`) ON DELETE CASCADE,
    CONSTRAINT `kpi_template_strategies_key_strategy_fk`
        FOREIGN KEY (`kpi_key_strategy_id`) REFERENCES `kpi_key_strategies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. KPI item per strategy
CREATE TABLE IF NOT EXISTS `kpi_template_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kpi_template_strategy_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `weight_percent` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    `target_value` VARCHAR(100) NULL,
    `target_direction` ENUM('higher_better', 'lower_better') NOT NULL DEFAULT 'higher_better',
    `frequency` VARCHAR(50) NOT NULL DEFAULT 'monthly',
    `formula` TEXT NULL,
    `scoring_levels` JSON NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `kpi_template_items_strategy_index` (`kpi_template_strategy_id`),
    CONSTRAINT `kpi_template_items_strategy_fk`
        FOREIGN KEY (`kpi_template_strategy_id`) REFERENCES `kpi_template_strategies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Parameter linked to KPI item
CREATE TABLE IF NOT EXISTS `kpi_template_item_parameters` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kpi_template_item_id` BIGINT UNSIGNED NOT NULL,
    `kpi_parameter_id` BIGINT UNSIGNED NOT NULL,
    `alias` VARCHAR(50) NULL COMMENT 'optional alias in formula, defaults to parameter code',
    `is_required` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `kpi_template_item_parameters_unique` (`kpi_template_item_id`, `kpi_parameter_id`),
    KEY `kpi_template_item_parameters_parameter_index` (`kpi_parameter_id`),
    CONSTRAINT `kpi_template_item_parameters_item_fk`
        FOREIGN KEY (`kpi_template_item_id`) REFERENCES `kpi_template_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `kpi_template_item_parameters_parameter_fk`
        FOREIGN KEY (`kpi_parameter_id`) REFERENCES `kpi_parameters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
