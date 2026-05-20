-- Menu Otomasi Inbox + permission (parent CRM 138). Jalankan sekali.

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
    'Otomasi Inbox',
    'omnichannel_flows',
    138,
    '/crm/omnichannel-flows',
    'fa-solid fa-diagram-project',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @flows_menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'omnichannel_flows' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @flows_menu_id,
    'view',
    'omnichannel_flows_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;
