-- =====================================================
-- ASSIGN PERMISSION KE ROLE YANG SUDAH ADA
-- =====================================================

-- Catatan: Sesuaikan role_id dengan role yang ingin diberi permission
-- Contoh: role_id = 1 untuk admin, role_id = 2 untuk manager, dll

-- 1. Assign permission view ke role admin (role_id = 1)
INSERT INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
SELECT 
    1 as role_id, -- Ganti dengan role_id yang sesuai
    (SELECT id FROM erp_permission WHERE code = 'daily_outlet_revenue_view') as permission_id,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (
    SELECT 1 FROM erp_role_permission rp 
    JOIN erp_permission p ON rp.permission_id = p.id 
    WHERE rp.role_id = 1 AND p.code = 'daily_outlet_revenue_view'
);

-- 2. Assign permission create ke role admin (role_id = 1)
INSERT INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
SELECT 
    1 as role_id, -- Ganti dengan role_id yang sesuai
    (SELECT id FROM erp_permission WHERE code = 'daily_outlet_revenue_create') as permission_id,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (
    SELECT 1 FROM erp_role_permission rp 
    JOIN erp_permission p ON rp.permission_id = p.id 
    WHERE rp.role_id = 1 AND p.code = 'daily_outlet_revenue_create'
);

-- 3. Assign permission update ke role admin (role_id = 1)
INSERT INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
SELECT 
    1 as role_id, -- Ganti dengan role_id yang sesuai
    (SELECT id FROM erp_permission WHERE code = 'daily_outlet_revenue_update') as permission_id,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (
    SELECT 1 FROM erp_role_permission rp 
    JOIN erp_permission p ON rp.permission_id = p.id 
    WHERE rp.role_id = 1 AND p.code = 'daily_outlet_revenue_update'
);

-- 4. Assign permission delete ke role admin (role_id = 1)
INSERT INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
SELECT 
    1 as role_id, -- Ganti dengan role_id yang sesuai
    (SELECT id FROM erp_permission WHERE code = 'daily_outlet_revenue_delete') as permission_id,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (
    SELECT 1 FROM erp_role_permission rp 
    JOIN erp_permission p ON rp.permission_id = p.id 
    WHERE rp.role_id = 1 AND p.code = 'daily_outlet_revenue_delete'
);

-- 5. Verifikasi hasil assign permission
SELECT '=== VERIFIKASI ROLE PERMISSION ===' as info;
SELECT 
    r.name as role_name,
    p.code as permission_code,
    p.action as permission_action,
    m.name as menu_name
FROM erp_role_permission rp
JOIN erp_roles r ON rp.role_id = r.id
JOIN erp_permission p ON rp.permission_id = p.id
JOIN erp_menu m ON p.menu_id = m.id
WHERE m.code = 'daily_outlet_revenue'
ORDER BY r.name, p.action; 