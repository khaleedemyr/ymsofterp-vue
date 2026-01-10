-- ============================================
-- Insert Menu Marketing Dashboard (Sekali Eksekusi)
-- ============================================
-- Menu untuk Marketing Dashboard - Customer Behavior Analysis
-- parent_id = 1 (Main Menu)

-- Insert ke erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'Marketing Dashboard',
    'marketing_dashboard',
    1,
    '/marketing/dashboard',
    'fa-solid fa-bullhorn',
    NOW(),
    NOW()
);

-- ============================================
-- Insert Permissions untuk Marketing Dashboard
-- ============================================
-- Menggunakan subquery untuk mendapatkan menu_id langsung
-- Bisa dieksekusi sekali langsung setelah INSERT menu

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    m.id as menu_id,
    p.action,
    p.code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu` m
CROSS JOIN (
    SELECT 'view' as action, 'marketing_dashboard_view' as code
    UNION ALL SELECT 'create', 'marketing_dashboard_create'
    UNION ALL SELECT 'update', 'marketing_dashboard_update'
    UNION ALL SELECT 'delete', 'marketing_dashboard_delete'
) p
WHERE m.`code` = 'marketing_dashboard';

-- ============================================
-- ALTERNATIF: Jika ingin insert langsung tanpa LAST_INSERT_ID
-- ============================================
-- Uncomment dan sesuaikan menu_id jika sudah tahu ID-nya

-- INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
-- SELECT 
--     id as menu_id,
--     'view' as action,
--     CONCAT(code, '_view') as code,
--     NOW() as created_at,
--     NOW() as updated_at
-- FROM `erp_menu`
-- WHERE `code` = 'marketing_dashboard'
-- UNION ALL
-- SELECT 
--     id as menu_id,
--     'create' as action,
--     CONCAT(code, '_create') as code,
--     NOW() as created_at,
--     NOW() as updated_at
-- FROM `erp_menu`
-- WHERE `code` = 'marketing_dashboard'
-- UNION ALL
-- SELECT 
--     id as menu_id,
--     'update' as action,
--     CONCAT(code, '_update') as code,
--     NOW() as created_at,
--     NOW() as updated_at
-- FROM `erp_menu`
-- WHERE `code` = 'marketing_dashboard'
-- UNION ALL
-- SELECT 
--     id as menu_id,
--     'delete' as action,
--     CONCAT(code, '_delete') as code,
--     NOW() as created_at,
--     NOW() as updated_at
-- FROM `erp_menu`
-- WHERE `code` = 'marketing_dashboard';

-- ============================================
-- VERIFIKASI: Cek menu dan permission yang sudah dibuat
-- ============================================
-- SELECT * FROM `erp_menu` WHERE `code` = 'marketing_dashboard';
-- SELECT * FROM `erp_permission` WHERE `code` LIKE 'marketing_dashboard%';
