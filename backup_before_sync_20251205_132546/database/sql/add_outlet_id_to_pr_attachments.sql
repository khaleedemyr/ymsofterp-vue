-- Add outlet_id to purchase_requisition_attachments table
-- This allows attachments to be associated with specific outlets for pr_ops mode

-- Add outlet_id column
ALTER TABLE purchase_requisition_attachments 
ADD COLUMN outlet_id BIGINT UNSIGNED NULL AFTER purchase_requisition_id,
ADD INDEX idx_pr_attachments_outlet (outlet_id);

-- Add foreign key
ALTER TABLE purchase_requisition_attachments 
ADD CONSTRAINT fk_pr_attachments_outlet 
FOREIGN KEY (outlet_id) REFERENCES tbl_data_outlet(id_outlet) ON DELETE SET NULL;

