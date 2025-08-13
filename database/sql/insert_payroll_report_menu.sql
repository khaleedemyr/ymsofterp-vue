-- Insert menu untuk Payroll Report
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) 
VALUES (107, 'Payroll', 'payroll_report', 106, '/payroll/report', 'fa-solid fa-file-invoice-dollar', NOW(), NOW());

-- Insert permissions untuk Payroll Report
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(107, 'view', 'payroll_report_view', NOW(), NOW()),
(107, 'create', 'payroll_report_create', NOW(), NOW()),
(107, 'update', 'payroll_report_update', NOW(), NOW()),
(107, 'delete', 'payroll_report_delete', NOW(), NOW());
