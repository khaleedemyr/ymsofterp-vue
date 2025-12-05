-- =====================================================
-- Add Jabatan and Level Targeting to LMS Courses
-- =====================================================

-- Add new target columns to lms_courses table
ALTER TABLE `lms_courses` 
ADD COLUMN `target_jabatan_ids` JSON DEFAULT NULL COMMENT 'Array of jabatan IDs that can access this course' AFTER `target_divisions`,
ADD COLUMN `target_level_ids` JSON DEFAULT NULL COMMENT 'Array of level IDs that can access this course' AFTER `target_jabatan_ids`;

-- Create indexes for better performance
CREATE INDEX `lms_courses_target_jabatan_ids_index` ON `lms_courses` ((CAST(target_jabatan_ids AS CHAR(1000))));
CREATE INDEX `lms_courses_target_level_ids_index` ON `lms_courses` ((CAST(target_level_ids AS CHAR(1000))));

-- Create junction tables for many-to-many relationships
CREATE TABLE IF NOT EXISTS `lms_course_jabatans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL COMMENT 'ID kursus',
  `jabatan_id` bigint(20) unsigned NOT NULL COMMENT 'ID jabatan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lms_course_jabatans_course_jabatan_unique` (`course_id`, `jabatan_id`),
  KEY `lms_course_jabatans_course_id_foreign` (`course_id`),
  KEY `lms_course_jabatans_jabatan_id_foreign` (`jabatan_id`),
  CONSTRAINT `lms_course_jabatans_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_course_jabatans_jabatan_id_foreign` FOREIGN KEY (`jabatan_id`) REFERENCES `tbl_data_jabatan` (`id_jabatan`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relasi many-to-many kursus dan jabatan';

CREATE TABLE IF NOT EXISTS `lms_course_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL COMMENT 'ID kursus',
  `level_id` bigint(20) unsigned NOT NULL COMMENT 'ID level',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lms_course_levels_course_level_unique` (`course_id`, `level_id`),
  KEY `lms_course_levels_course_id_foreign` (`course_id`),
  KEY `lms_course_levels_level_id_foreign` (`level_id`),
  CONSTRAINT `lms_course_levels_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_course_levels_level_id_foreign` FOREIGN KEY (`level_id`) REFERENCES `tbl_data_level` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relasi many-to-many kursus dan level';
