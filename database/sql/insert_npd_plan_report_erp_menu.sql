-- Menu: New Product Development Plan & Report — parent Ops Management (parent_id = 184)
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
    'NPD Plan & Report',
    'npd_plan_report',
    184,
    '/npd-plan-report',
    'fa-solid fa-lightbulb',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'npd_plan_report' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'npd_plan_report_view',   NOW(), NOW()),
    (@menu_id, 'create', 'npd_plan_report_create', NOW(), NOW()),
    (@menu_id, 'update', 'npd_plan_report_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'npd_plan_report_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;
