-- Menu: Pengajuan WFH — parent Human Resources (parent_id = 106)
-- Route: /wfh-requests
-- Eksekusi sekali di MySQL. Aman diulang (ON DUPLICATE KEY UPDATE).

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
    'Pengajuan WFH',
    'wfh_request',
    106,
    '/wfh-requests',
    'fa-solid fa-house-laptop',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'wfh_request' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'wfh_request_view',   NOW(), NOW()),
    (@menu_id, 'create', 'wfh_request_create', NOW(), NOW()),
    (@menu_id, 'update', 'wfh_request_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'wfh_request_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;

-- Opsional: assign ke role admin (role_id = 1)
-- INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
-- SELECT 1, id FROM erp_permission WHERE code LIKE 'wfh_request_%';
