-- Query untuk insert menu Activity Log Report ke dalam group Support (parent_id = 217)
-- Query ini bisa dieksekusi sekali untuk menambahkan menu dan permission

-- 1. Insert menu Activity Log Report
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'Activity Log Report',
    'activity_log_report',
    217,
    '/report/activity-log',
    'fa-solid fa-list-alt',
    NOW(),
    NOW()
);

-- 2. Insert permission 'view' untuk menu Activity Log Report
-- Menggunakan LAST_INSERT_ID() untuk mendapatkan menu_id yang baru saja diinsert
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (
    LAST_INSERT_ID(),
    'view',
    'activity_log_report_view',
    NOW(),
    NOW()
);

-- ============================================
-- CATATAN:
-- ============================================
-- 1. Query ini akan menambahkan menu "Activity Log Report" ke dalam group Support (parent_id = 217)
-- 2. Menu akan muncul di sidebar setelah query dijalankan
-- 3. Permission 'view' akan otomatis dibuat dengan code 'activity_log_report_view'
-- 4. Jika ingin menambahkan permission lain (create, update, delete), bisa ditambahkan query INSERT tambahan
-- 5. Pastikan user/role yang memerlukan akses sudah diberikan permission 'activity_log_report_view'

-- ============================================
-- QUERY ALTERNATIF (jika ingin insert permission secara terpisah):
-- ============================================
-- Jika menu_id sudah diketahui, bisa langsung insert permission:
-- INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
-- VALUES (
--     [MENU_ID_DARI_QUERY_DI_ATAS],
--     'view',
--     'activity_log_report_view',
--     NOW(),
--     NOW()
-- );

