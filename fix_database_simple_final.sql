-- Simple Database Fix for Curriculum System
-- Fix the curriculum_id field issue without dropping tables

USE ymsofterp;

-- 1. Remove problematic curriculum_id column if it exists
ALTER TABLE lms_curriculum_items 
DROP COLUMN IF EXISTS `curriculum_id`;

-- 2. Fix course_id column (make it NOT NULL)
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

-- 9. Update existing data to ensure course_id is set correctly
UPDATE lms_curriculum_items 
SET course_id = 5 
WHERE course_id IS NULL AND id IN (1, 2);

-- 10. Show final table structure
DESCRIBE lms_curriculum_items;

-- 11. Show any existing data
SELECT id, course_id, session_number, session_title, quiz_id, questionnaire_id, status 
FROM lms_curriculum_items 
WHERE course_id = 5;
