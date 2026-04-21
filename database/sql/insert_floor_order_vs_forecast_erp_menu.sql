-- Menu: RO Food Floor vs Forecast harian — parent Ops Management (parent_id = 184)
-- Eksekusi sekali di MySQL (paste semua query sekaligus).
-- Jika menu sudah ada dengan parent_id lama: UPDATE erp_menu SET parent_id = 184 WHERE code = 'floor_order_vs_forecast';

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
    'RO vs Forecast Harian',
    'floor_order_vs_forecast',
    184,
    '/reports/floor-order-vs-forecast',
    'fa-solid fa-scale-balanced',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'floor_order_vs_forecast' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view', 'floor_order_vs_forecast_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;
