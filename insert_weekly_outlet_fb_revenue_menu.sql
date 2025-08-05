-- =====================================================
-- INSERT MENU DAN PERMISSION UNTUK WEEKLY OUTLET FB REVENUE
-- =====================================================

-- 1. Insert menu Weekly Outlet FB Revenue
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) 
SELECT 
    'Weekly Outlet FB Revenue' as name,
    'weekly_outlet_fb_revenue' as code,
    (SELECT id FROM erp_menu WHERE code = 'outlet_sales_report' LIMIT 1) as parent_id,
    '/report-weekly-outlet-fb-revenue' as route,
    'fa-solid fa-calendar-week' as icon,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (SELECT 1 FROM erp_menu WHERE code = 'weekly_outlet_fb_revenue');

-- 2. Insert permissions untuk menu Weekly Outlet FB Revenue
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at)
SELECT 
    (SELECT id FROM erp_menu WHERE code = 'weekly_outlet_fb_revenue') as menu_id,
    'view' as action,
    'weekly_outlet_fb_revenue_view' as code,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (SELECT 1 FROM erp_permission WHERE code = 'weekly_outlet_fb_revenue_view');

INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at)
SELECT 
    (SELECT id FROM erp_menu WHERE code = 'weekly_outlet_fb_revenue') as menu_id,
    'create' as action,
    'weekly_outlet_fb_revenue_create' as code,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (SELECT 1 FROM erp_permission WHERE code = 'weekly_outlet_fb_revenue_create');

INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at)
SELECT 
    (SELECT id FROM erp_menu WHERE code = 'weekly_outlet_fb_revenue') as menu_id,
    'update' as action,
    'weekly_outlet_fb_revenue_update' as code,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (SELECT 1 FROM erp_permission WHERE code = 'weekly_outlet_fb_revenue_update');

INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at)
SELECT 
    (SELECT id FROM erp_menu WHERE code = 'weekly_outlet_fb_revenue') as menu_id,
    'delete' as action,
    'weekly_outlet_fb_revenue_delete' as code,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (SELECT 1 FROM erp_permission WHERE code = 'weekly_outlet_fb_revenue_delete');

-- 3. Verifikasi hasil insert
SELECT '=== VERIFIKASI MENU ===' as info;
SELECT id, name, code, parent_id, route, icon FROM erp_menu WHERE code = 'weekly_outlet_fb_revenue';

SELECT '=== VERIFIKASI PERMISSION ===' as info;
SELECT p.id, p.menu_id, p.action, p.code, m.name as menu_name 
FROM erp_permission p 
JOIN erp_menu m ON p.menu_id = m.id 
WHERE m.code = 'weekly_outlet_fb_revenue'; 