-- Insert Employee Survey menu
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) 
VALUES (107, 'Employee Survey', 'employee-survey', 106, 'employee-survey.index', 'fas fa-clipboard-list', NOW(), NOW());

-- Insert permissions for Employee Survey
INSERT INTO erp_permission (id, menu_id, action, code, created_at, updated_at) VALUES
(401, 107, 'view', 'employee-survey.view', NOW(), NOW()),
(402, 107, 'create', 'employee-survey.create', NOW(), NOW()),
(403, 107, 'update', 'employee-survey.update', NOW(), NOW()),
(404, 107, 'delete', 'employee-survey.delete', NOW(), NOW());
