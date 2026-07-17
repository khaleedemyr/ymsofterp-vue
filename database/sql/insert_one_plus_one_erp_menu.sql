-- Menu: One Plus One — parent Human Resources (parent_id = 106)
-- Route: /one-plus-one-submissions
-- Eksekusi sekali di MySQL (paste semua query sekaligus). Aman diulang (ON DUPLICATE KEY UPDATE).

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
    'One Plus One',
    'one_plus_one',
    106,
    '/one-plus-one-submissions',
    'fa-solid fa-minus-circle',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'one_plus_one' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'one_plus_one_view',   NOW(), NOW()),
    (@menu_id, 'create', 'one_plus_one_create', NOW(), NOW()),
    (@menu_id, 'update', 'one_plus_one_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'one_plus_one_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;
