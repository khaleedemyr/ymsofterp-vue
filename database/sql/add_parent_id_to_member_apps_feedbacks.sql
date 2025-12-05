-- Add parent_id column to support feedback thread/replies
ALTER TABLE `member_apps_feedbacks` 
ADD COLUMN IF NOT EXISTS `parent_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT 'Parent feedback ID for replies' AFTER `id`;

-- Add index for parent_id
ALTER TABLE `member_apps_feedbacks` 
ADD INDEX IF NOT EXISTS `idx_parent_id` (`parent_id`);

-- Add foreign key constraint
ALTER TABLE `member_apps_feedbacks` 
ADD CONSTRAINT `fk_feedback_parent` FOREIGN KEY (`parent_id`) REFERENCES `member_apps_feedbacks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

