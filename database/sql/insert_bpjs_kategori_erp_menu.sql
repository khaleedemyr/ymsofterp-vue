-- Master Kategori BPJS — erp_menu + erp_permission (parent_id = 106)
-- Jalankan sekali di MySQL, lalu assign permission ke role (erp_role_permission).

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
    'Kategori BPJS',
    'bpjs_kategori',
    106,
    '/bpjs-kategori',
    'fa-solid fa-percent',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'bpjs_kategori' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'bpjs_kategori_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;

-- Assign ke role (ganti :role_id):
-- INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
-- SELECT :role_id, id FROM erp_permission WHERE code = 'bpjs_kategori_view';
