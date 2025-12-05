-- =====================================================
-- Add Target Outlet IDs and Remove Target Level IDs from LMS Courses
-- =====================================================

-- Remove target_level_ids column from lms_courses table
ALTER TABLE `lms_courses` 
DROP COLUMN `target_level_ids`;

-- Add target_outlet_ids column to lms_courses table
ALTER TABLE `lms_courses` 
ADD COLUMN `target_outlet_ids` JSON DEFAULT NULL COMMENT 'Array of outlet IDs that can access this course' AFTER `target_jabatan_ids`;

-- Create index for better performance
CREATE INDEX `lms_courses_target_outlet_ids_index` ON `lms_courses` ((CAST(target_outlet_ids AS CHAR(1000))));

-- Create junction table for many-to-many relationship between courses and outlets
CREATE TABLE IF NOT EXISTS `lms_course_outlets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL COMMENT 'ID kursus',
  `outlet_id` bigint(20) unsigned NOT NULL COMMENT 'ID outlet',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lms_course_outlets_course_outlet_unique` (`course_id`, `outlet_id`),
  KEY `lms_course_outlets_course_id_foreign` (`course_id`),
  KEY `lms_course_outlets_outlet_id_foreign` (`outlet_id`),
  CONSTRAINT `lms_course_outlets_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_course_outlets_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relasi many-to-many kursus dan outlet';

-- Drop the lms_course_levels table since we're removing level targeting
DROP TABLE IF EXISTS `lms_course_levels`;
