-- Insert Quiz Report menu
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(129, 'Quiz Report', 'lms-quiz-report', 127, '/lms/quiz-report-page', 'fa-solid fa-question-circle', NOW(), NOW());

-- Insert permissions for Quiz Report
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(516, 129, 'view', 'lms-quiz-report-view', NOW(), NOW()),
(517, 129, 'create', 'lms-quiz-report-create', NOW(), NOW()),
(518, 129, 'update', 'lms-quiz-report-update', NOW(), NOW()),
(519, 129, 'delete', 'lms-quiz-report-delete', NOW(), NOW());
