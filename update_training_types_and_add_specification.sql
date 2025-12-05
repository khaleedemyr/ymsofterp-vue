-- Update training types from (online, offline) to (online, in_class, practice)
-- and add specification field (generic, departemental) to lms_courses table

USE ymsofterp;

-- Step 1: Update existing 'offline' values to 'in_class' (since offline becomes in_class)
UPDATE lms_courses SET type = 'in_class' WHERE type = 'offline';

-- Step 2: Modify the type column to use new ENUM values
ALTER TABLE lms_courses 
MODIFY COLUMN type ENUM('online', 'in_class', 'practice') DEFAULT 'in_class' 
COMMENT 'Training type: online, in_class, or practice';

-- Step 3: Add specification field
ALTER TABLE lms_courses 
ADD COLUMN specification ENUM('generic', 'departemental') DEFAULT 'generic' 
AFTER type
COMMENT 'Training specification: generic or departemental';

-- Step 4: Add index for better performance
CREATE INDEX idx_lms_courses_specification ON lms_courses(specification);
CREATE INDEX idx_lms_courses_type_specification ON lms_courses(type, specification);

-- Verify the changes
SELECT 
    id,
    title,
    type,
    specification,
    course_type,
    created_at
FROM lms_courses 
LIMIT 10;
