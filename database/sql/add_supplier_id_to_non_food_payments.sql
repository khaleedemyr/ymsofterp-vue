-- Add supplier_id column to non_food_payments table (if not exists)
-- This query checks if the column exists and adds it if it doesn't

-- Check if column exists and add if not
SET @dbname = DATABASE();
SET @tablename = "non_food_payments";
SET @columnname = "supplier_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column supplier_id already exists in non_food_payments table' AS result;",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " BIGINT UNSIGNED NOT NULL AFTER purchase_requisition_id, ADD INDEX idx_non_food_payments_supplier (supplier_id), ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Alternative simpler query (uncomment if the above doesn't work):
-- ALTER TABLE non_food_payments 
-- ADD COLUMN IF NOT EXISTS supplier_id BIGINT UNSIGNED NOT NULL AFTER purchase_requisition_id;

-- ALTER TABLE non_food_payments 
-- ADD INDEX IF NOT EXISTS idx_non_food_payments_supplier (supplier_id);

-- ALTER TABLE non_food_payments 
-- ADD CONSTRAINT fk_non_food_payments_supplier 
-- FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE;

