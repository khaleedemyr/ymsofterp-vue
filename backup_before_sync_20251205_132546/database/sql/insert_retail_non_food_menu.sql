-- Insert Retail Non Food Menu and Permissions
-- Run this SQL after creating the tables

-- Insert menu into erp_menus table
INSERT INTO `erp_menus` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Retail Non Food', 'view-retail-non-food', 4, '/retail-non-food', 'fa-solid fa-shopping-bag', NOW(), NOW());

-- Get the menu_id that was just inserted
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions for Retail Non Food menu
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'view-retail-non-food', NOW(), NOW()),
(@menu_id, 'create', 'create-retail-non-food', NOW(), NOW()),
(@menu_id, 'edit', 'edit-retail-non-food', NOW(), NOW()),
(@menu_id, 'delete', 'delete-retail-non-food', NOW(), NOW());

-- Show success message
SELECT 'Retail Non Food menu and permissions inserted successfully!' as message; 