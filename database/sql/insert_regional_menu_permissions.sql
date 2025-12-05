-- Insert menu Regional Management ke erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Regional Management', 'regional_management', 106, '/regional', 'fa-solid fa-globe', NOW(), NOW());

-- Get the menu_id for the newly inserted menu
SET @regional_menu_id = LAST_INSERT_ID();

-- Insert permissions untuk Regional Management
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@regional_menu_id, 'view', 'regional_management_view', NOW(), NOW()),
(@regional_menu_id, 'create', 'regional_management_create', NOW(), NOW()),
(@regional_menu_id, 'update', 'regional_management_update', NOW(), NOW()),
(@regional_menu_id, 'delete', 'regional_management_delete', NOW(), NOW());

-- Alternative: Jika ingin insert langsung dengan menu_id yang diketahui
-- INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
-- (107, 'Regional Management', 'regional_management', 106, '/regional', 'fa-solid fa-globe', NOW(), NOW());

-- INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
-- (107, 'view', 'regional_management_view', NOW(), NOW()),
-- (107, 'create', 'regional_management_create', NOW(), NOW()),
-- (107, 'update', 'regional_management_update', NOW(), NOW()),
-- (107, 'delete', 'regional_management_delete', NOW(), NOW());
