-- =====================================================
-- Product Sales by Outlet (Custom Pivot Report)
-- Parent Sales & Marketing: parent_id = 8
-- Route: /report/product-sales-pivot
-- Jika menu sudah ada dengan parent_id lama: UPDATE erp_menu SET parent_id = 8 WHERE code = 'product_sales_pivot';
-- =====================================================

START TRANSACTION;

INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Product Sales by Outlet',
    'product_sales_pivot',
    8,
    '/report/product-sales-pivot',
    'fa-solid fa-table-columns',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'product_sales_pivot' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'product_sales_pivot_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `action` = VALUES(`action`),
    `updated_at` = NOW();

COMMIT;
