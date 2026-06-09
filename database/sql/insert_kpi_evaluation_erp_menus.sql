-- =====================================================
-- KPI Evaluation — erp_menu + erp_permission
-- parent_id = 106 (Human Resources)
-- =====================================================

START TRANSACTION;

INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'KPI Evaluation',
    'kpi_evaluations',
    106,
    '/kpi-evaluations',
    'fa-solid fa-clipboard-check',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'kpi_evaluations' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view',   'kpi_evaluations_view',   NOW(), NOW()),
(@menu_id, 'create', 'kpi_evaluations_create', NOW(), NOW()),
(@menu_id, 'update', 'kpi_evaluations_edit',   NOW(), NOW()),
(@menu_id, 'delete', 'kpi_evaluations_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

COMMIT;

-- INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
-- SELECT 1, id FROM erp_permission WHERE code LIKE 'kpi_evaluations_%';
