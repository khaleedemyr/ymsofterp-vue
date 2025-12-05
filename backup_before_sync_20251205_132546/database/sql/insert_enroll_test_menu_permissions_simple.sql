-- Insert menu untuk Enroll Test dengan parent_id = 106
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Enroll Test', 'enroll_test', 106, '/enroll-test', 'fa-solid fa-user-graduate', NOW(), NOW()),
('My Tests', 'my_tests', 106, '/my-tests', 'fa-solid fa-clipboard-check', NOW(), NOW());

-- Insert permissions for Enroll Test menu (assuming menu_id will be the next available ID)
-- You may need to adjust the menu_id values based on your current database state
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
-- Enroll Test permissions (replace MENU_ID_1 with actual menu_id from erp_menu table)
((SELECT id FROM erp_menu WHERE code = 'enroll_test' AND parent_id = 106), 'view', 'enroll_test_view', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'enroll_test' AND parent_id = 106), 'create', 'enroll_test_create', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'enroll_test' AND parent_id = 106), 'update', 'enroll_test_update', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'enroll_test' AND parent_id = 106), 'delete', 'enroll_test_delete', NOW(), NOW()),

-- My Tests permissions (replace MENU_ID_2 with actual menu_id from erp_menu table)
((SELECT id FROM erp_menu WHERE code = 'my_tests' AND parent_id = 106), 'view', 'my_tests_view', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'my_tests' AND parent_id = 106), 'create', 'my_tests_create', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'my_tests' AND parent_id = 106), 'update', 'my_tests_update', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'my_tests' AND parent_id = 106), 'delete', 'my_tests_delete', NOW(), NOW());
