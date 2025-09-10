-- Insert Sales Outlet Dashboard Menu ke erp_menu
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) 
VALUES (
    1, 
    'Sales Outlet Dashboard', 
    'sales_outlet_dashboard', 
    1, 
    '/sales-outlet-dashboard', 
    'fa-solid fa-chart-line', 
    NOW(), 
    NOW()
);

-- Insert permissions untuk Sales Outlet Dashboard ke erp_permission
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(1, 'view', 'sales_outlet_dashboard_view', NOW(), NOW()),
(1, 'create', 'sales_outlet_dashboard_create', NOW(), NOW()),
(1, 'update', 'sales_outlet_dashboard_update', NOW(), NOW()),
(1, 'delete', 'sales_outlet_dashboard_delete', NOW(), NOW());

-- Jika ingin menambahkan permission untuk export data
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(1, 'view', 'sales_outlet_dashboard_export', NOW(), NOW());
