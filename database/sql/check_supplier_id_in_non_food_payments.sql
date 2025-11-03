-- Check if supplier_id column exists in non_food_payments table
-- Run this query first to verify the column structure

SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY,
    EXTRA
FROM INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'non_food_payments'
    AND COLUMN_NAME = 'supplier_id';

-- If the query returns empty result, the column doesn't exist
-- If it returns a row, the column exists and shows its structure

