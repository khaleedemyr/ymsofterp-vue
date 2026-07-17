-- Menu: Pengajuan Lembur — parent Human Resources (parent_id = 106)
-- Route: /overtime-submissions
-- Eksekusi sekali di MySQL (paste semua query sekaligus). Aman diulang (ON DUPLICATE KEY UPDATE).

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
    'Pengajuan Lembur',
    'overtime_submission',
    106,
    '/overtime-submissions',
    'fa-solid fa-business-time',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'overtime_submission' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'overtime_submission_view',   NOW(), NOW()),
    (@menu_id, 'create', 'overtime_submission_create', NOW(), NOW()),
    (@menu_id, 'update', 'overtime_submission_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'overtime_submission_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;

-- Opsional: assign ke role admin (role_id = 1)
-- INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
-- SELECT 1, id FROM erp_permission WHERE code LIKE 'overtime_submission_%';

-- Verifikasi:
-- SELECT m.*, p.action, p.code AS permission_code
-- FROM erp_menu m
-- LEFT JOIN erp_permission p ON p.menu_id = m.id
-- WHERE m.code = 'overtime_submission';
