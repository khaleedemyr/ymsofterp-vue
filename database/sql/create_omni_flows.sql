-- Otomasi flow inbox (native, tanpa n8n)

CREATE TABLE IF NOT EXISTS `omni_flows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `description` VARCHAR(500) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `trigger_type` VARCHAR(32) NOT NULL DEFAULT 'inbound_message',
    `channel` VARCHAR(32) NULL COMMENT 'whatsapp atau NULL = semua channel',
    `priority` INT NOT NULL DEFAULT 100 COMMENT 'Lebih kecil = lebih dulu dieksekusi',
    `definition` JSON NOT NULL,
    `created_by_user_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `omni_flows_active_priority_index` (`is_active`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `omni_flow_runs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `flow_id` BIGINT UNSIGNED NOT NULL,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `trigger_message_id` BIGINT UNSIGNED NULL,
    `status` VARCHAR(16) NOT NULL DEFAULT 'running',
    `current_step_index` INT UNSIGNED NOT NULL DEFAULT 0,
    `context` JSON NULL,
    `error_message` VARCHAR(500) NULL,
    `started_at` TIMESTAMP NULL,
    `finished_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `omni_flow_runs_conversation_status_index` (`conversation_id`, `status`),
    CONSTRAINT `omni_flow_runs_flow_id_foreign`
        FOREIGN KEY (`flow_id`) REFERENCES `omni_flows` (`id`) ON DELETE CASCADE,
    CONSTRAINT `omni_flow_runs_conversation_id_foreign`
        FOREIGN KEY (`conversation_id`) REFERENCES `omni_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `omni_flow_run_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `flow_run_id` BIGINT UNSIGNED NOT NULL,
    `step_index` INT UNSIGNED NOT NULL,
    `step_type` VARCHAR(32) NOT NULL,
    `status` VARCHAR(16) NOT NULL,
    `message` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `omni_flow_run_logs_flow_run_id_index` (`flow_run_id`),
    CONSTRAINT `omni_flow_run_logs_flow_run_id_foreign`
        FOREIGN KEY (`flow_run_id`) REFERENCES `omni_flow_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
