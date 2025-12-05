-- Complete Database Fix for Curriculum System
-- Fix all issues found in the database structure

USE ymsofterp;

-- 1. Remove problematic curriculum_id column
ALTER TABLE lms_curriculum_items 
DROP COLUMN IF EXISTS `curriculum_id`;

-- 2. Fix course_id column (make it NOT NULL and add default value)
ALTER TABLE lms_curriculum_items 
MODIFY COLUMN `course_id` BIGINT UNSIGNED NOT NULL;

-- 3. Fix session_number column (make it NOT NULL)
ALTER TABLE lms_curriculum_items 
MODIFY COLUMN `session_number` INT NOT NULL DEFAULT 1;

-- 4. Fix session_title column (make it NOT NULL)
ALTER TABLE lms_curriculum_items 
MODIFY COLUMN `session_title` VARCHAR(255) NOT NULL;

-- 5. Fix order_number column (make it NOT NULL)
ALTER TABLE lms_curriculum_items 
MODIFY COLUMN `order_number` INT NOT NULL DEFAULT 1;

-- 6. Fix is_required column (make it NOT NULL)
ALTER TABLE lms_curriculum_items 
MODIFY COLUMN `is_required` TINYINT(1) NOT NULL DEFAULT 1;

-- 7. Fix status column (make it NOT NULL)
ALTER TABLE lms_curriculum_items 
MODIFY COLUMN `status` ENUM('active','inactive') NOT NULL DEFAULT 'active';

-- 8. Fix created_by column (make it NOT NULL)
ALTER TABLE lms_curriculum_items 
MODIFY COLUMN `created_by` BIGINT UNSIGNED NOT NULL;

-- 9. Add foreign key constraints
ALTER TABLE lms_curriculum_items 
ADD CONSTRAINT `fk_curriculum_items_course_id`
FOREIGN KEY (`course_id`) REFERENCES `lms_courses`(`id`) ON DELETE CASCADE;

ALTER TABLE lms_curriculum_items 
ADD CONSTRAINT `fk_curriculum_items_quiz_id`
FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes`(`id`) ON DELETE SET NULL;

ALTER TABLE lms_curriculum_items 
ADD CONSTRAINT `fk_curriculum_items_questionnaire_id`
FOREIGN KEY (`questionnaire_id`) REFERENCES `lms_questionnaires`(`id`) ON DELETE SET NULL;

ALTER TABLE lms_curriculum_items 
ADD CONSTRAINT `fk_curriculum_items_created_by`
FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT;

ALTER TABLE lms_curriculum_items 
ADD CONSTRAINT `fk_curriculum_items_updated_by`
FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- 10. Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_curriculum_items_course_id` ON `lms_curriculum_items`(`course_id`);
CREATE INDEX IF NOT EXISTS `idx_curriculum_items_session_number` ON `lms_curriculum_items`(`session_number`);
CREATE INDEX IF NOT EXISTS `idx_curriculum_items_order_number` ON `lms_curriculum_items`(`order_number`);
CREATE INDEX IF NOT EXISTS `idx_curriculum_items_quiz_id` ON `lms_curriculum_items`(`quiz_id`);
CREATE INDEX IF NOT EXISTS `idx_curriculum_items_questionnaire_id` ON `lms_curriculum_items`(`questionnaire_id`);

-- 11. Update existing data to ensure course_id is set correctly
UPDATE lms_curriculum_items 
SET course_id = 5 
WHERE course_id IS NULL AND id IN (1, 2);

-- 12. Show final table structure
DESCRIBE lms_curriculum_items;

-- 13. Show any existing data
SELECT id, course_id, session_number, session_title, quiz_id, questionnaire_id, status 
FROM lms_curriculum_items 
WHERE course_id = 5;

-- 14. Show foreign key constraints
SELECT 
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'lms_curriculum_items'
AND REFERENCED_TABLE_NAME IS NOT NULL;
