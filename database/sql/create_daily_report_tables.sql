-- Create daily reports table
CREATE TABLE IF NOT EXISTS `daily_reports` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `outlet_id` BIGINT UNSIGNED NOT NULL,
    `inspection_time` ENUM('lunch', 'dinner') NOT NULL,
    `department_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('draft', 'completed') DEFAULT 'draft',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet`(`id_outlet`),
    FOREIGN KEY (`department_id`) REFERENCES `departemens`(`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

-- Create daily report areas table
CREATE TABLE IF NOT EXISTS `daily_report_areas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `daily_report_id` BIGINT UNSIGNED NOT NULL,
    `area_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('G', 'NG', 'NA') NULL,
    `finding_problem` TEXT NULL,
    `dept_concern_id` BIGINT UNSIGNED NULL,
    `documentation` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`daily_report_id`) REFERENCES `daily_reports`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`area_id`) REFERENCES `areas`(`id`),
    FOREIGN KEY (`dept_concern_id`) REFERENCES `tbl_data_divisi`(`id`)
);

-- Create daily report progress table for tracking user progress
CREATE TABLE IF NOT EXISTS `daily_report_progress` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `daily_report_id` BIGINT UNSIGNED NOT NULL,
    `area_id` BIGINT UNSIGNED NOT NULL,
    `progress_status` ENUM('pending', 'in_progress', 'completed', 'skipped') DEFAULT 'pending',
    `form_data` JSON NULL,
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`daily_report_id`) REFERENCES `daily_reports`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`area_id`) REFERENCES `areas`(`id`),
    UNIQUE KEY `unique_daily_report_area` (`daily_report_id`, `area_id`)
);

-- Add indexes for better performance
CREATE INDEX `idx_daily_reports_outlet` ON `daily_reports`(`outlet_id`);
CREATE INDEX `idx_daily_reports_department` ON `daily_reports`(`department_id`);
CREATE INDEX `idx_daily_reports_user` ON `daily_reports`(`user_id`);
CREATE INDEX `idx_daily_reports_status` ON `daily_reports`(`status`);
CREATE INDEX `idx_daily_reports_created` ON `daily_reports`(`created_at`);

CREATE INDEX `idx_daily_report_areas_report` ON `daily_report_areas`(`daily_report_id`);
CREATE INDEX `idx_daily_report_areas_area` ON `daily_report_areas`(`area_id`);
CREATE INDEX `idx_daily_report_areas_status` ON `daily_report_areas`(`status`);

CREATE INDEX `idx_daily_report_progress_report` ON `daily_report_progress`(`daily_report_id`);
CREATE INDEX `idx_daily_report_progress_area` ON `daily_report_progress`(`area_id`);
CREATE INDEX `idx_daily_report_progress_status` ON `daily_report_progress`(`progress_status`);
