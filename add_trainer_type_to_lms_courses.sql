-- Add trainer_type field to lms_courses table
-- This migration adds the trainer_type field for internal/external trainer distinction

USE ymsofterp;

-- Add trainer_type field
ALTER TABLE lms_courses 
ADD COLUMN trainer_type ENUM('internal', 'external') DEFAULT 'internal' AFTER instructor_id;

-- Add index for better performance
CREATE INDEX idx_lms_courses_trainer_type ON lms_courses(trainer_type);

-- Update existing courses to have default trainer_type
UPDATE lms_courses 
SET trainer_type = 'internal' 
WHERE trainer_type IS NULL;

-- Verify the changes
SELECT 
    id,
    title,
    instructor_id,
    trainer_type,
    external_trainer_name,
    created_at
FROM lms_courses 
LIMIT 5;
