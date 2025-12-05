-- Add warehouse_division_id column to pr_foods table
ALTER TABLE pr_foods ADD COLUMN warehouse_division_id BIGINT UNSIGNED NULL AFTER warehouse_id;

-- Add foreign key constraint
ALTER TABLE pr_foods ADD CONSTRAINT fk_pr_foods_warehouse_division 
FOREIGN KEY (warehouse_division_id) REFERENCES warehouse_division(id) ON DELETE SET NULL;
