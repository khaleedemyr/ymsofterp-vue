-- Script untuk insert menu Support dan permissions
-- Jalankan script ini untuk menambahkan menu Support Admin Panel ke sistem

-- Insert Support Group Menu
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Support', 'support', NULL, '#', 'fa-solid fa-headset', NOW(), NOW());

-- Get the Support group menu ID
SET @support_group_id = LAST_INSERT_ID();

-- Insert Support Admin Panel Menu
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Support Admin Panel', 'support_admin_panel', @support_group_id, '/support/admin', 'fa-solid fa-comments', NOW(), NOW());

-- Get the Support Admin Panel menu ID
SET @support_admin_panel_id = LAST_INSERT_ID();

-- Insert permissions for Support Admin Panel
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@support_admin_panel_id, 'view', 'support_admin_panel_view', NOW(), NOW()),
(@support_admin_panel_id, 'create', 'support_admin_panel_create', NOW(), NOW()),
(@support_admin_panel_id, 'update', 'support_admin_panel_update', NOW(), NOW()),
(@support_admin_panel_id, 'delete', 'support_admin_panel_delete', NOW(), NOW());

-- Verify the inserts
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
WHERE m.code IN ('support', 'support_admin_panel')
ORDER BY m.id, p.action;
