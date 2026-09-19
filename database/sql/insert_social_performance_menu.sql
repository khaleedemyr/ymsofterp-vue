-- =====================================================
-- Menu + permission Social Performance Dashboard (CRM)
-- parent_id = 138 (Customer Voice / CRM)
-- Jalankan sekali di DB production/staging (tanpa php artisan migrate)
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
    'Social Performance',
    'social_performance',
    138,
    '/crm/social-performance',
    'fa-solid fa-chart-column',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (
    SELECT `id` FROM `erp_menu` WHERE `code` = 'social_performance' LIMIT 1
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
    'social_performance_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;

INSERT INTO `erp_role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT rp.role_id, p_new.id, NOW(), NOW()
FROM `erp_role_permission` rp
INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id AND p_old.`code` = 'omnichannel_inbox_view'
INNER JOIN `erp_permission` p_new ON p_new.`code` = 'social_performance_view'
ON DUPLICATE KEY UPDATE `erp_role_permission`.`updated_at` = NOW();

-- Cek hasil (opsional):
-- SELECT m.id, m.code, m.route, p.code AS permission_code
-- FROM erp_menu m
-- LEFT JOIN erp_permission p ON p.menu_id = m.id
-- WHERE m.code = 'social_performance';
