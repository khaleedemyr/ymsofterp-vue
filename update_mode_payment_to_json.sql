-- Update mode_payment column to support multiple values (JSON)
-- First, convert existing single values to JSON array format
UPDATE `chart_of_accounts` 
SET `mode_payment` = CASE 
    WHEN `mode_payment` IS NOT NULL AND `mode_payment` != '' 
    THEN CONCAT('["', `mode_payment`, '"]')
    ELSE NULL
END
WHERE `mode_payment` IS NOT NULL;

-- Alter column to JSON type
ALTER TABLE `chart_of_accounts` 
MODIFY COLUMN `mode_payment` JSON DEFAULT NULL;

-- Drop old index
DROP INDEX IF EXISTS `idx_mode_payment` ON `chart_of_accounts`;

-- Add new index for JSON column (if needed for queries)
-- Note: MySQL JSON indexing requires generated columns or virtual columns

