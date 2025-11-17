-- Add member_id column to member_apps_members table
ALTER TABLE `member_apps_members`
ADD COLUMN `member_id` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Auto-generated member ID (e.g., JTS-2411-00001 = JTS-YYMM-XXXXX)' AFTER `id`,
ADD UNIQUE KEY `unique_member_id` (`member_id`),
ADD KEY `idx_member_id` (`member_id`);

