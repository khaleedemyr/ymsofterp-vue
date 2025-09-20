-- Create post-inspection briefing table
CREATE TABLE IF NOT EXISTS `daily_report_briefings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `daily_report_id` BIGINT UNSIGNED NOT NULL,
    `briefing_type` ENUM('morning', 'afternoon') NOT NULL,
    `time_of_conduct` TIME NULL,
    `participant` TEXT NULL,
    `outlet` VARCHAR(255) NULL,
    `service_in_charge` VARCHAR(255) NULL,
    `bar_in_charge` VARCHAR(255) NULL,
    `kitchen_in_charge` VARCHAR(255) NULL,
    `so_product` TEXT NULL,
    `product_up_selling` TEXT NULL,
    `commodity_issue` TEXT NULL,
    `oe_issue` TEXT NULL,
    `guest_reservation_pax` INT NULL,
    `daily_revenue_target` DECIMAL(15,2) NULL,
    `promotion_program_campaign` TEXT NULL,
    `guest_comment_target` TEXT NULL,
    `trip_advisor_target` TEXT NULL,
    `other_preparation` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
);

-- Create employee productivity program table
CREATE TABLE IF NOT EXISTS `daily_report_productivity` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `daily_report_id` BIGINT UNSIGNED NOT NULL,
    `product_knowledge_test` TEXT NULL,
    `sos_hospitality_role_play` TEXT NULL,
    `employee_daily_coaching` TEXT NULL,
    `others_activity` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
);

-- Create visit table records
CREATE TABLE IF NOT EXISTS `daily_report_visit_tables` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `daily_report_id` BIGINT UNSIGNED NOT NULL,
    `guest_name` VARCHAR(255) NULL,
    `table_no` VARCHAR(50) NULL,
    `no_of_pax` INT NULL,
    `guest_experience` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
);

-- Create report summary table
CREATE TABLE IF NOT EXISTS `daily_report_summaries` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `daily_report_id` BIGINT UNSIGNED NOT NULL,
    `summary_type` ENUM('summary_1', 'summary_2') NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
);
