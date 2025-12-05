-- Add status column to outlet_internal_use_waste_headers if not exists
ALTER TABLE outlet_internal_use_waste_headers 
ADD COLUMN IF NOT EXISTS status ENUM('DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'PROCESSED') DEFAULT 'PROCESSED' AFTER notes;

-- Update existing records without approval requirement to PROCESSED
UPDATE outlet_internal_use_waste_headers 
SET status = 'PROCESSED' 
WHERE status IS NULL OR status = '';

