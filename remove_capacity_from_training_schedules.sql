-- Remove capacity fields from training_schedules table
-- This migration removes max_participants and min_participants fields

USE ymsofterp;

-- Remove capacity columns
ALTER TABLE training_schedules 
DROP COLUMN max_participants,
DROP COLUMN min_participants;

-- Verify the changes
DESCRIBE training_schedules;
