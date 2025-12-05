-- Update existing lms_quizzes table to add new time limit fields
-- Remove course_id column if it exists
ALTER TABLE `lms_quizzes` 
DROP COLUMN IF EXISTS `course_id`;

-- Add new time limit fields
ALTER TABLE `lms_quizzes` 
ADD COLUMN `time_limit_type` enum('total','per_question') DEFAULT NULL COMMENT 'Tipe batas waktu' AFTER `instructions`,
ADD COLUMN `time_per_question_seconds` int(11) DEFAULT NULL COMMENT 'Batas waktu per pertanyaan dalam detik' AFTER `time_limit_minutes`;

-- Update existing time_limit_minutes comment
ALTER TABLE `lms_quizzes` 
MODIFY COLUMN `time_limit_minutes` int(11) DEFAULT NULL COMMENT 'Batas waktu total dalam menit';
