-- Add sale_date column to retail_warehouse_sales table
-- This allows users to select a specific date for the sale instead of using created_at

ALTER TABLE retail_warehouse_sales 
ADD COLUMN sale_date DATE NOT NULL DEFAULT (CURDATE()) 
AFTER customer_id;

-- Update existing records to use created_at date as sale_date
UPDATE retail_warehouse_sales 
SET sale_date = DATE(created_at) 
WHERE sale_date IS NULL OR sale_date = '0000-00-00';

-- Add index for better performance on date queries
CREATE INDEX idx_retail_warehouse_sales_sale_date ON retail_warehouse_sales(sale_date);

-- Add index for combined queries (sale_date + customer_id)
CREATE INDEX idx_retail_warehouse_sales_date_customer ON retail_warehouse_sales(sale_date, customer_id);
