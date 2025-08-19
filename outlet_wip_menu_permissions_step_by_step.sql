-- =====================================================
-- STEP 1: Insert menu untuk Outlet WIP Production
-- =====================================================
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Outlet WIP Production', 'outlet_wip_production', 4, '/outlet-wip', 'fa-solid fa-industry', NOW(), NOW());

-- =====================================================
-- STEP 2: Insert menu untuk Laporan Outlet WIP
-- =====================================================
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Laporan Outlet WIP', 'outlet_wip_report', 4, '/outlet-wip/report', 'fa-solid fa-file-lines', NOW(), NOW());

-- =====================================================
-- STEP 3: Cek ID menu yang baru dibuat
-- =====================================================
-- Jalankan query ini untuk melihat ID menu yang baru dibuat:
-- SELECT id, name, code FROM erp_menu WHERE code IN ('outlet_wip_production', 'outlet_wip_report') ORDER BY id DESC LIMIT 2;

-- =====================================================
-- STEP 4: Insert permissions untuk Outlet WIP Production
-- =====================================================
-- Ganti [ID_OUTLET_WIP_PRODUCTION] dengan ID yang didapat dari STEP 3
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
([ID_OUTLET_WIP_PRODUCTION], 'view', 'outlet_wip_production_view', NOW(), NOW()),
([ID_OUTLET_WIP_PRODUCTION], 'create', 'outlet_wip_production_create', NOW(), NOW()),
([ID_OUTLET_WIP_PRODUCTION], 'update', 'outlet_wip_production_update', NOW(), NOW()),
([ID_OUTLET_WIP_PRODUCTION], 'delete', 'outlet_wip_production_delete', NOW(), NOW());

-- =====================================================
-- STEP 5: Insert permissions untuk Laporan Outlet WIP
-- =====================================================
-- Ganti [ID_OUTLET_WIP_REPORT] dengan ID yang didapat dari STEP 3
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
([ID_OUTLET_WIP_REPORT], 'view', 'outlet_wip_report_view', NOW(), NOW());

-- =====================================================
-- STEP 6: Verifikasi hasil
-- =====================================================
-- Jalankan query ini untuk memverifikasi hasil:
-- SELECT m.name, m.code, p.action, p.code as permission_code 
-- FROM erp_menu m 
-- LEFT JOIN erp_permission p ON m.id = p.menu_id 
-- WHERE m.code IN ('outlet_wip_production', 'outlet_wip_report') 
-- ORDER BY m.id, p.action;
