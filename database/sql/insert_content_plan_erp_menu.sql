-- Menu: Content Plan — parent Sales & Marketing (parent_id = 8)
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
    'Content Plan',
    'content_plan',
    8,
    '/content-plans',
    'fa-solid fa-calendar-days',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'content_plan' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view', 'content_plan_view', NOW(), NOW()),
    (@menu_id, 'create', 'content_plan_create', NOW(), NOW()),
    (@menu_id, 'update', 'content_plan_update', NOW(), NOW()),
    (@menu_id, 'delete', 'content_plan_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;

-- Optional: assign ke role (contoh superadmin role_id = 1). Sesuaikan role_id.
-- INSERT IGNORE INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
-- SELECT 1, id, NOW(), NOW() FROM erp_permission WHERE code LIKE 'content_plan_%';
