-- Direct Insert for Budget Management Menu and Permissions
-- This script can be executed directly without modifications

-- Insert main menu for Budget Management
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Budget Management', 'budget_management', 3, '/budget-management', 'fa-solid fa-chart-pie', NOW(), NOW());

-- Insert permissions for Budget Management (main menu)
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) 
SELECT 
    m.id as menu_id,
    'view' as action,
    'budget_management_view' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM erp_menu m 
WHERE m.code = 'budget_management'

UNION ALL

SELECT 
    m.id as menu_id,
    'create' as action,
    'budget_management_create' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM erp_menu m 
WHERE m.code = 'budget_management'

UNION ALL

SELECT 
    m.id as menu_id,
    'update' as action,
    'budget_management_update' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM erp_menu m 
WHERE m.code = 'budget_management'

UNION ALL

SELECT 
    m.id as menu_id,
    'delete' as action,
    'budget_management_delete' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM erp_menu m 
WHERE m.code = 'budget_management';


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
