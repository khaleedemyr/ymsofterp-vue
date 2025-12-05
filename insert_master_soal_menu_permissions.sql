-- Insert Master Soal Menu dan Permissions
-- Menu Master Soal
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Master Soal', 'master_soal', 106, '/master-soal', 'fa-solid fa-clipboard-question', NOW(), NOW());

-- Get the menu_id for Master Soal (assuming it's the last inserted)
SET @master_soal_menu_id = LAST_INSERT_ID();

-- Insert permissions for Master Soal
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@master_soal_menu_id, 'view', 'master_soal_view', NOW(), NOW()),
(@master_soal_menu_id, 'create', 'master_soal_create', NOW(), NOW()),
(@master_soal_menu_id, 'update', 'master_soal_update', NOW(), NOW()),
(@master_soal_menu_id, 'delete', 'master_soal_delete', NOW(), NOW());

-- Alternative query if you want to use specific menu_id
-- Replace @master_soal_menu_id with the actual menu_id after insertion
-- Example:
-- INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
-- (107, 'view', 'master_soal_view', NOW(), NOW()),
-- (107, 'create', 'master_soal_create', NOW(), NOW()),
-- (107, 'update', 'master_soal_update', NOW(), NOW()),
-- (107, 'delete', 'master_soal_delete', NOW(), NOW());

-- Query to check the inserted data
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
WHERE m.code = 'master_soal'
ORDER BY p.action;
