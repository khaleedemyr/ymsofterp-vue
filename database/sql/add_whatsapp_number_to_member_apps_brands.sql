-- Add whatsapp_number column to member_apps_brands table
ALTER TABLE `member_apps_brands` 
ADD COLUMN IF NOT EXISTS `whatsapp_number` varchar(20) NULL DEFAULT NULL COMMENT 'WhatsApp number for outlet' AFTER `description`;

