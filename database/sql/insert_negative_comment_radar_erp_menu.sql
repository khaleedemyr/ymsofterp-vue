-- =====================================================
-- Negative Comment Radar — erp_menu + erp_permission
-- parent_id = 343 (Just Academy)
-- Eksekusi sekali di MySQL (boleh dipaste sekaligus).
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
    'Negative Comment Radar',
    'just_academy_negative_comment_radar',
    343,
    '/just-academy/negative-comment-radar',
    'fa-solid fa-satellite-dish',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

-- Jika instalasi lama tanpa unique di erp_menu.code, pastikan parent/route benar:
UPDATE `erp_menu`
SET `name` = 'Negative Comment Radar',
    `parent_id` = 343,
    `route` = '/just-academy/negative-comment-radar',
    `icon` = 'fa-solid fa-satellite-dish',
    `updated_at` = NOW()
WHERE `code` = 'just_academy_negative_comment_radar';

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'just_academy_negative_comment_radar' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'view', 'just_academy_negative_comment_radar_view', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'just_academy_negative_comment_radar_view' LIMIT 1
);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'create', 'just_academy_negative_comment_radar_create', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'just_academy_negative_comment_radar_create' LIMIT 1
);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'update', 'just_academy_negative_comment_radar_edit', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'just_academy_negative_comment_radar_edit' LIMIT 1
);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'delete', 'just_academy_negative_comment_radar_delete', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'just_academy_negative_comment_radar_delete' LIMIT 1
);

COMMIT;

-- Grant ke role yang sudah punya Just Academy dashboard (sesuaikan bila perlu):
-- INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
-- SELECT rp.role_id, p_new.id
-- FROM `erp_role_permission` rp
-- JOIN `erp_permission` p_old ON p_old.id = rp.permission_id AND p_old.code = 'just_academy_dashboard_view'
-- JOIN `erp_permission` p_new ON p_new.code LIKE 'just_academy_negative_comment_radar_%';
