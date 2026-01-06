-- Insert menu untuk Payment Report (PR Ops Report)
-- Menu akan ditambahkan dengan parent_id = 1

-- 1. Insert ke erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Payment Report', 'pr_ops_report', 1, '/pr-ops/report', 'fa-solid fa-chart-bar', NOW(), NOW());

-- 2. Insert permissions ke erp_permission
-- Ambil menu_id yang baru saja diinsert
SET @menu_id = LAST_INSERT_ID();

-- Insert permission untuk VIEW
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'view', 'pr_ops_report_view', NOW(), NOW());

-- Insert permission untuk CREATE (jika diperlukan)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'create', 'pr_ops_report_create', NOW(), NOW());

-- Insert permission untuk UPDATE (jika diperlukan)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'update', 'pr_ops_report_update', NOW(), NOW());

-- Insert permission untuk DELETE (jika diperlukan)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'delete', 'pr_ops_report_delete', NOW(), NOW());

-- Catatan:
-- - Menu akan muncul di sidebar dengan nama "Payment Report"
-- - Route: /pr-ops/report
-- - Icon: fa-solid fa-chart-bar
-- - Code: pr_ops_report
-- - Parent ID: 1 (sesuai permintaan)

