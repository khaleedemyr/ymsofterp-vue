-- Insert menu Master Daily Report ke tabel erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Master Daily Report', 'master_report', NULL, '/master-report', 'fa-solid fa-chart-line', NOW(), NOW());

-- Get the menu_id for Master Daily Report (assuming it's the last inserted record)
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions untuk Master Daily Report ke tabel erp_permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'master_report_view', NOW(), NOW()),
(@menu_id, 'create', 'master_report_create', NOW(), NOW()),
(@menu_id, 'update', 'master_report_update', NOW(), NOW()),
(@menu_id, 'delete', 'master_report_delete', NOW(), NOW());

-- Alternative: Jika ingin menggunakan ID yang spesifik, ganti @menu_id dengan ID yang sesuai
-- Contoh:
-- INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
-- (123, 'view', 'master_report_view', NOW(), NOW()),
-- (123, 'create', 'master_report_create', NOW(), NOW()),
-- (123, 'update', 'master_report_update', NOW(), NOW()),
-- (123, 'delete', 'master_report_delete', NOW(), NOW());
