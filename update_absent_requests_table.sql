-- Update absent_requests table to support leave_types
-- Add new columns
ALTER TABLE `absent_requests` 
ADD COLUMN `leave_type_id` bigint(20) unsigned NULL AFTER `user_id`,
ADD COLUMN `date_from` date NULL AFTER `leave_type_id`,
ADD COLUMN `date_to` date NULL AFTER `date_from`;

-- Add foreign key constraint
ALTER TABLE `absent_requests` 
ADD CONSTRAINT `absent_requests_leave_type_id_foreign` 
FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE;

-- Make old columns nullable (if they exist)
ALTER TABLE `absent_requests` 
MODIFY COLUMN `date` date NULL,
MODIFY COLUMN `type` varchar(255) NULL;

-- Remove duration columns if they exist
ALTER TABLE `absent_requests` 
DROP COLUMN IF EXISTS `duration_type`,
DROP COLUMN IF EXISTS `half_day_type`;
