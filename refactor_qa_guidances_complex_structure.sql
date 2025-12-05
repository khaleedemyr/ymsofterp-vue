-- Refactor QA Guidances to complex 3-level hierarchy structure

-- Drop existing tables if they exist (for clean migration)
DROP TABLE IF EXISTS `qa_guidance_parameters`;
DROP TABLE IF EXISTS `qa_guidance_categories`;

-- Create QA Guidances table (header) - keep existing structure
-- qa_guidances table already has title, departemen, status

-- Create QA Guidance Categories pivot table (many-to-many)
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

-- Create QA Guidance Category Parameters table (parameter pemeriksaan per category)
CREATE TABLE IF NOT EXISTS `qa_guidance_category_parameters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `guidance_category_id` bigint(20) unsigned NOT NULL,
  `parameter_pemeriksaan` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `qa_guidance_category_parameters_guidance_category_id_foreign` (`guidance_category_id`),
  CONSTRAINT `qa_guidance_category_parameters_guidance_category_id_foreign` FOREIGN KEY (`guidance_category_id`) REFERENCES `qa_guidance_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create QA Guidance Parameter Details table (multiple parameter + point per pemeriksaan)
CREATE TABLE IF NOT EXISTS `qa_guidance_parameter_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_parameter_id` bigint(20) unsigned NOT NULL,
  `parameter_id` bigint(20) unsigned NOT NULL,
  `point` int NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `qa_guidance_parameter_details_category_parameter_id_foreign` (`category_parameter_id`),
  KEY `qa_guidance_parameter_details_parameter_id_foreign` (`parameter_id`),
  CONSTRAINT `qa_guidance_parameter_details_category_parameter_id_foreign` FOREIGN KEY (`category_parameter_id`) REFERENCES `qa_guidance_category_parameters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qa_guidance_parameter_details_parameter_id_foreign` FOREIGN KEY (`parameter_id`) REFERENCES `qa_parameters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data for complex structure
-- First, insert guidance categories
INSERT INTO `qa_guidance_categories` (`guidance_id`, `category_id`, `created_at`, `updated_at`) VALUES
(1, 1, NOW(), NOW()), -- Kitchen Quality Control -> Food Safety
(1, 2, NOW(), NOW()), -- Kitchen Quality Control -> Hygiene
(2, 2, NOW(), NOW()), -- Bar Service Excellence -> Hygiene
(2, 3, NOW(), NOW()), -- Bar Service Excellence -> Service Quality
(3, 1, NOW(), NOW()), -- Service Quality Standards -> Food Safety
(3, 3, NOW(), NOW()); -- Service Quality Standards -> Service Quality

-- Insert sample category parameters (parameter pemeriksaan per category)
INSERT INTO `qa_guidance_category_parameters` (`guidance_category_id`, `parameter_pemeriksaan`, `created_at`, `updated_at`) VALUES
-- Kitchen Quality Control -> Food Safety
(1, 'Temperature Control Check', NOW(), NOW()),
(1, 'Food Storage Check', NOW(), NOW()),
-- Kitchen Quality Control -> Hygiene  
(2, 'Hand Washing Check', NOW(), NOW()),
(2, 'Surface Cleanliness Check', NOW(), NOW()),
-- Bar Service Excellence -> Hygiene
(3, 'Bar Sanitization Check', NOW(), NOW()),
-- Bar Service Excellence -> Service Quality
(4, 'Customer Service Check', NOW(), NOW()),
(4, 'Order Accuracy Check', NOW(), NOW()),
-- Service Quality Standards -> Food Safety
(5, 'Food Presentation Check', NOW(), NOW()),
-- Service Quality Standards -> Service Quality
(6, 'Table Service Check', NOW(), NOW()),
(6, 'Customer Satisfaction Check', NOW(), NOW());

-- Insert sample parameter details (multiple parameter + point per pemeriksaan)
INSERT INTO `qa_guidance_parameter_details` (`category_parameter_id`, `parameter_id`, `point`, `created_at`, `updated_at`) VALUES
-- Temperature Control Check
(1, 1, 10, NOW(), NOW()), -- Temperature Control (10 points)
(1, 2, 15, NOW(), NOW()), -- Hygiene Standards (15 points)
-- Food Storage Check
(2, 3, 20, NOW(), NOW()), -- Quality Check (20 points)
-- Hand Washing Check
(3, 2, 25, NOW(), NOW()), -- Hygiene Standards (25 points)
(3, 4, 30, NOW(), NOW()), -- Safety Protocols (30 points)
-- Surface Cleanliness Check
(4, 2, 20, NOW(), NOW()), -- Hygiene Standards (20 points)
-- Bar Sanitization Check
(5, 2, 15, NOW(), NOW()), -- Hygiene Standards (15 points)
(5, 4, 20, NOW(), NOW()), -- Safety Protocols (20 points)
-- Customer Service Check
(6, 5, 25, NOW(), NOW()), -- Performance Metrics (25 points)
-- Order Accuracy Check
(7, 3, 30, NOW(), NOW()), -- Quality Check (30 points)
-- Food Presentation Check
(8, 3, 20, NOW(), NOW()), -- Quality Check (20 points)
-- Table Service Check
(9, 5, 25, NOW(), NOW()), -- Performance Metrics (25 points)
-- Customer Satisfaction Check
(10, 5, 35, NOW(), NOW()); -- Performance Metrics (35 points)
