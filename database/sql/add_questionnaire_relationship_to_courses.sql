-- =====================================================
-- Add Questionnaire Relationship to Courses
-- =====================================================

-- Add course_id to lms_quizzes table if it doesn't exist
ALTER TABLE `lms_quizzes` 
ADD COLUMN `course_id` BIGINT UNSIGNED NULL AFTER `id`,
ADD KEY `lms_quizzes_course_id_foreign` (`course_id`),
ADD CONSTRAINT `lms_quizzes_course_id_foreign` 
    FOREIGN KEY (`course_id`) 
    REFERENCES `lms_courses` (`id`) 
    ON DELETE SET NULL;

-- Add course_id to lms_questionnaires table
ALTER TABLE `lms_questionnaires` 
ADD COLUMN `course_id` BIGINT UNSIGNED NULL AFTER `id`,
ADD KEY `lms_questionnaires_course_id_foreign` (`course_id`),
ADD CONSTRAINT `lms_questionnaires_course_id_foreign` 
    FOREIGN KEY (`course_id`) 
    REFERENCES `lms_courses` (`id`) 
    ON DELETE SET NULL;

-- Create indexes for better performance
CREATE INDEX `lms_quizzes_course_id_index` ON `lms_quizzes` (`course_id`);
CREATE INDEX `lms_questionnaires_course_id_index` ON `lms_questionnaires` (`course_id`);
