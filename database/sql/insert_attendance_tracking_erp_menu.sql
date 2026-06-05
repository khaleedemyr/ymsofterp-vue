-- =====================================================
-- Tracking Absensi — erp_menu + erp_permission
-- Parent Human Resource: parent_id = 106
-- Route: /attendance-tracking
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
    'Tracking Absensi',
    'attendance_tracking',
    106,
    '/attendance-tracking',
    'fa-solid fa-chart-pie',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'attendance_tracking' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'attendance_tracking_view',
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
-- WHERE p.`code` = 'attendance_tracking_view'
-- ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Verifikasi:
-- SELECT m.*, p.action, p.code AS permission_code
-- FROM erp_menu m
-- LEFT JOIN erp_permission p ON p.menu_id = m.id
-- WHERE m.code = 'attendance_tracking';
