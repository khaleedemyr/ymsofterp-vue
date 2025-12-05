-- Insert menu for Head Office Return Management
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) 
VALUES ('Kelola Return Outlet', 'head_office_return', 4, '/head-office-return', 'fa-solid fa-building', NOW(), NOW());

-- Get the menu ID for the inserted menu
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions for Head Office Return Management
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@menu_id, 'view', 'head_office_return_view', NOW(), NOW()),
(@menu_id, 'create', 'head_office_return_create', NOW(), NOW()),
(@menu_id, 'update', 'head_office_return_update', NOW(), NOW()),
(@menu_id, 'delete', 'head_office_return_delete', NOW(), NOW());
