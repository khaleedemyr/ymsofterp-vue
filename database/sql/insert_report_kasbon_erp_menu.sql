-- =====================================================
-- Report Kasbon — erp_menu + erp_permission
-- Parent Human Resource: parent_id = 106
-- Route: /report-kasbon
-- Satu eksekusi (START TRANSACTION … COMMIT). Aman diulang (ON DUPLICATE KEY UPDATE).
-- Setelah itu grant ke role: INSERT INTO erp_role_permission (...) SELECT …
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
    'Report Kasbon',
    'report_kasbon',
    106,
    '/report-kasbon',
    'fa-solid fa-money-bill-transfer',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'report_kasbon' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'report_kasbon_view',
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
-- WHERE p.`code` = 'report_kasbon_view'
-- ON DUPLICATE KEY UPDATE `updated_at` = NOW();
