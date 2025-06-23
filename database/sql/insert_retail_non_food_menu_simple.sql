-- Simple Retail Non Food Menu and Permissions Setup
-- Run this SQL after creating the tables

-- Step 1: Insert menu into erp_menus table
INSERT INTO `erp_menus` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Retail Non Food', 'view-retail-non-food', 4, '/retail-non-food', 'fa-solid fa-shopping-bag', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

-- Step 2: Get the menu_id
SET @menu_id = (SELECT id FROM erp_menus WHERE code = 'view-retail-non-food' LIMIT 1);

-- Step 3: Delete existing permissions for this menu (if any)
DELETE FROM `erp_permission` WHERE `menu_id` = @menu_id;

-- Step 4: Insert permissions for Retail Non Food menu
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'view-retail-non-food', NOW(), NOW()),
(@menu_id, 'create', 'create-retail-non-food', NOW(), NOW()),
(@menu_id, 'edit', 'edit-retail-non-food', NOW(), NOW()),
(@menu_id, 'delete', 'delete-retail-non-food', NOW(), NOW());

-- Step 5: Show result
SELECT 
    'Retail Non Food menu and permissions setup completed!' as message,
    @menu_id as menu_id,
    'view-retail-non-food' as menu_code; 