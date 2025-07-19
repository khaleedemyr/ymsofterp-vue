-- Check if CRM parent menu exists
SELECT 
    id,
    name,
    code,
    parent_id,
    route,
    icon,
    created_at
FROM erp_menu 
WHERE code = 'crm' OR id = 138;

-- Check existing CRM sub-menus
SELECT 
    m.id,
    m.name,
    m.code,
    m.parent_id,
    m.route,
    m.icon,
    p.action,
    p.code as permission_code
FROM erp_menu m
LEFT JOIN erp_permission p ON m.id = p.menu_id
WHERE m.parent_id = 138 OR m.parent_id = (SELECT id FROM erp_menu WHERE code = 'crm' LIMIT 1)
ORDER BY m.id, p.action;

-- Check table structure
DESCRIBE erp_menu;
DESCRIBE erp_permission; 