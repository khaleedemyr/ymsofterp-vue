-- Create table chart_of_accounts jika belum ada
CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('Asset','Liability','Equity','Revenue','Expense') NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chart_of_accounts_code_unique` (`code`),
  KEY `chart_of_accounts_type_index` (`type`),
  KEY `chart_of_accounts_is_active_index` (`is_active`),
  KEY `chart_of_accounts_parent_id_index` (`parent_id`),
  CONSTRAINT `chart_of_accounts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jika tabel sudah ada, tambahkan kolom parent_id
ALTER TABLE `chart_of_accounts` 
ADD COLUMN IF NOT EXISTS `parent_id` bigint(20) unsigned DEFAULT NULL AFTER `type`,
ADD KEY IF NOT EXISTS `chart_of_accounts_parent_id_index` (`parent_id`),
ADD CONSTRAINT IF NOT EXISTS `chart_of_accounts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL;

