-- Add travel application fields to purchase_requisition_items table
-- This allows travel items to have type (transport/allowance/others) and related fields

-- Add item_type column
ALTER TABLE purchase_requisition_items 
ADD COLUMN item_type ENUM('transport', 'allowance', 'others') NULL AFTER category_id;

-- Add allowance fields
ALTER TABLE purchase_requisition_items 
ADD COLUMN allowance_recipient_name VARCHAR(255) NULL AFTER item_type,
ADD COLUMN allowance_account_number VARCHAR(100) NULL AFTER allowance_recipient_name;

-- Add others notes field
ALTER TABLE purchase_requisition_items 
ADD COLUMN others_notes TEXT NULL AFTER allowance_account_number;

-- Add index for item_type
CREATE INDEX idx_pr_items_item_type ON purchase_requisition_items(item_type);

