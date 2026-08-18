-- Employee Summary — parent Human Resource (parent_id = 106)
-- Route: /attendance-report/employee-summary
-- Aman diulang (ON DUPLICATE KEY UPDATE).
-- Role yang sudah punya Report Attendance otomatis dapat view Employee Summary.

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
    'Employee Summary',
    'attendance_employee_summary',
    106,
    '/attendance-report/employee-summary',
    'fa-solid fa-users',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'attendance_employee_summary' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'attendance_employee_summary_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

SET @perm_id := (
    SELECT `id` FROM `erp_permission`
    WHERE `code` = 'attendance_employee_summary_view'
    LIMIT 1
);

INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
SELECT rp.role_id, @perm_id
FROM `erp_role_permission` rp
INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id
WHERE p_old.code IN ('attendance_report_view', 'attendance_outlet_summary.view');

COMMIT;
