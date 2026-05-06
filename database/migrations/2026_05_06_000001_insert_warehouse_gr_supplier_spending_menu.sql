-- =====================================================
-- INSERT MENU DAN PERMISSION: Report Pembelanjaan Supplier (Warehouse GR)
-- Created: 2026-05-06
-- Description: Menu di grup Cost Control (parent_id = 66)
-- Route: /food-good-receive-report-supplier-spending
-- =====================================================

INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Report Pembelanjaan Supplier (Warehouse GR)',
    'warehouse_gr_supplier_spending_report',
    66,
    '/food-good-receive-report-supplier-spending',
    'fa-solid fa-sack-dollar',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Report Pembelanjaan Supplier (Warehouse GR)',
    `parent_id` = 66,
    `route` = '/food-good-receive-report-supplier-spending',
    `icon` = 'fa-solid fa-sack-dollar',
    `updated_at` = NOW();

SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'warehouse_gr_supplier_spending_report' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'warehouse_gr_supplier_spending_report_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- Beri akses role (contoh):
-- INSERT INTO erp_role_permission (role_id, permission_id)
-- SELECT YOUR_ROLE_ID, id FROM erp_permission WHERE code = 'warehouse_gr_supplier_spending_report_view';
