-- Fix Quiz and Questionnaire Saving Issues
-- This script fixes the problem where quiz and questionnaire items are not being saved
-- properly in the LMS curriculum materials system.

USE ymsofterp;

-- 1. Check current table structure
SELECT '=== CHECKING TABLE STRUCTURE ===' as status;

-- Show current columns
DESCRIBE lms_curriculum_materials;

-- 2. Add missing columns if they don't exist
SELECT '=== ADDING MISSING COLUMNS ===' as status;

-- Add quiz_id column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'ymsofterp' 
     AND TABLE_NAME = 'lms_curriculum_materials' 
     AND COLUMN_NAME = 'quiz_id') = 0,
    'ALTER TABLE lms_curriculum_materials ADD COLUMN quiz_id BIGINT UNSIGNED NULL AFTER file_type',
    'SELECT "quiz_id column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add questionnaire_id column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'ymsofterp' 
     AND TABLE_NAME = 'lms_curriculum_materials' 
     AND COLUMN_NAME = 'questionnaire_id') = 0,
    'ALTER TABLE lms_curriculum_materials ADD COLUMN questionnaire_id BIGINT UNSIGNED NULL AFTER quiz_id',
    'SELECT "questionnaire_id column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Add foreign key constraints if they don't exist
SELECT '=== ADDING FOREIGN KEY CONSTRAINTS ===' as status;

-- Add quiz foreign key constraint
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = 'ymsofterp' 
     AND TABLE_NAME = 'lms_curriculum_materials' 
     AND COLUMN_NAME = 'quiz_id' 
     AND CONSTRAINT_NAME IS NOT NULL) = 0,
    'ALTER TABLE lms_curriculum_materials ADD CONSTRAINT fk_curriculum_materials_quiz FOREIGN KEY (quiz_id) REFERENCES lms_quizzes(id) ON DELETE SET NULL',
    'SELECT "quiz foreign key constraint already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add questionnaire foreign key constraint
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = 'ymsofterp' 
     AND TABLE_NAME = 'lms_curriculum_materials' 
     AND COLUMN_NAME = 'questionnaire_id' 
     AND CONSTRAINT_NAME IS NOT NULL) = 0,
    'ALTER TABLE lms_curriculum_materials ADD CONSTRAINT fk_curriculum_materials_questionnaire FOREIGN KEY (questionnaire_id) REFERENCES lms_questionnaires(id) ON DELETE SET NULL',
    'SELECT "questionnaire foreign key constraint already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Add indexes for better performance
SELECT '=== ADDING INDEXES ===' as status;

-- Add index for quiz_id if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = 'ymsofterp' 
     AND TABLE_NAME = 'lms_curriculum_materials' 
     AND INDEX_NAME = 'idx_curriculum_materials_quiz_id') = 0,
    'CREATE INDEX idx_curriculum_materials_quiz_id ON lms_curriculum_materials(quiz_id)',
    'SELECT "quiz_id index already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for questionnaire_id if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = 'ymsofterp' 
     AND TABLE_NAME = 'lms_curriculum_materials' 
     AND INDEX_NAME = 'idx_curriculum_materials_questionnaire_id') = 0,
    'CREATE INDEX idx_curriculum_materials_questionnaire_id ON lms_curriculum_materials(questionnaire_id)',
    'SELECT "questionnaire_id index already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Check existing data
SELECT '=== CHECKING EXISTING DATA ===' as status;

-- Count total materials
SELECT COUNT(*) as total_materials FROM lms_curriculum_materials;

-- Count materials with quiz_id
SELECT COUNT(*) as materials_with_quiz FROM lms_curriculum_materials WHERE quiz_id IS NOT NULL;

-- Count materials with questionnaire_id
SELECT COUNT(*) as materials_with_questionnaire FROM lms_curriculum_materials WHERE questionnaire_id IS NOT NULL;

-- 6. Check related tables
SELECT '=== CHECKING RELATED TABLES ===' as status;

-- Check if lms_quizzes table exists
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'lms_quizzes table exists'
        ELSE 'lms_quizzes table does not exist'
    END as quiz_table_status
FROM information_schema.tables 
WHERE table_schema = 'ymsofterp' AND table_name = 'lms_quizzes';

-- Check if lms_questionnaires table exists
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'lms_questionnaires table exists'
        ELSE 'lms_questionnaires table does not exist'
    END as questionnaire_table_status
FROM information_schema.tables 
WHERE table_schema = 'ymsofterp' AND table_name = 'lms_questionnaires';

-- 7. Show sample data from quizzes and questionnaires
SELECT '=== SAMPLE QUIZ DATA ===' as status;
SELECT id, title FROM lms_quizzes LIMIT 3;

SELECT '=== SAMPLE QUESTIONNAIRE DATA ===' as status;
SELECT id, title FROM lms_questionnaires LIMIT 3;

-- 8. Test data insertion (optional - uncomment if you want to test)
-- SELECT '=== TESTING DATA INSERTION ===' as status;

-- Test quiz material insertion (uncomment to test)
/*
INSERT INTO lms_curriculum_materials 
(title, description, quiz_id, estimated_duration_minutes, status, created_by, created_at) 
VALUES 
('Test Quiz Material', 'This is a test quiz material', 1, 30, 'active', 1, NOW());

SELECT 'Test quiz material created' as message;
SELECT id, title, quiz_id, questionnaire_id FROM lms_curriculum_materials WHERE title = 'Test Quiz Material';

-- Clean up test data
DELETE FROM lms_curriculum_materials WHERE title = 'Test Quiz Material';
SELECT 'Test data cleaned up' as message;
*/

-- 9. Final verification
SELECT '=== FINAL VERIFICATION ===' as status;

-- Show final table structure
DESCRIBE lms_curriculum_materials;

-- Show summary statistics
SELECT 
    COUNT(*) as total_materials,
    SUM(CASE WHEN quiz_id IS NOT NULL THEN 1 ELSE 0 END) as with_quiz,
    SUM(CASE WHEN questionnaire_id IS NOT NULL THEN 1 ELSE 0 END) as with_questionnaire,
    SUM(CASE WHEN quiz_id IS NULL AND questionnaire_id IS NULL THEN 1 ELSE 0 END) as without_references
FROM lms_curriculum_materials;

-- Show foreign key constraints
SELECT 
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'ymsofterp' 
AND TABLE_NAME = 'lms_curriculum_materials' 
AND REFERENCED_TABLE_NAME IS NOT NULL;

SELECT '=== FIX COMPLETED ===' as status;
SELECT 'Quiz and Questionnaire saving fix completed successfully!' as message;
SELECT 'Next steps:' as next_steps;
SELECT '1. Test creating a course with quiz items in your LMS application' as step1;
SELECT '2. Test creating a course with questionnaire items' as step2;
SELECT '3. Verify that the data is being saved correctly' as step3;
SELECT '4. Check the Laravel logs for any remaining errors' as step4;
