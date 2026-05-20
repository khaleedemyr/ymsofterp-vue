-- Kolom otomasi per percakapan

ALTER TABLE `omni_conversations`
    ADD COLUMN `active_flow_run_id` BIGINT UNSIGNED NULL AFTER `status`,
    ADD COLUMN `automation_paused_at` TIMESTAMP NULL AFTER `active_flow_run_id`,
    ADD KEY `omni_conversations_active_flow_run_id_index` (`active_flow_run_id`);
