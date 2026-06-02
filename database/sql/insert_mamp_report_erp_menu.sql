-- Report MAMP — erp_menu + erp_permission (parent HO Finance = 5)
-- Jalankan sekali di MySQL (paste seluruh blok).

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
    'Report MAMP',
    'mamp_report',
    5,
    '/mamp-report',
    'fa-solid fa-table-list',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'mamp_report' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'mamp_report_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;

-- Hubungkan permission ke role ERP Anda, contoh:
-- INSERT IGNORE INTO role_permissions (role_id, permission_id)
-- SELECT :role_id, id FROM erp_permission WHERE code = 'mamp_report_view';

-- Verifikasi:
-- SELECT m.*, p.action, p.code AS permission_code
-- FROM erp_menu m
-- LEFT JOIN erp_permission p ON p.menu_id = m.id
-- WHERE m.code = 'mamp_report';
