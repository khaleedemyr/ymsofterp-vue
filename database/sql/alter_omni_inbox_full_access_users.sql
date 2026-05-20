-- =====================================================
-- Siapa saja yang boleh melihat SEMUA inbox (atur manual di halaman Tim Omnichannel)
-- Jalankan sekali (setelah users + alter_omni_teams jika ada).
-- =====================================================

CREATE TABLE IF NOT EXISTS `omni_inbox_full_access_users` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `omni_inbox_full_access_users_user_fk`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
