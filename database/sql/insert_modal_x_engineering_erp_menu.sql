-- Menu: Laporan Modal x Engineering — parent Cost Control (parent_id = 66)
-- Eksekusi sekali di MySQL (paste semua query sekaligus).
-- Jika menu sudah ada dengan parent_id lama: UPDATE erp_menu SET parent_id = 66 WHERE code = 'modal_x_engineering';

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
    'Modal x Engineering',
    'modal_x_engineering',
    66,
    '/reports/modal-engineering',
    'fa-solid fa-chart-pie',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'modal_x_engineering' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view', 'modal_x_engineering_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;
