-- Migration: Update Material Structure to Use Separate Files Table
-- This migration converts the existing JSON-based file storage to the new relational structure

USE ymsofterp;

-- 1. Create the new material files table
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

-- 3. Migrate existing data from JSON fields to new table
-- First, let's check what data we have
SELECT 
    id, 
    title, 
    file_path, 
    file_type,
    created_by,
    created_at
FROM lms_curriculum_materials 
WHERE file_path IS NOT NULL 
AND file_path != '' 
AND file_path != 'null';

-- 4. Insert data from JSON fields to new table
-- Note: This is a sample migration - you may need to adjust based on your actual data
INSERT INTO lms_curriculum_material_files (
    material_id, 
    file_path, 
    file_name, 
    file_type, 
    order_number, 
    is_primary, 
    status, 
    created_by, 
    created_at
)
SELECT 
    id as material_id,
    JSON_UNQUOTE(JSON_EXTRACT(file_path, '$[0]')) as file_path,
    SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(file_path, '$[0]')), '/', -1) as file_name,
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(file_type, '$[0]')), 'document') as file_type,
    1 as order_number,
    1 as is_primary,
    'active' as status,
    created_by,
    created_at
FROM lms_curriculum_materials 
WHERE file_path IS NOT NULL 
AND file_path != '' 
AND file_path != 'null'
AND JSON_VALID(file_path) = 1
AND JSON_LENGTH(file_path) > 0;

-- 5. Add additional files if there are multiple files in JSON
-- This handles cases where there are more than one file
INSERT INTO lms_curriculum_material_files (
    material_id, 
    file_path, 
    file_name, 
    file_type, 
    order_number, 
    is_primary, 
    status, 
    created_by, 
    created_at
)
SELECT 
    id as material_id,
    JSON_UNQUOTE(JSON_EXTRACT(file_path, CONCAT('$[', numbers.n, ']'))) as file_path,
    SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(file_path, CONCAT('$[', numbers.n, ']'))), '/', -1) as file_name,
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(file_type, CONCAT('$[', numbers.n, ']'))), 'document') as file_type,
    numbers.n + 1 as order_number,
    0 as is_primary,
    'active' as status,
    created_by,
    created_at
FROM lms_curriculum_materials 
CROSS JOIN (
    SELECT 1 as n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
) numbers
WHERE file_path IS NOT NULL 
AND file_path != '' 
AND file_path != 'null'
AND JSON_VALID(file_path) = 1
AND JSON_LENGTH(file_path) > numbers.n
AND JSON_EXTRACT(file_path, CONCAT('$[', numbers.n, ']')) IS NOT NULL;

-- 6. Remove old JSON columns (optional - comment out if you want to keep them for backup)
-- ALTER TABLE lms_curriculum_materials DROP COLUMN file_path;
-- ALTER TABLE lms_curriculum_materials DROP COLUMN file_type;

-- 7. Verify migration
SELECT 
    m.id,
    m.title,
    COUNT(f.id) as files_count,
    GROUP_CONCAT(f.file_path ORDER BY f.order_number) as file_paths
FROM lms_curriculum_materials m
LEFT JOIN lms_curriculum_material_files f ON m.id = f.material_id
GROUP BY m.id, m.title
ORDER BY m.id;

-- 8. Show summary
SELECT 
    'Migration Summary' as info,
    COUNT(DISTINCT m.id) as total_materials,
    COUNT(f.id) as total_files,
    COUNT(DISTINCT CASE WHEN f.id IS NOT NULL THEN m.id END) as materials_with_files
FROM lms_curriculum_materials m
LEFT JOIN lms_curriculum_material_files f ON m.id = f.material_id;
