-- =====================================================
-- INSERT MENU + PERMISSION: Customer Voice Command Center
-- NOTE: Menu ini berdiri sendiri (tidak digabung ke menu Google Review/Guest Comment).
-- Parent menu default: CRM (parent_id = 138). Ubah jika diperlukan.
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
    'Customer Voice Command Center',
    'customer_voice_command_center',
    138,
    '/customer-voice-command-center',
    'fa-solid fa-headset',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (
    SELECT `id`
    FROM `erp_menu`
    WHERE `code` = 'customer_voice_command_center'
    LIMIT 1
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
    'customer_voice_command_center_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;

-- Optional: grant permission ke role tertentu
-- INSERT INTO `erp_role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
-- SELECT <ROLE_ID>, p.id, NOW(), NOW()
-- FROM `erp_permission` p
-- WHERE p.`code` = 'customer_voice_command_center_view'
-- ON DUPLICATE KEY UPDATE `updated_at` = NOW();
