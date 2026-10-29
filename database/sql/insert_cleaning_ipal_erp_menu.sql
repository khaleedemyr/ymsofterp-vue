-- Cleaning IPAL — erp_menu + erp_permission
-- parent_id = 4 (Outlet Management)
-- Eksekusi sekali di MySQL (boleh dipaste sekaligus).

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
    'Cleaning IPAL',
    'cleaning_ipal',
    4,
    '/cleaning-ipal',
    'fa-solid fa-broom',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'cleaning_ipal' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'cleaning_ipal_view',   NOW(), NOW()),
    (@menu_id, 'create', 'cleaning_ipal_create', NOW(), NOW()),
    (@menu_id, 'update', 'cleaning_ipal_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'cleaning_ipal_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;
