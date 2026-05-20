-- =====================================================
-- Menu "Tim Inbox Omnichannel" + permission lihat semua chat
-- parent_id = 138 (CRM). Jalankan sekali.
-- =====================================================

START TRANSACTION;

SET @inbox_menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'omnichannel_inbox' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
)
SELECT
    @inbox_menu_id,
    'see_all',
    'omnichannel_inbox_see_all',
    NOW(),
    NOW()
FROM DUAL
WHERE @inbox_menu_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Tim Inbox Omnichannel',
    'omnichannel_teams',
    138,
    '/crm/omnichannel-teams',
    'fa-solid fa-people-group',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @teams_menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'omnichannel_teams' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @teams_menu_id,
    'view',
    'omnichannel_teams_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;
