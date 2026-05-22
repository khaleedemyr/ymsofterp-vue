-- Tabel broadcast WhatsApp (jalankan jika belum migrate)
-- php artisan migrate

CREATE TABLE IF NOT EXISTS `wa_broadcast_campaigns` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
    `message_type` VARCHAR(32) NOT NULL DEFAULT 'template',
    `template_name` VARCHAR(128) NULL,
    `template_language` VARCHAR(16) NOT NULL DEFAULT 'id',
    `template_body_params` JSON NULL,
    `session_text` TEXT NULL,
    `filter_definition` JSON NOT NULL,
    `recipient_count_estimated` INT UNSIGNED NOT NULL DEFAULT 0,
    `recipient_count_total` INT UNSIGNED NOT NULL DEFAULT 0,
    `recipient_count_sent` INT UNSIGNED NOT NULL DEFAULT 0,
    `recipient_count_failed` INT UNSIGNED NOT NULL DEFAULT 0,
    `recipient_count_skipped` INT UNSIGNED NOT NULL DEFAULT 0,
    `daily_cap` INT UNSIGNED NOT NULL DEFAULT 100000,
    `scheduled_at` TIMESTAMP NULL,
    `started_at` TIMESTAMP NULL,
    `finished_at` TIMESTAMP NULL,
    `created_by_user_id` BIGINT UNSIGNED NULL,
    `phone_number_id` VARCHAR(64) NULL,
    `last_error` TEXT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `wa_broadcast_campaigns_status_idx` (`status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_broadcast_recipients` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id` BIGINT UNSIGNED NOT NULL,
    `phone_normalized` VARCHAR(32) NOT NULL,
    `wa_id` VARCHAR(32) NULL,
    `member_apps_member_id` BIGINT UNSIGNED NULL,
    `omni_contact_id` BIGINT UNSIGNED NULL,
    `display_name` VARCHAR(255) NULL,
    `source` VARCHAR(32) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `skip_reason` VARCHAR(64) NULL,
    `meta_message_id` VARCHAR(128) NULL,
    `error_code` VARCHAR(64) NULL,
    `error_message` TEXT NULL,
    `sent_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `wa_broadcast_recipients_unique` (`campaign_id`, `phone_normalized`),
    KEY `wa_broadcast_recipients_status_idx` (`campaign_id`, `status`),
    CONSTRAINT `wa_broadcast_recipients_campaign_fk` FOREIGN KEY (`campaign_id`) REFERENCES `wa_broadcast_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_broadcast_daily_usage` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usage_date` DATE NOT NULL,
    `phone_number_id` VARCHAR(64) NOT NULL DEFAULT '',
    `sent_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `wa_broadcast_daily_usage_unique` (`usage_date`, `phone_number_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
