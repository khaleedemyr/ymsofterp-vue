-- Create table for Schedule/Attendance Correction audit trail
CREATE TABLE `schedule_attendance_corrections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('schedule','attendance') NOT NULL COMMENT 'Type of correction: schedule or attendance',
  `record_id` bigint(20) unsigned NOT NULL COMMENT 'ID from user_shifts or att_log table',
  `old_value` text NOT NULL COMMENT 'Old value (JSON for attendance)',
  `new_value` text NOT NULL COMMENT 'New value (JSON for attendance)',
  `reason` text NOT NULL COMMENT 'Reason for correction',
  `corrected_by` bigint(20) unsigned NOT NULL COMMENT 'User ID who made the correction',
  `corrected_at` timestamp NOT NULL COMMENT 'When the correction was made',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type_record` (`type`,`record_id`),
  KEY `idx_corrected_by` (`corrected_by`),
  KEY `idx_corrected_at` (`corrected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for schedule and attendance corrections';
