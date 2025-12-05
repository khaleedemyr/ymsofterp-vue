-- Add attachment columns to purchase_requisition_comments table
ALTER TABLE purchase_requisition_comments 
ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL AFTER is_internal,
ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255) NULL AFTER attachment_path,
ADD COLUMN IF NOT EXISTS attachment_size BIGINT UNSIGNED NULL AFTER attachment_name,
ADD COLUMN IF NOT EXISTS attachment_mime_type VARCHAR(100) NULL AFTER attachment_size;

