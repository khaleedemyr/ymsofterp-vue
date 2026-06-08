-- Omnichannel inbox: tandai komplain / eskalasi ke Customer Voice Command Center
-- Jalankan manual di MySQL/MariaDB.

ALTER TABLE `omni_conversations`
    ADD COLUMN `complaint_severity` VARCHAR(32) NULL COMMENT 'minor|major|critical' AFTER `automation_paused`,
    ADD COLUMN `complaint_snippet` VARCHAR(500) NULL AFTER `complaint_severity`,
    ADD COLUMN `complaint_message_id` BIGINT UNSIGNED NULL AFTER `complaint_snippet`,
    ADD COLUMN `complaint_detected_at` DATETIME NULL AFTER `complaint_message_id`,
    ADD COLUMN `feedback_case_id` BIGINT UNSIGNED NULL AFTER `complaint_detected_at`,
    ADD KEY `omni_conversations_complaint_severity_index` (`complaint_severity`),
    ADD KEY `omni_conversations_feedback_case_id_index` (`feedback_case_id`);
