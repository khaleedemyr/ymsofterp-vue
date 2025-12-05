-- Insert menu for Absent Report
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) 
VALUES ('Report Absent', 'absent-report', 106, '/attendance/report', 'fa-solid fa-file-lines', NOW(), NOW());

-- Get the menu ID for the inserted menu
SET @absent_report_menu_id = LAST_INSERT_ID();

-- Insert permissions for Absent Report
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@absent_report_menu_id, 'view', 'absent-report-view', NOW(), NOW()),
(@absent_report_menu_id, 'create', 'absent-report-create', NOW(), NOW()),
(@absent_report_menu_id, 'update', 'absent-report-update', NOW(), NOW()),
(@absent_report_menu_id, 'delete', 'absent-report-delete', NOW(), NOW());
