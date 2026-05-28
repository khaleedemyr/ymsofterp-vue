-- =====================================================

-- Sekali eksekusi: tabel chat omnichannel (fresh install)

-- Termasuk kolom fase 1 (assign, lead stage, CRM ringkas, jendela WA)

-- + omni_contacts: identitas unik per nomor (nama + HP, tidak duplikat)

-- =====================================================



CREATE TABLE IF NOT EXISTS `omni_contacts` (

    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `phone_normalized` VARCHAR(32) NOT NULL,

    `display_name` VARCHAR(255) NULL,

    `member_apps_member_id` BIGINT UNSIGNED NULL,

    `created_at` TIMESTAMP NULL,

    `updated_at` TIMESTAMP NULL,

    PRIMARY KEY (`id`),

    UNIQUE KEY `omni_contacts_phone_normalized_unique` (`phone_normalized`),

    KEY `omni_contacts_member_apps_member_id_index` (`member_apps_member_id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS `omni_conversations` (

    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `channel` VARCHAR(32) NOT NULL DEFAULT 'whatsapp',

    `external_contact_id` VARCHAR(32) NOT NULL,

    `contact_name` VARCHAR(255) NULL,

    `phone_number_id` VARCHAR(32) NULL,

    `waba_id` VARCHAR(32) NULL,

    `member_apps_member_id` BIGINT UNSIGNED NULL,

    `omni_contact_id` BIGINT UNSIGNED NULL,

    `assigned_user_id` BIGINT UNSIGNED NULL,

    `lead_stage` VARCHAR(32) NOT NULL DEFAULT 'new_lead',

    `memo` TEXT NULL,

    `contact_first_name` VARCHAR(120) NULL,

    `contact_last_name` VARCHAR(120) NULL,

    `contact_email` VARCHAR(255) NULL,

    `contact_company` VARCHAR(255) NULL,

    `contact_job_title` VARCHAR(255) NULL,

    `last_message_at` TIMESTAMP NULL,

    `last_customer_message_at` TIMESTAMP NULL,

    `last_message_preview` VARCHAR(500) NULL,

    `unread_count` INT UNSIGNED NOT NULL DEFAULT 0,

    `status` VARCHAR(16) NOT NULL DEFAULT 'open',

    `created_at` TIMESTAMP NULL,

    `updated_at` TIMESTAMP NULL,

    PRIMARY KEY (`id`),

    UNIQUE KEY `omni_conv_channel_contact_phone_unique` (`channel`, `external_contact_id`, `phone_number_id`),

    KEY `omni_conversations_last_message_at_index` (`last_message_at`),

    KEY `omni_conversations_assigned_user_id_index` (`assigned_user_id`),

    KEY `omni_conversations_lead_stage_index` (`lead_stage`),

    KEY `omni_conversations_omni_contact_id_index` (`omni_contact_id`),

    CONSTRAINT `omni_conversations_omni_contact_id_foreign`

        FOREIGN KEY (`omni_contact_id`) REFERENCES `omni_contacts` (`id`) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS `omni_messages` (

    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `conversation_id` BIGINT UNSIGNED NOT NULL,

    `user_id` BIGINT UNSIGNED NULL,

    `direction` VARCHAR(16) NOT NULL,

    `meta_message_id` VARCHAR(512) NULL,

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



CREATE TABLE IF NOT EXISTS `omni_conversation_assignees` (

    `conversation_id` BIGINT UNSIGNED NOT NULL,

    `user_id` BIGINT UNSIGNED NOT NULL,

    `created_at` TIMESTAMP NULL,

    `updated_at` TIMESTAMP NULL,

    PRIMARY KEY (`conversation_id`, `user_id`),

    KEY `omni_conv_assignees_user_id_index` (`user_id`),

    CONSTRAINT `omni_conv_assignees_conversation_fk`

        FOREIGN KEY (`conversation_id`) REFERENCES `omni_conversations` (`id`) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS `omni_teams` (

    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `name` VARCHAR(120) NOT NULL,

    `description` VARCHAR(500) NULL,

    `created_at` TIMESTAMP NULL,

    `updated_at` TIMESTAMP NULL,

    PRIMARY KEY (`id`),

    KEY `omni_teams_name_index` (`name`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS `omni_team_user` (

    `team_id` BIGINT UNSIGNED NOT NULL,

    `user_id` BIGINT UNSIGNED NOT NULL,

    `created_at` TIMESTAMP NULL,

    `updated_at` TIMESTAMP NULL,

    PRIMARY KEY (`team_id`, `user_id`),

    KEY `omni_team_user_user_id_index` (`user_id`),

    CONSTRAINT `omni_team_user_team_fk`

        FOREIGN KEY (`team_id`) REFERENCES `omni_teams` (`id`) ON DELETE CASCADE,

    CONSTRAINT `omni_team_user_user_fk`

        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS `omni_conversation_teams` (

    `conversation_id` BIGINT UNSIGNED NOT NULL,

    `team_id` BIGINT UNSIGNED NOT NULL,

    `created_at` TIMESTAMP NULL,

    `updated_at` TIMESTAMP NULL,

    PRIMARY KEY (`conversation_id`, `team_id`),

    KEY `omni_conv_teams_team_id_index` (`team_id`),

    CONSTRAINT `omni_conv_teams_conversation_fk`

        FOREIGN KEY (`conversation_id`) REFERENCES `omni_conversations` (`id`) ON DELETE CASCADE,

    CONSTRAINT `omni_conv_teams_team_fk`

        FOREIGN KEY (`team_id`) REFERENCES `omni_teams` (`id`) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS `omni_inbox_full_access_users` (

    `user_id` BIGINT UNSIGNED NOT NULL,

    `created_at` TIMESTAMP NULL,

    `updated_at` TIMESTAMP NULL,

    PRIMARY KEY (`user_id`),

    CONSTRAINT `omni_inbox_full_access_users_user_fk`

        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


