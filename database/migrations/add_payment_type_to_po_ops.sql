-- Add payment_type and payment_terms columns to purchase_order_ops table
-- Migration: Add payment type selection (lunas/termin) to Purchase Order Ops

-- Check if columns don't exist before adding
SET @dbname = DATABASE();
SET @tablename = "purchase_order_ops";
SET @columnname1 = "payment_type";
SET @columnname2 = "payment_terms";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname1)
  ) > 0,
  "SELECT 'Column payment_type already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname1, " VARCHAR(20) DEFAULT 'lunas' COMMENT 'Payment type: lunas or termin' AFTER notes")
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
  "SELECT 'Column payment_terms already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname2, " TEXT NULL COMMENT 'Payment terms description for termin payment' AFTER payment_type")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

