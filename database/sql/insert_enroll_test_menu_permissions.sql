-- Insert menu untuk Enroll Test dengan parent_id = 106
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Enroll Test', 'enroll_test', 106, '/enroll-test', 'fa-solid fa-user-graduate', NOW(), NOW()),
('My Tests', 'my_tests', 106, '/my-tests', 'fa-solid fa-clipboard-check', NOW(), NOW());

-- Get the menu IDs for the inserted menus
SET @enroll_test_menu_id = (SELECT id FROM erp_menu WHERE code = 'enroll_test' AND parent_id = 106);
SET @my_tests_menu_id = (SELECT id FROM erp_menu WHERE code = 'my_tests' AND parent_id = 106);

-- Insert permissions for Enroll Test menu
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@enroll_test_menu_id, 'view', 'enroll_test_view', NOW(), NOW()),
(@enroll_test_menu_id, 'create', 'enroll_test_create', NOW(), NOW()),
(@enroll_test_menu_id, 'update', 'enroll_test_update', NOW(), NOW()),
(@enroll_test_menu_id, 'delete', 'enroll_test_delete', NOW(), NOW());

-- Insert permissions for My Tests menu
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@my_tests_menu_id, 'view', 'my_tests_view', NOW(), NOW()),
(@my_tests_menu_id, 'create', 'my_tests_create', NOW(), NOW()),
(@my_tests_menu_id, 'update', 'my_tests_update', NOW(), NOW()),
(@my_tests_menu_id, 'delete', 'my_tests_delete', NOW(), NOW());
