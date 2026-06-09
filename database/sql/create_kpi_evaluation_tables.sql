-- =====================================================
-- KPI Evaluation — tables
-- Jalankan setelah create_kpi_master_tables.sql
-- =====================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `kpi_evaluations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `evaluation_code` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Karyawan dinilai',
    `kpi_template_id` BIGINT UNSIGNED NOT NULL,
    `template_version` INT NOT NULL DEFAULT 1,
    `id_jabatan` INT NULL,
    `id_outlet` INT NULL,
    `division_id` INT NULL,
    `employee_name` VARCHAR(255) NULL,
    `jabatan_name` VARCHAR(255) NULL,
    `outlet_name` VARCHAR(255) NULL,
    `division_name` VARCHAR(255) NULL,
    `period_month` CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `eval_status` ENUM('draft', 'submitted', 'locked') NOT NULL DEFAULT 'draft',
    `total_score` DECIMAL(8, 2) NULL,
    `scoring_rules` JSON NULL,
    `assessed_by` BIGINT UNSIGNED NULL,
    `employee_comments` TEXT NULL,
    `assessor_comments` TEXT NULL,
    `submitted_at` TIMESTAMP NULL DEFAULT NULL,
    `locked_at` TIMESTAMP NULL DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `kpi_evaluations_code_unique` (`evaluation_code`),
    UNIQUE KEY `kpi_evaluations_user_period_unique` (`user_id`, `period_month`),
    KEY `kpi_evaluations_template_index` (`kpi_template_id`),
    KEY `kpi_evaluations_status_index` (`eval_status`),
    KEY `kpi_evaluations_period_index` (`period_month`),
    CONSTRAINT `kpi_evaluations_template_fk`
        FOREIGN KEY (`kpi_template_id`) REFERENCES `kpi_templates` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kpi_evaluation_parameter_values` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kpi_evaluation_id` BIGINT UNSIGNED NOT NULL,
    `kpi_parameter_id` BIGINT UNSIGNED NOT NULL,
    `parameter_code` VARCHAR(50) NOT NULL,
    `parameter_name` VARCHAR(255) NOT NULL,
    `source_type` ENUM('erp', 'manual', 'hybrid') NOT NULL DEFAULT 'manual',
    `scope_type` ENUM('outlet', 'employee', 'division') NOT NULL DEFAULT 'outlet',
    `erp_value` DECIMAL(18, 4) NULL,
    `manual_value` DECIMAL(18, 4) NULL,
    `final_value` DECIMAL(18, 4) NULL,
    `is_overridden` TINYINT(1) NOT NULL DEFAULT 0,
    `override_reason` TEXT NULL,
    `erp_fetched_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `kpi_eval_param_values_unique` (`kpi_evaluation_id`, `kpi_parameter_id`),
    KEY `kpi_eval_param_values_param_index` (`kpi_parameter_id`),
    CONSTRAINT `kpi_eval_param_values_eval_fk`
        FOREIGN KEY (`kpi_evaluation_id`) REFERENCES `kpi_evaluations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `kpi_eval_param_values_param_fk`
        FOREIGN KEY (`kpi_parameter_id`) REFERENCES `kpi_parameters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kpi_evaluation_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kpi_evaluation_id` BIGINT UNSIGNED NOT NULL,
    `kpi_template_strategy_id` BIGINT UNSIGNED NULL,
    `kpi_template_item_id` BIGINT UNSIGNED NULL,
    `kpi_parameter_id` BIGINT UNSIGNED NULL,
    `key_strategy_name` VARCHAR(255) NULL,
    `strategy_weight_percent` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    `item_name` VARCHAR(255) NOT NULL,
    `weight_percent` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    `target_value` VARCHAR(100) NULL,
    `target_direction` ENUM('higher_better', 'lower_better') NOT NULL DEFAULT 'higher_better',
    `frequency` VARCHAR(50) NULL,
    `formula` TEXT NULL,
    `achievement_percent` DECIMAL(10, 4) NULL,
    `performance_level` ENUM('exceeding', 'meeting', 'below') NULL,
    `score` DECIMAL(8, 2) NULL,
    `weighted_score` DECIMAL(8, 4) NULL,
    `improvement_plan` TEXT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `kpi_eval_items_eval_index` (`kpi_evaluation_id`),
    CONSTRAINT `kpi_eval_items_eval_fk`
        FOREIGN KEY (`kpi_evaluation_id`) REFERENCES `kpi_evaluations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
