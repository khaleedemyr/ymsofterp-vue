-- =====================================================
-- INSERT MENU: Server Performance Monitoring
-- =====================================================
-- Menambahkan menu Server Performance Monitoring
-- ke group Support (parent_id = 217)
-- =====================================================

-- 1. INSERT MENU KE erp_menu
-- =====================================================
INSERT INTO erp_menu (
    name,
    code,
    parent_id,
    route,
    icon,
    created_at,
    updated_at
) VALUES (
    'Server Performance Monitoring',
    'server_performance_monitoring',
    217, -- parent_id untuk group Support
    '/monitoring/server-performance',
    'fa-solid fa-server',
    NOW(),
    NOW()
);

-- 2. DAPATKAN menu_id YANG BARU DIBUAT
-- =====================================================
-- Setelah insert, dapatkan menu_id untuk insert permission
SET @menu_id = LAST_INSERT_ID();

-- Atau jika tidak menggunakan LAST_INSERT_ID, gunakan:
-- SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'server_performance_monitoring' LIMIT 1);

-- 3. INSERT PERMISSION KE erp_permission
-- =====================================================
-- Insert permission untuk action: view
INSERT INTO erp_permission (
    menu_id,
    action,
    code,
    created_at,
    updated_at
) VALUES (
    @menu_id,
    'view',
    'server_performance_monitoring_view',
    NOW(),
    NOW()
);

-- Insert permission untuk action: access (jika diperlukan)
INSERT INTO erp_permission (
    menu_id,
    action,
    code,
    created_at,
    updated_at
) VALUES (
    @menu_id,
    'access',
    'server_performance_monitoring_access',
    NOW(),
    NOW()
);

-- =====================================================
-- VERIFIKASI
-- =====================================================
-- Cek menu yang baru dibuat
SELECT * FROM erp_menu WHERE code = 'server_performance_monitoring';

-- Cek permission yang baru dibuat
SELECT 
    p.*,
    m.name as menu_name,
    m.code as menu_code
FROM erp_permission p
JOIN erp_menu m ON p.menu_id = m.id
WHERE m.code = 'server_performance_monitoring';

-- =====================================================
-- CATATAN:
-- =====================================================
-- 1. Pastikan parent_id = 217 adalah group Support
-- 2. Menu akan muncul di sidebar setelah:
--    - Query dijalankan
--    - User memiliki permission untuk menu ini
--    - Refresh browser
--
-- 3. Untuk memberikan akses ke user/role:
--    - Assign permission 'server_performance_monitoring_view' ke user/role
--    - Atau assign permission 'server_performance_monitoring_access' ke user/role
-- =====================================================
