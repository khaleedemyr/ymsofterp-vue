-- Insert menu untuk PO Report di erp_menu dengan parent_id = 6 (Warehouse Management)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Report PO GR', 'po_report', 6, '/po-report', 'fa-solid fa-chart-line', NOW(), NOW());

-- Ambil ID menu yang baru dibuat
SET @po_report_menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu PO Report
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@po_report_menu_id, 'view', 'po_report_view', NOW(), NOW()),
(@po_report_menu_id, 'create', 'po_report_create', NOW(), NOW()),
(@po_report_menu_id, 'update', 'po_report_update', NOW(), NOW()),
(@po_report_menu_id, 'delete', 'po_report_delete', NOW(), NOW());

-- Show result
SELECT 
    'PO Report menu and permissions setup completed!' as message,
    @po_report_menu_id as menu_id,
    'po_report' as menu_code,
    'parent_id = 6 (Warehouse Management)' as parent_info;
