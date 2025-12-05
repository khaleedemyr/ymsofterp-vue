saja query create -- Insert Certificate Template Menu
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) VALUES
(128, 'Template Sertifikat', 'lms-certificate-templates', 127, '/lms/certificate-templates', 'fa-solid fa-file-certificate', NOW(), NOW());

-- Insert Certificate Template Permissions
INSERT INTO erp_permission (id, menu_id, action, code, created_at, updated_at) VALUES
(512, 128, 'view', 'lms-certificate-templates-view', NOW(), NOW()),
(513, 128, 'create', 'lms-certificate-templates-create', NOW(), NOW()),
(514, 128, 'update', 'lms-certificate-templates-update', NOW(), NOW()),
(515, 128, 'delete', 'lms-certificate-templates-delete', NOW(), NOW());
