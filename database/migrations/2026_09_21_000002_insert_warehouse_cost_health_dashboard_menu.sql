-- =====================================================
-- INSERT MENU DAN PERMISSION: WAREHOUSE COST HEALTH DASHBOARD
-- Created: 2026-09-21
-- Description: Dashboard kesehatan cost gudang (MAC / orphan value) + shortcut ops
-- Route: /warehouse-cost-health-dashboard | Icon: fa-solid fa-heart-pulse
-- Parent: Warehouse Management (parent_id = 6)
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
    'Warehouse Dashboard',
    'warehouse_cost_health_dashboard',
    6,
    '/warehouse-cost-health-dashboard',
    'fa-solid fa-heart-pulse',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Warehouse Dashboard',
    `parent_id` = 6,
    `route` = '/warehouse-cost-health-dashboard',
    `icon` = 'fa-solid fa-heart-pulse',
    `updated_at` = NOW();

SET @menu_id = (SELECT id FROM `erp_menu` WHERE `code` = 'warehouse_cost_health_dashboard' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'warehouse_cost_health_dashboard_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- Beri akses ke role yang sudah punya warehouse MAC anomaly / tracking
INSERT INTO erp_role_permission (role_id, permission_id)
SELECT DISTINCT rp.role_id, p2.id
FROM erp_permission p1
JOIN erp_role_permission rp ON rp.permission_id = p1.id
JOIN erp_permission p2 ON p2.code = 'warehouse_cost_health_dashboard_view'
WHERE p1.code IN (
    'warehouse_mac_anomaly_tracking_view',
    'warehouse_mac_tracking_view',
    'mac_anomaly_tracking_view',
    'food_good_receive_view'
)
AND NOT EXISTS (
    SELECT 1 FROM erp_role_permission x
    WHERE x.role_id = rp.role_id AND x.permission_id = p2.id
);
