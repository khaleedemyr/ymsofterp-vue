-- =====================================================
-- Create LMS Curriculum Tables
-- =====================================================
-- This migration creates tables for course curriculum management
-- Each course can have multiple curriculum items (quizzes, questionnaires, materials)

-- 1. Create lms_curriculum table (main curriculum structure)
CREATE TABLE IF NOT EXISTS `lms_curriculum` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL COMMENT 'ID kursus yang terkait',
  `title` varchar(255) NOT NULL COMMENT 'Judul kurikulum',
  `description` text DEFAULT NULL COMMENT 'Deskripsi kurikulum',
  `order_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Urutan kurikulum dalam kursus',
  `is_required` tinyint(1) DEFAULT 1 COMMENT 'Apakah kurikulum wajib diselesaikan',
  `estimated_duration_minutes` int(11) DEFAULT NULL COMMENT 'Estimasi durasi dalam menit',
  `status` enum('active','inactive') DEFAULT 'active' COMMENT 'Status kurikulum',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_curriculum_course_id_foreign` (`course_id`),
  KEY `lms_curriculum_created_by_foreign` (`created_by`),
  KEY `lms_curriculum_order_number_index` (`order_number`),
  KEY `lms_curriculum_status_index` (`status`),
  CONSTRAINT `lms_curriculum_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_curriculum_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel utama kurikulum kursus';

-- 2. Create lms_curriculum_items table (individual items in curriculum)
CREATE TABLE IF NOT EXISTS `lms_curriculum_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `curriculum_id` bigint(20) unsigned NOT NULL COMMENT 'ID kurikulum yang terkait',
  `item_type` enum('quiz','questionnaire','material') NOT NULL COMMENT 'Tipe item: quiz, questionnaire, atau material',
  `item_id` bigint(20) unsigned NOT NULL COMMENT 'ID item sesuai tipe (quiz_id, questionnaire_id, atau material_id)',
  `title` varchar(255) NOT NULL COMMENT 'Judul item',
  `description` text DEFAULT NULL COMMENT 'Deskripsi item',
  `order_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Urutan item dalam kurikulum',
  `is_required` tinyint(1) DEFAULT 1 COMMENT 'Apakah item wajib diselesaikan',
  `estimated_duration_minutes` int(11) DEFAULT NULL COMMENT 'Estimasi durasi dalam menit',
  `passing_score` int(11) DEFAULT NULL COMMENT 'Nilai minimum untuk lulus (untuk quiz)',
  `max_attempts` int(11) DEFAULT 1 COMMENT 'Maksimal percobaan (untuk quiz)',
  `status` enum('active','inactive') DEFAULT 'active' COMMENT 'Status item',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_curriculum_items_curriculum_id_foreign` (`curriculum_id`),
  KEY `lms_curriculum_items_created_by_foreign` (`created_by`),
  KEY `lms_curriculum_items_item_type_index` (`item_type`),
  KEY `lms_curriculum_items_item_id_index` (`item_id`),
  KEY `lms_curriculum_items_order_number_index` (`order_number`),
  KEY `lms_curriculum_items_status_index` (`status`),
  CONSTRAINT `lms_curriculum_items_curriculum_id_foreign` FOREIGN KEY (`curriculum_id`) REFERENCES `lms_curriculum` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_curriculum_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Item-item dalam kurikulum (quiz, questionnaire, material)';

-- 3. Create lms_curriculum_materials table (for PDF, image, video materials)
CREATE TABLE IF NOT EXISTS `lms_curriculum_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `curriculum_item_id` bigint(20) unsigned NOT NULL COMMENT 'ID curriculum item yang terkait',
  `title` varchar(255) NOT NULL COMMENT 'Judul materi',
  `description` text DEFAULT NULL COMMENT 'Deskripsi materi',
  `material_type` enum('pdf','image','video','document','link') NOT NULL COMMENT 'Tipe materi',
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
  `status` enum('active','inactive') DEFAULT 'active' COMMENT 'Status materi',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_curriculum_materials_curriculum_item_id_foreign` (`curriculum_item_id`),
  KEY `lms_curriculum_materials_created_by_foreign` (`created_by`),
  KEY `lms_curriculum_materials_material_type_index` (`material_type`),
  KEY `lms_curriculum_materials_order_number_index` (`order_number`),
  KEY `lms_curriculum_materials_status_index` (`status`),
  CONSTRAINT `lms_curriculum_materials_curriculum_item_id_foreign` FOREIGN KEY (`curriculum_item_id`) REFERENCES `lms_curriculum_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_curriculum_materials_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Materi pembelajaran (PDF, image, video, document, link)';

-- 4. Create lms_curriculum_progress table (track student progress)
CREATE TABLE IF NOT EXISTS `lms_curriculum_progress` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID user/student',
  `curriculum_item_id` bigint(20) unsigned NOT NULL COMMENT 'ID curriculum item',
  `status` enum('not_started','in_progress','completed','failed') DEFAULT 'not_started' COMMENT 'Status progress',
  `started_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu mulai',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu selesai',
  `score` int(11) DEFAULT NULL COMMENT 'Nilai yang didapat (untuk quiz)',
  `attempts_count` int(11) DEFAULT 0 COMMENT 'Jumlah percobaan',
  `last_attempt_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu percobaan terakhir',
  `time_spent_minutes` int(11) DEFAULT 0 COMMENT 'Total waktu yang dihabiskan (menit)',
  `notes` text DEFAULT NULL COMMENT 'Catatan progress',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lms_curriculum_progress_user_item_unique` (`user_id`, `curriculum_item_id`),
  KEY `lms_curriculum_progress_user_id_foreign` (`user_id`),
  KEY `lms_curriculum_progress_curriculum_item_id_foreign` (`curriculum_item_id`),
  KEY `lms_curriculum_progress_status_index` (`status`),
  CONSTRAINT `lms_curriculum_progress_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_curriculum_progress_curriculum_item_id_foreign` FOREIGN KEY (`curriculum_item_id`) REFERENCES `lms_curriculum_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Progress siswa dalam kurikulum';

-- 5. Create indexes for better performance
CREATE INDEX `lms_curriculum_course_order_index` ON `lms_curriculum` (`course_id`, `order_number`);
CREATE INDEX `lms_curriculum_items_curriculum_order_index` ON `lms_curriculum_items` (`curriculum_id`, `order_number`);
CREATE INDEX `lms_curriculum_materials_item_order_index` ON `lms_curriculum_materials` (`curriculum_item_id`, `order_number`);
CREATE INDEX `lms_curriculum_progress_user_status_index` ON `lms_curriculum_progress` (`user_id`, `status`);

-- 6. Add curriculum_id to existing lms_quizzes table (if not exists)
-- This allows existing quizzes to be linked to curriculum
ALTER TABLE `lms_quizzes` 
ADD COLUMN `curriculum_item_id` bigint(20) unsigned NULL AFTER `course_id`,
ADD KEY `lms_quizzes_curriculum_item_id_foreign` (`curriculum_item_id`),
ADD CONSTRAINT `lms_quizzes_curriculum_item_id_foreign` 
    FOREIGN KEY (`curriculum_item_id`) 
    REFERENCES `lms_curriculum_items` (`id`) 
    ON DELETE SET NULL;

-- 7. Add curriculum_id to existing lms_questionnaires table (if not exists)
-- This allows existing questionnaires to be linked to curriculum
ALTER TABLE `lms_questionnaires` 
ADD COLUMN `curriculum_item_id` bigint(20) unsigned NULL AFTER `course_id`,
ADD KEY `lms_questionnaires_curriculum_item_id_foreign` (`curriculum_item_id`),
ADD CONSTRAINT `lms_questionnaires_curriculum_item_id_foreign` 
    FOREIGN KEY (`curriculum_item_id`) 
    REFERENCES `lms_curriculum_items` (`id`) 
    ON DELETE SET NULL;

-- 8. Insert sample curriculum data (optional)
-- Uncomment the following lines if you want to insert sample data
/*
INSERT INTO `lms_curriculum` (`course_id`, `title`, `description`, `order_number`, `is_required`, `estimated_duration_minutes`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Pengenalan Dasar', 'Modul pengenalan untuk pemula', 1, 1, 30, 'active', 1, NOW(), NOW()),
(1, 'Materi Utama', 'Materi pembelajaran inti', 2, 1, 120, 'active', 1, NOW(), NOW()),
(1, 'Evaluasi & Ujian', 'Quiz dan kuesioner untuk evaluasi', 3, 1, 60, 'active', 1, NOW(), NOW());
*/

-- 9. Create view for curriculum overview
CREATE OR REPLACE VIEW `v_lms_curriculum_overview` AS
SELECT 
    c.id as course_id,
    c.title as course_title,
    cur.id as curriculum_id,
    cur.title as curriculum_title,
    cur.order_number as curriculum_order,
    cur.is_required as curriculum_required,
    cur.estimated_duration_minutes as curriculum_duration,
    COUNT(ci.id) as total_items,
    SUM(CASE WHEN ci.item_type = 'quiz' THEN 1 ELSE 0 END) as quiz_count,
    SUM(CASE WHEN ci.item_type = 'questionnaire' THEN 1 ELSE 0 END) as questionnaire_count,
    SUM(CASE WHEN ci.item_type = 'material' THEN 1 ELSE 0 END) as material_count,
    cur.status as curriculum_status,
    cur.created_at as curriculum_created_at
FROM lms_courses c
LEFT JOIN lms_curriculum cur ON c.id = cur.course_id
LEFT JOIN lms_curriculum_items ci ON cur.id = ci.curriculum_id
WHERE c.deleted_at IS NULL AND (cur.deleted_at IS NULL OR cur.deleted_at IS NULL)
GROUP BY c.id, cur.id
ORDER BY c.id, cur.order_number;

-- 10. Create view for curriculum items with details
CREATE OR REPLACE VIEW `v_lms_curriculum_items_detail` AS
SELECT 
    ci.id as item_id,
    ci.curriculum_id,
    ci.item_type,
    ci.item_id as linked_item_id,
    ci.title as item_title,
    ci.description as item_description,
    ci.order_number as item_order,
    ci.is_required as item_required,
    ci.estimated_duration_minutes as item_duration,
    ci.passing_score,
    ci.max_attempts,
    ci.status as item_status,
    CASE 
        WHEN ci.item_type = 'quiz' THEN q.title
        WHEN ci.item_type = 'questionnaire' THEN qu.title
        WHEN ci.item_type = 'material' THEN 'Material'
        ELSE 'Unknown'
    END as linked_item_title,
    ci.created_at as item_created_at
FROM lms_curriculum_items ci
LEFT JOIN lms_quizzes q ON ci.item_type = 'quiz' AND ci.item_id = q.id
LEFT JOIN lms_questionnaires qu ON ci.item_type = 'questionnaire' AND ci.item_id = qu.id
WHERE ci.deleted_at IS NULL
ORDER BY ci.curriculum_id, ci.order_number;

-- =====================================================
-- Migration completed successfully!
-- =====================================================
-- 
-- Tables created:
-- 1. lms_curriculum - Main curriculum structure
-- 2. lms_curriculum_items - Individual items (quiz, questionnaire, material)
-- 3. lms_curriculum_materials - Materials (PDF, image, video, document, link)
-- 4. lms_curriculum_progress - Student progress tracking
-- 
-- Views created:
-- 1. v_lms_curriculum_overview - Curriculum overview per course
-- 2. v_lms_curriculum_items_detail - Detailed curriculum items
-- 
-- Features:
-- - Multiple curriculum per course
-- - Multiple items per curriculum (quiz, questionnaire, material)
-- - Multiple materials per item (PDF, image, video, document, link)
-- - Progress tracking for students
-- - Flexible ordering and requirements
-- - Support for existing quizzes and questionnaires
