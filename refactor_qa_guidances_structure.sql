-- Refactor QA Guidances structure to add title and multiple categories

-- Add title column to qa_guidances table
ALTER TABLE `qa_guidances` ADD COLUMN `title` varchar(255) NOT NULL AFTER `id`;

-- Create pivot table for qa_guidances and qa_categories (many-to-many)
CREATE TABLE IF NOT EXISTS `qa_guidance_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `guidance_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qa_guidance_categories_guidance_category_unique` (`guidance_id`, `category_id`),
  KEY `qa_guidance_categories_guidance_id_foreign` (`guidance_id`),
  KEY `qa_guidance_categories_category_id_foreign` (`category_id`),
  CONSTRAINT `qa_guidance_categories_guidance_id_foreign` FOREIGN KEY (`guidance_id`) REFERENCES `qa_guidances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qa_guidance_categories_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `qa_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remove old category_id foreign key constraint and column
ALTER TABLE `qa_guidances` DROP FOREIGN KEY `qa_guidances_category_id_foreign`;
ALTER TABLE `qa_guidances` DROP COLUMN `category_id`;

-- Insert sample data with titles
UPDATE `qa_guidances` SET `title` = 'Kitchen Quality Control' WHERE `departemen` = 'Kitchen';
UPDATE `qa_guidances` SET `title` = 'Bar Service Excellence' WHERE `departemen` = 'Bar';
UPDATE `qa_guidances` SET `title` = 'Service Quality Standards' WHERE `departemen` = 'Service';

-- Insert sample guidance categories (assuming we have categories with id 1, 2, 3)
INSERT INTO `qa_guidance_categories` (`guidance_id`, `category_id`, `created_at`, `updated_at`) VALUES
(1, 1, NOW(), NOW()),
(1, 2, NOW(), NOW()), -- Kitchen guidance can have multiple categories
(2, 2, NOW(), NOW()),
(2, 3, NOW(), NOW()), -- Bar guidance can have multiple categories
(3, 1, NOW(), NOW()),
(3, 3, NOW(), NOW()); -- Service guidance can have multiple categories
