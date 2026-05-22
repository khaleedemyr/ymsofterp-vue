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

-- Penting: UNIQUE biasanya (menu_id, action) — UPDATE harus ikut perbaiki `code`
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES
    (@menu_id, 'view', 'wa_broadcast_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `code` = VALUES(`code`),
    `updated_at` = NOW();

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES
    (@menu_id, 'create', 'wa_broadcast_send', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `code` = VALUES(`code`),
    `updated_at` = NOW();

-- Perbaiki data lama yang salah pakai code = 'wa_broadcast' (harus wa_broadcast_view)
UPDATE `erp_permission` p
INNER JOIN `erp_menu` m ON m.id = p.menu_id AND m.`code` = 'wa_broadcast'
SET p.`code` = 'wa_broadcast_view', p.`updated_at` = NOW()
WHERE p.`action` = 'view' AND p.`code` = 'wa_broadcast';

COMMIT;

-- Role yang sudah punya inbox / IG comments otomatis dapat view (sidebar)
-- Tabel erp_role_permission hanya: role_id, permission_id (tanpa timestamp)
INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
SELECT rp.role_id, p_new.id
FROM `erp_role_permission` rp
INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id
    AND p_old.`code` IN ('omnichannel_inbox_view', 'instagram_comments_view')
INNER JOIN `erp_permission` p_new ON p_new.`code` = 'wa_broadcast_view';

-- Verifikasi: harus return 1 baris dengan menu_code = wa_broadcast, action = view
-- SELECT m.code, p.action, p.code FROM erp_permission p JOIN erp_menu m ON m.id = p.menu_id WHERE m.code = 'wa_broadcast';
