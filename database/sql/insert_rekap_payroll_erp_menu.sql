-- Rekap Payroll — erp_menu + erp_permission (parent HO Finance = 5)
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
    'Rekap Payroll',
    'rekap_payroll',
    5,
    '/payroll/rekap',
    'fa-solid fa-table-list',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'rekap_payroll' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'rekap_payroll_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;

-- Hubungkan permission ke role ERP Anda, contoh:
-- INSERT IGNORE INTO role_permissions (role_id, permission_id)
-- SELECT :role_id, id FROM erp_permission WHERE code = 'rekap_payroll_view';

