-- =====================================================
-- Insert LMS Menu and Permissions
-- =====================================================

-- Insert main LMS menu (parent)
INSERT IGNORE INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Learning Management System', 'lms', NULL, '#', 'fa-graduation-cap', NOW(), NOW());

-- Get the main LMS menu ID
SET @lms_main_menu_id = (SELECT id FROM erp_menu WHERE code = 'lms' AND parent_id IS NULL LIMIT 1);

-- Insert LMS sub-menus
INSERT IGNORE INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Dashboard LMS', 'lms-dashboard', @lms_main_menu_id, '/lms/dashboard', 'fa-tachometer-alt', NOW(), NOW()),
('Kategori Kursus', 'lms-categories', @lms_main_menu_id, '/lms/categories', 'fa-folder', NOW(), NOW()),
('Kursus', 'lms-courses', @lms_main_menu_id, '/lms/courses', 'fa-book', NOW(), NOW()),
('Lesson', 'lms-lessons', @lms_main_menu_id, '/lms/lessons', 'fa-play-circle', NOW(), NOW()),
('Enrollment', 'lms-enrollments', @lms_main_menu_id, '/lms/enrollments', 'fa-users', NOW(), NOW()),
('Quiz', 'lms-quizzes', @lms_main_menu_id, '/lms/quizzes', 'fa-question-circle', NOW(), NOW()),
('Assignment', 'lms-assignments', @lms_main_menu_id, '/lms/assignments', 'fa-tasks', NOW(), NOW()),
('Sertifikat', 'lms-certificates', @lms_main_menu_id, '/lms/certificates', 'fa-certificate', NOW(), NOW()),
('Diskusi', 'lms-discussions', @lms_main_menu_id, '/lms/discussions', 'fa-comments', NOW(), NOW()),
('Laporan', 'lms-reports', @lms_main_menu_id, '/lms/reports', 'fa-chart-bar', NOW(), NOW());

-- Get menu IDs for permissions
SET @lms_dashboard_menu_id = (SELECT id FROM erp_menu WHERE code = 'lms-dashboard' AND parent_id = @lms_main_menu_id LIMIT 1);
SET @lms_categories_menu_id = (SELECT id FROM erp_menu WHERE code = 'lms-categories' AND parent_id = @lms_main_menu_id LIMIT 1);
SET @lms_courses_menu_id = (SELECT id FROM erp_menu WHERE code = 'lms-courses' AND parent_id = @lms_main_menu_id LIMIT 1);
SET @lms_lessons_menu_id = (SELECT id FROM erp_menu WHERE code = 'lms-lessons' AND parent_id = @lms_main_menu_id LIMIT 1);
SET @lms_enrollments_menu_id = (SELECT id FROM erp_menu WHERE code = 'lms-enrollments' AND parent_id = @lms_main_menu_id LIMIT 1);
SET @lms_quizzes_menu_id = (SELECT id FROM erp_menu WHERE code = 'lms-quizzes' AND parent_id = @lms_main_menu_id LIMIT 1);
SET @lms_assignments_menu_id = (SELECT id FROM erp_menu WHERE code = 'lms-assignments' AND parent_id = @lms_main_menu_id LIMIT 1);
SET @lms_certificates_menu_id = (SELECT id FROM erp_menu WHERE code = 'lms-certificates' AND parent_id = @lms_main_menu_id LIMIT 1);
SET @lms_discussions_menu_id = (SELECT id FROM erp_menu WHERE code = 'lms-discussions' AND parent_id = @lms_main_menu_id LIMIT 1);
SET @lms_reports_menu_id = (SELECT id FROM erp_menu WHERE code = 'lms-reports' AND parent_id = @lms_main_menu_id LIMIT 1);

-- Insert permissions for LMS Dashboard
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@lms_dashboard_menu_id, 'view', 'lms-dashboard-view', NOW(), NOW());

-- Insert permissions for LMS Categories
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@lms_categories_menu_id, 'view', 'lms-categories-view', NOW(), NOW()),
(@lms_categories_menu_id, 'create', 'lms-categories-create', NOW(), NOW()),
(@lms_categories_menu_id, 'update', 'lms-categories-update', NOW(), NOW()),
(@lms_categories_menu_id, 'delete', 'lms-categories-delete', NOW(), NOW());

-- Insert permissions for LMS Courses
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@lms_courses_menu_id, 'view', 'lms-courses-view', NOW(), NOW()),
(@lms_courses_menu_id, 'create', 'lms-courses-create', NOW(), NOW()),
(@lms_courses_menu_id, 'update', 'lms-courses-update', NOW(), NOW()),
(@lms_courses_menu_id, 'delete', 'lms-courses-delete', NOW(), NOW());

-- Insert permissions for LMS Lessons
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@lms_lessons_menu_id, 'view', 'lms-lessons-view', NOW(), NOW()),
(@lms_lessons_menu_id, 'create', 'lms-lessons-create', NOW(), NOW()),
(@lms_lessons_menu_id, 'update', 'lms-lessons-update', NOW(), NOW()),
(@lms_lessons_menu_id, 'delete', 'lms-lessons-delete', NOW(), NOW());

-- Insert permissions for LMS Enrollments
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@lms_enrollments_menu_id, 'view', 'lms-enrollments-view', NOW(), NOW()),
(@lms_enrollments_menu_id, 'create', 'lms-enrollments-create', NOW(), NOW()),
(@lms_enrollments_menu_id, 'update', 'lms-enrollments-update', NOW(), NOW()),
(@lms_enrollments_menu_id, 'delete', 'lms-enrollments-delete', NOW(), NOW());

-- Insert permissions for LMS Quizzes
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@lms_quizzes_menu_id, 'view', 'lms-quizzes-view', NOW(), NOW()),
(@lms_quizzes_menu_id, 'create', 'lms-quizzes-create', NOW(), NOW()),
(@lms_quizzes_menu_id, 'update', 'lms-quizzes-update', NOW(), NOW()),
(@lms_quizzes_menu_id, 'delete', 'lms-quizzes-delete', NOW(), NOW());

-- Insert permissions for LMS Assignments
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@lms_assignments_menu_id, 'view', 'lms-assignments-view', NOW(), NOW()),
(@lms_assignments_menu_id, 'create', 'lms-assignments-create', NOW(), NOW()),
(@lms_assignments_menu_id, 'update', 'lms-assignments-update', NOW(), NOW()),
(@lms_assignments_menu_id, 'delete', 'lms-assignments-delete', NOW(), NOW());

-- Insert permissions for LMS Certificates
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@lms_certificates_menu_id, 'view', 'lms-certificates-view', NOW(), NOW()),
(@lms_certificates_menu_id, 'create', 'lms-certificates-create', NOW(), NOW()),
(@lms_certificates_menu_id, 'update', 'lms-certificates-update', NOW(), NOW()),
(@lms_certificates_menu_id, 'delete', 'lms-certificates-delete', NOW(), NOW());

-- Insert permissions for LMS Discussions
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@lms_discussions_menu_id, 'view', 'lms-discussions-view', NOW(), NOW()),
(@lms_discussions_menu_id, 'create', 'lms-discussions-create', NOW(), NOW()),
(@lms_discussions_menu_id, 'update', 'lms-discussions-update', NOW(), NOW()),
(@lms_discussions_menu_id, 'delete', 'lms-discussions-delete', NOW(), NOW());

-- Insert permissions for LMS Reports
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@lms_reports_menu_id, 'view', 'lms-reports-view', NOW(), NOW());

-- Show success message
SELECT 
    'LMS Menu and Permissions Setup Complete!' as message,
    (SELECT COUNT(*) FROM erp_menu WHERE parent_id = @lms_main_menu_id) as sub_menus_created,
    (SELECT COUNT(*) FROM erp_permission WHERE menu_id IN (
        @lms_dashboard_menu_id, @lms_categories_menu_id, @lms_courses_menu_id, 
        @lms_lessons_menu_id, @lms_enrollments_menu_id, @lms_quizzes_menu_id,
        @lms_assignments_menu_id, @lms_certificates_menu_id, @lms_discussions_menu_id, @lms_reports_menu_id
    )) as permissions_created; 