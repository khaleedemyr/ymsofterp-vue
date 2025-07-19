-- Insert Point Management Menu
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) 
VALUES ('Point Management', 'crm_point_management', 138, '/crm/point-management', 'fa-solid fa-coins', NOW(), NOW());

-- Get the inserted menu ID
SET @menu_id = LAST_INSERT_ID();

-- Insert Point Management Permissions
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@menu_id, 'view', 'crm_point_management_view', NOW(), NOW()),
(@menu_id, 'create', 'crm_point_management_create', NOW(), NOW()),
(@menu_id, 'delete', 'crm_point_management_delete', NOW(), NOW());

-- Verify the insertion
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
WHERE m.code = 'crm_point_management'
ORDER BY p.action; 