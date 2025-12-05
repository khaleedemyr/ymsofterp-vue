-- Insert menu group Ops Management ke tabel erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Ops Management', 'ops_management', NULL, NULL, 'fa-solid fa-cogs', NOW(), NOW());

-- Get the parent_id for Ops Management
SET @ops_management_id = LAST_INSERT_ID();

-- Insert menu Master Daily Report ke tabel erp_menu dengan parent_id Ops Management
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Master Daily Report', 'master_report', @ops_management_id, '/master-report', 'fa-solid fa-chart-line', NOW(), NOW());

-- Get the menu_id for Master Daily Report
SET @master_report_id = LAST_INSERT_ID();

-- Insert permissions untuk Master Daily Report ke tabel erp_permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@master_report_id, 'view', 'master_report_view', NOW(), NOW()),
(@master_report_id, 'create', 'master_report_create', NOW(), NOW()),
(@master_report_id, 'update', 'master_report_update', NOW(), NOW()),
(@master_report_id, 'delete', 'master_report_delete', NOW(), NOW());

-- Insert permissions untuk Ops Management group (optional - untuk akses ke group menu)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@ops_management_id, 'view', 'ops_management_view', NOW(), NOW());
