-- =====================================================
-- Just Academy — menu Rekap Kehadiran
-- Jalankan sekali di MySQL (staging/production)
-- Aman diulang (ON DUPLICATE KEY UPDATE)
-- =====================================================

START TRANSACTION;

SET @ja_parent_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'just_academy' LIMIT 1);

INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Rekap Kehadiran', 'just_academy_attendance_recap', @ja_parent_id, '/just-academy/attendance-recap', 'fa-solid fa-clipboard-user', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'just_academy_attendance_recap' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'just_academy_attendance_recap_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE `menu_id` = VALUES(`menu_id`), `updated_at` = NOW();

COMMIT;

-- Grant ke role (sesuaikan ROLE_ID):
-- INSERT INTO `erp_role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
-- SELECT 1, p.id, NOW(), NOW()
-- FROM `erp_permission` p
-- WHERE p.`code` = 'just_academy_attendance_recap_view'
-- ON DUPLICATE KEY UPDATE `updated_at` = NOW();
