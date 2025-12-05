-- Migration: Add quiz_id and questionnaire_id to lms_curriculum_materials table
-- This allows curriculum materials to reference specific quizzes and questionnaires

-- Add quiz_id column
ALTER TABLE lms_curriculum_materials 
ADD COLUMN quiz_id BIGINT UNSIGNED NULL AFTER file_type,
ADD COLUMN questionnaire_id BIGINT UNSIGNED NULL AFTER quiz_id;

-- Add foreign key constraints
ALTER TABLE lms_curriculum_materials 
ADD CONSTRAINT fk_curriculum_materials_quiz 
FOREIGN KEY (quiz_id) REFERENCES lms_quizzes(id) ON DELETE SET NULL;

ALTER TABLE lms_curriculum_materials 
ADD CONSTRAINT fk_curriculum_materials_questionnaire 
FOREIGN KEY (questionnaire_id) REFERENCES lms_questionnaires(id) ON DELETE SET NULL;

-- Add indexes for better performance
CREATE INDEX idx_curriculum_materials_quiz_id ON lms_curriculum_materials(quiz_id);
CREATE INDEX idx_curriculum_materials_questionnaire_id ON lms_curriculum_materials(questionnaire_id);

-- Update existing materials that might have quiz or questionnaire data
-- This is optional and depends on your existing data structure
-- UPDATE lms_curriculum_materials 
-- SET quiz_id = (SELECT id FROM lms_quizzes WHERE title = lms_curriculum_materials.title LIMIT 1)
-- WHERE item_type = 'quiz' AND quiz_id IS NULL;

-- UPDATE lms_curriculum_materials 
-- SET questionnaire_id = (SELECT id FROM lms_questionnaires WHERE title = lms_curriculum_materials.title LIMIT 1)
-- WHERE item_type = 'questionnaire' AND questionnaire_id IS NULL;
