-- Update menu_id column to support multiple values (JSON)
-- First, convert existing single menu_id to JSON array format
UPDATE `chart_of_accounts` 
SET `menu_id` = CASE 
    WHEN `menu_id` IS NOT NULL 
    THEN JSON_ARRAY(`menu_id`)
    ELSE NULL
END
WHERE `menu_id` IS NOT NULL;

-- Drop old foreign key constraint
ALTER TABLE `chart_of_accounts` 
DROP FOREIGN KEY IF EXISTS `chart_of_accounts_menu_id_foreign`;

-- Drop old index
DROP INDEX IF EXISTS `idx_menu_id` ON `chart_of_accounts`;

-- Alter column to JSON type
ALTER TABLE `chart_of_accounts` 
MODIFY COLUMN `menu_id` JSON DEFAULT NULL;
