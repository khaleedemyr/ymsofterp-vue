-- Add approval_id field to notifications table
ALTER TABLE `notifications` 
ADD COLUMN `approval_id` bigint(20) unsigned NULL DEFAULT NULL COMMENT 'ID approval request terkait' AFTER `task_id`,
ADD KEY `notifications_approval_id_foreign` (`approval_id`),
ADD CONSTRAINT `notifications_approval_id_foreign` FOREIGN KEY (`approval_id`) REFERENCES `approval_requests` (`id`) ON DELETE CASCADE;
