-- =====================================================
-- INSERT MENU DAN PERMISSION UNTUK WAREHOUSE MAC TRACKING
-- Created: 2026-03-02
-- Description: Insert menu "Warehouse MAC Tracking" ke group Cost Control (parent_id=66)
-- Route: /warehouse-mac-tracking | Icon: fa-solid fa-warehouse
-- =====================================================

-- =====================================================
-- 1. INSERT MENU KE TABLE erp_menu
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
    'Warehouse MAC Tracking',
    'warehouse_mac_tracking',
    66, -- parent_id = 66 (Cost Control)
    '/warehouse-mac-tracking',
    'fa-solid fa-warehouse',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Warehouse MAC Tracking',
    `parent_id` = 66,
    `route` = '/warehouse-mac-tracking',
    `icon` = 'fa-solid fa-warehouse',
    `updated_at` = NOW();

-- =====================================================
-- 2. INSERT PERMISSION KE TABLE erp_permission
-- =====================================================
SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'warehouse_mac_tracking' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'warehouse_mac_tracking_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- =====================================================
-- SELESAI
-- =====================================================
-- Catatan:
-- 1. Menu muncul di sidebar group "Cost Control" (parent_id = 66)
-- 2. Permission code: warehouse_mac_tracking_view (action: view)
-- 3. Route: /warehouse-mac-tracking
-- 4. Untuk beri akses ke role: INSERT INTO erp_role_permission (role_id, permission_id)
--    SELECT YOUR_ROLE_ID, id FROM erp_permission WHERE code = 'warehouse_mac_tracking_view';
-- =====================================================
