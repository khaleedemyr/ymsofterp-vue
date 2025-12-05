-- Complete Retail Non Food Menu and Permissions Setup
-- Run this SQL after creating the tables

-- Check if menu already exists
SET @existing_menu_id = (SELECT id FROM erp_menus WHERE code = 'view-retail-non-food' LIMIT 1);

-- Insert or update menu
IF @existing_menu_id IS NULL THEN
    -- Insert new menu
    INSERT INTO `erp_menus` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
    ('Retail Non Food', 'view-retail-non-food', 4, '/retail-non-food', 'fa-solid fa-shopping-bag', NOW(), NOW());
    
    SET @menu_id = LAST_INSERT_ID();
    SELECT 'New Retail Non Food menu created with ID:' as message, @menu_id as menu_id;
ELSE
    -- Update existing menu
    UPDATE `erp_menus` SET 
        `name` = 'Retail Non Food',
        `parent_id` = 4,
        `route` = '/retail-non-food',
        `icon` = 'fa-solid fa-shopping-bag',
        `updated_at` = NOW()
    WHERE `id` = @existing_menu_id;
    
    SET @menu_id = @existing_menu_id;
    SELECT 'Existing Retail Non Food menu updated with ID:' as message, @menu_id as menu_id;
END IF;

-- Delete existing permissions for this menu (if any)
DELETE FROM `erp_permission` WHERE `menu_id` = @menu_id;

-- Insert permissions for Retail Non Food menu
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'view-retail-non-food', NOW(), NOW()),
(@menu_id, 'create', 'create-retail-non-food', NOW(), NOW()),
(@menu_id, 'edit', 'edit-retail-non-food', NOW(), NOW()),
(@menu_id, 'delete', 'delete-retail-non-food', NOW(), NOW());

-- Show final result
SELECT 
    'Retail Non Food setup completed!' as status,
    @menu_id as menu_id,
    'view-retail-non-food' as main_code,
    'Permissions: view, create, edit, delete' as permissions; 