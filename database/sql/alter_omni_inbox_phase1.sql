-- =====================================================
-- Fase 1 inbox omnichannel (jalankan sekali jika tabel sudah ada)
-- Abaikan error "Duplicate column name" jika sudah pernah dijalankan
-- =====================================================

ALTER TABLE `omni_conversations`
    ADD COLUMN `assigned_user_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `member_apps_member_id`,
    ADD COLUMN `lead_stage` VARCHAR(32) NOT NULL DEFAULT 'new_lead' AFTER `assigned_user_id`,
    ADD COLUMN `memo` TEXT NULL AFTER `lead_stage`,
    ADD COLUMN `contact_first_name` VARCHAR(120) NULL AFTER `memo`,
    ADD COLUMN `contact_last_name` VARCHAR(120) NULL AFTER `contact_first_name`,
    ADD COLUMN `contact_email` VARCHAR(255) NULL AFTER `contact_last_name`,
    ADD COLUMN `contact_company` VARCHAR(255) NULL AFTER `contact_email`,
    ADD COLUMN `contact_job_title` VARCHAR(255) NULL AFTER `contact_company`,
    ADD COLUMN `last_customer_message_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_message_at`;

ALTER TABLE `omni_messages`
    ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `conversation_id`;

-- Index untuk filter inbox
ALTER TABLE `omni_conversations`
    ADD KEY `omni_conversations_assigned_user_id_index` (`assigned_user_id`),
    ADD KEY `omni_conversations_lead_stage_index` (`lead_stage`);
