-- =====================================================
-- Rekap Kunjungan Outlet Regional — erp_menu + erp_permission
-- Parent Human Resource: parent_id = 106
-- Route: /regional/visit-report
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
    'Rekap Kunjungan Regional',
    'regional_visit_report',
    106,
    '/regional/visit-report',
    'fa-solid fa-map-location-dot',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'regional_visit_report' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'regional_visit_report_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `action` = VALUES(`action`),
    `updated_at` = NOW();

COMMIT;
