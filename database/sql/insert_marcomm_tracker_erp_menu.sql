-- Menu: Marcomm Tracker — parent Sales & Marketing (parent_id = 8)
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
    'Marcomm Tracker',
    'marcomm_tracker',
    8,
    '/marcomm-trackers',
    'fa-solid fa-bullhorn',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'marcomm_tracker' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view', 'marcomm_tracker_view', NOW(), NOW()),
    (@menu_id, 'create', 'marcomm_tracker_create', NOW(), NOW()),
    (@menu_id, 'update', 'marcomm_tracker_update', NOW(), NOW()),
    (@menu_id, 'delete', 'marcomm_tracker_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;

-- Optional: assign ke role (contoh superadmin role_id = 1). Sesuaikan role_id.
-- INSERT IGNORE INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
-- SELECT 1, id, NOW(), NOW() FROM erp_permission WHERE code LIKE 'marcomm_tracker_%';
