-- Menu Broadcast WhatsApp — pola SAMA insert_instagram_comments_menu.sql
-- CRM parent_id = 138 | permission view = wa_broadcast_view | sidebar filter by erp_menu.code

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

-- Hanya view dulu (sama instagram_comments — ini yang dipakai sidebar)
INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'wa_broadcast_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `code` = VALUES(`code`),
    `updated_at` = NOW();

COMMIT;

-- Permission create (opsional, untuk kirim broadcast — tidak dipakai sidebar)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'create', 'wa_broadcast_send', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `menu_id` = @menu_id AND `action` = 'create'
);

-- Perbaiki data lama: code view harus wa_broadcast_view (bukan wa_broadcast)
UPDATE `erp_permission` p
INNER JOIN `erp_menu` m ON m.id = p.menu_id AND m.`code` = 'wa_broadcast'
SET p.`code` = 'wa_broadcast_view', p.`updated_at` = NOW()
WHERE p.`action` = 'view' AND p.`code` = 'wa_broadcast';

-- Role sync (erp_role_permission hanya role_id + permission_id)
INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
SELECT rp.role_id, p_new.id
FROM `erp_role_permission` rp
INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id AND p_old.`code` = 'omnichannel_inbox_view'
INNER JOIN `erp_permission` p_new ON p_new.`code` = 'wa_broadcast_view';
