-- Laporan Payroll — erp_menu + erp_permission (parent HO Finance = 5)
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
    'Laporan Payroll',
    'payroll_finance_report',
    5,
    '/payroll/finance-report',
    'fa-solid fa-coins',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'payroll_finance_report' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'payroll_finance_report_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;

-- Hubungkan permission ke role ERP Anda, contoh:
-- INSERT IGNORE INTO role_permissions (role_id, permission_id)
-- SELECT :role_id, id FROM erp_permission WHERE code = 'payroll_finance_report_view';

-- Catatan: kolom action hanya view | create | update | delete.
-- Export Excel di halaman ini mengikuti permission view (sama seperti menu laporan lain).

-- Verifikasi:
-- SELECT m.*, p.action, p.code AS permission_code
-- FROM erp_menu m
-- LEFT JOIN erp_permission p ON p.menu_id = m.id
-- WHERE m.code = 'payroll_finance_report';
