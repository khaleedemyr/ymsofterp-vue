-- Refactor Course Curriculum to Session-Based Structure
-- This migration updates the LMS curriculum system to support flexible sessions

USE ymsofterp;

-- 1. Create lms_sessions table if not exists
CREATE TABLE IF NOT EXISTS `lms_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id` BIGINT UNSIGNED NOT NULL,
    `session_number` INT NOT NULL,
    `session_title` VARCHAR(255) NOT NULL,
    `session_description` TEXT NULL,
    `order_number` INT NOT NULL DEFAULT 1,
    `is_required` TINYINT(1) NOT NULL DEFAULT 1,
    `estimated_duration_minutes` INT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_course_id` (`course_id`),
    KEY `idx_sessions_order` (`order_number`),
    CONSTRAINT `fk_sessions_course_id` FOREIGN KEY (`course_id`) REFERENCES `lms_courses`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sessions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sessions_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sessions dalam course LMS';

-- 2. Create lms_session_items table if not exists
CREATE TABLE IF NOT EXISTS `lms_session_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` BIGINT UNSIGNED NOT NULL,
    `item_type` ENUM('quiz','material','questionnaire') NOT NULL,
    `item_id` BIGINT UNSIGNED NULL, -- Reference to quiz/material/questionnaire
    `title` VARCHAR(255) NULL, -- Custom title if needed
    `description` TEXT NULL, -- Custom description if needed
    `order_number` INT NOT NULL DEFAULT 1,
    `is_required` TINYINT(1) NOT NULL DEFAULT 1,
    `estimated_duration_minutes` INT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `idx_session_items_session_id` (`session_id`),
    KEY `idx_session_items_type` (`item_type`),
    KEY `idx_session_items_order` (`order_number`),
    CONSTRAINT `fk_session_items_session_id` FOREIGN KEY (`session_id`) REFERENCES `lms_sessions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_session_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_session_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items dalam session (quiz, material, questionnaire)';

-- 3. Create lms_curriculum_materials table if not exists (for materials)
CREATE TABLE IF NOT EXISTS `lms_curriculum_materials` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `content` LONGTEXT NULL,
    `file_path` VARCHAR(500) NULL,
    `file_type` ENUM('pdf','image','video','document','link') DEFAULT 'document',
    `estimated_duration_minutes` INT DEFAULT 0,
    `status` ENUM('active','inactive') DEFAULT 'active',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `idx_materials_status` (`status`),
    CONSTRAINT `fk_materials_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_materials_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Materials untuk curriculum LMS';

-- 4. Add indexes for better performance
CREATE INDEX idx_sessions_course_order ON lms_sessions(course_id, order_number);
CREATE INDEX idx_session_items_session_order ON lms_session_items(session_id, order_number);
CREATE INDEX idx_session_items_type_id ON lms_session_items(item_type, item_id);

-- 5. Insert sample data for existing courses (if any)
-- This will create a default session for existing courses
INSERT IGNORE INTO `lms_sessions` (
    `course_id`, `session_number`, `session_title`, `session_description`, 
    `order_number`, `is_required`, `estimated_duration_minutes`, 
    `status`, `created_by`, `created_at`
)
SELECT 
    `id`, 1, 'Session 1', 'Default session for existing course', 
    1, 1, 60, 'active', `created_by`, NOW()
FROM `lms_courses` 
WHERE NOT EXISTS (
    SELECT 1 FROM `lms_sessions` WHERE `lms_sessions`.`course_id` = `lms_courses`.`id`
);

-- 6. Create default materials for existing sessions
INSERT IGNORE INTO `lms_curriculum_materials` (
    `title`, `description`, `content`, `estimated_duration_minutes`, 
    `status`, `created_by`, `created_at`
)
SELECT 
    'Course Overview', 'Default material for course overview', 
    '<p>Welcome to this course. This is a default material that will be replaced with actual content.</p>',
    30, 'active', `created_by`, NOW()
FROM `lms_sessions` 
WHERE NOT EXISTS (
    SELECT 1 FROM `lms_session_items` WHERE `lms_session_items`.`session_id` = `lms_sessions`.`id`
);

-- 7. Create default session items for existing sessions
INSERT IGNORE INTO `lms_session_items` (
    `session_id`, `item_type`, `item_id`, `title`, `description`, 
    `order_number`, `is_required`, `estimated_duration_minutes`, 
    `status`, `created_by`, `created_at`
)
SELECT 
    `id`, 'material', 
    (SELECT `id` FROM `lms_curriculum_materials` WHERE `created_by` = `lms_sessions`.`created_by` LIMIT 1),
    'Course Overview', 'Default material for course overview',
    1, 1, 30, 'active', `created_by`, NOW()
FROM `lms_sessions` 
WHERE NOT EXISTS (
    SELECT 1 FROM `lms_session_items` WHERE `lms_session_items`.`session_id` = `lms_sessions`.`id`
);

-- 8. Update existing lms_courses table to add session support
ALTER TABLE `lms_courses` 
ADD COLUMN `has_sessions` TINYINT(1) DEFAULT 0 AFTER `status`,
ADD COLUMN `total_sessions` INT DEFAULT 0 AFTER `has_sessions`;

-- 9. Update course session counts
UPDATE `lms_courses` c 
SET 
    `has_sessions` = 1,
    `total_sessions` = (
        SELECT COUNT(*) FROM `lms_sessions` s 
        WHERE s.`course_id` = c.`id` AND s.`deleted_at` IS NULL
    )
WHERE EXISTS (
    SELECT 1 FROM `lms_sessions` s 
    WHERE s.`course_id` = c.`id` AND s.`deleted_at` IS NULL
);

-- 10. Create view for easy session management
CREATE OR REPLACE VIEW `v_course_sessions` AS
SELECT 
    c.id as course_id,
    c.title as course_title,
    c.status as course_status,
    s.id as session_id,
    s.session_number,
    s.session_title,
    s.session_description,
    s.order_number as session_order,
    s.estimated_duration_minutes as session_duration,
    s.status as session_status,
    COUNT(si.id) as total_items,
    SUM(CASE WHEN si.item_type = 'quiz' THEN 1 ELSE 0 END) as quiz_count,
    SUM(CASE WHEN si.item_type = 'material' THEN 1 ELSE 0 END) as material_count,
    SUM(CASE WHEN si.item_type = 'questionnaire' THEN 1 ELSE 0 END) as questionnaire_count
FROM `lms_courses` c
LEFT JOIN `lms_sessions` s ON c.id = s.course_id AND s.deleted_at IS NULL
LEFT JOIN `lms_session_items` si ON s.id = si.session_id AND si.deleted_at IS NULL
GROUP BY c.id, s.id
ORDER BY c.id, s.order_number;

-- Success message
SELECT 'Course curriculum refactoring completed successfully!' as status;
