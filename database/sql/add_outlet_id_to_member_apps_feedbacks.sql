-- Add outlet_id column to member_apps_feedbacks table if it doesn't exist
ALTER TABLE `member_apps_feedbacks` 
ADD COLUMN IF NOT EXISTS `outlet_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT 'Outlet ID from tbl_data_outlet' AFTER `member_id`;

-- Add index for outlet_id
ALTER TABLE `member_apps_feedbacks` 
ADD INDEX IF NOT EXISTS `idx_outlet_id` (`outlet_id`);

