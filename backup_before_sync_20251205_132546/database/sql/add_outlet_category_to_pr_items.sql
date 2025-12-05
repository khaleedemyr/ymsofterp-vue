-- Add outlet_id and category_id to purchase_requisition_items table
-- This allows multi-outlet, multi-category per outlet, and multi-item per category for pr_ops mode

-- Add outlet_id column
ALTER TABLE purchase_requisition_items 
ADD COLUMN outlet_id BIGINT UNSIGNED NULL AFTER purchase_requisition_id,
ADD INDEX idx_pr_items_outlet (outlet_id);

-- Add category_id column
ALTER TABLE purchase_requisition_items 
ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER outlet_id,
ADD INDEX idx_pr_items_category (category_id);

-- Add foreign keys
ALTER TABLE purchase_requisition_items 
ADD CONSTRAINT fk_pr_items_outlet 
FOREIGN KEY (outlet_id) REFERENCES tbl_data_outlet(id_outlet) ON DELETE SET NULL;

ALTER TABLE purchase_requisition_items 
ADD CONSTRAINT fk_pr_items_category 
FOREIGN KEY (category_id) REFERENCES purchase_requisition_categories(id) ON DELETE SET NULL;

-- Add composite index for better query performance
CREATE INDEX idx_pr_items_outlet_category ON purchase_requisition_items(outlet_id, category_id);

