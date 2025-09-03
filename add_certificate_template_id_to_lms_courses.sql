-- Add certificate_template_id to lms_courses table
ALTER TABLE `lms_courses` 
ADD COLUMN `certificate_template_id` BIGINT UNSIGNED NULL AFTER `external_trainer_description`,
ADD KEY `lms_courses_certificate_template_id_foreign` (`certificate_template_id`),
ADD CONSTRAINT `lms_courses_certificate_template_id_foreign` 
    FOREIGN KEY (`certificate_template_id`) 
    REFERENCES `certificate_templates` (`id`) 
    ON DELETE SET NULL;

-- Add type field to lms_courses table
ALTER TABLE `lms_courses` 
ADD COLUMN `type` ENUM('online', 'offline') NOT NULL DEFAULT 'offline' AFTER `status`;
