-- =====================================================
-- Add Image Support to Quiz Questions
-- =====================================================

-- Add image_path column to lms_quiz_questions table
ALTER TABLE `lms_quiz_questions` 
ADD COLUMN `image_path` VARCHAR(500) NULL AFTER `points`,
ADD COLUMN `image_alt_text` VARCHAR(255) NULL AFTER `image_path`;

-- Create index for better performance
CREATE INDEX `lms_quiz_questions_image_path_index` ON `lms_quiz_questions` (`image_path`);

-- Add comment to explain the new columns
ALTER TABLE `lms_quiz_questions` 
MODIFY COLUMN `image_path` VARCHAR(500) NULL COMMENT 'Path to question image file',
MODIFY COLUMN `image_alt_text` VARCHAR(255) NULL COMMENT 'Alt text for accessibility';
