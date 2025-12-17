-- Insert menu untuk Stock Opname Adjustment Report di erp_menu dengan parent_id = 66 (Cost Control)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES
('Stock Opname Adjustment Report', 'stock_opname_adjustment_report', 66, '/stock-opname-adjustment-report', 'fa-solid fa-chart-bar', NOW(), NOW());

-- Get the menu_id that was just inserted
SET @stock_opname_adjustment_report_menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu Stock Opname Adjustment Report
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES
(@stock_opname_adjustment_report_menu_id, 'view', 'stock_opname_adjustment_report', NOW(), NOW());

-- Show confirmation
SELECT 
    'Stock Opname Adjustment Report menu and permissions setup completed!' as message,
    @stock_opname_adjustment_report_menu_id as menu_id,
    'stock_opname_adjustment_report' as menu_code,
    'view' as permission_action;

