-- Insert OPEX Report menu into erp_menu table
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) 
VALUES ('OPEX Report', 'opex_report', 5, '/opex-report', 'fa-solid fa-chart-line', NOW(), NOW());

-- Get the menu ID for OPEX Report
SET @opex_report_menu_id = LAST_INSERT_ID();

-- Insert permissions for OPEX Report
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@opex_report_menu_id, 'view', 'opex_report_view', NOW(), NOW()),
(@opex_report_menu_id, 'create', 'opex_report_create', NOW(), NOW()),
(@opex_report_menu_id, 'update', 'opex_report_update', NOW(), NOW()),
(@opex_report_menu_id, 'delete', 'opex_report_delete', NOW(), NOW());
