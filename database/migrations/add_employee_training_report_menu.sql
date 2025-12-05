-- Add Employee Training Report menu and permissions
-- Execute this script to add the new menu item and its permissions

-- Insert menu item
INSERT INTO `menus` (`id`, `name`, `code`, `parent_id`, `order`, `icon`, `route`, `created_at`, `updated_at`) 
VALUES (
    UUID(),
    'Laporan Training Karyawan',
    'lms-employee-training-report',
    (SELECT id FROM menus WHERE code = 'lms' LIMIT 1),
    8,
    'fa-solid fa-users',
    '/lms/employee-training-report-page',
    NOW(),
    NOW()
);

-- Get the menu ID for permission insertion
SET @menu_id = (SELECT id FROM menus WHERE code = 'lms-employee-training-report' LIMIT 1);

-- Insert permissions for the menu
INSERT INTO `permissions` (`id`, `name`, `code`, `created_at`, `updated_at`) VALUES
(UUID(), 'View Employee Training Report', 'lms-employee-training-report-view', NOW(), NOW()),
(UUID(), 'Create Employee Training Report', 'lms-employee-training-report-create', NOW(), NOW()),
(UUID(), 'Update Employee Training Report', 'lms-employee-training-report-update', NOW(), NOW()),
(UUID(), 'Delete Employee Training Report', 'lms-employee-training-report-delete', NOW(), NOW());

-- Get permission IDs
SET @view_permission_id = (SELECT id FROM permissions WHERE code = 'lms-employee-training-report-view' LIMIT 1);
SET @create_permission_id = (SELECT id FROM permissions WHERE code = 'lms-employee-training-report-create' LIMIT 1);
SET @update_permission_id = (SELECT id FROM permissions WHERE code = 'lms-employee-training-report-update' LIMIT 1);
SET @delete_permission_id = (SELECT id FROM permissions WHERE code = 'lms-employee-training-report-delete' LIMIT 1);

-- Insert menu permissions
INSERT INTO `menu_permissions` (`id`, `menu_id`, `permission_id`, `created_at`, `updated_at`) VALUES
(UUID(), @menu_id, @view_permission_id, NOW(), NOW()),
(UUID(), @menu_id, @create_permission_id, NOW(), NOW()),
(UUID(), @menu_id, @update_permission_id, NOW(), NOW()),
(UUID(), @menu_id, @delete_permission_id, NOW(), NOW());

-- Assign permissions to admin role (assuming admin role ID is '5af56935b011a')
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`, `updated_at`) VALUES
(UUID(), '5af56935b011a', @view_permission_id, NOW(), NOW()),
(UUID(), '5af56935b011a', @create_permission_id, NOW(), NOW()),
(UUID(), '5af56935b011a', @update_permission_id, NOW(), NOW()),
(UUID(), '5af56935b011a', @delete_permission_id, NOW(), NOW());

-- Verify the insertion
SELECT 'Menu and permissions added successfully' as status;
SELECT m.name as menu_name, m.code as menu_code, m.route 
FROM menus m 
WHERE m.code = 'lms-employee-training-report';

SELECT p.name as permission_name, p.code as permission_code 
FROM permissions p 
WHERE p.code LIKE 'lms-employee-training-report%';
