-- =====================================================
-- INSERT MENU + PERMISSION: POS Design Sync Monitor
-- Parent menu: Outlet Management (parent_id = 4)
-- =====================================================

START TRANSACTION;

-- 1) Insert or update menu
INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'POS Design Sync Monitor',
    'pos_design_sync_monitor',
    4,
    '/admin/pos-design-sync-monitor',
    'fa-solid fa-arrows-rotate',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

-- 2) Resolve menu id
SET @menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'pos_design_sync_monitor' LIMIT 1);

-- 3) Insert or update view permission (used by sidebar allowedMenus)
INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'pos_design_sync_monitor_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;

-- Optional: grant permission to a role manually
-- INSERT INTO `erp_role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
-- SELECT <ROLE_ID>, p.id, NOW(), NOW()
-- FROM `erp_permission` p
-- WHERE p.`code` = 'pos_design_sync_monitor_view'
-- ON DUPLICATE KEY UPDATE `updated_at` = NOW();
