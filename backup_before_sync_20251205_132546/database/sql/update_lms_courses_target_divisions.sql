-- =====================================================
-- Update LMS Courses for Multi-Division Targeting
-- =====================================================

-- 1. Add missing columns to lms_courses table
ALTER TABLE `lms_courses` 
ADD COLUMN `short_description` TEXT DEFAULT NULL COMMENT 'Deskripsi singkat kursus' AFTER `description`,
ADD COLUMN `target_type` ENUM('single', 'multiple', 'all') DEFAULT 'single' COMMENT 'Tipe target: single=1 divisi, multiple=multi divisi, all=semua divisi' AFTER `difficulty_level`,
ADD COLUMN `target_divisions` JSON DEFAULT NULL COMMENT 'Array ID divisi yang ditarget (untuk multiple)' AFTER `target_type`,
ADD COLUMN `target_division_id` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'ID divisi target (untuk single)' AFTER `target_divisions`,
ADD COLUMN `duration_minutes` INT DEFAULT NULL COMMENT 'Durasi dalam menit' AFTER `target_division_id`,
ADD COLUMN `thumbnail` VARCHAR(500) DEFAULT NULL COMMENT 'Path thumbnail' AFTER `duration_minutes`,
ADD COLUMN `is_featured` TINYINT(1) DEFAULT 0 COMMENT 'Apakah featured' AFTER `thumbnail`,
ADD COLUMN `meta_title` VARCHAR(255) DEFAULT NULL COMMENT 'Meta title' AFTER `is_featured`,
ADD COLUMN `meta_description` TEXT DEFAULT NULL COMMENT 'Meta description' AFTER `meta_title`,
ADD COLUMN `updated_by` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'User yang update' AFTER `meta_description`;

-- 2. Create lms_course_divisions table for many-to-many relationship
CREATE TABLE IF NOT EXISTS `lms_course_divisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL COMMENT 'ID kursus',
  `division_id` bigint(20) unsigned NOT NULL COMMENT 'ID divisi',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lms_course_divisions_course_division_unique` (`course_id`, `division_id`),
  KEY `lms_course_divisions_course_id_foreign` (`course_id`),
  KEY `lms_course_divisions_division_id_foreign` (`division_id`),
  CONSTRAINT `lms_course_divisions_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_course_divisions_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `divisi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relasi many-to-many kursus dan divisi';

-- 3. Update existing courses to use new structure
-- Set target_type to 'single' for existing courses with target_division_id
UPDATE `lms_courses` 
SET `target_type` = 'single' 
WHERE `target_division_id` IS NOT NULL;

-- 4. Create indexes for better performance
CREATE INDEX `lms_courses_target_type_index` ON `lms_courses` (`target_type`);
CREATE INDEX `lms_courses_target_divisions_index` ON `lms_courses` ((CAST(target_divisions AS CHAR(1000))));
CREATE INDEX `lms_courses_target_division_id_index` ON `lms_courses` (`target_division_id`); 