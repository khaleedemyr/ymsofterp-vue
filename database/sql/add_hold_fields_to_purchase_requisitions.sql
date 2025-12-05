-- Add hold fields to purchase_requisitions table
-- This allows PR to be put on hold to prevent PO creation or payment

ALTER TABLE purchase_requisitions 
ADD COLUMN is_held BOOLEAN DEFAULT FALSE AFTER status,
ADD COLUMN held_at TIMESTAMP NULL DEFAULT NULL AFTER is_held,
ADD COLUMN held_by BIGINT UNSIGNED NULL AFTER held_at,
ADD COLUMN hold_reason TEXT NULL AFTER held_by,
ADD FOREIGN KEY (held_by) REFERENCES users(id) ON DELETE SET NULL;

-- Add index for better performance
CREATE INDEX idx_purchase_requisitions_is_held ON purchase_requisitions(is_held);

