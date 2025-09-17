-- Add Employee Training Report menu and permissions for ERP tables
-- Execute this script to add the new menu item and its permissions

-- Insert menu item into erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES (
    'Laporan Training Karyawan',
    'lms-employee-training-report',
    127,
    '/lms/employee-training-report-page',
    'fa-solid fa-users',
    NOW(),
    NOW()
);

-- Get the menu ID for permission insertion
SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'lms-employee-training-report' LIMIT 1);

-- Insert permissions for the menu into erp_permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'lms-employee-training-report-view', NOW(), NOW()),
(@menu_id, 'create', 'lms-employee-training-report-create', NOW(), NOW()),
(@menu_id, 'update', 'lms-employee-training-report-update', NOW(), NOW()),
(@menu_id, 'delete', 'lms-employee-training-report-delete', NOW(), NOW());

-- Verify the insertion
SELECT 'Menu and permissions added successfully' as status;
SELECT m.name as menu_name, m.code as menu_code, m.route, m.parent_id
FROM erp_menu m 
WHERE m.code = 'lms-employee-training-report';

SELECT p.action, p.code as permission_code, p.menu_id
FROM erp_permission p 
WHERE p.code LIKE 'lms-employee-training-report%';
