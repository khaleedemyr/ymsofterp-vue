-- Insert menu untuk Shared Documents di erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
-- Menu parent "Dokumen Bersama"
('Dokumen Bersama', 'shared_documents', NULL, NULL, 'fa-solid fa-file-alt', NOW(), NOW());

-- Ambil ID menu parent yang baru dibuat
SET @parent_menu_id = LAST_INSERT_ID();

-- Insert sub-menu untuk Shared Documents
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Daftar Dokumen', 'shared_documents_list', @parent_menu_id, '/shared-documents', 'fa-solid fa-list', NOW(), NOW()),
('Upload Dokumen', 'shared_documents_create', @parent_menu_id, '/shared-documents/create', 'fa-solid fa-upload', NOW(), NOW());

-- Ambil ID sub-menu yang baru dibuat
SET @list_menu_id = (SELECT id FROM erp_menu WHERE code = 'shared_documents_list' LIMIT 1);
SET @create_menu_id = (SELECT id FROM erp_menu WHERE code = 'shared_documents_create' LIMIT 1);

-- Insert permissions untuk menu "Daftar Dokumen"
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@list_menu_id, 'view', 'shared_documents_view', NOW(), NOW()),
(@list_menu_id, 'create', 'shared_documents_create', NOW(), NOW()),
(@list_menu_id, 'update', 'shared_documents_update', NOW(), NOW()),
(@list_menu_id, 'delete', 'shared_documents_delete', NOW(), NOW());

-- Insert permissions untuk menu "Upload Dokumen"
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@create_menu_id, 'view', 'shared_documents_create_view', NOW(), NOW()),
(@create_menu_id, 'create', 'shared_documents_create_action', NOW(), NOW());

-- Insert additional permissions untuk fitur sharing
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@list_menu_id, 'view', 'shared_documents_share', NOW(), NOW()),
(@list_menu_id, 'update', 'shared_documents_share_update', NOW(), NOW()),
(@list_menu_id, 'delete', 'shared_documents_share_delete', NOW(), NOW());

-- Verifikasi data yang diinsert
SELECT 'Menu yang diinsert:' as info;
SELECT id, name, code, parent_id, route, icon FROM erp_menu WHERE code LIKE 'shared_documents%' ORDER BY parent_id, id;

SELECT 'Permission yang diinsert:' as info;
SELECT p.id, m.name as menu_name, p.action, p.code 
FROM erp_permission p 
JOIN erp_menu m ON p.menu_id = m.id 
WHERE p.code LIKE 'shared_documents%' 
ORDER BY m.name, p.action; 