-- Employee Coaching — eksekusi manual sekali di MySQL

CREATE TABLE IF NOT EXISTS `employee_coachings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `employee_name` VARCHAR(255) NOT NULL,
    `jabatan_id` INT UNSIGNED NULL,
    `jabatan_name` VARCHAR(255) NULL,
    `outlet_id` INT UNSIGNED NULL,
    `outlet_name` VARCHAR(255) NULL,
    `division_id` INT UNSIGNED NULL,
    `division_name` VARCHAR(255) NULL,
    `performance_description` TEXT NULL,
    `action_taken` TEXT NULL,
    `action_due_date` DATE NULL,
    `performance_review_plan_date` DATE NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `employee_coachings_employee_id_index` (`employee_id`),
    KEY `employee_coachings_outlet_id_index` (`outlet_id`),
    KEY `employee_coachings_performance_review_plan_date_index` (`performance_review_plan_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_coaching_concerns` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_coaching_id` BIGINT UNSIGNED NOT NULL,
    `concern_code` VARCHAR(50) NOT NULL,
    `other_label` VARCHAR(255) NULL,
    `comment` TEXT NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `employee_coaching_concerns_coaching_id_index` (`employee_coaching_id`),
    CONSTRAINT `employee_coaching_concerns_coaching_id_foreign`
        FOREIGN KEY (`employee_coaching_id`) REFERENCES `employee_coachings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
