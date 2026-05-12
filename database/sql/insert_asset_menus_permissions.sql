-- Asset menus & permissions — parent_id = 251 (Asset Management)
-- Jalankan sekali di MySQL (paste seluruh blok).

START TRANSACTION;

-- 1. PR Assets menu
INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'PR Assets',
    'pr_assets',
    251,
    '/purchase-requisitions/create?mode=pr_assets',
    'fa-solid fa-boxes-stacked',
    NOW(), NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @pr_assets_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'pr_assets' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@pr_assets_menu_id, 'view',   'pr_assets_view',   NOW(), NOW()),
(@pr_assets_menu_id, 'create', 'pr_assets_create', NOW(), NOW()),
(@pr_assets_menu_id, 'edit',   'pr_assets_edit',   NOW(), NOW()),
(@pr_assets_menu_id, 'delete', 'pr_assets_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- 2. Asset Good Receive menu
INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'Asset Good Receive',
    'asset_good_receive',
    251,
    '/asset-good-receives',
    'fa-solid fa-truck-ramp-box',
    NOW(), NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @agr_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'asset_good_receive' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@agr_menu_id, 'view',   'asset_good_receive_view',   NOW(), NOW()),
(@agr_menu_id, 'create', 'asset_good_receive_create', NOW(), NOW()),
(@agr_menu_id, 'edit',   'asset_good_receive_edit',   NOW(), NOW()),
(@agr_menu_id, 'delete', 'asset_good_receive_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

COMMIT;
