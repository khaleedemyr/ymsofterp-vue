-- Insert menu untuk Warehouse Retail Food di erp_menu dengan parent_id = 6 (Warehouse Management)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Warehouse Retail Food', 'view-retail-warehouse-food', 6, '/retail-warehouse-food', 'fa-solid fa-warehouse', NOW(), NOW());

-- Ambil ID menu yang baru dibuat
SET @retail_warehouse_food_menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu Warehouse Retail Food
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@retail_warehouse_food_menu_id, 'view', 'view-retail-warehouse-food_view', NOW(), NOW()),
(@retail_warehouse_food_menu_id, 'create', 'view-retail-warehouse-food_create', NOW(), NOW()),
(@retail_warehouse_food_menu_id, 'update', 'view-retail-warehouse-food_update', NOW(), NOW()),
(@retail_warehouse_food_menu_id, 'delete', 'view-retail-warehouse-food_delete', NOW(), NOW());

-- Show result
SELECT 
    'Warehouse Retail Food menu and permissions setup completed!' as message,
    @retail_warehouse_food_menu_id as menu_id,
    'view-retail-warehouse-food' as menu_code,
    'parent_id = 6 (Warehouse Management)' as parent_info;

