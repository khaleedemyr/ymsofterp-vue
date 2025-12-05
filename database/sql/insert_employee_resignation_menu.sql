-- Insert menu Employee Resignation ke erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Employee Resignation', 'employee_resignation', 106, '/employee-resignations', 'fa-solid fa-user-minus', NOW(), NOW());

-- Set variabel untuk menu_id yang baru diinsert
SET @menu_id = (SELECT id FROM `erp_menu` WHERE `code` = 'employee_resignation' LIMIT 1);

-- Insert permissions untuk Employee Resignation
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'employee_resignation_view', NOW(), NOW()),
(@menu_id, 'create', 'employee_resignation_create', NOW(), NOW()),
(@menu_id, 'update', 'employee_resignation_update', NOW(), NOW()),
(@menu_id, 'delete', 'employee_resignation_delete', NOW(), NOW());

