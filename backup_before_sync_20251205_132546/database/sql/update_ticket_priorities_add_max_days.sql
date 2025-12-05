-- Add max_days column to ticket_priorities table
ALTER TABLE `ticket_priorities` ADD COLUMN `max_days` INT DEFAULT 7 COMMENT 'Maximum days to resolve ticket' AFTER `level`;

-- Update existing priorities with default max_days values
UPDATE `ticket_priorities` SET `max_days` = 14 WHERE `name` = 'Low';
UPDATE `ticket_priorities` SET `max_days` = 7 WHERE `name` = 'Medium';
UPDATE `ticket_priorities` SET `max_days` = 3 WHERE `name` = 'High';
UPDATE `ticket_priorities` SET `max_days` = 1 WHERE `name` = 'Critical';
