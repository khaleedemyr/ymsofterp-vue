-- Add allow_notification column to member_apps_members table
ALTER TABLE `member_apps_members` 
ADD COLUMN `allow_notification` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Allow push notifications for JUST-Points & vouchers expiration reminder' AFTER `is_active`;

