-- Kontrol Suhu Chiller & Freezer — erp_menu + erp_permission
-- parent_id = 4 (Outlet Management)
-- Eksekusi sekali di MySQL (boleh dipaste sekaligus).

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
    'Kontrol Suhu Chiller & Freezer',
    'chiller_temperature',
    4,
    '/chiller-temperature',
    'fa-solid fa-temperature-half',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'chiller_temperature' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'chiller_temperature_view',   NOW(), NOW()),
    (@menu_id, 'create', 'chiller_temperature_create', NOW(), NOW()),
    (@menu_id, 'update', 'chiller_temperature_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'chiller_temperature_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

COMMIT;
