-- =====================================================
-- Sales Trend Dashboard — erp_menu + erp_permission
-- Parent Main: parent_id = 1
-- Route: /sales-trend-dashboard
-- Eksekusi sekali di MySQL (paste semua query sekaligus).
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
    'Sales Trend Dashboard',
    'sales_trend_dashboard',
    1,
    '/sales-trend-dashboard',
    'fa-solid fa-chart-area',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'sales_trend_dashboard' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'sales_trend_dashboard_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `action` = VALUES(`action`),
    `updated_at` = NOW();

COMMIT;
