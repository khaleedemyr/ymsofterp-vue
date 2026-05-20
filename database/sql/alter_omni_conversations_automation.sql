-- Kolom otomasi per percakapan

ALTER TABLE `omni_conversations`
    ADD COLUMN `automation_paused` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN `active_flow_run_id` BIGINT UNSIGNED NULL AFTER `automation_paused`,
    ADD KEY `omni_conversations_automation_paused_index` (`automation_paused`);
