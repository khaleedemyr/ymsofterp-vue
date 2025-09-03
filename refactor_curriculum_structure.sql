-- Refactor Curriculum Structure for Flexibility
-- New structure: Session -> Multiple Items (Quiz/Material/Questionnaire)

USE ymsofterp;

-- 1. Create new flexible structure
-- Sessions table (container)
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
    KEY `idx_sessions_order` (`order_number`)
);

-- Session Items table (flexible content)
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
    KEY `idx_session_items_order` (`order_number`)
);

-- 2. Migrate existing data
INSERT INTO `lms_sessions` (
    `course_id`, `session_number`, `session_title`, `session_description`, 
    `order_number`, `is_required`, `estimated_duration_minutes`, 
    `status`, `created_by`, `updated_by`, `created_at`, `updated_at`
)
SELECT 
    `course_id`, `session_number`, `session_title`, `session_description`,
    `order_number`, `is_required`, `estimated_duration_minutes`,
    `status`, `created_by`, `updated_by`, `created_at`, `updated_at`
FROM `lms_curriculum_items` 
WHERE `course_id` = 5;

-- 3. Add foreign key constraints
ALTER TABLE `lms_sessions` 
ADD CONSTRAINT `fk_sessions_course_id` 
FOREIGN KEY (`course_id`) REFERENCES `lms_courses`(`id`) ON DELETE CASCADE;

ALTER TABLE `lms_sessions` 
ADD CONSTRAINT `fk_sessions_created_by` 
FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT;

ALTER TABLE `lms_sessions` 
ADD CONSTRAINT `fk_sessions_updated_by` 
FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `lms_session_items` 
ADD CONSTRAINT `fk_session_items_session_id` 
FOREIGN KEY (`session_id`) REFERENCES `lms_sessions`(`id`) ON DELETE CASCADE;

ALTER TABLE `lms_session_items` 
ADD CONSTRAINT `fk_session_items_created_by` 
FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT;

ALTER TABLE `lms_session_items` 
ADD CONSTRAINT `fk_session_items_updated_by` 
FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- 4. Show new structure
DESCRIBE lms_sessions;
DESCRIBE lms_session_items;

-- 5. Show migrated data
SELECT * FROM lms_sessions WHERE course_id = 5;
