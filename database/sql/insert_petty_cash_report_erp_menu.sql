-- =====================================================
-- Petty Cash Report — erp_menu + erp_permission
-- Parent Outlet Report: parent_id = 111
-- Route: /report-petty-cash
-- Satu eksekusi (START TRANSACTION … COMMIT). Aman diulang (ON DUPLICATE KEY UPDATE).
-- =====================================================

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
    'Petty Cash Report',
    'petty_cash_report',
    111,
    '/report-petty-cash',
    'fa-solid fa-wallet',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'petty_cash_report' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'petty_cash_report_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;

-- Contoh grant ke role (ganti ROLE_ID):
-- INSERT INTO `erp_role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
-- SELECT 1, p.id, NOW(), NOW()
-- FROM `erp_permission` p
-- WHERE p.`code` = 'petty_cash_report_view'
-- ON DUPLICATE KEY UPDATE `updated_at` = NOW();
