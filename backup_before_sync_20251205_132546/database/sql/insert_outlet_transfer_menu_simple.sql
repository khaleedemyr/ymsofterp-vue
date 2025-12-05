-- Simple INSERT queries for Outlet Transfer menu and permissions
-- parent_id = 4 refers to "Outlet Management" group

-- Insert menu into erp_menu table
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Outlet Transfer', 'outlet_transfer', 4, '/outlet-transfer', 'fa-solid fa-right-left', NOW(), NOW());

-- Insert permissions into erp_permission table
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(LAST_INSERT_ID(), 'view', 'outlet_transfer_view', NOW(), NOW()),
(LAST_INSERT_ID(), 'create', 'outlet_transfer_create', NOW(), NOW()),
(LAST_INSERT_ID(), 'update', 'outlet_transfer_update', NOW(), NOW()),
(LAST_INSERT_ID(), 'delete', 'outlet_transfer_delete', NOW(), NOW());
