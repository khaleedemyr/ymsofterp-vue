-- =====================================================
-- Menu + permission: Laporan Void Bill POS
-- Parent: Outlet Management (parent_id = 4)
-- Route: /pos-void-bill-report
-- Jalankan sekali di DB ymsofterp; aman diulang (ON DUPLICATE KEY UPDATE).
-- Setelah itu, grant ke role: INSERT INTO erp_role_permission (...) SELECT ...
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
    'Laporan Void Bill POS',
    'pos_void_bill_report',
    4,
    '/pos-void-bill-report',
    'fa-solid fa-file-circle-xmark',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'pos_void_bill_report' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'pos_void_bill_report_view',
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
-- WHERE p.`code` = 'pos_void_bill_report_view'
-- ON DUPLICATE KEY UPDATE `updated_at` = NOW();
