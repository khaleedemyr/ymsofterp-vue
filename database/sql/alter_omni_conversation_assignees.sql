-- =====================================================
-- Multi-assign: pivot omni_conversation_assignees
-- Jalankan sekali. Lalu backfill dari assigned_user_id (jika ada).
-- =====================================================

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

-- Salin assign tunggal lama ke pivot (abaikan duplikat)
INSERT IGNORE INTO `omni_conversation_assignees` (`conversation_id`, `user_id`, `created_at`, `updated_at`)
SELECT `id`, `assigned_user_id`, NOW(), NOW()
FROM `omni_conversations`
WHERE `assigned_user_id` IS NOT NULL;
