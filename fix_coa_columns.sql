-- Fix menu_id column (rename menu_ids to menu_id if exists)
-- Check if menu_ids exists and rename it
SET @menu_ids_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'chart_of_accounts' 
    AND COLUMN_NAME = 'menu_ids'
);

SET @sql = IF(@menu_ids_exists > 0,
    'ALTER TABLE `chart_of_accounts` CHANGE COLUMN `menu_ids` `menu_id` JSON DEFAULT NULL',
    'SELECT "Column menu_ids does not exist, skipping rename" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure menu_id is JSON type
ALTER TABLE `chart_of_accounts` 
MODIFY COLUMN `menu_id` JSON DEFAULT NULL;

-- Update mode_payment to JSON if not already
-- First check current type
SET @mode_payment_type = (
    SELECT DATA_TYPE 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'chart_of_accounts' 
    AND COLUMN_NAME = 'mode_payment'
);

-- Convert existing single values to JSON array format if mode_payment is varchar
SET @sql2 = IF(@mode_payment_type = 'varchar',
    CONCAT('UPDATE `chart_of_accounts` SET `mode_payment` = CASE WHEN `mode_payment` IS NOT NULL AND `mode_payment` != \'\' THEN CONCAT(\'["\', `mode_payment`, \'"]\') ELSE NULL END WHERE `mode_payment` IS NOT NULL'),
    'SELECT "mode_payment is already JSON or does not exist" AS message'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Alter mode_payment to JSON type
ALTER TABLE `chart_of_accounts` 
MODIFY COLUMN `mode_payment` JSON DEFAULT NULL;

-- Drop old indexes if they exist
DROP INDEX IF EXISTS `idx_mode_payment` ON `chart_of_accounts`;
DROP INDEX IF EXISTS `idx_menu_id` ON `chart_of_accounts`;

