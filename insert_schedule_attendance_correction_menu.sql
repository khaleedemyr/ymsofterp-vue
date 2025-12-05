-- Insert menu for Schedule/Attendance Correction
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(107, 'Schedule/Attendance Correction', 'schedule_attendance_correction', 106, '/schedule-attendance-correction', 'fa-solid fa-edit', NOW(), NOW());

-- Insert permissions for Schedule/Attendance Correction menu
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(107, 'view', 'schedule_attendance_correction_view', NOW(), NOW()),
(107, 'create', 'schedule_attendance_correction_create', NOW(), NOW()),
(107, 'update', 'schedule_attendance_correction_update', NOW(), NOW()),
(107, 'delete', 'schedule_attendance_correction_delete', NOW(), NOW());
