-- Menu: Manual Monthly Labor Cost — parent HO Finance (parent_id = 5)
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
    'Manual Monthly Labor Cost',
    'manual_monthly_labor_cost',
    5,
    '/manual-monthly-labor-cost',
    'fa-solid fa-user-clock',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'manual_monthly_labor_cost' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'manual_monthly_labor_cost_view',   NOW(), NOW()),
    (@menu_id, 'create', 'manual_monthly_labor_cost_create', NOW(), NOW()),
    (@menu_id, 'update', 'manual_monthly_labor_cost_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'manual_monthly_labor_cost_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;
