-- Insert Budget Management Menu and Permissions
-- This script creates menu entries for Budget Management system

-- Insert main menu for Budget Management
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Budget Management', 'budget_management', 3, '/budget-management', 'fa-solid fa-chart-pie', NOW(), NOW());

-- Get the menu_id for Budget Management (assuming it's the last inserted)
SET @budget_management_menu_id = LAST_INSERT_ID();

-- Insert permissions for Budget Management
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
-- View permissions
(@budget_management_menu_id, 'view', 'budget_management_view', NOW(), NOW()),

-- Create permissions  
(@budget_management_menu_id, 'create', 'budget_management_create', NOW(), NOW()),

-- Update permissions
(@budget_management_menu_id, 'update', 'budget_management_update', NOW(), NOW()),

-- Delete permissions
(@budget_management_menu_id, 'delete', 'budget_management_delete', NOW(), NOW());

-- Alternative approach if LAST_INSERT_ID() doesn't work as expected
-- You can manually set the menu_id if you know the ID

-- If you need to manually set the menu_id, uncomment and modify the following:
-- SET @budget_management_menu_id = [ACTUAL_MENU_ID];

-- INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
-- (@budget_management_menu_id, 'view', 'budget_management_view', NOW(), NOW()),
-- (@budget_management_menu_id, 'create', 'budget_management_create', NOW(), NOW()),
-- (@budget_management_menu_id, 'update', 'budget_management_update', NOW(), NOW()),
-- (@budget_management_menu_id, 'delete', 'budget_management_delete', NOW(), NOW());

-- Verify the insertions
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
