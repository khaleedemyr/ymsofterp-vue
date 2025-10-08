-- Insert menu untuk Enroll Test dengan parent_id = 106
-- Note: Adjust the menu_id values below based on your current database state
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Enroll Test', 'enroll_test', 106, '/enroll-test', 'fa-solid fa-user-graduate', NOW(), NOW()),
('My Tests', 'my_tests', 106, '/my-tests', 'fa-solid fa-clipboard-check', NOW(), NOW());

-- Insert permissions for Enroll Test menu
-- Replace 200 and 201 with actual menu_id values from the inserted menus above
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
-- Enroll Test permissions (menu_id = 200 - adjust as needed)
(200, 'view', 'enroll_test_view', NOW(), NOW()),
(200, 'create', 'enroll_test_create', NOW(), NOW()),
(200, 'update', 'enroll_test_update', NOW(), NOW()),
(200, 'delete', 'enroll_test_delete', NOW(), NOW()),

-- My Tests permissions (menu_id = 201 - adjust as needed)
(201, 'view', 'my_tests_view', NOW(), NOW()),
(201, 'create', 'my_tests_create', NOW(), NOW()),
(201, 'update', 'my_tests_update', NOW(), NOW()),
(201, 'delete', 'my_tests_delete', NOW(), NOW());
