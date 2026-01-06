-- Insert Asset Management Menus ke erp_menu
-- Parent Menu: Asset Management
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Asset Management', 'asset_management', NULL, '#', 'fa-solid fa-boxes-stacked', NOW(), NOW());

-- Get parent_id untuk Asset Management
SET @asset_management_parent_id = LAST_INSERT_ID();

-- Child Menus
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Dashboard', 'asset_management_dashboard', @asset_management_parent_id, '/asset-management/dashboard', 'fa-solid fa-gauge', NOW(), NOW()),
('Asset Categories', 'asset_management_categories', @asset_management_parent_id, '/asset-management/categories', 'fa-solid fa-tags', NOW(), NOW()),
('Assets', 'asset_management_assets', @asset_management_parent_id, '/asset-management/assets', 'fa-solid fa-box', NOW(), NOW()),
('Transfers', 'asset_management_transfers', @asset_management_parent_id, '/asset-management/transfers', 'fa-solid fa-exchange-alt', NOW(), NOW()),
('Maintenance Schedules', 'asset_management_maintenance_schedules', @asset_management_parent_id, '/asset-management/maintenance-schedules', 'fa-solid fa-calendar-check', NOW(), NOW()),
('Maintenances', 'asset_management_maintenances', @asset_management_parent_id, '/asset-management/maintenances', 'fa-solid fa-wrench', NOW(), NOW()),
('Disposals', 'asset_management_disposals', @asset_management_parent_id, '/asset-management/disposals', 'fa-solid fa-trash', NOW(), NOW()),
('Documents', 'asset_management_documents', @asset_management_parent_id, '/asset-management/documents', 'fa-solid fa-file', NOW(), NOW()),
('Depreciations', 'asset_management_depreciations', @asset_management_parent_id, '/asset-management/depreciations', 'fa-solid fa-chart-line', NOW(), NOW()),
('Reports', 'asset_management_reports', @asset_management_parent_id, '/asset-management/reports', 'fa-solid fa-chart-bar', NOW(), NOW());

-- Get menu IDs untuk permissions
SET @menu_dashboard_id = (SELECT id FROM erp_menu WHERE code = 'asset_management_dashboard');
SET @menu_categories_id = (SELECT id FROM erp_menu WHERE code = 'asset_management_categories');
SET @menu_assets_id = (SELECT id FROM erp_menu WHERE code = 'asset_management_assets');
SET @menu_transfers_id = (SELECT id FROM erp_menu WHERE code = 'asset_management_transfers');
SET @menu_maintenance_schedules_id = (SELECT id FROM erp_menu WHERE code = 'asset_management_maintenance_schedules');
SET @menu_maintenances_id = (SELECT id FROM erp_menu WHERE code = 'asset_management_maintenances');
SET @menu_disposals_id = (SELECT id FROM erp_menu WHERE code = 'asset_management_disposals');
SET @menu_documents_id = (SELECT id FROM erp_menu WHERE code = 'asset_management_documents');
SET @menu_depreciations_id = (SELECT id FROM erp_menu WHERE code = 'asset_management_depreciations');
SET @menu_reports_id = (SELECT id FROM erp_menu WHERE code = 'asset_management_reports');

-- Insert Permissions untuk Dashboard
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_dashboard_id, 'view', 'asset_management_dashboard_view', NOW(), NOW());

-- Insert Permissions untuk Asset Categories
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_categories_id, 'view', 'asset_management_categories_view', NOW(), NOW()),
(@menu_categories_id, 'create', 'asset_management_categories_create', NOW(), NOW()),
(@menu_categories_id, 'update', 'asset_management_categories_update', NOW(), NOW()),
(@menu_categories_id, 'delete', 'asset_management_categories_delete', NOW(), NOW());

-- Insert Permissions untuk Assets
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_assets_id, 'view', 'asset_management_assets_view', NOW(), NOW()),
(@menu_assets_id, 'create', 'asset_management_assets_create', NOW(), NOW()),
(@menu_assets_id, 'update', 'asset_management_assets_update', NOW(), NOW()),
(@menu_assets_id, 'delete', 'asset_management_assets_delete', NOW(), NOW());

-- Insert Permissions untuk Transfers
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_transfers_id, 'view', 'asset_management_transfers_view', NOW(), NOW()),
(@menu_transfers_id, 'create', 'asset_management_transfers_create', NOW(), NOW()),
(@menu_transfers_id, 'update', 'asset_management_transfers_update', NOW(), NOW()),
(@menu_transfers_id, 'delete', 'asset_management_transfers_delete', NOW(), NOW()),
(@menu_transfers_id, 'update', 'asset_management_transfers_approve', NOW(), NOW()),
(@menu_transfers_id, 'update', 'asset_management_transfers_reject', NOW(), NOW()),
(@menu_transfers_id, 'update', 'asset_management_transfers_complete', NOW(), NOW());

-- Insert Permissions untuk Maintenance Schedules
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_maintenance_schedules_id, 'view', 'asset_management_maintenance_schedules_view', NOW(), NOW()),
(@menu_maintenance_schedules_id, 'create', 'asset_management_maintenance_schedules_create', NOW(), NOW()),
(@menu_maintenance_schedules_id, 'update', 'asset_management_maintenance_schedules_update', NOW(), NOW()),
(@menu_maintenance_schedules_id, 'delete', 'asset_management_maintenance_schedules_delete', NOW(), NOW());

-- Insert Permissions untuk Maintenances
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_maintenances_id, 'view', 'asset_management_maintenances_view', NOW(), NOW()),
(@menu_maintenances_id, 'create', 'asset_management_maintenances_create', NOW(), NOW()),
(@menu_maintenances_id, 'update', 'asset_management_maintenances_update', NOW(), NOW()),
(@menu_maintenances_id, 'delete', 'asset_management_maintenances_delete', NOW(), NOW()),
(@menu_maintenances_id, 'update', 'asset_management_maintenances_complete', NOW(), NOW());

-- Insert Permissions untuk Disposals
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_disposals_id, 'view', 'asset_management_disposals_view', NOW(), NOW()),
(@menu_disposals_id, 'create', 'asset_management_disposals_create', NOW(), NOW()),
(@menu_disposals_id, 'update', 'asset_management_disposals_update', NOW(), NOW()),
(@menu_disposals_id, 'delete', 'asset_management_disposals_delete', NOW(), NOW()),
(@menu_disposals_id, 'update', 'asset_management_disposals_approve', NOW(), NOW()),
(@menu_disposals_id, 'update', 'asset_management_disposals_reject', NOW(), NOW()),
(@menu_disposals_id, 'update', 'asset_management_disposals_complete', NOW(), NOW());

-- Insert Permissions untuk Documents
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_documents_id, 'view', 'asset_management_documents_view', NOW(), NOW()),
(@menu_documents_id, 'create', 'asset_management_documents_create', NOW(), NOW()),
(@menu_documents_id, 'update', 'asset_management_documents_update', NOW(), NOW()),
(@menu_documents_id, 'delete', 'asset_management_documents_delete', NOW(), NOW()),
(@menu_documents_id, 'view', 'asset_management_documents_download', NOW(), NOW());

-- Insert Permissions untuk Depreciations
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_depreciations_id, 'view', 'asset_management_depreciations_view', NOW(), NOW()),
(@menu_depreciations_id, 'update', 'asset_management_depreciations_calculate', NOW(), NOW());

-- Insert Permissions untuk Reports
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_reports_id, 'view', 'asset_management_reports_view', NOW(), NOW()),
(@menu_reports_id, 'view', 'asset_management_reports_export', NOW(), NOW());

