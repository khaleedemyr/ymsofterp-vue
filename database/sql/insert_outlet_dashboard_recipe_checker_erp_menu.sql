-- Dashboard Sales Outlet + Cek Resep BOM — erp_menu + erp_permission
-- Parent Outlet Management = 4 | Parent Cost Control = 66
-- Jalankan sekali di MySQL, lalu assign permission ke role yang perlu.

START TRANSACTION;

-- 1) Dashboard Sales Outlet (Outlet Management)
INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Dashboard Sales Outlet',
    'outlet_dashboard',
    4,
    '/outlet-dashboard',
    'fa-solid fa-store',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @outlet_dashboard_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'outlet_dashboard' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @outlet_dashboard_menu_id,
    'view',
    'outlet_dashboard_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

-- 2) Cek Resep BOM (Cost Control)
INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Cek Resep BOM',
    'recipe_checker',
    66,
    '/stock-cut/recipe-checker',
    'fa-solid fa-magnifying-glass',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @recipe_checker_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'recipe_checker' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @recipe_checker_menu_id,
    'view',
    'recipe_checker_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;

-- Contoh assign ke role (ganti ROLE_ID):
-- INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
-- SELECT :role_id, id FROM erp_permission WHERE code IN ('outlet_dashboard_view', 'recipe_checker_view');
