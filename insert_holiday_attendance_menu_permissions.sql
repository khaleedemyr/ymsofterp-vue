-- Insert Holiday Attendance Menu and Permissions
-- Parent ID 106 refers to Human Resource group

-- Insert main menu for Holiday Attendance
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(107, 'Holiday Attendance', 'holiday_attendance', 106, '/holiday-attendance', 'fa-solid fa-calendar-day', NOW(), NOW());

-- Insert permissions for Holiday Attendance menu
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(1071, 107, 'view', 'holiday_attendance_view', NOW(), NOW()),
(1072, 107, 'create', 'holiday_attendance_create', NOW(), NOW()),
(1073, 107, 'update', 'holiday_attendance_update', NOW(), NOW()),
(1074, 107, 'delete', 'holiday_attendance_delete', NOW(), NOW());

-- Optional: Insert additional specific permissions for Holiday Attendance features
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(1075, 107, 'view', 'holiday_attendance_process', NOW(), NOW()),
(1076, 107, 'view', 'holiday_attendance_export', NOW(), NOW()),
(1077, 107, 'update', 'holiday_attendance_use_extra_off', NOW(), NOW());
