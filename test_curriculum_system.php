<?php
/**
 * Test Curriculum System
 * Script untuk testing dan debugging sistem kurikulum LMS
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\LmsCourse;
use App\Models\LmsCurriculumItem;
use App\Models\LmsCurriculumMaterial;
use App\Models\LmsQuiz;
use App\Models\LmsQuestionnaire;

echo "=== LMS Curriculum System Test ===\n\n";

try {
    // 1. Test database connection
    echo "1. Testing database connection...\n";
    $connection = DB::connection();
    $connection->getPdo();
    echo "✓ Database connected successfully\n\n";
    
    // 2. Check if tables exist
    echo "2. Checking table structure...\n";
    $tables = ['lms_courses', 'lms_curriculum_items', 'lms_curriculum_materials', 'lms_quizzes', 'lms_questionnaires'];
    
    foreach ($tables as $table) {
        try {
            $exists = DB::select("SHOW TABLES LIKE '$table'");
            if (!empty($exists)) {
                echo "✓ Table '$table' exists\n";
                
                // Show table structure
                $columns = DB::select("DESCRIBE $table");
                echo "  Columns: " . count($columns) . "\n";
                foreach ($columns as $column) {
                    echo "    - {$column->Field}: {$column->Type}\n";
                }
            } else {
                echo "✗ Table '$table' does not exist\n";
            }
        } catch (Exception $e) {
            echo "✗ Error checking table '$table': " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    // 3. Check if courses exist
    echo "3. Checking courses...\n";
    try {
        $courses = LmsCourse::all();
        echo "✓ Found " . $courses->count() . " courses\n";
        
        if ($courses->count() > 0) {
            $firstCourse = $courses->first();
            echo "  First course: ID={$firstCourse->id}, Title='{$firstCourse->title}'\n";
            
            // Check curriculum items for first course
            $curriculumItems = $firstCourse->curriculumItems;
            echo "  Curriculum items: " . $curriculumItems->count() . "\n";
            
            foreach ($curriculumItems as $item) {
                echo "    - Session {$item->session_number}: {$item->session_title}\n";
            }
        }
    } catch (Exception $e) {
        echo "✗ Error checking courses: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // 4. Check quizzes and questionnaires
    echo "4. Checking quizzes and questionnaires...\n";
    try {
        $quizzes = LmsQuiz::all();
        echo "✓ Found " . $quizzes->count() . " quizzes\n";
        
        $questionnaires = LmsQuestionnaire::all();
        echo "✓ Found " . $questionnaires->count() . " questionnaires\n";
    } catch (Exception $e) {
        echo "✗ Error checking quizzes/questionnaires: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // 5. Test creating a curriculum item
    echo "5. Testing curriculum item creation...\n";
    try {
        if ($courses->count() > 0) {
            $course = $courses->first();
            
            // Check if session number 1 already exists
            $existingSession = LmsCurriculumItem::where('course_id', $course->id)
                ->where('session_number', 1)
                ->first();
            
            if ($existingSession) {
                echo "✓ Session 1 already exists for course {$course->id}\n";
                echo "  Session title: {$existingSession->session_title}\n";
            } else {
                echo "  Creating test session...\n";
                
                $curriculumItem = LmsCurriculumItem::create([
                    'course_id' => $course->id,
                    'session_number' => 1,
                    'session_title' => 'Test Session',
                    'session_description' => 'This is a test session',
                    'order_number' => 1,
                    'is_required' => true,
                    'estimated_duration_minutes' => 30,
                    'status' => 'active',
                    'created_by' => 1, // Assuming user ID 1 exists
                    'updated_by' => 1,
                ]);
                
                echo "✓ Test session created successfully with ID: {$curriculumItem->id}\n";
            }
        } else {
            echo "✗ No courses available for testing\n";
        }
    } catch (Exception $e) {
        echo "✗ Error testing curriculum creation: " . $e->getMessage() . "\n";
        echo "  Stack trace: " . $e->getTraceAsString() . "\n";
    }
    echo "\n";
    
    // 6. Test API endpoint
    echo "6. Testing API endpoint...\n";
    try {
        if ($courses->count() > 0) {
            $course = $courses->first();
            $url = "/lms/courses/{$course->id}/curriculum";
            echo "  Testing URL: $url\n";
            
            // This would normally be a real HTTP request
            // For now, just simulate the controller logic
            $curriculumItems = $course->curriculumItems()
                ->with(['quiz', 'questionnaire', 'materials'])
                ->orderBy('order_number')
                ->get();
            
            echo "  ✓ API logic works, found " . $curriculumItems->count() . " items\n";
        }
    } catch (Exception $e) {
        echo "✗ Error testing API: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    echo "=== Test completed ===\n";
    
} catch (Exception $e) {
    echo "✗ Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
