-- Drop index on mode_payment if exists (check first)
-- Note: MySQL doesn't support DROP INDEX IF EXISTS, so we need to check manually
-- Run this query first to check if index exists:
-- SHOW INDEX FROM chart_of_accounts WHERE Key_name = 'idx_mode_payment';

-- If index exists, drop it:
-- ALTER TABLE `chart_of_accounts` DROP INDEX `idx_mode_payment`;

-- Then alter mode_payment to JSON
ALTER TABLE `chart_of_accounts` 
MODIFY COLUMN `mode_payment` JSON DEFAULT NULL;

