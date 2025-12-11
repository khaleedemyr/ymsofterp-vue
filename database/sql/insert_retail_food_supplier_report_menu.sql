-- Insert menu untuk Report Retail Food per Supplier di erp_menu dengan parent_id = 66 (Cost Control)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Report Retail Food per Supplier', 'retail_food_supplier_report', 66, '/retail-food/report-supplier', 'fa-solid fa-chart-line', NOW(), NOW());

-- Ambil ID menu yang baru dibuat
SET @retail_food_supplier_report_menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu Report Retail Food per Supplier
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@retail_food_supplier_report_menu_id, 'view', 'retail_food_supplier_report', NOW(), NOW());

-- Show result
SELECT 
    'Report Retail Food per Supplier menu and permissions setup completed!' as message,
    @retail_food_supplier_report_menu_id as menu_id,
    'retail_food_supplier_report' as menu_code,
    'parent_id = 66 (Cost Control)' as parent_info;

