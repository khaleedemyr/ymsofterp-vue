-- Simple Database Fix for Curriculum System
-- Fix the curriculum_id field issue without dropping tables

USE ymsofterp;

-- 1. Check current table structure
DESCRIBE lms_curriculum_items;

-- 2. Add missing columns if they don't exist
ALTER TABLE lms_curriculum_items 
ADD COLUMN IF NOT EXISTS `course_id` BIGINT UNSIGNED NOT NULL AFTER `id`,
ADD COLUMN IF NOT EXISTS `session_number` INT NOT NULL DEFAULT 1 AFTER `course_id`,
ADD COLUMN IF NOT EXISTS `session_title` VARCHAR(255) NOT NULL AFTER `session_number`,
ADD COLUMN IF NOT EXISTS `session_description` TEXT DEFAULT NULL AFTER `session_title`,
ADD COLUMN IF NOT EXISTS `quiz_id` BIGINT UNSIGNED NULL AFTER `session_description`,
ADD COLUMN IF NOT EXISTS `questionnaire_id` BIGINT UNSIGNED NULL AFTER `quiz_id`,
ADD COLUMN IF NOT EXISTS `order_number` INT NOT NULL DEFAULT 1 AFTER `questionnaire_id`,
ADD COLUMN IF NOT EXISTS `is_required` TINYINT(1) DEFAULT 1 AFTER `order_number`,
ADD COLUMN IF NOT EXISTS `estimated_duration_minutes` INT DEFAULT NULL AFTER `is_required`,
ADD COLUMN IF NOT EXISTS `status` ENUM('active','inactive') DEFAULT 'active' AFTER `estimated_duration_minutes`,
ADD COLUMN IF NOT EXISTS `updated_by` BIGINT UNSIGNED NULL AFTER `created_by`;

-- 3. Remove problematic curriculum_id column if it exists
ALTER TABLE lms_curriculum_items 
DROP COLUMN IF EXISTS `curriculum_id`;

-- 4. Add foreign key constraints if they don't exist
ALTER TABLE lms_curriculum_items 
ADD CONSTRAINT IF NOT EXISTS `fk_curriculum_items_course_id` 
FOREIGN KEY (`course_id`) REFERENCES `lms_courses`(`id`) ON DELETE CASCADE;

ALTER TABLE lms_curriculum_items 
ADD CONSTRAINT IF NOT EXISTS `fk_curriculum_items_quiz_id` 
FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes`(`id`) ON DELETE SET NULL;

ALTER TABLE lms_curriculum_items 
ADD CONSTRAINT IF NOT EXISTS `fk_curriculum_items_questionnaire_id` 
FOREIGN KEY (`questionnaire_id`) REFERENCES `lms_questionnaires`(`id`) ON DELETE SET NULL;

-- 5. Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_curriculum_items_course_id` ON `lms_curriculum_items`(`course_id`);
CREATE INDEX IF NOT EXISTS `idx_curriculum_items_session_number` ON `lms_curriculum_items`(`session_number`);
CREATE INDEX IF NOT EXISTS `idx_curriculum_items_order_number` ON `lms_curriculum_items`(`order_number`);

-- 6. Show final table structure
DESCRIBE lms_curriculum_items;

-- 7. Show any existing data
SELECT * FROM lms_curriculum_items LIMIT 5;
