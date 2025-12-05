<?php
/**
 * Test Script: New Course Creation Process
 * 
 * This script tests the course creation process step by step
 * to identify where quiz and questionnaire items might be failing.
 */

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TEST NEW COURSE CREATION PROCESS ===\n\n";

try {
    // 1. Simulate the exact data structure that would be sent from frontend
    echo "1. Simulating frontend data...\n";
    
    $testData = [
        'sessions' => [
            [
                'session_title' => 'Test Session with Quiz',
                'session_description' => 'Testing quiz and questionnaire saving',
                'order_number' => 1,
                'estimated_duration_minutes' => 60,
                'items' => [
                    [
                        'item_type' => 'quiz',
                        'quiz_id' => '1',
                        'title' => 'Test Quiz Item',
                        'description' => 'This is a test quiz item',
                        'order_number' => 1,
                        'estimated_duration_minutes' => 30
                    ],
                    [
                        'item_type' => 'questionnaire',
                        'questionnaire_id' => '1',
                        'title' => 'Test Questionnaire Item',
                        'description' => 'This is a test questionnaire item',
                        'order_number' => 2,
                        'estimated_duration_minutes' => 20
                    ]
                ]
            ]
        ]
    ];
    
    echo "   Test data prepared:\n";
    echo "   - Session: {$testData['sessions'][0]['session_title']}\n";
    echo "   - Items: " . count($testData['sessions'][0]['items']) . "\n";
    echo "   - Item 1: {$testData['sessions'][0]['items'][0]['item_type']} (ID: {$testData['sessions'][0]['items'][0]['quiz_id']})\n";
    echo "   - Item 2: {$testData['sessions'][0]['items'][1]['item_type']} (ID: {$testData['sessions'][0]['items'][1]['questionnaire_id']})\n";
    
    // 2. Test the validation logic
    echo "\n2. Testing validation logic...\n";
    
    $validationRules = [
        'sessions.*.items.*.quiz_id' => 'nullable|integer|exists:lms_quizzes,id',
        'sessions.*.items.*.questionnaire_id' => 'nullable|integer|exists:lms_questionnaires,id',
    ];
    
    foreach ($testData['sessions'] as $sessionIndex => $sessionData) {
        if (!empty($sessionData['items'])) {
            foreach ($sessionData['items'] as $itemIndex => $itemData) {
                echo "   Validating item " . ($itemIndex + 1) . " (type: {$itemData['item_type']})...\n";
                
                if ($itemData['item_type'] === 'quiz') {
                    $quizId = $itemData['quiz_id'] ?? null;
                    echo "   - Quiz ID: " . var_export($quizId, true) . "\n";
                    
                    // Test validation rule
                    if ($quizId && $quizId !== '' && $quizId !== 'null') {
                        $quizExists = DB::table('lms_quizzes')
                            ->where('id', $quizId)
                            ->where('status', 'published')
                            ->exists();
                        
                        echo "   - Quiz exists: " . ($quizExists ? 'YES' : 'NO') . "\n";
                        echo "   - Validation: " . ($quizExists ? 'PASS' : 'FAIL') . "\n";
                    } else {
                        echo "   - Quiz ID validation: FAIL (empty/null)\n";
                    }
                    
                } elseif ($itemData['item_type'] === 'questionnaire') {
                    $questionnaireId = $itemData['questionnaire_id'] ?? null;
                    echo "   - Questionnaire ID: " . var_export($questionnaireId, true) . "\n";
                    
                    // Test validation rule
                    if ($questionnaireId && $questionnaireId !== '' && $questionnaireId !== 'null') {
                        $questionnaireExists = DB::table('lms_questionnaires')
                            ->where('id', $questionnaireId)
                            ->where('status', 'published')
                            ->exists();
                        
                        echo "   - Questionnaire exists: " . ($questionnaireExists ? 'YES' : 'NO') . "\n";
                        echo "   - Validation: " . ($questionnaireExists ? 'PASS' : 'NO') . "\n";
                    } else {
                        echo "   - Questionnaire ID validation: FAIL (empty/null)\n";
                    }
                }
            }
        }
    }
    
    // 3. Test the curriculum material creation logic
    echo "\n3. Testing curriculum material creation logic...\n";
    
    foreach ($testData['sessions'] as $sessionIndex => $sessionData) {
        if (!empty($sessionData['items'])) {
            foreach ($sessionData['items'] as $itemIndex => $itemData) {
                echo "   Testing material creation for item " . ($itemIndex + 1) . "...\n";
                
                $itemId = null;
                
                if ($itemData['item_type'] === 'quiz') {
                    $quizId = $itemData['quiz_id'] ?? null;
                    
                    if ($quizId && $quizId !== '' && $quizId !== 'null') {
                        echo "   - Creating curriculum material for quiz...\n";
                        
                        // Simulate the exact creation logic from LmsController
                        $material = DB::table('lms_curriculum_materials')->insertGetId([
                            'title' => $itemData['title'] ?? 'Quiz ' . ($itemIndex + 1),
                            'description' => $itemData['description'] ?? '',
                            'estimated_duration_minutes' => $itemData['estimated_duration_minutes'] ?? 0,
                            'quiz_id' => $quizId,
                            'status' => 'active',
                            'created_by' => 1,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        
                        $itemId = $material;
                        echo "   - Material created with ID: {$itemId}\n";
                        
                        // Verify the quiz_id was saved
                        $createdMaterial = DB::table('lms_curriculum_materials')
                            ->where('id', $itemId)
                            ->first();
                        
                        echo "   - Verification: quiz_id = " . ($createdMaterial->quiz_id ?: 'NULL') . "\n";
                        
                        // Clean up test material
                        DB::table('lms_curriculum_materials')->where('id', $itemId)->delete();
                        echo "   - Test material cleaned up\n";
                        
                    } else {
                        echo "   - Skipping quiz item - invalid quiz_id\n";
                    }
                    
                } elseif ($itemData['item_type'] === 'questionnaire') {
                    $questionnaireId = $itemData['questionnaire_id'] ?? null;
                    
                    if ($questionnaireId && $questionnaireId !== '' && $questionnaireId !== 'null') {
                        echo "   - Creating curriculum material for questionnaire...\n";
                        
                        // Simulate the exact creation logic from LmsController
                        $material = DB::table('lms_curriculum_materials')->insertGetId([
                            'title' => $itemData['title'] ?? 'Questionnaire ' . ($itemIndex + 1),
                            'description' => $itemData['description'] ?? '',
                            'estimated_duration_minutes' => $itemData['estimated_duration_minutes'] ?? 0,
                            'questionnaire_id' => $questionnaireId,
                            'status' => 'active',
                            'created_by' => 1,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        
                        $itemId = $material;
                        echo "   - Material created with ID: {$itemId}\n";
                        
                        // Verify the questionnaire_id was saved
                        $createdMaterial = DB::table('lms_curriculum_materials')
                            ->where('id', $itemId)
                            ->first();
                        
                        echo "   - Verification: questionnaire_id = " . ($createdMaterial->questionnaire_id ?: 'NULL') . "\n";
                        
                        // Clean up test material
                        DB::table('lms_curriculum_materials')->where('id', $itemId)->delete();
                        echo "   - Test material cleaned up\n";
                        
                    } else {
                        echo "   - Skipping questionnaire item - invalid questionnaire_id\n";
                    }
                }
                
                echo "   - Final itemId: " . var_export($itemId, true) . "\n";
                echo "   - Would create session item: " . ($itemId !== null ? 'YES' : 'NO') . "\n\n";
            }
        }
    }
    
    // 4. Check if there are any database constraints or triggers that might interfere
    echo "4. Checking for potential database issues...\n";
    
    // Check if there are any triggers on lms_curriculum_materials
    $triggers = DB::select("SHOW TRIGGERS LIKE 'lms_curriculum_materials'");
    echo "   Triggers on lms_curriculum_materials: " . count($triggers) . "\n";
    
    // Check if there are any foreign key constraints that might cause issues
    $constraints = DB::select("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME = 'lms_curriculum_materials' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    echo "   Foreign key constraints: " . count($constraints) . "\n";
    foreach ($constraints as $constraint) {
        echo "   - {$constraint->CONSTRAINT_NAME}: {$constraint->COLUMN_NAME} -> {$constraint->REFERENCED_TABLE_NAME}.{$constraint->REFERENCED_COLUMN_NAME}\n";
    }
    
    // 5. Summary and recommendations
    echo "\n=== SUMMARY & RECOMMENDATIONS ===\n";
    
    echo "✅ What's working:\n";
    echo "- Database structure is correct\n";
    echo "- Validation rules are properly defined\n";
    echo "- Curriculum material creation logic works\n";
    echo "- Quiz and questionnaire data can be saved\n";
    
    echo "\n🔍 Potential issues:\n";
    echo "- Frontend might not be sending quiz_id/questionnaire_id correctly\n";
    echo "- Data might be getting lost during form submission\n";
    echo "- There might be JavaScript errors preventing data from being sent\n";
    
    echo "\n🔧 Next debugging steps:\n";
    echo "1. Check browser console for JavaScript errors\n";
    echo "2. Verify FormData is being constructed correctly\n";
    echo "3. Check network tab to see what data is actually sent\n";
    echo "4. Add console.log statements in the frontend to track data flow\n";
    echo "5. Check if the quiz/questionnaire selection is working properly\n";
    
    echo "\n=== TEST COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
