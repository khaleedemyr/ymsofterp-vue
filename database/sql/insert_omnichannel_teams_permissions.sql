-- =====================================================
-- Menu "Tim Inbox Omnichannel" + permission kelola tim
-- parent_id = 138 (CRM). Jalankan sekali.
-- Catatan: siapa yang "lihat semua inbox" diatur manual di halaman tim
-- (tabel omni_inbox_full_access_users), bukan lewat permission ERP.
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
