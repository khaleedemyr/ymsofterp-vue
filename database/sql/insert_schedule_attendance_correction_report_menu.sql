-- Insert menu untuk Schedule/Attendance Correction Report
-- Parent ID = 106 (Human Resource group)

-- Insert ke tabel erp_menu
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(NULL, 'Schedule/Attendance Correction Report', 'schedule_attendance_correction_report', 106, '/schedule-attendance-correction/report', 'fa-solid fa-chart-bar', NOW(), NOW());

-- Dapatkan ID menu yang baru saja diinsert
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu tersebut
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(NULL, @menu_id, 'view', 'schedule_attendance_correction_report_view', NOW(), NOW()),
(NULL, @menu_id, 'create', 'schedule_attendance_correction_report_create', NOW(), NOW()),
(NULL, @menu_id, 'update', 'schedule_attendance_correction_report_update', NOW(), NOW()),
(NULL, @menu_id, 'delete', 'schedule_attendance_correction_report_delete', NOW(), NOW());
