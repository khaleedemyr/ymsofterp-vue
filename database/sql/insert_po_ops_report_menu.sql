-- Insert menu untuk Report Purchase Order Ops di erp_menu dengan parent_id = 225 (Purchasing)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES
('Report Purchase Order Ops', 'po_ops_report', 225, '/po-ops/report', 'fa-solid fa-chart-bar', NOW(), NOW());

-- Get the menu_id that was just inserted
SET @po_ops_report_menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu Report Purchase Order Ops
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES
(@po_ops_report_menu_id, 'view', 'po_ops_report_view', NOW(), NOW());

-- Show confirmation
SELECT 
    'Report Purchase Order Ops menu and permissions setup completed!' as message,
    @po_ops_report_menu_id as menu_id,
    'po_ops_report' as menu_code,
    'view' as permission_action;

