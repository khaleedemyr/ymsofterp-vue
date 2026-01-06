-- Create chart_of_accounts table with complete structure
-- Drop table if exists (optional, uncomment if needed)
-- DROP TABLE IF EXISTS `chart_of_accounts`;

CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('Asset','Liability','Equity','Revenue','Expense') NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `show_in_menu_payment` tinyint(1) NOT NULL DEFAULT 0,
  `static_or_dynamic` varchar(20) DEFAULT NULL,
  `menu_id` json DEFAULT NULL,
  `mode_payment` json DEFAULT NULL,
  `budget_limit` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chart_of_accounts_code_unique` (`code`),
  KEY `chart_of_accounts_type_index` (`type`),
  KEY `chart_of_accounts_parent_id_index` (`parent_id`),
  KEY `chart_of_accounts_is_active_index` (`is_active`),
  KEY `idx_show_in_menu_payment` (`show_in_menu_payment`),
  CONSTRAINT `chart_of_accounts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

