-- Insert PR Payment menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('PR Payment', 'pr_payment', 5, '/payments', 'fa-solid fa-credit-card', NOW(), NOW());

-- Get the menu_id of the inserted menu
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions for PR Payment
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
VALUES 
(@menu_id, 'view', 'pr_payment_view', NOW(), NOW()),
(@menu_id, 'create', 'pr_payment_create', NOW(), NOW()),
(@menu_id, 'update', 'pr_payment_update', NOW(), NOW()),
(@menu_id, 'delete', 'pr_payment_delete', NOW(), NOW());
