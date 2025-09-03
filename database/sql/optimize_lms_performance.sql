-- =====================================================
-- LMS PERFORMANCE OPTIMIZATION RECOMMENDATIONS
-- =====================================================

-- 1. Add indexes for frequently queried fields
ALTER TABLE `lms_courses` ADD INDEX `idx_status_created_at` (`status`, `created_at`);
ALTER TABLE `lms_courses` ADD INDEX `idx_category_status` (`category_id`, `status`);
ALTER TABLE `lms_courses` ADD INDEX `idx_instructor_status` (`instructor_id`, `status`);
ALTER TABLE `lms_courses` ADD INDEX `idx_target_type` (`target_type`);

-- 2. Add indexes for target divisions (JSON fields)
ALTER TABLE `lms_courses` ADD INDEX `idx_target_divisions` ((CAST(target_divisions AS CHAR(100))));
ALTER TABLE `lms_courses` ADD INDEX `idx_target_jabatan_ids` ((CAST(target_jabatan_ids AS CHAR(100))));
ALTER TABLE `lms_courses` ADD INDEX `idx_target_outlet_ids` ((CAST(target_outlet_ids AS CHAR(100))));

-- 3. Add indexes for sessions
ALTER TABLE `lms_sessions` ADD INDEX `idx_course_order` (`course_id`, `order_number`);
ALTER TABLE `lms_sessions` ADD INDEX `idx_status` (`status`);

-- 4. Add indexes for session items
ALTER TABLE `lms_session_items` ADD INDEX `idx_session_order` (`session_id`, `order_number`);
ALTER TABLE `lms_session_items` ADD INDEX `idx_item_type` (`item_type`);

-- 5. Add indexes for quizzes and questionnaires
ALTER TABLE `lms_quizzes` ADD INDEX `idx_status_course` (`status`, `course_id`);
ALTER TABLE `lms_questionnaires` ADD INDEX `idx_status_course` (`status`, `course_id`);

-- 6. Add indexes for user tables
ALTER TABLE `users` ADD INDEX `idx_status_jabatan` (`status`, `id_jabatan`);
ALTER TABLE `tbl_data_jabatan` ADD INDEX `idx_status_divisi` (`status`, `id_divisi`);
ALTER TABLE `tbl_data_divisi` ADD INDEX `idx_status` (`status`);
ALTER TABLE `tbl_data_outlet` ADD INDEX `idx_status` (`status`);

-- 7. Add composite indexes for better query performance
ALTER TABLE `lms_courses` ADD INDEX `idx_composite_search` (`status`, `category_id`, `difficulty_level`, `created_at`);

-- 8. Analyze table statistics (run periodically)
ANALYZE TABLE `lms_courses`;
ANALYZE TABLE `lms_sessions`;
ANALYZE TABLE `lms_session_items`;
ANALYZE TABLE `lms_quizzes`;
ANALYZE TABLE `lms_questionnaires`;
ANALYZE TABLE `users`;
ANALYZE TABLE `tbl_data_jabatan`;
ANALYZE TABLE `tbl_data_divisi`;
ANALYZE TABLE `tbl_data_outlet`;

-- 9. Check current indexes
SHOW INDEX FROM `lms_courses`;
SHOW INDEX FROM `lms_sessions`;
SHOW INDEX FROM `lms_session_items`;

-- 10. Performance monitoring queries
-- Check slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;

-- Check table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
    table_rows
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name LIKE 'lms_%'
ORDER BY (data_length + index_length) DESC;

-- Check index usage
SELECT 
    table_name,
    index_name,
    cardinality
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name LIKE 'lms_%'
ORDER BY table_name, index_name;
