-- =====================================================
-- Add Image Support to Quiz Options
-- =====================================================

-- Add image_path column to lms_quiz_options table
ALTER TABLE `lms_quiz_options` 
ADD COLUMN `image_path` VARCHAR(500) NULL AFTER `option_text`,
ADD COLUMN `image_alt_text` VARCHAR(255) NULL AFTER `image_path`;

-- Create index for better performance
CREATE INDEX `lms_quiz_options_image_path_index` ON `lms_quiz_options` (`image_path`);

-- Add comment to explain the new columns
ALTER TABLE `lms_quiz_options` 
MODIFY COLUMN `image_path` VARCHAR(500) NULL COMMENT 'Path to option image file',
MODIFY COLUMN `image_alt_text` VARCHAR(255) NULL COMMENT 'Alt text for accessibility';
