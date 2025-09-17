-- Insert Trainer Report menu into erp_menu table
-- Parent ID: 127 (LMS group)

INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) VALUES
(128, 'Trainer Report', 'lms-trainer-report', 127, '/lms/trainer-report-page', 'fa-solid fa-chart-line', NOW(), NOW());

-- Insert permissions for Trainer Report menu into erp_permission table
-- Menu ID: 128 (Trainer Report)

INSERT INTO erp_permission (id, menu_id, action, code, created_at, updated_at) VALUES
(129, 128, 'view', 'lms-trainer-report-view', NOW(), NOW()),
(130, 128, 'create', 'lms-trainer-report-create', NOW(), NOW()),
(131, 128, 'update', 'lms-trainer-report-update', NOW(), NOW()),
(132, 128, 'delete', 'lms-trainer-report-delete', NOW(), NOW());
