-- Insert Employee Upload menu item
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) 
VALUES (
    (SELECT COALESCE(MAX(id), 0) + 1 FROM erp_menu AS em), 
    'Upload Data Karyawan', 
    'employee_upload', 
    106, 
    '/employee-upload', 
    'fa-solid fa-upload', 
    NOW(), 
    NOW()
);

-- Get the menu ID for permissions
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions for Employee Upload
INSERT INTO erp_permission (id, menu_id, action, code, created_at, updated_at) VALUES
((SELECT COALESCE(MAX(id), 0) + 1 FROM erp_permission AS ep), @menu_id, 'view', 'employee_upload_view', NOW(), NOW()),
((SELECT COALESCE(MAX(id), 0) + 2 FROM erp_permission AS ep), @menu_id, 'create', 'employee_upload_create', NOW(), NOW()),
((SELECT COALESCE(MAX(id), 0) + 3 FROM erp_permission AS ep), @menu_id, 'update', 'employee_upload_update', NOW(), NOW()),
((SELECT COALESCE(MAX(id), 0) + 4 FROM erp_permission AS ep), @menu_id, 'delete', 'employee_upload_delete', NOW(), NOW());
