-- Fix Curriculum Materials Table Structure for Session-Based System
-- This migration updates the lms_curriculum_materials table to work with the new session-based curriculum

USE ymsofterp;

-- 1. Backup existing data if needed
CREATE TABLE IF NOT EXISTS `lms_curriculum_materials_backup` AS 
SELECT * FROM `lms_curriculum_materials` WHERE 1=0;

-- 2. Drop and recreate the table with correct structure
DROP TABLE IF EXISTS `lms_curriculum_materials`;

CREATE TABLE `lms_curriculum_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Judul materi',
  `description` text DEFAULT NULL COMMENT 'Deskripsi materi',
  `file_path` varchar(500) DEFAULT NULL COMMENT 'Path file materi (untuk upload)',
  `file_type` enum('pdf','image','video','document','link') DEFAULT 'document' COMMENT 'Tipe file materi',
  `estimated_duration_minutes` int(11) DEFAULT 0 COMMENT 'Estimasi durasi dalam menit',
  `status` enum('active','inactive') DEFAULT 'active' COMMENT 'Status materi',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `updated_by` bigint(20) unsigned DEFAULT NULL COMMENT 'User yang terakhir update',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_materials_status` (`status`),
  KEY `idx_materials_file_type` (`file_type`),
  KEY `idx_materials_created_by` (`created_by`),
  KEY `idx_materials_updated_by` (`updated_by`),
  CONSTRAINT `fk_materials_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_materials_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Materials untuk curriculum LMS (session-based)';

-- 3. Create storage directory if not exists
-- Note: This is handled by Laravel's storage system, but you can create manually if needed
-- mkdir -p storage/app/public/lms/materials

-- 4. Insert sample data for testing (optional)
INSERT INTO `lms_curriculum_materials` (
    `title`, 
    `description`, 
    `file_type`, 
    `estimated_duration_minutes`, 
    `status`, 
    `created_by`, 
    `created_at`
) VALUES 
(
    'Sample PDF Material', 
    'This is a sample PDF material for testing purposes', 
    'pdf', 
    30, 
    'active', 
    1, 
    NOW()
),
(
    'Sample Video Material', 
    'This is a sample video material for testing purposes', 
    'video', 
    45, 
    'active', 
    1, 
    NOW()
);

-- 5. Verify table structure
DESCRIBE `lms_curriculum_materials`;

-- 6. Show sample data
SELECT * FROM `lms_curriculum_materials`;
