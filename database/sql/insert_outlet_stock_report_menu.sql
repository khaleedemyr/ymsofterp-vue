-- Insert menu untuk Outlet Stock Report di erp_menu dengan parent_id = 66 (Cost Control)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Outlet Stock Report', 'outlet_stock_report', 66, '/outlet-stock-report', 'fa-solid fa-chart-line', NOW(), NOW());

-- Ambil ID menu yang baru dibuat
SET @outlet_stock_report_menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu Outlet Stock Report
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@outlet_stock_report_menu_id, 'view', 'outlet_stock_report', NOW(), NOW());

-- Show result
SELECT 
    'Outlet Stock Report menu and permissions setup completed!' as message,
    @outlet_stock_report_menu_id as menu_id,
    'outlet_stock_report' as menu_code,
    'parent_id = 66 (Cost Control)' as parent_info;

