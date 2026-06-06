-- =====================================================
-- Outlet Analyzer — erp_menu + erp_permission
-- Parent Main: parent_id = 1
-- Route: /outlet-analyzer
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
    'Outlet Analyzer',
    'outlet_analyzer',
    1,
    '/outlet-analyzer',
    'fa-solid fa-magnifying-glass-chart',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'outlet_analyzer' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'outlet_analyzer_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `action` = VALUES(`action`),
    `updated_at` = NOW();

COMMIT;
