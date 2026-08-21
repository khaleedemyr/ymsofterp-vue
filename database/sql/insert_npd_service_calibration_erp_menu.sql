-- Menu: NPD Service Calibration — parent Ops Management (parent_id = 184)
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
    'NPD Service Calibration',
    'npd_service_calibration',
    184,
    '/npd-service-calibration',
    'fa-solid fa-concierge-bell',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'npd_service_calibration' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'npd_service_calibration_view',   NOW(), NOW()),
    (@menu_id, 'create', 'npd_service_calibration_create', NOW(), NOW()),
    (@menu_id, 'update', 'npd_service_calibration_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'npd_service_calibration_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;
