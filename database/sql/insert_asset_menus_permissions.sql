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
(@pr_assets_menu_id, 'update',   'pr_assets_edit',   NOW(), NOW()),
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

-- 3. Asset Inventory Transfer menu
INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'Asset Inventory Transfer',
    'asset_inventory_transfer',
    251,
    '/asset-inventory-transfers',
    'fa-solid fa-right-left',
    NOW(), NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @ait_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'asset_inventory_transfer' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@ait_menu_id, 'view',    'asset_inventory_transfer_view',    NOW(), NOW()),
(@ait_menu_id, 'create',  'asset_inventory_transfer_create',  NOW(), NOW()),
(@ait_menu_id, 'update',    'asset_inventory_transfer_edit',    NOW(), NOW()),
(@ait_menu_id, 'delete',  'asset_inventory_transfer_delete',  NOW(), NOW()),
(@ait_menu_id, 'approve', 'asset_inventory_transfer_approve', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- 4. Asset Stock Adjustment menu
INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'Asset Stock Adjustment',
    'asset_stock_adjustment',
    251,
    '/asset-inventory-adjustments',
    'fa-solid fa-sliders',
    NOW(), NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @asa_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'asset_stock_adjustment' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@asa_menu_id, 'view',    'asset_stock_adjustment_view',    NOW(), NOW()),
(@asa_menu_id, 'create',  'asset_stock_adjustment_create',  NOW(), NOW()),
(@asa_menu_id, 'update',  'asset_stock_adjustment_edit',    NOW(), NOW()),
(@asa_menu_id, 'delete',  'asset_stock_adjustment_delete',  NOW(), NOW()),
(@asa_menu_id, 'approve', 'asset_stock_adjustment_approve', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- 5. Asset Service menu
INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'Asset Service',
    'asset_service_order',
    251,
    '/asset-service-orders',
    'fa-solid fa-screwdriver-wrench',
    NOW(), NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @asv_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'asset_service_order' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@asv_menu_id, 'view',    'asset_service_order_view',    NOW(), NOW()),
(@asv_menu_id, 'create',  'asset_service_order_create',  NOW(), NOW()),
(@asv_menu_id, 'update',  'asset_service_order_edit',    NOW(), NOW()),
(@asv_menu_id, 'delete',  'asset_service_order_delete',  NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Asset Disposal Menu
INSERT INTO `erp_menu` (`code`, `name`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'asset_disposal',
    'Asset Disposal',
    251,
    '/asset-disposals',
    'fa-solid fa-dumpster',
    NOW(), NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @adp_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'asset_disposal' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@adp_menu_id, 'view',    'asset_disposal_view',    NOW(), NOW()),
(@adp_menu_id, 'create',  'asset_disposal_create',  NOW(), NOW()),
(@adp_menu_id, 'update',  'asset_disposal_edit',    NOW(), NOW()),
(@adp_menu_id, 'delete',  'asset_disposal_delete',  NOW(), NOW()),
(@adp_menu_id, 'approve', 'asset_disposal_approve', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Asset Inventory Report Menu
INSERT INTO `erp_menu` (`code`, `name`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'asset_inventory_report',
    'Asset Inventory Report',
    251,
    '/asset-inventory-report/stock-position',
    'fa-solid fa-chart-line',
    NOW(), NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @air_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'asset_inventory_report' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@air_menu_id, 'view', 'asset_inventory_report_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Saldo Awal Stock Asset Menu
INSERT INTO `erp_menu` (`code`, `name`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'asset_stock_balance',
    'Saldo Awal Stock Asset',
    251,
    '/asset-stock-balances',
    'fa-solid fa-scale-balanced',
    NOW(), NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @asb_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'asset_stock_balance' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@asb_menu_id, 'view',   'asset_stock_balance_view',   NOW(), NOW()),
(@asb_menu_id, 'create', 'asset_stock_balance_create', NOW(), NOW()),
(@asb_menu_id, 'edit',   'asset_stock_balance_edit',   NOW(), NOW()),
(@asb_menu_id, 'delete', 'asset_stock_balance_delete', NOW(), NOW()),
(@asb_menu_id, 'import', 'asset_stock_balance_import', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

COMMIT;
