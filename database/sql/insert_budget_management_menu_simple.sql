-- Simple Insert for Budget Management Menu and Permissions
-- Execute this script directly

-- Step 1: Insert main menu for Budget Management
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Budget Management', 'budget_management', 3, '/budget-management', 'fa-solid fa-chart-pie', NOW(), NOW());

-- Step 2: Insert permissions for Budget Management (using the menu_id from above)
-- Note: Replace [MENU_ID] with the actual ID returned from the insert above
-- You can find the menu_id by running: SELECT id FROM erp_menu WHERE code = 'budget_management';

INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
-- Replace [MENU_ID] with actual menu ID
([MENU_ID], 'view', 'budget_management_view', NOW(), NOW()),
([MENU_ID], 'create', 'budget_management_create', NOW(), NOW()),
([MENU_ID], 'update', 'budget_management_update', NOW(), NOW()),
([MENU_ID], 'delete', 'budget_management_delete', NOW(), NOW());

-- Step 3: Verify the insertions
SELECT 
    m.id as menu_id,
    m.name as menu_name,
    m.code as menu_code,
    m.parent_id,
    m.route,
    m.icon,
    p.action,
    p.code as permission_code
FROM erp_menu m
LEFT JOIN erp_permission p ON m.id = p.menu_id
WHERE m.code = 'budget_management'
ORDER BY p.action;
