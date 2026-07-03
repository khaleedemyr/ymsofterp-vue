-- Menu: Asset Manual Monthly Lost & Breakage — parent Asset Management (parent_id = 251)
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
    'Asset Manual Monthly Lost & Breakage',
    'asset_manual_monthly_lost_breakage',
    251,
    '/asset-manual-monthly-lost-breakage',
    'fa-solid fa-file-pen',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'asset_manual_monthly_lost_breakage' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'asset_manual_monthly_lost_breakage_view',   NOW(), NOW()),
    (@menu_id, 'create', 'asset_manual_monthly_lost_breakage_create', NOW(), NOW()),
    (@menu_id, 'update', 'asset_manual_monthly_lost_breakage_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'asset_manual_monthly_lost_breakage_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;
