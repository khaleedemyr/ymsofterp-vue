-- =====================================================
-- INSERT MENU DAN PERMISSION UNTUK OUTLET MAC TRACKING
-- Created: 2026-07-20
-- Description: Insert menu "Outlet MAC Tracking" ke group Cost Control (parent_id=66)
-- Route: /outlet-mac-tracking | Icon: fa-solid fa-clock-rotate-left
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
    'Outlet MAC Tracking',
    'outlet_mac_tracking',
    66, -- parent_id = 66 (Cost Control)
    '/outlet-mac-tracking',
    'fa-solid fa-clock-rotate-left',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Outlet MAC Tracking',
    `parent_id` = 66,
    `route` = '/outlet-mac-tracking',
    `icon` = 'fa-solid fa-clock-rotate-left',
    `updated_at` = NOW();

SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'outlet_mac_tracking' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'outlet_mac_tracking_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- =====================================================
-- Catatan:
-- 1. Menu muncul di sidebar group "Cost Control" (parent_id = 66)
-- 2. Permission code: outlet_mac_tracking_view (action: view)
-- 3. Route: /outlet-mac-tracking
-- 4. Untuk beri akses ke role:
--    INSERT INTO erp_role_permission (role_id, permission_id)
--    SELECT YOUR_ROLE_ID, id FROM erp_permission WHERE code = 'outlet_mac_tracking_view';
-- =====================================================
