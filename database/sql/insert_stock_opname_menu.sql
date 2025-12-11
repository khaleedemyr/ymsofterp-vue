-- Insert menu untuk Stock Opname di erp_menu dengan parent_id = 4 (Outlet Management)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Stock Opname', 'stock_opname', 4, '/stock-opnames', 'fa-solid fa-clipboard-check', NOW(), NOW());

-- Ambil ID menu yang baru dibuat
SET @stock_opname_menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu Stock Opname
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@stock_opname_menu_id, 'view', 'stock_opname', NOW(), NOW()),
(@stock_opname_menu_id, 'create', 'stock_opname_create', NOW(), NOW()),
(@stock_opname_menu_id, 'edit', 'stock_opname_edit', NOW(), NOW()),
(@stock_opname_menu_id, 'delete', 'stock_opname_delete', NOW(), NOW()),
(@stock_opname_menu_id, 'approve', 'stock_opname_approve', NOW(), NOW()),
(@stock_opname_menu_id, 'process', 'stock_opname_process', NOW(), NOW());

-- Show result
SELECT 
    'Stock Opname menu and permissions setup completed!' as message,
    @stock_opname_menu_id as menu_id,
    'stock_opname' as menu_code,
    'parent_id = 4 (Outlet Management)' as parent_info;

