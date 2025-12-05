-- Insert menu untuk Outlet Transfer di erp_menu
-- parent_id = 4 refers to "Outlet Management" group

-- Check if menu already exists
SET @existing_menu_id = (SELECT id FROM erp_menu WHERE code = 'outlet_transfer' LIMIT 1);

-- Insert or update menu
IF @existing_menu_id IS NULL THEN
    -- Insert new menu
    INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
    ('Outlet Transfer', 'outlet_transfer', 4, '/outlet-transfer', 'fa-solid fa-right-left', NOW(), NOW());
    
    SET @menu_id = LAST_INSERT_ID();
    SELECT 'New Outlet Transfer menu created with ID:' as message, @menu_id as menu_id;
ELSE
    -- Update existing menu
    UPDATE `erp_menu` SET 
        `name` = 'Outlet Transfer',
        `parent_id` = 4,
        `route` = '/outlet-transfer',
        `icon` = 'fa-solid fa-right-left',
        `updated_at` = NOW()
    WHERE `id` = @existing_menu_id;
    
    SET @menu_id = @existing_menu_id;
    SELECT 'Existing Outlet Transfer menu updated with ID:' as message, @menu_id as menu_id;
END IF;

-- Delete existing permissions for this menu (if any)
DELETE FROM `erp_permission` WHERE `menu_id` = @menu_id;

-- Insert permissions for Outlet Transfer menu
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'outlet_transfer_view', NOW(), NOW()),
(@menu_id, 'create', 'outlet_transfer_create', NOW(), NOW()),
(@menu_id, 'update', 'outlet_transfer_update', NOW(), NOW()),
(@menu_id, 'delete', 'outlet_transfer_delete', NOW(), NOW());

-- Show final result
SELECT 
    'Outlet Transfer setup completed!' as status,
    @menu_id as menu_id,
    'outlet_transfer' as main_code,
    'Permissions: view, create, update, delete' as permissions;

-- Verification queries
SELECT 'Menu yang diinsert:' as info;
SELECT id, name, code, parent_id, route, icon FROM erp_menu WHERE code = 'outlet_transfer';

SELECT 'Permission yang diinsert:' as info;
SELECT p.id, m.name as menu_name, p.action, p.code 
FROM erp_permission p 
JOIN erp_menu m ON p.menu_id = m.id 
WHERE p.code LIKE 'outlet_transfer%' 
ORDER BY m.name, p.action;
