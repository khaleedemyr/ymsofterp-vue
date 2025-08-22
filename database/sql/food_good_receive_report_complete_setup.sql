-- =====================================================
-- COMPLETE SETUP FOR FOOD GOOD RECEIVE REPORT
-- =====================================================

-- 1. Insert menu Food Good Receive Report ke erp_menu
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) 
VALUES ('Food Good Receive Report', 'food_good_receive_report', 6, '/food-good-receive-report', 'fa-solid fa-chart-bar', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    route = VALUES(route),
    icon = VALUES(icon),
    updated_at = NOW();

-- Get the menu ID yang baru dibuat atau yang sudah ada
SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'food_good_receive_report' AND parent_id = 6);

-- 2. Insert permissions untuk menu Food Good Receive Report
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@menu_id, 'view', 'food_good_receive_report_view', NOW(), NOW()),
(@menu_id, 'create', 'food_good_receive_report_create', NOW(), NOW()),
(@menu_id, 'update', 'food_good_receive_report_update', NOW(), NOW()),
(@menu_id, 'delete', 'food_good_receive_report_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- 3. Get permission IDs
SET @view_permission_id = (SELECT id FROM erp_permission WHERE menu_id = @menu_id AND action = 'view');
SET @create_permission_id = (SELECT id FROM erp_permission WHERE menu_id = @menu_id AND action = 'create');
SET @update_permission_id = (SELECT id FROM erp_permission WHERE menu_id = @menu_id AND action = 'update');
SET @delete_permission_id = (SELECT id FROM erp_permission WHERE menu_id = @menu_id AND action = 'delete');

-- 4. Get role IDs
SET @admin_role_id = (SELECT id FROM roles WHERE name = 'admin');
SET @purchasing_manager_role_id = (SELECT id FROM roles WHERE name = 'purchasing manager');
SET @warehouse_manager_role_id = (SELECT id FROM roles WHERE name = 'warehouse manager');
SET @finance_role_id = (SELECT id FROM roles WHERE name = 'finance');

-- 5. Insert permissions ke role_has_permissions untuk semua role
-- Admin
INSERT INTO role_has_permissions (permission_id, role_id) VALUES
(@view_permission_id, @admin_role_id),
(@create_permission_id, @admin_role_id),
(@update_permission_id, @admin_role_id),
(@delete_permission_id, @admin_role_id)
ON DUPLICATE KEY UPDATE permission_id = permission_id;

-- Purchasing Manager
INSERT INTO role_has_permissions (permission_id, role_id) VALUES
(@view_permission_id, @purchasing_manager_role_id),
(@create_permission_id, @purchasing_manager_role_id),
(@update_permission_id, @purchasing_manager_role_id),
(@delete_permission_id, @purchasing_manager_role_id)
ON DUPLICATE KEY UPDATE permission_id = permission_id;

-- Warehouse Manager
INSERT INTO role_has_permissions (permission_id, role_id) VALUES
(@view_permission_id, @warehouse_manager_role_id),
(@create_permission_id, @warehouse_manager_role_id),
(@update_permission_id, @warehouse_manager_role_id),
(@delete_permission_id, @warehouse_manager_role_id)
ON DUPLICATE KEY UPDATE permission_id = permission_id;

-- Finance
INSERT INTO role_has_permissions (permission_id, role_id) VALUES
(@view_permission_id, @finance_role_id),
(@create_permission_id, @finance_role_id),
(@update_permission_id, @finance_role_id),
(@delete_permission_id, @finance_role_id)
ON DUPLICATE KEY UPDATE permission_id = permission_id;

-- 6. Tampilkan hasil setup
SELECT '=== FOOD GOOD RECEIVE REPORT SETUP COMPLETED ===' as status;
SELECT 'Menu ID:' as info, @menu_id as value;
SELECT 'Menu Code:' as info, 'food_good_receive_report' as value;
SELECT 'Parent ID:' as info, 6 as value;
SELECT 'Route:' as info, '/food-good-receive-report' as value;

SELECT '=== PERMISSIONS ===' as info;
SELECT 'View Permission ID:' as info, @view_permission_id as value;
SELECT 'Create Permission ID:' as info, @create_permission_id as value;
SELECT 'Update Permission ID:' as info, @update_permission_id as value;
SELECT 'Delete Permission ID:' as info, @delete_permission_id as value;

SELECT '=== ROLES ASSIGNED ===' as info;
SELECT 'Admin Role ID:' as info, @admin_role_id as value;
SELECT 'Purchasing Manager Role ID:' as info, @purchasing_manager_role_id as value;
SELECT 'Warehouse Manager Role ID:' as info, @warehouse_manager_role_id as value;
SELECT 'Finance Role ID:' as info, @finance_role_id as value;
