-- Menu: Item Engineering Breakdown — parent Cost Control (parent_id = 66)
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
    'Item Engineering Breakdown',
    'item_engineering_breakdown',
    66,
    '/item-engineering-breakdown',
    'fa-solid fa-layer-group',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'item_engineering_breakdown' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view', 'item_engineering_breakdown_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;

-- Optional: assign ke role (contoh superadmin role_id = 1). Sesuaikan role_id.
-- INSERT IGNORE INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
-- SELECT 1, id, NOW(), NOW() FROM erp_permission WHERE code = 'item_engineering_breakdown_view';
