<?php
/**
 * Fix Quiz and Questionnaire Saving Issues
 * 
 * This script fixes the problem where quiz and questionnaire items are not being saved
 * properly in the LMS curriculum materials system.
 */

// Database configuration
$host = 'localhost';
$dbname = 'ymsofterp'; // Adjust to your database name
$username = 'root';     // Adjust to your database username
$password = '';         // Adjust to your database password

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    echo "Starting quiz and questionnaire saving fix...\n\n";
    
    // 1. Check current table structure
    echo "=== CHECKING TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE lms_curriculum_materials");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasQuizId = false;
    $hasQuestionnaireId = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'quiz_id') {
            $hasQuizId = true;
            echo "✓ Column 'quiz_id' exists\n";
        }
        if ($column['Field'] === 'questionnaire_id') {
            $hasQuestionnaireId = true;
            echo "✓ Column 'questionnaire_id' exists\n";
        }
    }
    
    if (!$hasQuizId || !$hasQuestionnaireId) {
        echo "❌ Missing required columns. Adding them...\n";
        
        if (!$hasQuizId) {
            $pdo->exec("ALTER TABLE lms_curriculum_materials ADD COLUMN quiz_id BIGINT UNSIGNED NULL AFTER file_type");
            echo "✓ Added 'quiz_id' column\n";
        }
        
        if (!$hasQuestionnaireId) {
            $pdo->exec("ALTER TABLE lms_curriculum_materials ADD COLUMN questionnaire_id BIGINT UNSIGNED NULL AFTER quiz_id");
            echo "✓ Added 'questionnaire_id' column\n";
        }
    }
    
    // 2. Check if foreign key constraints exist
    echo "\n=== CHECKING FOREIGN KEY CONSTRAINTS ===\n";
    
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                         WHERE TABLE_SCHEMA = '$dbname' 
                         AND TABLE_NAME = 'lms_curriculum_materials' 
                         AND COLUMN_NAME IN ('quiz_id', 'questionnaire_id')");
    $constraints = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('fk_curriculum_materials_quiz', $constraints)) {
        try {
            $pdo->exec("ALTER TABLE lms_curriculum_materials 
                       ADD CONSTRAINT fk_curriculum_materials_quiz 
                       FOREIGN KEY (quiz_id) REFERENCES lms_quizzes(id) ON DELETE SET NULL");
            echo "✓ Added foreign key constraint for quiz_id\n";
        } catch (Exception $e) {
            echo "⚠ Warning: Could not add quiz foreign key: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✓ Foreign key constraint for quiz_id already exists\n";
    }
    
    if (!in_array('fk_curriculum_materials_questionnaire', $constraints)) {
        try {
            $pdo->exec("ALTER TABLE lms_curriculum_materials 
                       ADD CONSTRAINT fk_curriculum_materials_questionnaire 
                       FOREIGN KEY (questionnaire_id) REFERENCES lms_questionnaires(id) ON DELETE SET NULL");
            echo "✓ Added foreign key constraint for questionnaire_id\n";
        } catch (Exception $e) {
            echo "⚠ Warning: Could not add questionnaire foreign key: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✓ Foreign key constraint for questionnaire_id already exists\n";
    }
    
    // 3. Check existing data
    echo "\n=== CHECKING EXISTING DATA ===\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM lms_curriculum_materials");
    $totalMaterials = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total curriculum materials: $totalMaterials\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM lms_curriculum_materials WHERE quiz_id IS NOT NULL");
    $totalQuizMaterials = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Materials with quiz_id: $totalQuizMaterials\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM lms_curriculum_materials WHERE questionnaire_id IS NOT NULL");
    $totalQuestionnaireMaterials = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Materials with questionnaire_id: $totalQuestionnaireMaterials\n";
    
    // 4. Check if quizzes and questionnaires tables exist
    echo "\n=== CHECKING RELATED TABLES ===\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'lms_quizzes'");
    $quizzesTableExists = $stmt->rowCount() > 0;
    echo $quizzesTableExists ? "✓ lms_quizzes table exists\n" : "❌ lms_quizzes table does not exist\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'lms_questionnaires'");
    $questionnairesTableExists = $stmt->rowCount() > 0;
    echo $questionnairesTableExists ? "✓ lms_questionnaires table exists\n" : "❌ lms_questionnaires table does not exist\n";
    
    // 5. Check sample data from quizzes and questionnaires
    if ($quizzesTableExists) {
        $stmt = $pdo->query("SELECT id, title FROM lms_quizzes LIMIT 3");
        $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Sample quizzes:\n";
        foreach ($quizzes as $quiz) {
            echo "  - ID: {$quiz['id']}, Title: {$quiz['title']}\n";
        }
    }
    
    if ($questionnairesTableExists) {
        $stmt = $pdo->query("SELECT id, title FROM lms_questionnaires LIMIT 3");
        $questionnaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Sample questionnaires:\n";
        foreach ($questionnaires as $questionnaire) {
            echo "  - ID: {$questionnaire['id']}, Title: {$questionnaire['title']}\n";
        }
    }
    
    // 6. Test creating a sample quiz material
    echo "\n=== TESTING QUIZ MATERIAL CREATION ===\n";
    
    if ($quizzesTableExists && count($quizzes) > 0) {
        $quizId = $quizzes[0]['id'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO lms_curriculum_materials 
                                  (title, description, quiz_id, estimated_duration_minutes, status, created_by, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                'Test Quiz Material',
                'This is a test quiz material',
                $quizId,
                30,
                'active',
                1 // Assuming user ID 1 exists
            ]);
            
            $newMaterialId = $pdo->lastInsertId();
            echo "✓ Successfully created test quiz material with ID: $newMaterialId\n";
            
            // Verify the data was saved correctly
            $stmt = $pdo->prepare("SELECT id, title, quiz_id, questionnaire_id FROM lms_curriculum_materials WHERE id = ?");
            $stmt->execute([$newMaterialId]);
            $savedMaterial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "✓ Verified saved data:\n";
            echo "  - ID: {$savedMaterial['id']}\n";
            echo "  - Title: {$savedMaterial['title']}\n";
            echo "  - Quiz ID: {$savedMaterial['quiz_id']}\n";
            echo "  - Questionnaire ID: " . ($savedMaterial['questionnaire_id'] ?? 'NULL') . "\n";
            
            // Clean up test data
            $stmt = $pdo->prepare("DELETE FROM lms_curriculum_materials WHERE id = ?");
            $stmt->execute([$newMaterialId]);
            echo "✓ Cleaned up test data\n";
            
        } catch (Exception $e) {
            echo "❌ Error creating test quiz material: " . $e->getMessage() . "\n";
        }
    }
    
    // 7. Test creating a sample questionnaire material
    echo "\n=== TESTING QUESTIONNAIRE MATERIAL CREATION ===\n";
    
    if ($questionnairesTableExists && count($questionnaires) > 0) {
        $questionnaireId = $questionnaires[0]['id'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO lms_curriculum_materials 
                                  (title, description, questionnaire_id, estimated_duration_minutes, status, created_by, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                'Test Questionnaire Material',
                'This is a test questionnaire material',
                $questionnaireId,
                20,
                'active',
                1 // Assuming user ID 1 exists
            ]);
            
            $newMaterialId = $pdo->lastInsertId();
            echo "✓ Successfully created test questionnaire material with ID: $newMaterialId\n";
            
            // Verify the data was saved correctly
            $stmt = $pdo->prepare("SELECT id, title, quiz_id, questionnaire_id FROM lms_curriculum_materials WHERE id = ?");
            $stmt->execute([$newMaterialId]);
            $savedMaterial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "✓ Verified saved data:\n";
            echo "  - ID: {$savedMaterial['id']}\n";
            echo "  - Title: {$savedMaterial['title']}\n";
            echo "  - Quiz ID: " . ($savedMaterial['quiz_id'] ?? 'NULL') . "\n";
            echo "  - Questionnaire ID: {$savedMaterial['questionnaire_id']}\n";
            
            // Clean up test data
            $stmt = $pdo->prepare("DELETE FROM lms_curriculum_materials WHERE id = ?");
            $stmt->execute([$newMaterialId]);
            echo "✓ Cleaned up test data\n";
            
        } catch (Exception $e) {
            echo "❌ Error creating test questionnaire material: " . $e->getMessage() . "\n";
        }
    }
    
    // 8. Final verification
    echo "\n=== FINAL VERIFICATION ===\n";
    
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN quiz_id IS NOT NULL THEN 1 ELSE 0 END) as with_quiz,
                            SUM(CASE WHEN questionnaire_id IS NOT NULL THEN 1 ELSE 0 END) as with_questionnaire
                         FROM lms_curriculum_materials");
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Database structure verification complete:\n";
    echo "✓ Total materials: {$summary['total']}\n";
    echo "✓ Materials with quiz_id: {$summary['with_quiz']}\n";
    echo "✓ Materials with questionnaire_id: {$summary['with_questionnaire']}\n";
    
    echo "\n🎉 Quiz and Questionnaire saving fix completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Test creating a course with quiz items in your LMS application\n";
    echo "2. Test creating a course with questionnaire items\n";
    echo "3. Verify that the data is being saved correctly\n";
    echo "4. Check the Laravel logs for any remaining errors\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
