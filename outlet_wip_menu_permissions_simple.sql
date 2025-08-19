-- Insert menu untuk Outlet WIP Production
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Outlet WIP Production', 'outlet_wip_production', 4, '/outlet-wip', 'fa-solid fa-industry', NOW(), NOW()),
('Laporan Outlet WIP', 'outlet_wip_report', 4, '/outlet-wip/report', 'fa-solid fa-file-lines', NOW(), NOW());

-- Catatan: Setelah menjalankan query di atas, Anda perlu mendapatkan ID menu yang baru dibuat
-- dan mengganti [OUTLET_WIP_PRODUCTION_ID] dan [OUTLET_WIP_REPORT_ID] dengan ID yang sebenarnya

-- Insert permissions untuk Outlet WIP Production (ganti [OUTLET_WIP_PRODUCTION_ID] dengan ID sebenarnya)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
([OUTLET_WIP_PRODUCTION_ID], 'view', 'outlet_wip_production_view', NOW(), NOW()),
([OUTLET_WIP_PRODUCTION_ID], 'create', 'outlet_wip_production_create', NOW(), NOW()),
([OUTLET_WIP_PRODUCTION_ID], 'update', 'outlet_wip_production_update', NOW(), NOW()),
([OUTLET_WIP_PRODUCTION_ID], 'delete', 'outlet_wip_production_delete', NOW(), NOW());

-- Insert permissions untuk Laporan Outlet WIP (ganti [OUTLET_WIP_REPORT_ID] dengan ID sebenarnya)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
([OUTLET_WIP_REPORT_ID], 'view', 'outlet_wip_report_view', NOW(), NOW());
