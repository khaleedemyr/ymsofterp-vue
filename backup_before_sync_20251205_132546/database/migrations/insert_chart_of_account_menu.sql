-- Insert menu Chart of Account ke erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Chart of Account', 'chart_of_account', 3, '/chart-of-accounts', 'fa-solid fa-chart-line', NOW(), NOW());

-- Get the menu_id yang baru saja diinsert
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions untuk Chart of Account
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'chart_of_account_view', NOW(), NOW()),
(@menu_id, 'create', 'chart_of_account_create', NOW(), NOW()),
(@menu_id, 'update', 'chart_of_account_update', NOW(), NOW()),
(@menu_id, 'delete', 'chart_of_account_delete', NOW(), NOW());

