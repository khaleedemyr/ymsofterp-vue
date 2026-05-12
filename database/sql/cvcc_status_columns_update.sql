-- CVCC: Add follow_up_status column and migrate status values
-- Run this ONCE on production

-- 1. Add follow_up_status column
ALTER TABLE feedback_cases
ADD COLUMN follow_up_status VARCHAR(32) NOT NULL DEFAULT 'new' AFTER status;

-- 2. Migrate old status values to new ones
UPDATE feedback_cases SET status = 'internal_follow_up' WHERE status IN ('courtesy_by_cs', 'follow_up_by_ops', 'in_progress');
UPDATE feedback_cases SET status = 'courtesy_done' WHERE status IN ('done', 'resolved', 'ignored');
