-- Tracking Nomor Seri — erp_menu + erp_permission
-- Jalankan sekali di MySQL, lalu assign permission ke role yang perlu.

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
    'Tracking Nomor Seri',
    'serial_tracking',
    106,
    '/serial-tracking',
    'fa-solid fa-barcode',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'serial_tracking' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'serial_tracking_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;

-- Contoh assign ke role:
-- INSERT IGNORE INTO role_permissions (role_id, permission_id)
-- SELECT :role_id, id FROM erp_permission WHERE code = 'serial_tracking_view';
