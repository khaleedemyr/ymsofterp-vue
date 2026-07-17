-- Menu: Input Complaint CS Manual — parent CRM (parent_id = 138)
-- Route: /manual-cs-complaints
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
    'Input Complaint CS',
    'manual_cs_complaint',
    138,
    '/manual-cs-complaints',
    'fa-solid fa-headset',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'manual_cs_complaint' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'manual_cs_complaint_view',   NOW(), NOW()),
    (@menu_id, 'create', 'manual_cs_complaint_create', NOW(), NOW()),
    (@menu_id, 'update', 'manual_cs_complaint_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'manual_cs_complaint_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;
