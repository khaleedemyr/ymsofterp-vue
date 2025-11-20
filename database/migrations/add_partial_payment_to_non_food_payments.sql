-- Add partial payment tracking columns to non_food_payments table
-- Migration: Add support for partial/termin payments tracking

-- Check if columns don't exist before adding
SET @dbname = DATABASE();
SET @tablename = "non_food_payments";
SET @columnname1 = "is_partial_payment";
SET @columnname2 = "payment_sequence";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname1)
  ) > 0,
  "SELECT 'Column is_partial_payment already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname1, " BOOLEAN DEFAULT FALSE COMMENT 'Indicates if this is a partial payment for termin PO' AFTER status")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname2)
  ) > 0,
  "SELECT 'Column payment_sequence already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname2, " INT NULL COMMENT 'Sequence number for partial payments (1, 2, 3, etc)' AFTER is_partial_payment")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

