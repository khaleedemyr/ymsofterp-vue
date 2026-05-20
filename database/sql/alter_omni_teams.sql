-- =====================================================
-- Tim omnichannel + penugasan tim pada percakapan
-- Jalankan setelah create_omni_inbox_tables / alter sebelumnya.
-- =====================================================

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
