-- =====================================================
-- INSERT MENU DAN PERMISSION: TRACK PENJUALAN ITEM WAREHOUSE
-- Created: 2026-09-28
-- Description: Laporan track qty/nilai penjualan item warehouse
--   (antar gudang + retail + outlet GR) dengan breakdown bulanan
-- Route: /report-warehouse-item-sales | Icon: fa-solid fa-chart-line
-- Parent: Cost Control (parent_id = 66)
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
    'Track Penjualan Item Warehouse',
    'report_warehouse_item_sales',
    66,
    '/report-warehouse-item-sales',
    'fa-solid fa-chart-line',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Track Penjualan Item Warehouse',
    `parent_id` = 66,
    `route` = '/report-warehouse-item-sales',
    `icon` = 'fa-solid fa-chart-line',
    `updated_at` = NOW();

SET @menu_id = (SELECT id FROM `erp_menu` WHERE `code` = 'report_warehouse_item_sales' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'report_warehouse_item_sales_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- Beri akses ke role yang sudah punya laporan penjualan terkait
INSERT INTO erp_role_permission (role_id, permission_id)
SELECT DISTINCT rp.role_id, p2.id
FROM erp_permission p1
JOIN erp_role_permission rp ON rp.permission_id = p1.id
JOIN erp_permission p2 ON p2.code = 'report_warehouse_item_sales_view'
WHERE p1.code IN (
    'report_sales_all_item_all_outlet_view',
    'report_sales_per_category_view',
    'report_sales_per_tanggal_view',
    'report_good_receive_outlet_view',
    'warehouse_sales_view',
    'retail_warehouse_sale_view'
)
AND NOT EXISTS (
    SELECT 1 FROM erp_role_permission x
    WHERE x.role_id = rp.role_id AND x.permission_id = p2.id
);
