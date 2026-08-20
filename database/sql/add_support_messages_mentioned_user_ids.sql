-- Mentioned users on live support replies (safe to re-run)
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'support_messages'
      AND COLUMN_NAME = 'mentioned_user_ids'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE `support_messages` ADD COLUMN `mentioned_user_ids` JSON NULL',
    'SELECT "mentioned_user_ids already exists" AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
