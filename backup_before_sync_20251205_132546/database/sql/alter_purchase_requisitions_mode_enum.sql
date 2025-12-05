-- Alter purchase_requisitions table to update mode enum
-- This script adds new mode values: travel_application and kasbon
-- Date: 2025-01-XX

-- Step 1: Check current structure
-- Run this query first to check if mode column exists and its type:
-- SHOW COLUMNS FROM purchase_requisitions LIKE 'mode';

-- Step 2: Update mode enum to include new values
-- If mode column exists as ENUM with old values ('pr_ops', 'purchase_payment')
-- This will modify it to include new values
ALTER TABLE purchase_requisitions 
MODIFY COLUMN mode ENUM('pr_ops', 'purchase_payment', 'travel_application', 'kasbon') NULL DEFAULT NULL;

-- Step 3: Add index on mode column for better query performance (if not exists)
-- Note: MySQL doesn't support IF NOT EXISTS for CREATE INDEX, so run this only if index doesn't exist
-- Check first: SHOW INDEX FROM purchase_requisitions WHERE Key_name = 'idx_purchase_requisitions_mode';
CREATE INDEX idx_purchase_requisitions_mode ON purchase_requisitions(mode);

-- Alternative: If mode column doesn't exist yet, use this instead:
-- ALTER TABLE purchase_requisitions 
-- ADD COLUMN mode ENUM('pr_ops', 'purchase_payment', 'travel_application', 'kasbon') NULL AFTER priority;

-- Alternative: If mode is VARCHAR instead of ENUM, convert to ENUM:
-- ALTER TABLE purchase_requisitions 
-- MODIFY COLUMN mode ENUM('pr_ops', 'purchase_payment', 'travel_application', 'kasbon') NULL;

