-- Menu: Laporan Minus Stock Cut — parent Cost Control (parent_id = 66)
-- Eksekusi sekali di MySQL (paste semua query sekaligus).

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
    'Laporan Minus Stock Cut',
    'stock_cut_variance_report',
    66,
    '/stock-cut/variance-report',
    'fa-solid fa-minus-circle',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'stock_cut_variance_report' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view', 'stock_cut_variance_report_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `action` = VALUES(`action`),
    `updated_at` = NOW();

COMMIT;

-- Berikan akses ke role (sesuaikan role_id):
-- INSERT INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
-- SELECT 1, id, NOW(), NOW() FROM erp_permission WHERE code = 'stock_cut_variance_report_view';
