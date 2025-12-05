-- Fix Curriculum Database Structure
-- This script ensures the correct structure for LMS curriculum system

USE ymsofterp;

-- 1. Check if lms_curriculum_items table exists and has correct structure
CREATE TABLE IF NOT EXISTS `lms_curriculum_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL COMMENT 'ID kursus yang terkait',
  `session_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Nomor sesi dalam kursus',
  `session_title` varchar(255) NOT NULL COMMENT 'Judul sesi',
  `session_description` text DEFAULT NULL COMMENT 'Deskripsi sesi',
  `order_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Urutan sesi dalam kursus',
  `is_required` tinyint(1) DEFAULT 1 COMMENT 'Apakah sesi wajib diselesaikan',
  `estimated_duration_minutes` int(11) DEFAULT NULL COMMENT 'Estimasi durasi dalam menit',
  `quiz_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID quiz yang terkait',
  `questionnaire_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID kuesioner yang terkait',
  `status` enum('active','inactive') DEFAULT 'active' COMMENT 'Status sesi',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `updated_by` bigint(20) unsigned DEFAULT NULL COMMENT 'User yang terakhir update',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_curriculum_items_course_id_foreign` (`course_id`),
  KEY `lms_curriculum_items_session_number_index` (`session_number`),
  KEY `lms_curriculum_items_order_number_index` (`order_number`),
  KEY `lms_curriculum_items_quiz_id_foreign` (`quiz_id`),
  KEY `lms_curriculum_items_questionnaire_id_foreign` (`questionnaire_id`),
  KEY `lms_curriculum_items_status_index` (`status`),
  KEY `lms_curriculum_items_created_by_foreign` (`created_by`),
  KEY `lms_curriculum_items_updated_by_foreign` (`updated_by`),
  CONSTRAINT `lms_curriculum_items_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_curriculum_items_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lms_curriculum_items_questionnaire_id_foreign` FOREIGN KEY (`questionnaire_id`) REFERENCES `lms_questionnaires` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lms_curriculum_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_curriculum_items_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Item kurikulum per sesi dalam kursus';

-- 2. Check if lms_curriculum_materials table exists and has correct structure
CREATE TABLE IF NOT EXISTS `lms_curriculum_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `curriculum_item_id` bigint(20) unsigned NOT NULL COMMENT 'ID curriculum item yang terkait',
  `title` varchar(255) NOT NULL COMMENT 'Judul materi',
  `description` text DEFAULT NULL COMMENT 'Deskripsi materi',
  `material_type` enum('pdf','image','video','document','link') NOT NULL DEFAULT 'document' COMMENT 'Tipe materi',
  `file_path` varchar(500) DEFAULT NULL COMMENT 'Path file materi (untuk upload)',
  `file_name` varchar(255) DEFAULT NULL COMMENT 'Nama file asli',
  `file_size` bigint(20) DEFAULT NULL COMMENT 'Ukuran file dalam bytes',
  `file_mime_type` varchar(100) DEFAULT NULL COMMENT 'MIME type file',
  `external_url` varchar(500) DEFAULT NULL COMMENT 'URL eksternal (untuk link)',
  `thumbnail_path` varchar(500) DEFAULT NULL COMMENT 'Path thumbnail (untuk video/image)',
  `duration_seconds` int(11) DEFAULT NULL COMMENT 'Durasi video dalam detik',
  `is_downloadable` tinyint(1) DEFAULT 1 COMMENT 'Apakah bisa didownload',
  `is_previewable` tinyint(1) DEFAULT 1 COMMENT 'Apakah bisa di-preview',
  `order_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Urutan materi dalam item',
  `estimated_duration_minutes` int(11) DEFAULT 0 COMMENT 'Estimasi durasi dalam menit',
  `status` enum('active','inactive') DEFAULT 'active' COMMENT 'Status materi',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `updated_by` bigint(20) unsigned DEFAULT NULL COMMENT 'User yang terakhir update',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_curriculum_materials_curriculum_item_id_foreign` (`curriculum_item_id`),
  KEY `lms_curriculum_materials_material_type_index` (`material_type`),
  KEY `lms_curriculum_materials_order_number_index` (`order_number`),
  KEY `lms_curriculum_materials_status_index` (`status`),
  KEY `lms_curriculum_materials_created_by_foreign` (`created_by`),
  KEY `lms_curriculum_materials_updated_by_foreign` (`updated_by`),
  CONSTRAINT `lms_curriculum_materials_curriculum_item_id_foreign` FOREIGN KEY (`curriculum_item_id`) REFERENCES `lms_curriculum_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_curriculum_materials_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_curriculum_materials_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Materi pembelajaran untuk setiap item kurikulum';

-- 3. Add missing columns if they don't exist
ALTER TABLE `lms_curriculum_items` 
ADD COLUMN IF NOT EXISTS `course_id` BIGINT UNSIGNED NOT NULL AFTER `id`,
ADD COLUMN IF NOT EXISTS `session_number` INT NOT NULL DEFAULT 1 AFTER `course_id`,
ADD COLUMN IF NOT EXISTS `session_title` VARCHAR(255) NOT NULL AFTER `session_number`,
ADD COLUMN IF NOT EXISTS `session_description` TEXT DEFAULT NULL AFTER `session_title`,
ADD COLUMN IF NOT EXISTS `quiz_id` BIGINT UNSIGNED NULL AFTER `session_description`,
ADD COLUMN IF NOT EXISTS `questionnaire_id` BIGINT UNSIGNED NULL AFTER `quiz_id`,
ADD COLUMN IF NOT EXISTS `updated_by` BIGINT UNSIGNED NULL AFTER `created_by`;

-- 4. Add missing columns to materials table if they don't exist
ALTER TABLE `lms_curriculum_materials` 
ADD COLUMN IF NOT EXISTS `title` VARCHAR(255) NOT NULL AFTER `curriculum_item_id`,
ADD COLUMN IF NOT EXISTS `description` TEXT DEFAULT NULL AFTER `title`,
ADD COLUMN IF NOT EXISTS `material_type` ENUM('pdf','image','video','document','link') DEFAULT 'document' AFTER `description`,
ADD COLUMN IF NOT EXISTS `estimated_duration_minutes` INT DEFAULT 0 AFTER `material_type`,
ADD COLUMN IF NOT EXISTS `updated_by` BIGINT UNSIGNED NULL AFTER `created_by`;

-- 5. Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_curriculum_items_course_session` ON `lms_curriculum_items`(`course_id`, `session_number`);
CREATE INDEX IF NOT EXISTS `idx_curriculum_items_course_order` ON `lms_curriculum_items`(`course_id`, `order_number`);
CREATE INDEX IF NOT EXISTS `idx_curriculum_materials_item_order` ON `lms_curriculum_materials`(`curriculum_item_id`, `order_number`);

-- 6. Insert sample data for testing (optional)
-- INSERT INTO `lms_curriculum_items` (`course_id`, `session_number`, `session_title`, `session_description`, `order_number`, `is_required`, `estimated_duration_minutes`, `status`, `created_by`, `created_at`, `updated_at`) 
-- VALUES (1, 1, 'Sesi Pertama', 'Pengenalan dan overview course', 1, 1, 30, 'active', 1, NOW(), NOW());

-- 7. Show table structure
DESCRIBE `lms_curriculum_items`;
DESCRIBE `lms_curriculum_materials`;

-- 8. Show sample data
SELECT 
    ci.id,
    ci.course_id,
    ci.session_number,
    ci.session_title,
    ci.session_description,
    ci.order_number,
    ci.is_required,
    ci.estimated_duration_minutes,
    ci.quiz_id,
    ci.questionnaire_id,
    ci.status,
    c.title as course_title
FROM `lms_curriculum_items` ci
LEFT JOIN `lms_courses` c ON ci.course_id = c.id
WHERE ci.deleted_at IS NULL
ORDER BY ci.course_id, ci.order_number;
