-- Insert Training Report Menu
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(128, 'Training Report', 'lms-training-report', 127, '/lms/training-report-page', 'fa-solid fa-chart-bar', NOW(), NOW());

-- Insert Training Report Permissions
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(512, 128, 'view', 'lms-training-report-view', NOW(), NOW()),
(513, 128, 'create', 'lms-training-report-create', NOW(), NOW()),
(514, 128, 'update', 'lms-training-report-update', NOW(), NOW()),
(515, 128, 'delete', 'lms-training-report-delete', NOW(), NOW());
