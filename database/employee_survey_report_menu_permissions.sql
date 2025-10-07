-- Insert Employee Survey Report menu
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) 
VALUES (108, 'Employee Survey Report', 'employee-survey-report', 106, 'employee-survey.report', 'fas fa-chart-bar', NOW(), NOW());

-- Insert permissions for Employee Survey Report
INSERT INTO erp_permission (id, menu_id, action, code, created_at, updated_at) VALUES
(405, 108, 'view', 'employee-survey-report.view', NOW(), NOW()),
(406, 108, 'create', 'employee-survey-report.create', NOW(), NOW()),
(407, 108, 'update', 'employee-survey-report.update', NOW(), NOW()),
(408, 108, 'delete', 'employee-survey-report.delete', NOW(), NOW());
