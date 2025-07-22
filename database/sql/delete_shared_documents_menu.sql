-- Delete permissions untuk Shared Documents (hapus dulu karena ada foreign key)
DELETE FROM `erp_permission` 
WHERE code LIKE 'shared_documents%';

-- Delete sub-menu Shared Documents
DELETE FROM `erp_menu` 
WHERE code IN ('shared_documents_list', 'shared_documents_create');

-- Delete menu parent Shared Documents
DELETE FROM `erp_menu` 
WHERE code = 'shared_documents';

-- Verifikasi data sudah terhapus
SELECT 'Menu yang tersisa:' as info;
SELECT id, name, code, parent_id, route, icon FROM erp_menu WHERE code LIKE 'shared_documents%';

SELECT 'Permission yang tersisa:' as info;
SELECT p.id, m.name as menu_name, p.action, p.code 
FROM erp_permission p 
JOIN erp_menu m ON p.menu_id = m.id 
WHERE p.code LIKE 'shared_documents%'; 