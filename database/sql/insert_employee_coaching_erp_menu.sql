-- Menu: Employee Coaching — parent Ops Management (parent_id = 184)
-- Eksekusi sekali di MySQL (paste semua query sekaligus).

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
    'Employee Coaching',
    'employee_coaching',
    184,
    '/employee-coaching',
    'fa-solid fa-user-graduate',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'employee_coaching' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'employee_coaching_view',   NOW(), NOW()),
    (@menu_id, 'create', 'employee_coaching_create', NOW(), NOW()),
    (@menu_id, 'update', 'employee_coaching_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'employee_coaching_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;

-- Opsional: assign ke role admin (role_id = 1)
-- INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
-- SELECT 1, id FROM erp_permission WHERE code LIKE 'employee_coaching_%';
