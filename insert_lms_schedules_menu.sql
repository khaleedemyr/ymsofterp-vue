-- Insert menu untuk Training Schedule
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) 
VALUES (128, 'Jadwal Training', 'lms-schedules', 127, '/lms/schedules', 'fa-solid fa-calendar-alt', NOW(), NOW());

-- Insert permissions untuk Training Schedule
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(128, 'view', 'lms-schedules-view', NOW(), NOW()),
(128, 'create', 'lms-schedules-create', NOW(), NOW()),
(128, 'update', 'lms-schedules-update', NOW(), NOW()),
(128, 'delete', 'lms-schedules-delete', NOW(), NOW());

-- Insert sub-menu untuk Training Schedule (jika diperlukan)
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) VALUES
(129, 'Buat Jadwal', 'lms-schedules-create', 128, '/lms/schedules/create', 'fa-solid fa-plus', NOW(), NOW()),
(130, 'Edit Jadwal', 'lms-schedules-edit', 128, '/lms/schedules/edit', 'fa-solid fa-edit', NOW(), NOW()),
(131, 'Detail Jadwal', 'lms-schedules-show', 128, '/lms/schedules/show', 'fa-solid fa-eye', NOW(), NOW());

-- Insert permissions untuk sub-menu
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(129, 'view', 'lms-schedules-create-view', NOW(), NOW()),
(129, 'create', 'lms-schedules-create-create', NOW(), NOW()),
(130, 'view', 'lms-schedules-edit-view', NOW(), NOW()),
(130, 'update', 'lms-schedules-edit-update', NOW(), NOW()),
(131, 'view', 'lms-schedules-show-view', NOW(), NOW());

-- Insert permissions untuk QR Code dan Invitation management
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(128, 'view', 'lms-schedules-qr-scanner', NOW(), NOW()),
(128, 'view', 'lms-schedules-invitation', NOW(), NOW()),
(128, 'create', 'lms-schedules-invitation-create', NOW(), NOW()),
(128, 'update', 'lms-schedules-invitation-update', NOW(), NOW()),
(128, 'delete', 'lms-schedules-invitation-delete', NOW(), NOW());
