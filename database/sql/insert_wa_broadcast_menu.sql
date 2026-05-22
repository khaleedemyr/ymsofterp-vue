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
    (@menu_id, 'view', 'wa_broadcast_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES
    (@menu_id, 'create', 'wa_broadcast_send', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;

-- Role yang sudah punya inbox omnichannel otomatis dapat menu broadcast (view)
INSERT INTO `erp_role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT rp.role_id, p_new.id, NOW(), NOW()
FROM `erp_role_permission` rp
INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id AND p_old.`code` = 'omnichannel_inbox_view'
INNER JOIN `erp_permission` p_new ON p_new.`code` = 'wa_broadcast_view'
ON DUPLICATE KEY UPDATE `erp_role_permission`.`updated_at` = NOW();
