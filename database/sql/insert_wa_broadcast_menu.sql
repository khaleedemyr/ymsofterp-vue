-- Menu + permission Broadcast WhatsApp (CRM, parent omnichannel / CRM)
START TRANSACTION;

INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'Broadcast WhatsApp',
    'wa_broadcast',
    138,
    '/crm/wa-broadcast',
    'fa-brands fa-whatsapp',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'wa_broadcast' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES
    (@menu_id, 'view', 'wa_broadcast_view', NOW(), NOW()),
    (@menu_id, 'create', 'wa_broadcast_send', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

COMMIT;
