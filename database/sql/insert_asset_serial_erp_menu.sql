-- Asset Serial / RFID — insert menu & permission (sekali eksekusi)
-- parent_id = 251 (Asset Management)

INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Asset Serial / RFID',
    'asset_serial',
    251,
    '/asset-serials',
    'fa-solid fa-nfc-symbol',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @asset_serial_menu_id := (
    SELECT `id` FROM `erp_menu` WHERE `code` = 'asset_serial' LIMIT 1
);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@asset_serial_menu_id, 'view',   'asset_serial_view',   NOW(), NOW()),
    (@asset_serial_menu_id, 'create', 'asset_serial_create', NOW(), NOW()),
    (@asset_serial_menu_id, 'update', 'asset_serial_edit',   NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();
