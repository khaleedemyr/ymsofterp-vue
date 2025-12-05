<?php
/**
 * Migration Script: Add quiz_id and questionnaire_id to lms_curriculum_materials
 * 
 * This script safely adds the required columns for quiz and questionnaire references
 * Run this script after backing up your database
 */

// Database configuration - adjust these values according to your setup
$host = 'localhost';
$dbname = 'db_justus'; // Adjust to your database name
$username = 'root';     // Adjust to your database username
$password = '';         // Adjust to your database password

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    echo "Starting migration...\n\n";
    
    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE lms_curriculum_materials");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('quiz_id', $columns)) {
        echo "Column 'quiz_id' already exists. Skipping...\n";
    } else {
        // Add quiz_id column
        $pdo->exec("ALTER TABLE lms_curriculum_materials ADD COLUMN quiz_id BIGINT UNSIGNED NULL AFTER file_type");
        echo "Added 'quiz_id' column successfully.\n";
    }
    
    if (in_array('questionnaire_id', $columns)) {
        echo "Column 'questionnaire_id' already exists. Skipping...\n";
    } else {
        // Add questionnaire_id column
        $pdo->exec("ALTER TABLE lms_curriculum_materials ADD COLUMN questionnaire_id BIGINT UNSIGNED NULL AFTER quiz_id");
        echo "Added 'questionnaire_id' column successfully.\n";
    }
    
    // Check if foreign key constraints exist
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                         WHERE TABLE_SCHEMA = '$dbname' 
                         AND TABLE_NAME = 'lms_curriculum_materials' 
                         AND COLUMN_NAME = 'quiz_id' 
                         AND REFERENCED_TABLE_NAME IS NOT NULL");
    $quizFkExists = $stmt->fetch();
    
    if (!$quizFkExists) {
        // Add foreign key constraint for quiz_id
        $pdo->exec("ALTER TABLE lms_curriculum_materials 
                    ADD CONSTRAINT fk_curriculum_materials_quiz 
                    FOREIGN KEY (quiz_id) REFERENCES lms_quizzes(id) ON DELETE SET NULL");
        echo "Added foreign key constraint for 'quiz_id' successfully.\n";
    } else {
        echo "Foreign key constraint for 'quiz_id' already exists. Skipping...\n";
    }
    
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                         WHERE TABLE_SCHEMA = '$dbname' 
                         AND TABLE_NAME = 'lms_curriculum_materials' 
                         AND COLUMN_NAME = 'questionnaire_id' 
                         AND REFERENCED_TABLE_NAME IS NOT NULL");
    $questionnaireFkExists = $stmt->fetch();
    
    if (!$questionnaireFkExists) {
        // Add foreign key constraint for questionnaire_id
        $pdo->exec("ALTER TABLE lms_curriculum_materials 
                    ADD CONSTRAINT fk_curriculum_materials_questionnaire 
                    FOREIGN KEY (questionnaire_id) REFERENCES lms_questionnaires(id) ON DELETE SET NULL");
        echo "Added foreign key constraint for 'questionnaire_id' successfully.\n";
    } else {
        echo "Foreign key constraint for 'questionnaire_id' already exists. Skipping...\n";
    }
    
    // Check if indexes exist
    $stmt = $pdo->query("SHOW INDEX FROM lms_curriculum_materials WHERE Key_name = 'idx_curriculum_materials_quiz_id'");
    $quizIndexExists = $stmt->fetch();
    
    if (!$quizIndexExists) {
        // Create index for quiz_id
        $pdo->exec("CREATE INDEX idx_curriculum_materials_quiz_id ON lms_curriculum_materials(quiz_id)");
        echo "Created index for 'quiz_id' successfully.\n";
    } else {
        echo "Index for 'quiz_id' already exists. Skipping...\n";
    }
    
    $stmt = $pdo->query("SHOW INDEX FROM lms_curriculum_materials WHERE Key_name = 'idx_curriculum_materials_questionnaire_id'");
    $questionnaireIndexExists = $stmt->fetch();
    
    if (!$questionnaireIndexExists) {
        // Create index for questionnaire_id
        $pdo->exec("CREATE INDEX idx_curriculum_materials_questionnaire_id ON lms_curriculum_materials(questionnaire_id)");
        echo "Created index for 'questionnaire_id' successfully.\n";
    } else {
        echo "Index for 'questionnaire_id' already exists. Skipping...\n";
    }
    
    echo "\nMigration completed successfully!\n";
    echo "New table structure:\n";
    
    // Show the new table structure
    $stmt = $pdo->query("DESCRIBE lms_curriculum_materials");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']} " . 
             ($column['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . 
             ($column['Key'] ? " ({$column['Key']})" : '') . "\n";
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
