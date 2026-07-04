-- Employee Onboarding System — eksekusi manual sekali di MySQL

CREATE TABLE IF NOT EXISTS `onboarding_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_weeks` TINYINT UNSIGNED NOT NULL DEFAULT 8,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `onboarding_templates_code_unique` (`code`),
    KEY `onboarding_templates_is_active_index` (`is_active`),
    KEY `onboarding_templates_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `onboarding_template_weeks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` BIGINT UNSIGNED NOT NULL,
    `week_number` TINYINT UNSIGNED NOT NULL,
    `week_label` VARCHAR(255) NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `onboarding_template_weeks_template_week_unique` (`template_id`, `week_number`),
    KEY `onboarding_template_weeks_template_id_index` (`template_id`),
    CONSTRAINT `onboarding_template_weeks_template_id_foreign`
        FOREIGN KEY (`template_id`) REFERENCES `onboarding_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `onboarding_template_areas` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` BIGINT UNSIGNED NOT NULL,
    `week_id` BIGINT UNSIGNED NOT NULL,
    `area_name` VARCHAR(255) NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `onboarding_template_areas_template_id_index` (`template_id`),
    KEY `onboarding_template_areas_week_id_index` (`week_id`),
    CONSTRAINT `onboarding_template_areas_template_id_foreign`
        FOREIGN KEY (`template_id`) REFERENCES `onboarding_templates` (`id`) ON DELETE CASCADE,
    CONSTRAINT `onboarding_template_areas_week_id_foreign`
        FOREIGN KEY (`week_id`) REFERENCES `onboarding_template_weeks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `onboarding_template_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` BIGINT UNSIGNED NOT NULL,
    `area_id` BIGINT UNSIGNED NOT NULL,
    `checklist_text` TEXT NOT NULL,
    `pic_role_hint` VARCHAR(255) NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `onboarding_template_items_template_id_index` (`template_id`),
    KEY `onboarding_template_items_area_id_index` (`area_id`),
    CONSTRAINT `onboarding_template_items_template_id_foreign`
        FOREIGN KEY (`template_id`) REFERENCES `onboarding_templates` (`id`) ON DELETE CASCADE,
    CONSTRAINT `onboarding_template_items_area_id_foreign`
        FOREIGN KEY (`area_id`) REFERENCES `onboarding_template_areas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `onboarding_template_week_approvers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` BIGINT UNSIGNED NOT NULL,
    `week_number` TINYINT UNSIGNED NOT NULL,
    `approver_user_id` BIGINT UNSIGNED NOT NULL,
    `approval_level` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `onboarding_template_week_approvers_template_week_index` (`template_id`, `week_number`),
    CONSTRAINT `onboarding_template_week_approvers_template_id_foreign`
        FOREIGN KEY (`template_id`) REFERENCES `onboarding_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_onboardings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(50) NOT NULL,
    `template_id` BIGINT UNSIGNED NOT NULL,
    `template_name` VARCHAR(255) NOT NULL,
    `employee_user_id` BIGINT UNSIGNED NOT NULL,
    `outlet_id` INT UNSIGNED NULL,
    `outlet_name` VARCHAR(255) NULL,
    `start_date` DATE NOT NULL,
    `current_week` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `unlocked_week` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `total_weeks` TINYINT UNSIGNED NOT NULL DEFAULT 8,
    `status` ENUM('draft', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'in_progress',
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_onboardings_number_unique` (`number`),
    KEY `employee_onboardings_employee_user_id_index` (`employee_user_id`),
    KEY `employee_onboardings_template_id_index` (`template_id`),
    KEY `employee_onboardings_status_index` (`status`),
    KEY `employee_onboardings_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_onboarding_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `onboarding_id` BIGINT UNSIGNED NOT NULL,
    `template_item_id` BIGINT UNSIGNED NULL,
    `week_number` TINYINT UNSIGNED NOT NULL,
    `area_name` VARCHAR(255) NOT NULL,
    `checklist_text` TEXT NOT NULL,
    `pic_role_hint` VARCHAR(255) NULL,
    `assigned_pic_user_id` BIGINT UNSIGNED NULL,
    `status` ENUM('pending', 'ongoing', 'done') NOT NULL DEFAULT 'pending',
    `remark` TEXT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `employee_onboarding_items_onboarding_id_index` (`onboarding_id`),
    KEY `employee_onboarding_items_week_number_index` (`week_number`),
    KEY `employee_onboarding_items_assigned_pic_user_id_index` (`assigned_pic_user_id`),
    CONSTRAINT `employee_onboarding_items_onboarding_id_foreign`
        FOREIGN KEY (`onboarding_id`) REFERENCES `employee_onboardings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_onboarding_week_submissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `onboarding_id` BIGINT UNSIGNED NOT NULL,
    `week_number` TINYINT UNSIGNED NOT NULL,
    `status` ENUM('submitted', 'approved', 'rejected', 'requires_revision') NOT NULL DEFAULT 'submitted',
    `submitted_at` TIMESTAMP NULL DEFAULT NULL,
    `submitted_by` BIGINT UNSIGNED NULL,
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_onboarding_week_submissions_unique` (`onboarding_id`, `week_number`),
    KEY `employee_onboarding_week_submissions_status_index` (`status`),
    CONSTRAINT `employee_onboarding_week_submissions_onboarding_id_foreign`
        FOREIGN KEY (`onboarding_id`) REFERENCES `employee_onboardings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_onboarding_approval_flows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `week_submission_id` BIGINT UNSIGNED NOT NULL,
    `approver_id` BIGINT UNSIGNED NOT NULL,
    `approval_level` INT UNSIGNED NOT NULL DEFAULT 1,
    `status` ENUM('PENDING', 'APPROVED', 'REJECTED', 'REQUIRES_REVISION') NOT NULL DEFAULT 'PENDING',
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    `rejected_at` TIMESTAMP NULL DEFAULT NULL,
    `comments` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `employee_onboarding_approval_flows_week_submission_id_index` (`week_submission_id`),
    KEY `employee_onboarding_approval_flows_approver_id_index` (`approver_id`),
    CONSTRAINT `employee_onboarding_approval_flows_week_submission_id_foreign`
        FOREIGN KEY (`week_submission_id`) REFERENCES `employee_onboarding_week_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
