-- Add trainer and curriculum related fields to lms_courses table
-- This migration adds fields for external trainer information

USE ymsofterp;

-- Add external trainer fields
ALTER TABLE lms_courses 
ADD COLUMN external_trainer_name VARCHAR(255) NULL AFTER requirements,
ADD COLUMN external_trainer_description TEXT NULL AFTER external_trainer_name;

-- Add index for better performance
CREATE INDEX idx_lms_courses_external_trainer ON lms_courses(external_trainer_name);

-- Update existing courses to have default values
UPDATE lms_courses 
SET external_trainer_name = NULL, 
    external_trainer_description = NULL 
WHERE external_trainer_name IS NULL;

-- Verify the changes
SELECT 
    id,
    title,
    external_trainer_name,
    external_trainer_description,
    created_at
FROM lms_courses 
LIMIT 5;
