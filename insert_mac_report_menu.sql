-- Insert MAC Report menu into erp_menu table
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) 
VALUES ('MAC Report', 'mac_report', 66, '/mac-report', 'fa-solid fa-chart-line', NOW(), NOW());

-- Get the inserted menu ID
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions for MAC Report
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@menu_id, 'view', 'mac_report_view', NOW(), NOW()),
(@menu_id, 'create', 'mac_report_create', NOW(), NOW()),
(@menu_id, 'update', 'mac_report_update', NOW(), NOW()),
(@menu_id, 'delete', 'mac_report_delete', NOW(), NOW());

-- Display the inserted data
SELECT 'Menu inserted with ID:' as info, @menu_id as menu_id;

SELECT 'Menu data:' as info;
SELECT * FROM erp_menu WHERE id = @menu_id;

SELECT 'Permissions data:' as info;
SELECT * FROM erp_permission WHERE menu_id = @menu_id;
