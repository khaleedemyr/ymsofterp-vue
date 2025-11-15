-- Update mode enum in purchase_requisitions table
-- Add new mode values: travel_application and kasbon

-- Main query: Modify mode column to include all 4 values
ALTER TABLE purchase_requisitions 
MODIFY COLUMN mode ENUM('pr_ops', 'purchase_payment', 'travel_application', 'kasbon') NULL;

-- Optional: Add index for better performance (run only if index doesn't exist)
-- CREATE INDEX idx_purchase_requisitions_mode ON purchase_requisitions(mode);

