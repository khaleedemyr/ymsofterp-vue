-- Test Insert Notification untuk User ID 26
-- CATATAN: Query SQL ini TIDAK akan trigger observer (push notification)
-- Observer hanya terpicu jika menggunakan Eloquent (Notification::create())
-- 
-- Untuk test push notification, gunakan kode PHP di bawah atau test via tinker

-- Query SQL (untuk insert langsung ke database, TIDAK trigger push notification)
INSERT INTO `notifications` (
    `user_id`,
    `task_id`,
    `approval_id`,
    `type`,
    `title`,
    `message`,
    `url`,
    `is_read`,
    `created_at`,
    `updated_at`
) VALUES (
    26,                              -- user_id
    NULL,                            -- task_id (optional)
    NULL,                            -- approval_id (optional)
    'test',                          -- type
    'Test Notification',             -- title
    'Ini adalah test notification untuk push notification ke mobile app',  -- message
    NULL,                            -- url (optional)
    0,                               -- is_read (0 = belum dibaca)
    NOW(),                           -- created_at
    NOW()                            -- updated_at
);

-- Untuk melihat notification yang baru dibuat
SELECT * FROM `notifications` WHERE `user_id` = 26 ORDER BY `id` DESC LIMIT 1;

