-- =====================================================
-- KPI Master — erp_menu + erp_permission
-- parent_id = 106 (Human Resources)
-- Jalankan sekali (copy-paste seluruh blok)
-- =====================================================

START TRANSACTION;

-- ── 1. KPI Parameter ─────────────────────────────────
INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'KPI Parameter',
    'kpi_parameters',
    106,
    '/kpi-parameters',
    'fa-solid fa-sliders',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name`       = VALUES(`name`),
    `parent_id`  = VALUES(`parent_id`),
    `route`      = VALUES(`route`),
    `icon`       = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_kpi_parameters := (SELECT `id` FROM `erp_menu` WHERE `code` = 'kpi_parameters' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_kpi_parameters, 'view',   'kpi_parameters_view',   NOW(), NOW()),
(@menu_kpi_parameters, 'create', 'kpi_parameters_create', NOW(), NOW()),
(@menu_kpi_parameters, 'update', 'kpi_parameters_edit',   NOW(), NOW()),
(@menu_kpi_parameters, 'delete', 'kpi_parameters_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id`    = VALUES(`menu_id`),
    `action`     = VALUES(`action`),
    `updated_at` = NOW();

-- ── 2. KPI Key Strategy ──────────────────────────────
INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'KPI Key Strategy',
    'kpi_key_strategies',
    106,
    '/kpi-key-strategies',
    'fa-solid fa-layer-group',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name`       = VALUES(`name`),
    `parent_id`  = VALUES(`parent_id`),
    `route`      = VALUES(`route`),
    `icon`       = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_kpi_key_strategies := (SELECT `id` FROM `erp_menu` WHERE `code` = 'kpi_key_strategies' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_kpi_key_strategies, 'view',   'kpi_key_strategies_view',   NOW(), NOW()),
(@menu_kpi_key_strategies, 'create', 'kpi_key_strategies_create', NOW(), NOW()),
(@menu_kpi_key_strategies, 'update', 'kpi_key_strategies_edit',   NOW(), NOW()),
(@menu_kpi_key_strategies, 'delete', 'kpi_key_strategies_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id`    = VALUES(`menu_id`),
    `action`     = VALUES(`action`),
    `updated_at` = NOW();

-- ── 3. KPI Template ────────────────────────────────────
INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'KPI Template',
    'kpi_templates',
    106,
    '/kpi-templates',
    'fa-solid fa-bullseye',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name`       = VALUES(`name`),
    `parent_id`  = VALUES(`parent_id`),
    `route`      = VALUES(`route`),
    `icon`       = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_kpi_templates := (SELECT `id` FROM `erp_menu` WHERE `code` = 'kpi_templates' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_kpi_templates, 'view',   'kpi_templates_view',   NOW(), NOW()),
(@menu_kpi_templates, 'create', 'kpi_templates_create', NOW(), NOW()),
(@menu_kpi_templates, 'update', 'kpi_templates_edit',   NOW(), NOW()),
(@menu_kpi_templates, 'delete', 'kpi_templates_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id`    = VALUES(`menu_id`),
    `action`     = VALUES(`action`),
    `updated_at` = NOW();

COMMIT;

-- ── Assign permission ke role (ganti 1 dengan role_id Anda) ──
-- INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
-- SELECT 1, `id` FROM `erp_permission` WHERE `code` LIKE 'kpi_%';
