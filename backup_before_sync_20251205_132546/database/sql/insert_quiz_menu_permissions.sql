-- Insert Quiz Menu ke erp_menu
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(128, 'Quiz', 'lms-quizzes', 127, '/lms/quizzes', 'fa-solid fa-question-circle', NOW(), NOW());

-- Insert Permissions untuk Quiz Menu ke erp_permission
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(512, 128, 'view', 'lms-quizzes-view', NOW(), NOW()),
(513, 128, 'create', 'lms-quizzes-create', NOW(), NOW()),
(514, 128, 'update', 'lms-quizzes-update', NOW(), NOW()),
(515, 128, 'delete', 'lms-quizzes-delete', NOW(), NOW());
