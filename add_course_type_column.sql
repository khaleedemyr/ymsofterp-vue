-- Add course_type column to lms_courses table
ALTER TABLE lms_courses 
ADD COLUMN course_type ENUM('mandatory', 'optional') DEFAULT 'optional' 
AFTER type;

-- Add comment to the column
ALTER TABLE lms_courses 
MODIFY COLUMN course_type ENUM('mandatory', 'optional') DEFAULT 'optional' 
COMMENT 'Course type: mandatory or optional';
