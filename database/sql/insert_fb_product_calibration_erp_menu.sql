-- Menu: F&B Product Calibration — parent Ops Management (parent_id = 184)
-- Eksekusi sekali di MySQL (paste semua query sekaligus).

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
    'F&B Product Calibration',
    'fb_product_calibration',
    184,
    '/fb-product-calibration',
    'fa-solid fa-utensils',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'fb_product_calibration' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'fb_product_calibration_view',   NOW(), NOW()),
    (@menu_id, 'create', 'fb_product_calibration_create', NOW(), NOW()),
    (@menu_id, 'update', 'fb_product_calibration_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'fb_product_calibration_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;
