-- =====================================================
-- INSERT MENU DAN PERMISSION UNTUK COST REPORT
-- Created: 2026-02-11
-- Description: Insert menu "Cost Report" ke group Cost Control (parent_id=66)
-- Route: /cost-report | Icon: fa-solid fa-coins
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
    'Cost Report',
    'cost_report',
    66, -- parent_id = 66 (Cost Control)
    '/cost-report',
    'fa-solid fa-coins',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Cost Report',
    `parent_id` = 66,
    `route` = '/cost-report',
    `icon` = 'fa-solid fa-coins',
    `updated_at` = NOW();

-- =====================================================
-- 2. INSERT PERMISSION KE TABLE erp_permission
-- =====================================================
SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'cost_report' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'cost_report_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- =====================================================
-- SELESAI
-- =====================================================
-- Catatan:
-- 1. Menu muncul di sidebar group "Cost Control" (parent_id = 66)
-- 2. Permission code: cost_report_view (action: view)
-- 3. Route: /cost-report
-- 4. Untuk beri akses ke role: INSERT INTO erp_role_permission (role_id, permission_id)
--    SELECT YOUR_ROLE_ID, id FROM erp_permission WHERE code = 'cost_report_view';
-- =====================================================
