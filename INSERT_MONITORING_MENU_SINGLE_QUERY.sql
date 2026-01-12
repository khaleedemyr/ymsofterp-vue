-- =====================================================
-- INSERT MENU: Server Performance Monitoring
-- =====================================================
-- Single query untuk insert menu dan permission sekaligus
-- =====================================================

-- Insert menu dan permission dalam satu transaction
START TRANSACTION;

-- 1. INSERT MENU
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

-- 2. INSERT PERMISSIONS
-- Menggunakan LAST_INSERT_ID() untuk mendapatkan menu_id yang baru dibuat
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at)
SELECT 
    LAST_INSERT_ID() as menu_id,
    action,
    CONCAT('server_performance_monitoring_', action) as code,
    NOW() as created_at,
    NOW() as updated_at
FROM (
    SELECT 'view' as action
    UNION ALL
    SELECT 'access' as action
) as actions;

COMMIT;

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
