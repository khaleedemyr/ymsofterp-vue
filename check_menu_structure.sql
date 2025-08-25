-- Check erp_menu table structure
DESCRIBE erp_menu;

-- Check erp_permission table structure  
DESCRIBE erp_permission;

-- Check if role_permissions table exists and its structure
SHOW TABLES LIKE 'role_permissions';
DESCRIBE role_permissions;

-- Check existing menus in Cost Control group (parent_id = 66)
SELECT id, name, code, parent_id, route, icon 
FROM erp_menu 
WHERE parent_id = 66 
ORDER BY id;

-- Check existing permissions for Cost Control menus
SELECT 
    m.name as menu_name,
    m.code as menu_code,
    p.action,
    p.code as permission_code
FROM erp_menu m
JOIN erp_permission p ON m.id = p.menu_id
WHERE m.parent_id = 66
ORDER BY m.id, p.action;
