-- Create Material Files Table for Multiple Files per Material
-- This migration creates a new table to store multiple files per curriculum material
-- Instead of storing file paths as JSON arrays, each file will have its own record

USE ymsofterp;

-- 1. Create lms_curriculum_material_files table
CREATE TABLE IF NOT EXISTS `lms_curriculum_material_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_id` bigint(20) unsigned NOT NULL COMMENT 'ID material yang terkait',
  `file_path` varchar(500) NOT NULL COMMENT 'Path file di storage',
  `file_name` varchar(255) NOT NULL COMMENT 'Nama file asli',
  `file_size` bigint(20) DEFAULT NULL COMMENT 'Ukuran file dalam bytes',
  `file_mime_type` varchar(100) DEFAULT NULL COMMENT 'MIME type file',
  `file_type` enum('pdf','image','video','document','link') DEFAULT 'document' COMMENT 'Tipe file',
  `order_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Urutan file dalam material',
  `is_primary` tinyint(1) DEFAULT 0 COMMENT 'Apakah file utama (untuk thumbnail/preview)',
  `status` enum('active','inactive') DEFAULT 'active' COMMENT 'Status file',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_material_files_material_id` (`material_id`),
  KEY `idx_material_files_file_type` (`file_type`),
  KEY `idx_material_files_order_number` (`order_number`),
  KEY `idx_material_files_status` (`status`),
  KEY `idx_material_files_created_by` (`created_by`),
  CONSTRAINT `fk_material_files_material_id` FOREIGN KEY (`material_id`) REFERENCES `lms_curriculum_materials`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_material_files_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Multiple files untuk setiap curriculum material';

-- 2. Add indexes for better performance
CREATE INDEX `idx_material_files_material_order` ON `lms_curriculum_material_files`(`material_id`, `order_number`);
CREATE INDEX `idx_material_files_type_status` ON `lms_curriculum_material_files`(`file_type`, `status`);

-- 3. Create storage directory if not exists
-- Note: This is handled by Laravel, but you can create manually if needed
-- mkdir -p storage/app/public/lms/materials
