-- Update Curriculum Structure for Session-based Approach
-- This migration updates the curriculum system to support sessions with quizzes, materials, and questionnaires

USE ymsofterp;

-- 1. Update lms_curriculum_items table structure
ALTER TABLE lms_curriculum_items 
ADD COLUMN course_id BIGINT UNSIGNED AFTER id,
ADD COLUMN session_number INT DEFAULT 1 AFTER course_id,
ADD COLUMN session_title VARCHAR(255) AFTER session_number,
ADD COLUMN session_description TEXT AFTER session_title,
ADD COLUMN quiz_id BIGINT UNSIGNED NULL AFTER session_description,
ADD COLUMN questionnaire_id BIGINT UNSIGNED NULL AFTER quiz_id,
ADD COLUMN updated_by BIGINT UNSIGNED NULL AFTER created_by;

-- 2. Add foreign key constraints
ALTER TABLE lms_curriculum_items 
ADD CONSTRAINT fk_curriculum_items_course_id 
FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_curriculum_items_quiz_id 
FOREIGN KEY (quiz_id) REFERENCES lms_quizzes(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_curriculum_items_questionnaire_id 
FOREIGN KEY (questionnaire_id) REFERENCES lms_questionnaires(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_curriculum_items_updated_by 
FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- 3. Add indexes for better performance
CREATE INDEX idx_curriculum_items_course_id ON lms_curriculum_items(course_id);
CREATE INDEX idx_curriculum_items_session_number ON lms_curriculum_items(session_number);
CREATE INDEX idx_curriculum_items_quiz_id ON lms_curriculum_items(quiz_id);
CREATE INDEX idx_curriculum_items_questionnaire_id ON lms_curriculum_items(questionnaire_id);

-- 4. Update lms_curriculum_materials table structure
ALTER TABLE lms_curriculum_materials 
ADD COLUMN title VARCHAR(255) AFTER curriculum_item_id,
ADD COLUMN description TEXT AFTER title,
ADD COLUMN material_type ENUM('pdf', 'image', 'video', 'document', 'link') DEFAULT 'document' AFTER description,
ADD COLUMN estimated_duration_minutes INT DEFAULT 0 AFTER material_type,
ADD COLUMN updated_by BIGINT UNSIGNED NULL AFTER created_by;

-- 5. Add foreign key for updated_by
ALTER TABLE lms_curriculum_materials 
ADD CONSTRAINT fk_curriculum_materials_updated_by 
FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- 6. Add indexes for materials
CREATE INDEX idx_curriculum_materials_type ON lms_curriculum_materials(material_type);
CREATE INDEX idx_curriculum_materials_order ON lms_curriculum_materials(order_number);

-- 7. Update existing data (if any)
-- Set default values for existing records
UPDATE lms_curriculum_items 
SET course_id = 1, 
    session_number = 1, 
    session_title = CONCAT('Sesi ', order_number),
    session_description = description,
    updated_by = created_by
WHERE course_id IS NULL;

-- 8. Remove old columns that are no longer needed
-- ALTER TABLE lms_curriculum_items 
-- DROP COLUMN curriculum_id,
-- DROP COLUMN item_type,
-- DROP COLUMN item_id,
-- DROP COLUMN title,
-- DROP COLUMN description,
-- DROP COLUMN passing_score,
-- DROP COLUMN max_attempts;

-- 9. Update lms_curriculum_materials to use new structure
UPDATE lms_curriculum_materials 
SET title = 'Materi',
    description = 'Materi pembelajaran',
    material_type = 'document',
    estimated_duration_minutes = 30,
    updated_by = created_by
WHERE title IS NULL;

-- 10. Verify the changes
SELECT 
    'lms_curriculum_items' as table_name,
    COUNT(*) as total_records
FROM lms_curriculum_items
UNION ALL
SELECT 
    'lms_curriculum_materials' as table_name,
    COUNT(*) as total_records
FROM lms_curriculum_materials;

-- 11. Show sample data structure
SELECT 
    ci.id,
    ci.course_id,
    ci.session_number,
    ci.session_title,
    ci.session_description,
    ci.quiz_id,
    ci.questionnaire_id,
    ci.order_number,
    ci.status,
    ci.created_at
FROM lms_curriculum_items ci
LIMIT 5;
