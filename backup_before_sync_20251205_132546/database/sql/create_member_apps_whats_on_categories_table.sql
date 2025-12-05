-- Create table for Whats On Categories
CREATE TABLE IF NOT EXISTS `member_apps_whats_on_categories` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add category_id column to member_apps_whats_on table
ALTER TABLE `member_apps_whats_on` 
ADD COLUMN IF NOT EXISTS `category_id` bigint(20) UNSIGNED NULL DEFAULT NULL AFTER `published_at`,
ADD KEY `idx_category_id` (`category_id`),
ADD CONSTRAINT `fk_whats_on_category` FOREIGN KEY (`category_id`) REFERENCES `member_apps_whats_on_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

