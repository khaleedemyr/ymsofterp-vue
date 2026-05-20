-- =====================================================
-- Sekali eksekusi: tabel chat omnichannel (tanpa php artisan migrate)
-- =====================================================

CREATE TABLE IF NOT EXISTS `omni_conversations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel` VARCHAR(32) NOT NULL DEFAULT 'whatsapp',
    `external_contact_id` VARCHAR(32) NOT NULL,
    `contact_name` VARCHAR(255) NULL,
    `phone_number_id` VARCHAR(32) NULL,
    `waba_id` VARCHAR(32) NULL,
    `member_apps_member_id` BIGINT UNSIGNED NULL,
    `last_message_at` TIMESTAMP NULL,
    `last_message_preview` VARCHAR(500) NULL,
    `unread_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` VARCHAR(16) NOT NULL DEFAULT 'open',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `omni_conv_channel_contact_phone_unique` (`channel`, `external_contact_id`, `phone_number_id`),
    KEY `omni_conversations_last_message_at_index` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `omni_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `direction` VARCHAR(16) NOT NULL,
    `meta_message_id` VARCHAR(128) NULL,
    `message_type` VARCHAR(32) NOT NULL DEFAULT 'text',
    `body` TEXT NULL,
    `payload` JSON NULL,
    `status` VARCHAR(32) NULL,
    `sent_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `omni_messages_meta_message_id_unique` (`meta_message_id`),
    KEY `omni_messages_conversation_id_created_at_index` (`conversation_id`, `created_at`),
    CONSTRAINT `omni_messages_conversation_id_foreign`
        FOREIGN KEY (`conversation_id`) REFERENCES `omni_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
