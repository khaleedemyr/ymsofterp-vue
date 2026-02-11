-- =====================================================
-- INSERT MENU DAN PERMISSION UNTUK REPORT REKAP DISKON
-- Created: 2026-02-11
-- Description: Insert menu "Report Rekap Diskon" ke group Cost Control (parent_id=66)
-- Sumber data: order_promos (status=active) + orders + promos
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
    'Report Rekap Diskon',
    'report_rekap_diskon',
    66, -- parent_id = 66 (Cost Control)
    '/report-rekap-diskon',
    'fa-solid fa-tags',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Report Rekap Diskon',
    `parent_id` = 66,
    `route` = '/report-rekap-diskon',
    `icon` = 'fa-solid fa-tags',
    `updated_at` = NOW();

-- =====================================================
-- 2. INSERT PERMISSION KE TABLE erp_permission
-- =====================================================
SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'report_rekap_diskon' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'report_rekap_diskon_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- =====================================================
-- SELESAI
-- =====================================================
-- Catatan:
-- 1. Menu muncul di sidebar group "Cost Control" (parent_id = 66)
-- 2. Permission code: report_rekap_diskon_view (action: view)
-- 3. Route: /report-rekap-diskon
-- 4. Untuk beri akses ke role: INSERT INTO erp_role_permission (role_id, permission_id)
--    SELECT YOUR_ROLE_ID, id FROM erp_permission WHERE code = 'report_rekap_diskon_view';
-- =====================================================
