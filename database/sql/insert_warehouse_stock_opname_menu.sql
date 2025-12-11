-- Insert menu untuk Warehouse Stock Opname di erp_menu dengan parent_id = 245 (Warehouse Management)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Stock Opname', 'warehouse_stock_opname', 245, '/warehouse-stock-opnames', 'fa-solid fa-clipboard-check', NOW(), NOW());

-- Ambil ID menu yang baru dibuat
SET @warehouse_stock_opname_menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu Warehouse Stock Opname
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@warehouse_stock_opname_menu_id, 'view', 'warehouse_stock_opname', NOW(), NOW()),
(@warehouse_stock_opname_menu_id, 'create', 'warehouse_stock_opname_create', NOW(), NOW()),
(@warehouse_stock_opname_menu_id, 'edit', 'warehouse_stock_opname_edit', NOW(), NOW()),
(@warehouse_stock_opname_menu_id, 'delete', 'warehouse_stock_opname_delete', NOW(), NOW()),
(@warehouse_stock_opname_menu_id, 'approve', 'warehouse_stock_opname_approve', NOW(), NOW()),
(@warehouse_stock_opname_menu_id, 'process', 'warehouse_stock_opname_process', NOW(), NOW());

-- Show result
SELECT 
    'Warehouse Stock Opname menu and permissions setup completed!' as message,
    @warehouse_stock_opname_menu_id as menu_id,
    'warehouse_stock_opname' as menu_code,
    'parent_id = 245 (Warehouse Management)' as parent_info;

