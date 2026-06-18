-- Just Academy: trainer internal / eksternal pada training plan
-- Jalankan setelah create_just_academy_tables.sql

ALTER TABLE `ja_schedule_trainers`
    ADD COLUMN `trainer_type` ENUM('internal', 'external') NOT NULL DEFAULT 'internal' AFTER `schedule_id`,
    ADD COLUMN `external_name` VARCHAR(255) NULL AFTER `user_id`,
    MODIFY COLUMN `user_id` BIGINT UNSIGNED NULL;

UPDATE `ja_schedule_trainers`
SET `trainer_type` = 'internal'
WHERE `trainer_type` IS NULL OR `trainer_type` = '';
