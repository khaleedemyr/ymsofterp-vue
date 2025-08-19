-- Insert menu untuk Outlet WIP Production
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(NULL, 'Outlet WIP Production', 'outlet_wip_production', 4, '/outlet-wip', 'fa-solid fa-industry', NOW(), NOW()),
(NULL, 'Laporan Outlet WIP', 'outlet_wip_report', 4, '/outlet-wip/report', 'fa-solid fa-file-lines', NOW(), NOW());

-- Ambil ID menu yang baru dibuat
SET @outlet_wip_production_id = LAST_INSERT_ID();
SET @outlet_wip_report_id = @outlet_wip_production_id + 1;

-- Insert permissions untuk Outlet WIP Production
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(NULL, @outlet_wip_production_id, 'view', 'outlet_wip_production_view', NOW(), NOW()),
(NULL, @outlet_wip_production_id, 'create', 'outlet_wip_production_create', NOW(), NOW()),
(NULL, @outlet_wip_production_id, 'update', 'outlet_wip_production_update', NOW(), NOW()),
(NULL, @outlet_wip_production_id, 'delete', 'outlet_wip_production_delete', NOW(), NOW());

-- Insert permissions untuk Laporan Outlet WIP
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(NULL, @outlet_wip_report_id, 'view', 'outlet_wip_report_view', NOW(), NOW());
