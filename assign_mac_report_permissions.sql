-- Assign MAC Report permissions to admin role (assuming role_id = 1 for admin)
-- First, get the menu ID for MAC Report
SET @mac_report_menu_id = (SELECT id FROM erp_menu WHERE code = 'mac_report' LIMIT 1);

-- Get all permission IDs for MAC Report
SET @mac_report_view_permission_id = (SELECT id FROM erp_permission WHERE menu_id = @mac_report_menu_id AND action = 'view' LIMIT 1);
SET @mac_report_create_permission_id = (SELECT id FROM erp_permission WHERE menu_id = @mac_report_menu_id AND action = 'create' LIMIT 1);
SET @mac_report_update_permission_id = (SELECT id FROM erp_permission WHERE menu_id = @mac_report_menu_id AND action = 'update' LIMIT 1);
SET @mac_report_delete_permission_id = (SELECT id FROM erp_permission WHERE menu_id = @mac_report_menu_id AND action = 'delete' LIMIT 1);

-- Insert role permissions for admin role (role_id = 1)
-- Note: Adjust role_id according to your admin role ID
INSERT INTO role_permissions (role_id, permission_id, created_at, updated_at) VALUES
(1, @mac_report_view_permission_id, NOW(), NOW()),
(1, @mac_report_create_permission_id, NOW(), NOW()),
(1, @mac_report_update_permission_id, NOW(), NOW()),
(1, @mac_report_delete_permission_id, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Display the assigned permissions
SELECT 'MAC Report permissions assigned to admin role:' as info;
SELECT 
    rp.role_id,
    r.name as role_name,
    p.code as permission_code,
    p.action as permission_action
FROM role_permissions rp
JOIN roles r ON rp.role_id = r.id
JOIN erp_permission p ON rp.permission_id = p.id
WHERE p.menu_id = @mac_report_menu_id
ORDER BY rp.role_id, p.action;
