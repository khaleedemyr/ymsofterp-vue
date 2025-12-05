-- Insert menu Job Vacancy
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) 
VALUES (121, 'Job Vacancy', 'job_vacancy', 106, '/admin/job-vacancy', 'fa-solid fa-briefcase', NOW(), NOW());

-- Insert permissions for Job Vacancy menu
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(121, 'view', 'job_vacancy_view', NOW(), NOW()),
(121, 'create', 'job_vacancy_create', NOW(), NOW()),
(121, 'update', 'job_vacancy_update', NOW(), NOW()),
(121, 'delete', 'job_vacancy_delete', NOW(), NOW()); 