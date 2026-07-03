-- Menu: Manual COGS, Deviation & Catcost — parent Cost Control (parent_id = 66)
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
    'Manual COGS, Deviation & Catcost',
    'manual_cogs_deviation_catcost',
    66,
    '/manual-cogs-deviation-catcost',
    'fa-solid fa-calculator',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'manual_cogs_deviation_catcost' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'manual_cogs_deviation_catcost_view',   NOW(), NOW()),
    (@menu_id, 'create', 'manual_cogs_deviation_catcost_create', NOW(), NOW()),
    (@menu_id, 'update', 'manual_cogs_deviation_catcost_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'manual_cogs_deviation_catcost_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;
