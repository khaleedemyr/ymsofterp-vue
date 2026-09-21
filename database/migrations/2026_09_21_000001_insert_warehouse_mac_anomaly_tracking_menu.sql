-- =====================================================
-- INSERT MENU DAN PERMISSION: WAREHOUSE MAC ANOMALY TRACKING
-- Created: 2026-09-21
-- Description: Scan anomali MAC warehouse (HO/MK), mirror outlet mac-anomaly-tracking
-- Route: /warehouse-mac-anomaly-tracking | Icon: fa-solid fa-triangle-exclamation
-- Parent: Cost Control (parent_id = 66)
-- =====================================================

INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Warehouse MAC Anomaly',
    'warehouse_mac_anomaly_tracking',
    66,
    '/warehouse-mac-anomaly-tracking',
    'fa-solid fa-triangle-exclamation',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Warehouse MAC Anomaly',
    `parent_id` = 66,
    `route` = '/warehouse-mac-anomaly-tracking',
    `icon` = 'fa-solid fa-triangle-exclamation',
    `updated_at` = NOW();

SET @menu_id = (SELECT id FROM `erp_menu` WHERE `code` = 'warehouse_mac_anomaly_tracking' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'warehouse_mac_anomaly_tracking_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- Opsional: beri akses ke role yang sudah punya outlet mac anomaly / warehouse mac tracking
-- INSERT INTO erp_role_permission (role_id, permission_id)
-- SELECT rp.role_id, p2.id
-- FROM erp_permission p1
-- JOIN erp_role_permission rp ON rp.permission_id = p1.id
-- JOIN erp_permission p2 ON p2.code = 'warehouse_mac_anomaly_tracking_view'
-- WHERE p1.code IN ('mac_anomaly_tracking_view', 'warehouse_mac_tracking_view', 'outlet_mac_tracking_view')
-- ON DUPLICATE KEY UPDATE permission_id = permission_id;
