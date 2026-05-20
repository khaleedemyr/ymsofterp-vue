-- =====================================================
-- Sekali eksekusi: menu + permission Inbox Omnichannel (CRM)
-- parent_id = 138
-- =====================================================

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
    'Inbox Omnichannel',
    'omnichannel_inbox',
    138,
    '/crm/omnichannel-inbox',
    'fa-solid fa-inbox',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (
    SELECT `id` FROM `erp_menu` WHERE `code` = 'omnichannel_inbox' LIMIT 1
);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'omnichannel_inbox_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;

-- Opsional: beri akses ke semua role yang sudah punya Customer Voice Command Center
-- INSERT INTO `erp_role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
-- SELECT rp.role_id, p_new.id, NOW(), NOW()
-- FROM `erp_role_permission` rp
-- INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id AND p_old.`code` = 'customer_voice_command_center_view'
-- INNER JOIN `erp_permission` p_new ON p_new.`code` = 'omnichannel_inbox_view'
-- ON DUPLICATE KEY UPDATE `erp_role_permission`.`updated_at` = NOW();
