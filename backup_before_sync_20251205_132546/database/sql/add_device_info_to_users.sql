-- Add device_info column to users table for Approval App
-- This column stores device information (platform, model, manufacturer, version) as JSON

ALTER TABLE `users` 
ADD COLUMN `device_info` TEXT NULL DEFAULT NULL COMMENT 'Device information in JSON format for Approval App' 
AFTER `imei`;

