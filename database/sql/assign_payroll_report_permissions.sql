-- Assign Payroll Report permissions to Admin role (assuming role_id = 1 for admin)
-- Ganti role_id sesuai dengan ID role yang sesuai di sistem Anda

-- Assign view permission
INSERT INTO role_permissions (role_id, permission_id, created_at, updated_at)
SELECT 1, p.id, NOW(), NOW()
FROM erp_permission p 
WHERE p.code = 'payroll_report_view';

-- Assign create permission  
INSERT INTO role_permissions (role_id, permission_id, created_at, updated_at)
SELECT 1, p.id, NOW(), NOW()
FROM erp_permission p 
WHERE p.code = 'payroll_report_create';

-- Assign update permission
INSERT INTO role_permissions (role_id, permission_id, created_at, updated_at)
SELECT 1, p.id, NOW(), NOW()
FROM erp_permission p 
WHERE p.code = 'payroll_report_update';

-- Assign delete permission
INSERT INTO role_permissions (role_id, permission_id, created_at, updated_at)
SELECT 1, p.id, NOW(), NOW()
FROM erp_permission p 
WHERE p.code = 'payroll_report_delete';

-- Catatan: Jalankan query di atas untuk setiap role yang perlu akses ke Payroll Report
-- Ganti angka 1 dengan role_id yang sesuai
