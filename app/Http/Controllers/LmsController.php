<?php

namespace App\Http\Controllers;

use App\Models\LmsCourse;
use App\Models\LmsCategory;
use App\Models\LmsEnrollment;
// use App\Models\LmsLesson; // REMOVED - using sessions instead
use App\Models\User;
use App\Models\Divisi;
use App\Models\Jabatan;
use App\Models\DataLevel;
use App\Models\DataOutlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LmsController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        
        // Get user's enrolled courses
        $enrolledCourses = LmsEnrollment::with(['course.category', 'course.instructor'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['enrolled', 'in_progress', 'completed'])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        // Get user's progress
        $userProgress = LmsEnrollment::where('user_id', $user->id)
            ->selectRaw('
                COUNT(*) as total_enrollments,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_courses,
                AVG(progress_percentage) as average_progress
            ')
            ->first();

        // Get recent courses (published)
        $recentCourses = LmsCourse::with(['category', 'instructor'])
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        // Get course statistics
        $courseStats = [
            'total_courses' => LmsCourse::count(),
            'published_courses' => LmsCourse::where('status', 'published')->count(),
            'total_enrollments' => LmsEnrollment::count(),
            'total_categories' => LmsCategory::count(),
        ];

        // Get top categories by course count
        $topCategories = LmsCategory::withCount('courses')
            ->where('status', 'active')
            ->orderBy('courses_count', 'desc')
            ->limit(5)
            ->get();

        // Get user's learning activity (last 30 days)
        $learningActivity = LmsEnrollment::where('user_id', $user->id)
            ->where('updated_at', '>=', now()->subDays(30))
            ->with(['course'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('Lms/Dashboard', [
            'enrolledCourses' => $enrolledCourses,
            'userProgress' => $userProgress,
            'recentCourses' => $recentCourses,
            'courseStats' => $courseStats,
            'topCategories' => $topCategories,
            'learningActivity' => $learningActivity,
        ]);
    }

    public function courses(Request $request)
    {
        
        $startTime = microtime(true);
        
        $user = auth()->user()->load('jabatan');
        
        // Check if user is admin/manager (can see all courses)
        $canSeeAllCourses = false;
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canSeeAllCourses = true;
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canSeeAllCourses = true;
        }


        $query = LmsCourse::with(['category', 'instructor.jabatan.divisi', 'instructor.jabatan.level', 'instructor.divisi', 'targetDivisions', 'competencies'])
            ->where('status', 'published');


        // If user is not admin/manager, filter courses based on user's data
        if (!$canSeeAllCourses) {
            $query->where(function ($q) use ($user) {
                // Courses for all divisions
                $q->where('target_type', 'all');
                
                // OR courses targeting user's division
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->where('target_type', 'single')
                         ->where('target_division_id', $user->division_id);
                });
                
                // OR courses targeting multiple divisions including user's division
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->where('target_type', 'multiple')
                         ->whereJsonContains('target_divisions', $user->division_id);
                });
                
                // OR courses targeting user's jabatan
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->whereNotNull('target_jabatan_ids')
                         ->whereJsonContains('target_jabatan_ids', $user->id_jabatan);
                });
                
                // OR courses targeting user's outlet
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->whereNotNull('target_outlet_ids')
                         ->whereJsonContains('target_outlet_ids', $user->id_outlet ?? null);
                });
            });
        }


        $query->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->category, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            // ->when($request->difficulty, function ($query, $difficulty) { // REMOVED - difficulty_level field removed
            //     $query->where('difficulty_level', $difficulty);
            // })
            ->when($request->division, function ($query, $divisionId) {
                $query->where(function ($q) use ($divisionId) {
                    $q->where('target_type', 'all')
                      ->orWhere('target_division_id', $divisionId)
                      ->orWhereJsonContains('target_divisions', $divisionId);
                });
            });

        // Optimize courses query with pagination and timeout
        try {
            
            $courses = $query->orderBy('created_at', 'desc')
                ->with(['category:id,name', 'instructor:id,nama_lengkap'])
                ->paginate(20); // Limit to 20 courses per page
                
            // Add instructor_name to each course for frontend compatibility
            $courses->getCollection()->transform(function ($course) {
                $course->instructor_name = $course->instructor ? $course->instructor->nama_lengkap : 'Tidak ada';
                return $course;
            });
        } catch (\Exception $e) {
            throw $e;
        }


        // Optimize categories query
        $categories = LmsCategory::active()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();


        // Optimize divisions query
        $divisions = Divisi::active()
            ->select(['id', 'nama_divisi'])
            ->orderBy('nama_divisi')
            ->get();


        // Optimize jabatans query with limited fields
        $jabatans = Jabatan::active()
            ->select(['id_jabatan', 'nama_jabatan', 'id_divisi'])
            ->with(['divisi:id,nama_divisi'])
            ->orderBy('nama_jabatan')
            ->limit(100) // Limit to prevent memory issues
            ->get();


        // Optimize outlets query
        $outlets = DataOutlet::where('status', 'A')
            ->select(['id_outlet', 'nama_outlet'])
            ->orderBy('nama_outlet')
            ->limit(100) // Limit to prevent memory issues
            ->get();


        // Get internal trainers (users with specific jabatan that can be trainers)
        $internalTrainers = User::where('status', 'A')
            ->whereNotNull('id_jabatan')
            ->select(['id', 'nama_lengkap', 'id_jabatan'])
            ->with(['jabatan:id_jabatan,nama_jabatan'])
            ->orderBy('nama_lengkap')
            ->get(); // Removed limit to show all available trainers

        

        // Optimize quizzes query
        $availableQuizzes = \App\Models\LmsQuiz::where('status', 'published')
            ->whereNull('course_id')
            ->select(['id', 'title', 'description'])
            ->orderBy('title')
            ->limit(50) // Limit to prevent memory issues
            ->get();
            

        // Optimize questionnaires query
        $availableQuestionnaires = \App\Models\LmsQuestionnaire::where('status', 'published')
            ->whereNull('course_id')
            ->select(['id', 'title', 'description'])
            ->orderBy('title')
            ->limit(50) // Limit to prevent memory issues
            ->get();



        // Get all competencies for the form
        $competencies = \App\Models\Competency::active()->orderBy('category')->orderBy('name')->get();

        // Get certificate templates for the form
        $certificateTemplates = \App\Models\CertificateTemplate::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Lms/Courses', [
            'courses' => $courses,
            'categories' => $categories,
            'divisions' => $divisions,
            'jabatans' => $jabatans,
            'outlets' => $outlets,
            'internalTrainers' => $internalTrainers,
            'user' => auth()->user(),
            'availableQuizzes' => $availableQuizzes,
            'availableQuestionnaires' => $availableQuestionnaires,
            'competencies' => $competencies,
            'certificateTemplates' => $certificateTemplates,
        ]);
    }

    public function archivedCourses(Request $request)
    {
        // Check if user can view archived courses
        $user = auth()->user();
        $canViewArchived = false;
        
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canViewArchived = true;
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canViewArchived = true;
        }
        
        if (!$canViewArchived) {
            abort(403, 'Unauthorized');
        }

        $query = LmsCourse::with(['category', 'instructor', 'targetDivisions'])
            ->where('status', 'archived')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->category, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            // ->when($request->difficulty, function ($query, $difficulty) { // REMOVED - difficulty_level field removed
            //     $query->where('difficulty_level', $difficulty);
            // })
            ->when($request->division, function ($query, $divisionId) {
                $query->where(function ($q) use ($divisionId) {
                    $q->where('target_type', 'all')
                      ->orWhere('target_division_id', $divisionId)
                      ->orWhereJsonContains('target_divisions', $divisionId);
                });
            });

        $courses = $query->orderBy('created_at', 'desc')->get();
        
        // Add instructor_name to each course for frontend compatibility
        $courses->transform(function ($course) {
            $course->instructor_name = $course->instructor ? $course->instructor->nama_lengkap : 'Tidak ada';
            return $course;
        });

        $categories = LmsCategory::active()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get divisions from tbl_data_divisi
        $divisions = Divisi::active()
            ->orderBy('nama_divisi')
            ->get(['id', 'nama_divisi']);

        // Get jabatans from tbl_data_jabatan
        $jabatans = Jabatan::active()
            ->with(['divisi', 'level'])
            ->orderBy('nama_jabatan')
            ->get(['id_jabatan', 'nama_jabatan', 'id_divisi', 'id_level']);

        // Get outlets from tbl_data_outlet
        $outlets = DataOutlet::where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);

        // Get internal trainers (users with specific jabatan that can be trainers)
        $internalTrainers = User::where('status', 'A')
            ->whereNotNull('id_jabatan')
            ->with('jabatan')
            ->orderBy('nama_lengkap')
            ->get(); // Removed limit to show all available trainers

        return Inertia::render('Lms/ArchivedCourses', [
            'courses' => $courses,
            'categories' => $categories,
            'divisions' => $divisions,
            'jabatans' => $jabatans,
            'outlets' => $outlets,
            'internalTrainers' => $internalTrainers,
            'user' => auth()->user(),
        ]);
    }

    public function storeCourse(Request $request)
    {
        $startTime = microtime(true);
        
        
        
        // CRITICAL FIX: Get material files from request before validation
        // Laravel validation removes files from input data, so we need to extract them first
        $materialFiles = [];
        if ($request->has('sessions')) {
            foreach ($request->input('sessions', []) as $sessionIndex => $session) {
                if (isset($session['items'])) {
                    foreach ($session['items'] as $itemIndex => $item) {
                        if (isset($item['item_type']) && $item['item_type'] === 'material') {
                            $key = "sessions.{$sessionIndex}.items.{$itemIndex}.material_files";
                            \Log::info('Checking for material files:', [
                                'key' => $key,
                                'has_file' => $request->hasFile($key),
                                'item_data' => $item
                            ]);
                            
                            if ($request->hasFile($key)) {
                                $files = $request->file($key);
                                $materialFiles["{$sessionIndex}_{$itemIndex}"] = $files;
                                \Log::info('Material files extracted before validation:', [
                                    'key' => $key,
                                    'files_count' => is_array($files) ? count($files) : 1,
                                    'files_type' => gettype($files),
                                    'files' => $files
                                ]);
                            } else {
                                \Log::warning('No material files found for key:', [
                                    'key' => $key,
                                    'available_files' => array_keys($request->allFiles())
                                ]);
                            }
                        }
                    }
                }
            }
        }
        
        
        
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'short_description' => 'nullable|string',
                'description' => 'required|string',
                'category_id' => 'required|exists:lms_categories,id',
                'target_type' => 'nullable|in:single,multiple,all',
                'target_division_id' => 'nullable|exists:tbl_data_divisi,id',
                'target_divisions' => 'nullable|array',
                'target_divisions.*' => 'exists:tbl_data_divisi,id',
                'target_jabatan_ids' => 'nullable|array',
                'target_jabatan_ids.*' => 'exists:tbl_data_jabatan,id_jabatan',
                'target_outlet_ids' => 'nullable|array',
                'target_outlet_ids.*' => 'exists:tbl_data_outlet,id_outlet',
                'duration_minutes' => 'required|integer|min:1',
                'type' => 'required|in:online,in_class,practice',
                'specification' => 'required|in:generic,departemental',
                'course_type' => 'required|in:mandatory,optional',
                'status' => 'required|in:draft,published,archived',
                // 'max_students' => 'nullable|integer|min:1', // REMOVED
                'sessions' => 'required|array|min:1',
                'sessions.*.session_title' => 'required|string|max:255',
                'sessions.*.session_description' => 'nullable|string',
                'sessions.*.order_number' => 'required|integer|min:1',
                'sessions.*.estimated_duration_minutes' => 'required|integer|min:1',
                'sessions.*.items' => 'nullable|array',
                'sessions.*.items.*.item_type' => 'required|in:quiz,material,questionnaire',
                'sessions.*.items.*.item_id' => 'nullable|integer',
                'sessions.*.items.*.title' => 'nullable|string|max:255',
                'sessions.*.items.*.description' => 'nullable|string',
                'sessions.*.items.*.order_number' => 'required|integer|min:1',
                'sessions.*.items.*.estimated_duration_minutes' => 'nullable|integer|min:0',
                'sessions.*.items.*.quiz_id' => 'nullable|integer|exists:lms_quizzes,id',
                'sessions.*.items.*.questionnaire_id' => 'nullable|integer|exists:lms_questionnaires,id',
                // 'requirements' => 'nullable|array', // REMOVED - requirements field removed
                // 'requirements.*' => 'string|max:500', // REMOVED - requirements field removed
                'certificate_template_id' => 'nullable|exists:certificate_templates,id',
                'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'competencies' => 'nullable|array',
                'competencies.*.competency_id' => 'required|exists:competencies,id',
                'new_competencies' => 'nullable|array',
                'new_competencies.*.name' => 'required|string|max:255'
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }
        

        // Set default values
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();


        // Create slug from title
        $validated['slug'] = Str::slug($validated['title']);

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('lms/thumbnails', 'public');
            $validated['thumbnail'] = $thumbnailPath;
        }

        // Handle target divisions based on target_type
        $targetDivisions = $request->input('target_divisions', []);
        
        if ($validated['target_type'] === 'all') {
            $validated['target_division_id'] = null;
            $validated['target_divisions'] = null;
        } elseif ($validated['target_type'] === 'single') {
            $validated['target_divisions'] = null;
        } elseif ($validated['target_type'] === 'multiple') {
            $validated['target_division_id'] = null;
            $validated['target_divisions'] = $targetDivisions;
        }

        // Filter out empty learning objectives and requirements
        if (isset($validated['learning_objectives'])) {
            $validated['learning_objectives'] = array_filter($validated['learning_objectives'], function($objective) {
                return !empty(trim($objective));
            });
        }
        
        if (isset($validated['requirements'])) {
            $validated['requirements'] = array_filter($validated['requirements'], function($requirement) {
                return !empty(trim($requirement));
            });
        }

        // Filter out empty target arrays
        if (isset($validated['target_jabatan_ids'])) {
            $validated['target_jabatan_ids'] = array_filter($validated['target_jabatan_ids'], function($id) {
                return !empty($id);
            });
        }
        
        if (isset($validated['target_outlet_ids'])) {
            $validated['target_outlet_ids'] = array_filter($validated['target_outlet_ids'], function($id) {
                return !empty($id);
            });
        }

        // Final data yang akan disimpan
        
        // Custom validation: At least one target must be selected
        $hasDivision = !empty($validated['target_division_id']) || 
                      !empty($validated['target_divisions'] ?? []) || 
                      ($validated['target_type'] ?? '') === 'all';
        $hasJabatan = !empty($validated['target_jabatan_ids'] ?? []);
        $hasOutlet = !empty($validated['target_outlet_ids'] ?? []);
        
        if (!$hasDivision && !$hasJabatan && !$hasOutlet) {
            return back()->withErrors([
                'target' => 'Minimal harus memilih satu target: divisi, jabatan, atau outlet.'
            ])->withInput();
        }
        
        try {
            DB::beginTransaction();
            // Create the course
            
            $course = LmsCourse::create([
                'title' => $validated['title'],
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'],
                'category_id' => $validated['category_id'],
                'target_type' => $validated['target_type'],
                'target_division_id' => $validated['target_division_id'] ?? null,
                'target_divisions' => isset($validated['target_divisions']) && !empty($validated['target_divisions']) ? json_encode($validated['target_divisions']) : null,
                'target_jabatan_ids' => isset($validated['target_jabatan_ids']) && !empty($validated['target_jabatan_ids']) ? json_encode($validated['target_jabatan_ids']) : null,
                'target_outlet_ids' => isset($validated['target_outlet_ids']) && !empty($validated['target_outlet_ids']) ? json_encode($validated['target_outlet_ids']) : null,
                'duration_minutes' => $validated['duration_minutes'],
                'type' => $validated['type'],
                'specification' => $validated['specification'],
                'course_type' => $validated['course_type'],
                'status' => $validated['status'],
                // 'max_students' => $validated['max_students'] ?? null, // REMOVED
                'thumbnail' => $validated['thumbnail'] ?? null,
                'certificate_template_id' => $validated['certificate_template_id'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id()
            ]);
            

            // Create requirements if any - REMOVED - requirements field removed
            // if (isset($validated['requirements']) && !empty($validated['requirements'])) {
            //     \Log::info('=== CREATING REQUIREMENTS ===');
            //     foreach ($validated['requirements'] as $requirement) {
            //         $course->requirements()->create([
            //             'requirement' => $requirement,
            //             'order_number' => 1 // Default order
            //         ]);
            //     }
            //     \Log::info('Requirements created successfully');
            // }

            // Sync target divisions for many-to-many relationship
            if ($validated['target_type'] === 'multiple' && !empty($targetDivisions)) {
                $course->targetDivisions()->sync($targetDivisions);
            }

            // Sync target jabatans for many-to-many relationship
            if (isset($validated['target_jabatan_ids']) && !empty($validated['target_jabatan_ids'])) {
                $course->targetJabatans()->sync($validated['target_jabatan_ids']);
            }

            // Sync target outlets for many-to-many relationship
            if (isset($validated['target_outlet_ids']) && !empty($validated['target_outlet_ids'])) {
                $course->targetOutlets()->sync($validated['target_outlet_ids']);
            }

            // Handle new competencies first
            $newCompetencyIds = [];
            if (isset($validated['new_competencies']) && !empty($validated['new_competencies'])) {
                foreach ($validated['new_competencies'] as $newCompetency) {
                    $competency = \App\Models\Competency::create([
                        'name' => $newCompetency['name'],
                        'description' => null,
                        'category' => null,
                        'level' => 'beginner',
                        'is_active' => true,
                    ]);
                    $newCompetencyIds[] = $competency->id;
                }
            }

            // Sync competencies for many-to-many relationship
            $competencyData = [];
            
            // Add existing competencies
            if (isset($validated['competencies']) && !empty($validated['competencies'])) {
                foreach ($validated['competencies'] as $competency) {
                    $competencyData[$competency['competency_id']] = [];
                }
            }
            
            // Add new competencies
            if (!empty($validated['new_competencies'])) {
                foreach ($validated['new_competencies'] as $index => $newCompetency) {
                    if (isset($newCompetencyIds[$index])) {
                        $competencyData[$newCompetencyIds[$index]] = [];
                    }
                }
            }
            
            if (!empty($competencyData)) {
                $course->competencies()->sync($competencyData);
            }

            // Create sessions with items (new flexible structure)
            if (!empty($validated['sessions'])) {
                \Log::info('=== CREATING SESSIONS ===');
                foreach ($validated['sessions'] as $sessionIndex => $sessionData) {
                    \Log::info('Creating session ' . ($sessionIndex + 1) . ':', [
                        'session_title' => $sessionData['session_title'],
                        'items_count' => count($sessionData['items'] ?? [])
                    ]);
                    
                    $session = $course->sessions()->create([
                        'title' => $sessionData['session_title'],
                        'description' => $sessionData['session_description'] ?? '',
                        'duration_minutes' => $sessionData['estimated_duration_minutes'],
                        'order_number' => $sessionData['order_number']
                    ]);
                    
                    \Log::info('Session created:', [
                        'session_id' => $session->id, 
                        'title' => $session->title,
                        'session_index' => $sessionIndex + 1
                    ]);
                    
                    // Create session items
                    if (!empty($sessionData['items'])) {
                        \Log::info('Creating session items for session ' . ($sessionIndex + 1) . ':', [
                            'items_count' => count($sessionData['items'])
                        ]);
                        
                        foreach ($sessionData['items'] as $itemIndex => $itemData) {
                            \Log::info('Creating item ' . ($itemIndex + 1) . ':', [
                                'item_type' => $itemData['item_type'],
                                'title' => $itemData['title'] ?? 'No title'
                            ]);
                            
                            // Handle material type with file uploads
                            $itemId = null; // Reset itemId for each item
                            
                            if ($itemData['item_type'] === 'material') {
                                \Log::info('Processing material item ' . ($itemIndex + 1) . ':', [
                                    'item_data' => $itemData,
                                    'material_files_key' => "{$sessionIndex}_{$itemIndex}"
                                ]);
                                
                                // Get files from extracted materialFiles array
                                $filesKey = "{$sessionIndex}_{$itemIndex}";
                                $uploadedFiles = $materialFiles[$filesKey] ?? null;
                                
                                if ($uploadedFiles && !empty($uploadedFiles)) {
                                    \Log::info('Material files found for item ' . ($itemIndex + 1) . ':', [
                                        'files_count' => count($uploadedFiles),
                                        'files' => $uploadedFiles
                                    ]);
                                    
                                    // Process all uploaded files, not just the first one
                                    $filePaths = [];
                                    $fileTypes = [];
                                    
                                    foreach ($uploadedFiles as $fileIndex => $uploadedFile) {
                                        if ($uploadedFile instanceof \Illuminate\Http\UploadedFile) {
                                            \Log::info('Processing file ' . ($fileIndex + 1) . ':', [
                                                'file_name' => $uploadedFile->getClientOriginalName(),
                                                'file_size' => $uploadedFile->getSize(),
                                                'mime_type' => $uploadedFile->getMimeType()
                                            ]);
                                        
                                            try {
                                                // Store file to storage
                                                $filePath = $uploadedFile->store('lms/materials', 'public');
                                                
                                                \Log::info('File ' . ($fileIndex + 1) . ' stored successfully:', [
                                                    'file_path' => $filePath,
                                                    'full_storage_path' => storage_path('app/public/' . $filePath)
                                                ]);
                                                
                                                // Determine file type
                                                $fileType = $this->getFileType($uploadedFile->getMimeType());
                                                
                                                $filePaths[] = $filePath;
                                                $fileTypes[] = $fileType;
                                                
                                            } catch (\Exception $e) {
                                                \Log::error('Error storing material file ' . ($fileIndex + 1) . ':', [
                                                    'error' => $e->getMessage(),
                                                    'trace' => $e->getTraceAsString()
                                                ]);
                                            }
                                        }
                                    }
                                    
                                    // Create curriculum material record first
                                    $material = \App\Models\LmsCurriculumMaterial::create([
                                        'title' => $itemData['title'] ?? 'Material ' . ($itemIndex + 1),
                                        'description' => $itemData['description'] ?? '',
                                        'estimated_duration_minutes' => $itemData['estimated_duration_minutes'] ?? 0,
                                        'status' => 'active',
                                        'created_by' => auth()->id(),
                                    ]);
                                    
                                    $itemId = $material->id;
                                    
                                    // Add files to the material if any
                                    if (!empty($filePaths)) {
                                        foreach ($filePaths as $fileIndex => $filePath) {
                                            $uploadedFile = $uploadedFiles[$fileIndex];
                                            $fileType = $fileTypes[$fileIndex];
                                            
                                            // Add file to material (first file as primary)
                                            $material->addFile(
                                                $uploadedFile, 
                                                $fileIndex + 1, 
                                                $fileIndex === 0 // First file is primary
                                            );
                                        }
                                        
                                        \Log::info('Material record created with multiple files:', [
                                            'material_id' => $material->id,
                                            'title' => $material->title,
                                            'total_files' => count($filePaths)
                                        ]);
                                    } else {
                                        \Log::info('Material record created without files');
                                    }
                                } else {
                                    \Log::info('No material files for item ' . ($itemIndex + 1) . ':', [
                                        'item_type' => $itemData['item_type'],
                                        'has_material_files' => isset($itemData['material_files']),
                                        'material_files_count' => isset($itemData['material_files']) ? count($itemData['material_files']) : 0
                                    ]);
                                    
                                    // Create material record without file
                                    $material = \App\Models\LmsCurriculumMaterial::create([
                                        'title' => $itemData['title'] ?? 'Material ' . ($itemIndex + 1),
                                        'description' => $itemData['description'] ?? '',
                                        'estimated_duration_minutes' => $itemData['estimated_duration_minutes'] ?? 0,
                                        'status' => 'active',
                                        'created_by' => auth()->id(),
                                    ]);
                                    
                                    $itemId = $material->id;
                                    
                                    \Log::info('Material record created without files');
                                }
                            } elseif ($itemData['item_type'] === 'quiz') {
                                \Log::info('Processing quiz item ' . ($itemIndex + 1) . ':', [
                                    'item_data' => $itemData,
                                    'quiz_id_from_data' => $itemData['quiz_id'] ?? 'NOT SET',
                                    'quiz_id_type' => gettype($itemData['quiz_id'] ?? null)
                                ]);
                                
                                // Handle quiz type - create curriculum material with quiz_id
                                $quizId = $itemData['quiz_id'] ?? null;
                                
                                if ($quizId && $quizId !== '' && $quizId !== 'null') {
                                    \Log::info('Quiz ID found:', ['quiz_id' => $quizId, 'quiz_id_type' => gettype($quizId)]);
                                    
                                    // Create curriculum material record for quiz
                                    $material = \App\Models\LmsCurriculumMaterial::create([
                                        'title' => $itemData['title'] ?? 'Quiz ' . ($itemIndex + 1),
                                        'description' => $itemData['description'] ?? '',
                                        'estimated_duration_minutes' => $itemData['estimated_duration_minutes'] ?? 0,
                                        'quiz_id' => $quizId,
                                        'status' => 'active',
                                        'created_by' => auth()->id(),
                                    ]);
                                    
                                    $itemId = $material->id;
                                    \Log::info('Quiz curriculum material created:', ['material_id' => $material->id, 'quiz_id' => $quizId]);
                                } else {
                                    \Log::warning('No quiz_id provided for quiz item ' . ($itemIndex + 1) . ' - skipping quiz item');
                                    $itemId = null;
                                }
                            } elseif ($itemData['item_type'] === 'questionnaire') {
                                \Log::info('Processing questionnaire item ' . ($itemIndex + 1) . ':', [
                                    'item_data' => $itemData,
                                    'questionnaire_id_from_data' => $itemData['questionnaire_id'] ?? 'NOT SET',
                                    'questionnaire_id_type' => gettype($itemData['questionnaire_id'] ?? null)
                                ]);
                                
                                // Handle questionnaire type - create curriculum material with questionnaire_id
                                $questionnaireId = $itemData['questionnaire_id'] ?? null;
                                
                                if ($questionnaireId && $questionnaireId !== '' && $questionnaireId !== 'null') {
                                    \Log::info('Questionnaire ID found:', ['questionnaire_id' => $questionnaireId, 'questionnaire_id_type' => gettype($questionnaireId)]);
                                    
                                    // Create curriculum material record for questionnaire
                                    $material = \App\Models\LmsCurriculumMaterial::create([
                                        'title' => $itemData['title'] ?? 'Questionnaire ' . ($itemIndex + 1),
                                        'description' => $itemData['description'] ?? '',
                                        'estimated_duration_minutes' => $itemData['estimated_duration_minutes'] ?? 0,
                                        'questionnaire_id' => $questionnaireId,
                                        'status' => 'active',
                                        'created_by' => auth()->id(),
                                    ]);
                                    
                                    $itemId = $material->id;
                                    \Log::info('Questionnaire curriculum material created:', ['material_id' => $material->id, 'questionnaire_id' => $questionnaireId]);
                                } else {
                                    \Log::warning('No questionnaire_id provided for questionnaire item ' . ($itemIndex + 1) . ' - skipping questionnaire item');
                                    $itemId = null;
                                }
                            } else {
                                \Log::warning('Unknown item type: ' . $itemData['item_type'] . ' for item ' . ($itemIndex + 1));
                                
                                // Create generic material record for unknown types
                                $material = \App\Models\LmsCurriculumMaterial::create([
                                    'title' => $itemData['title'] ?? 'Item ' . ($itemIndex + 1),
                                    'description' => $itemData['description'] ?? '',
                                    'estimated_duration_minutes' => $itemData['estimated_duration_minutes'] ?? 0,
                                    'status' => 'active',
                                    'created_by' => auth()->id(),
                                ]);
                                
                                $itemId = $material->id;
                            }
                            
                            // Create session item with proper curriculum material ID handling
                            $sessionItemData = [
                                'session_id' => $session->id,
                                'item_type' => $itemData['item_type'],
                                'title' => $itemData['title'] ?? null,
                                'description' => $itemData['description'] ?? null,
                                'order_number' => $itemData['order_number'],
                                'estimated_duration_minutes' => $itemData['estimated_duration_minutes'] ?? 0,
                                'status' => 'active',
                                'created_by' => auth()->id(),
                            ];
                            
                            // Set item_id based on item type - all types now use curriculum material ID
                            if ($itemData['item_type'] === 'quiz' || $itemData['item_type'] === 'questionnaire' || $itemData['item_type'] === 'material') {
                                if ($itemId !== null) {
                                    $sessionItemData['item_id'] = $itemId; // Use the curriculum material ID
                                    \Log::info('Setting session item_id:', [
                                        'item_type' => $itemData['item_type'],
                                        'curriculum_material_id' => $itemId,
                                        'quiz_id' => $itemData['quiz_id'] ?? null,
                                        'questionnaire_id' => $itemData['questionnaire_id'] ?? null
                                    ]);
                                } else {
                                    \Log::error('Failed to create curriculum material for item type: ' . $itemData['item_type']);
                                    continue; // Skip this item if material creation failed
                                }
                            }
                            
                            // Only create session item if we have valid data
                            if (isset($sessionItemData['item_id']) && $sessionItemData['item_id'] !== null) {
                                $item = $session->items()->create($sessionItemData);
                                
                                \Log::info('Session item created:', [
                                    'item_id' => $item->id, 
                                    'type' => $item->item_type,
                                    'referenced_id' => $item->item_id,
                                    'session_index' => $sessionIndex + 1,
                                    'item_index' => $itemIndex + 1
                                ]);
                            } else {
                                \Log::error('Failed to create session item - missing item_id:', [
                                    'item_type' => $itemData['item_type'],
                                    'session_index' => $sessionIndex + 1,
                                    'item_index' => $itemIndex + 1,
                                    'item_data' => $itemData
                                ]);
                            }
                        }
                    }
                }
                \Log::info('All sessions created successfully');
            } else {
                \Log::info('No sessions to create');
            }


            DB::commit();

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

            \Log::info('=== STORE COURSE COMPLETED SUCCESSFULLY ===', [
                'course_id' => $course->id,
                'course_title' => $course->title,
                'execution_time_ms' => $executionTime,
                'sessions_created' => count($validated['sessions'] ?? []),
                'requirements_created' => count($validated['requirements'] ?? [])
            ]);

            return redirect()->route('lms.courses.index')->with('success', 'Course berhasil dibuat dan tersimpan!');

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('=== ERROR CREATING COURSE ===');
            \Log::error('Error message: ' . $e->getMessage());
            \Log::error('Error trace: ' . $e->getTraceAsString());
            \Log::error('Data that failed to save:', $validated);
            throw $e;
        }
    }

    public function showCourse(LmsCourse $course)
    {
        $user = auth()->user();
        
        // Debug logging untuk troubleshooting
        \Log::info('=== SHOW COURSE DEBUG START ===');
        \Log::info('Course details:', [
            'course_id' => $course->id,
            'course_title' => $course->title,
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?? 'Unknown'
        ]);
        
        // Debug storage paths
        \Log::info('Storage paths:', [
            'storage_path' => storage_path('app/public'),
            'public_storage_path' => public_path('storage'),
            'storage_exists' => file_exists(storage_path('app/public')),
            'public_storage_exists' => file_exists(public_path('storage')),
            'storage_readable' => is_readable(storage_path('app/public')),
            'public_storage_readable' => is_readable(public_path('storage'))
        ]);
        
        // Check if storage link exists
        $storageLink = public_path('storage');
        if (is_link($storageLink)) {
            \Log::info('Storage link info:', [
                'link_target' => readlink($storageLink),
                'link_exists' => true
            ]);
        } else {
            \Log::warning('Storage link not found or not a symbolic link');
        }
        
        $course->load(['category', 'instructor.jabatan.divisi', 'instructor.jabatan.level', 'instructor.divisi', 'sessions' => function ($query) {
            $query->orderBy('order_number')->with(['items' => function ($q) {
                $q->orderBy('order_number');
            }]);
        }, 'targetJabatans', 'quizzes', 'questionnaires', 'competencies']);
        
        // Load material files for material type items using new files relationship
        foreach ($course->sessions as $session) {
            if ($session->items) {
                foreach ($session->items as $item) {
                    if ($item->item_type === 'material' && $item->item_id) {
                        // Load material with files relationship
                        $material = \App\Models\LmsCurriculumMaterial::with('files')->find($item->item_id);
                        if ($material) {
                            \Log::info('Loading material with files:', [
                                'material_id' => $material->id,
                                'material_title' => $material->title,
                                'files_count' => $material->files->count(),
                                'has_primary_file' => $material->primaryFile ? 'yes' : 'no'
                            ]);
                            
                            // Set material data with files
                            $item->material_data = $material;
                            
                            \Log::info('Material loaded successfully:', [
                                'material_id' => $material->id,
                                'files_count' => $material->files->count(),
                                'primary_file_type' => $material->primary_file_type,
                                'files_count_attribute' => $material->files_count
                            ]);
                        } else {
                            \Log::warning('Material not found:', [
                                'item_id' => $item->item_id,
                                'item_type' => $item->item_type
                            ]);
                        }
                    } elseif ($item->item_type === 'quiz' && $item->item_id) {
                        // Load quiz data for quiz type items
                        $material = \App\Models\LmsCurriculumMaterial::with('quiz')->find($item->item_id);
                        if ($material && $material->quiz) {
                            \Log::info('Loading quiz data:', [
                                'material_id' => $material->id,
                                'quiz_id' => $material->quiz_id,
                                'quiz_title' => $material->quiz->title,
                                'quiz_description' => $material->quiz->description
                            ]);
                            
                            // Set quiz data
                            $item->quiz_data = $material->quiz;
                            
                            \Log::info('Quiz data loaded successfully:', [
                                'quiz_id' => $material->quiz->id,
                                'quiz_title' => $material->quiz->title
                            ]);
                        } else {
                            \Log::warning('Quiz data not found:', [
                                'item_id' => $item->item_id,
                                'item_type' => $item->item_type,
                                'material_exists' => $material ? 'yes' : 'no',
                                'quiz_exists' => $material && $material->quiz ? 'yes' : 'no'
                            ]);
                        }
                    } elseif ($item->item_type === 'questionnaire' && $item->item_id) {
                        // Load questionnaire data for questionnaire type items
                        $material = \App\Models\LmsCurriculumMaterial::with('questionnaire')->find($item->item_id);
                        if ($material && $material->questionnaire) {
                            \Log::info('Loading questionnaire data:', [
                                'material_id' => $material->id,
                                'questionnaire_id' => $material->questionnaire_id,
                                'questionnaire_title' => $material->questionnaire->title,
                                'questionnaire_description' => $material->questionnaire->description
                            ]);
                            
                            // Set questionnaire data
                            $item->questionnaire_data = $material->questionnaire;
                            
                            \Log::info('Questionnaire data loaded successfully:', [
                                'questionnaire_id' => $material->questionnaire->id,
                                'questionnaire_title' => $material->questionnaire->title
                            ]);
                        } else {
                            \Log::warning('Questionnaire data not found:', [
                                'item_id' => $item->item_id,
                                'item_type' => $item->item_type,
                                'material_exists' => $material ? 'yes' : 'no',
                                'questionnaire_exists' => $material && $material->questionnaire ? 'yes' : 'no'
                            ]);
                        }
                    }
                }
            }
        }

        // Get user enrollment
        $enrollment = LmsEnrollment::where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->first();

        // Check if user can schedule training for this course
        $canScheduleTraining = $user->is_admin || 
                              $user->id_role === $course->created_by_role || 
                              $user->id_jabatan === $course->created_by_jabatan;

        // Use actual data from database, fallback to default if empty
        if (empty($course->learning_objectives)) {
            $course->learning_objectives = [
                'Memahami kebijakan dan prosedur perusahaan',
                'Menguasai skill yang diperlukan untuk pekerjaan',
                'Meningkatkan produktivitas dan efisiensi kerja',
                'Mengembangkan kompetensi sesuai standar perusahaan',
                'Memenuhi persyaratan compliance dan regulasi'
            ];
        }

        if (empty($course->requirements)) {
            $course->requirements = [
                'Karyawan aktif perusahaan',
                'Komputer dengan koneksi intranet',
                'Waktu belajar sesuai jadwal yang ditentukan',
                'Kemauan untuk mengikuti training'
            ];
        }

        // Get available quizzes and questionnaires for this course
        $availableQuizzes = \App\Models\LmsQuiz::where('status', 'published')
            ->whereNull('course_id')
            ->orWhere('course_id', $course->id)
            ->get();
            
        $availableQuestionnaires = \App\Models\LmsQuestionnaire::where('status', 'published')
            ->whereNull('course_id')
            ->orWhere('course_id', $course->id)
            ->get();

        // Debug logging untuk material files yang diproses
        $totalMaterials = 0;
        $totalFiles = 0;
        $validFiles = 0;
        $errorFiles = 0;
        
        foreach ($course->sessions as $session) {
            if ($session->items) {
                foreach ($session->items as $item) {
                    if ($item->item_type === 'material' && $item->material_data) {
                        $totalMaterials++;
                        $totalFiles += $item->material_data->total_file_count ?? 0;
                        $validFiles += $item->material_data->valid_file_count ?? 0;
                        $errorFiles += count($item->material_data->file_errors ?? []);
                    }
                }
            }
        }
        
        \Log::info('=== SHOW COURSE DEBUG END ===');
        \Log::info('Material processing summary:', [
            'total_materials' => $totalMaterials,
            'total_files' => $totalFiles,
            'valid_files' => $validFiles,
            'error_files' => $errorFiles,
            'success_rate' => $totalFiles > 0 ? round(($validFiles / $totalFiles) * 100, 2) . '%' : 'N/A'
        ]);
        
        return Inertia::render('Lms/CourseDetail', [
            'course' => $course,
            'isEnrolled' => $enrollment ? true : false,
            'canScheduleTraining' => $canScheduleTraining,
            'availableQuizzes' => $availableQuizzes,
            'availableQuestionnaires' => $availableQuestionnaires,
        ]);
    }

    public function enroll(LmsCourse $course)
    {
        $user = auth()->user();

        // Check if already enrolled
        $existingEnrollment = LmsEnrollment::where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingEnrollment) {
            return back()->withErrors(['error' => 'Anda sudah terdaftar di kursus ini.']);
        }

        // Create enrollment
        $enrollment = LmsEnrollment::create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'status' => 'enrolled',
            'progress_percentage' => 0,
        ]);

        return redirect()->route('lms.courses.show', $course)
            ->with('success', 'Berhasil mendaftar ke kursus!');
    }

    public function myCourses(Request $request)
    {
        $user = auth()->user();
        
        $query = LmsEnrollment::with(['course.category', 'course.instructor'])
            ->where('user_id', $user->id)
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->search, function ($query, $search) {
                $query->whereHas('course', function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%");
                });
            });

        $enrollments = $query->orderBy('updated_at', 'desc')
            ->paginate(12)
            ->withQueryString();

        $statusOptions = [
            ['value' => 'enrolled', 'label' => 'Terdaftar'],
            ['value' => 'in_progress', 'label' => 'Sedang Belajar'],
            ['value' => 'completed', 'label' => 'Selesai'],
            ['value' => 'dropped', 'label' => 'Dibatalkan'],
        ];

        return Inertia::render('Lms/MyCourses', [
            'enrollments' => $enrollments,
            'statusOptions' => $statusOptions,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function reports()
    {
        $user = auth()->user();
        
        // Check if user has permission to view reports
        // This should be implemented based on your permission system
        
        // Get overall LMS statistics
        $overallStats = [
            'total_courses' => LmsCourse::count(),
            'published_courses' => LmsCourse::where('status', 'published')->count(),
            'total_enrollments' => LmsEnrollment::count(),
            'active_enrollments' => LmsEnrollment::whereIn('status', ['enrolled', 'in_progress'])->count(),
            'completed_enrollments' => LmsEnrollment::where('status', 'completed')->count(),
            'total_categories' => LmsCategory::count(),
            'total_users' => User::count(),
        ];

        // Get enrollment trends (last 6 months)
        $enrollmentTrends = LmsEnrollment::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                COUNT(*) as enrollments
            ')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Get top courses by enrollment
        $topCourses = LmsCourse::with(['category', 'instructor'])
            ->withCount('enrollments')
            ->orderBy('enrollments_count', 'desc')
            ->limit(10)
            ->get();

        // Get category statistics
        $categoryStats = LmsCategory::withCount(['courses'])
            ->orderBy('courses_count', 'desc')
            ->get();

        return Inertia::render('Lms/Reports', [
            'overallStats' => $overallStats,
            'enrollmentTrends' => $enrollmentTrends,
            'topCourses' => $topCourses,
            'categoryStats' => $categoryStats,
        ]);
    }

    public function editCourse(LmsCourse $course)
    {
        // Check if user can edit course
        $user = auth()->user();
        $canEdit = false;
        
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canEdit = true;
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canEdit = true;
        }
        
        if (!$canEdit) {
            abort(403, 'Unauthorized');
        }

        $course->load(['category', 'instructor.jabatan.divisi', 'instructor.jabatan.level', 'instructor.divisi', 'sessions', 'targetJabatans', 'targetOutlets']);

        $categories = LmsCategory::active()->orderBy('name')->get(['id', 'name']);
        $divisions = Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']);
        $jabatans = Jabatan::active()->with(['divisi', 'level'])->orderBy('nama_jabatan')->get(['id_jabatan', 'nama_jabatan', 'id_divisi', 'id_level']);
        $outlets = DataOutlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);
        $internalTrainers = User::where('status', 'A')->whereNotNull('id_jabatan')->with('jabatan')->orderBy('nama_lengkap')->get(); // Removed limit to show all available trainers
        $certificateTemplates = \App\Models\CertificateTemplate::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Lms/CourseEdit', [
            'course' => $course,
            'categories' => $categories,
            'divisions' => $divisions,
            'jabatans' => $jabatans,
            'outlets' => $outlets,
            'internalTrainers' => $internalTrainers,
            'certificateTemplates' => $certificateTemplates,
        ]);
    }

    public function updateCourse(Request $request, LmsCourse $course)
    {
        // Check if user can edit course
        $user = auth()->user();
        $canEdit = false;
        
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canEdit = true;
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canEdit = true;
        }
        
        if (!$canEdit) {
            abort(403, 'Unauthorized');
        }

        // Same validation as storeCourse
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'description' => 'required|string',
            'category_id' => 'required|exists:lms_categories,id',
            'target_type' => 'nullable|in:single,multiple,all',
            'target_division_id' => 'nullable|exists:tbl_data_divisi,id',
            'target_divisions' => 'nullable|array',
            'target_divisions.*' => 'exists:tbl_data_divisi,id',
            'target_jabatan_ids' => 'nullable|array',
            'target_jabatan_ids.*' => 'exists:tbl_data_jabatan,id_jabatan',
            'target_outlet_ids' => 'nullable|array',
            'target_outlet_ids.*' => 'exists:tbl_data_outlet,id_outlet',
            'duration_minutes' => 'required|integer|min:1',
            'type' => 'required|in:online,in_class,practice',
            'specification' => 'required|in:generic,departemental',
            'course_type' => 'required|in:mandatory,optional',
            // 'requirements' => 'nullable|array', // REMOVED - requirements field removed
            // 'requirements.*' => 'string|max:500', // REMOVED - requirements field removed
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'competencies' => 'nullable|array',
            'competencies.*.competency_id' => 'required|exists:competencies,id',
            'new_competencies' => 'nullable|array',
            'new_competencies.*.name' => 'required|string|max:255'
        ]);

        // Custom validation: At least one target must be selected
        $hasDivision = !empty($validated['target_division_id']) || 
                      !empty($validated['target_divisions'] ?? []) || 
                      ($validated['target_type'] ?? '') === 'all';
        $hasJabatan = !empty($validated['target_jabatan_ids'] ?? []);
        $hasOutlet = !empty($validated['target_outlet_ids'] ?? []);
        
        if (!$hasDivision && !$hasJabatan && !$hasOutlet) {
            return back()->withErrors([
                'target' => 'Minimal harus memilih satu target: divisi, jabatan, atau outlet.'
            ])->withInput();
        }

        try {
            DB::beginTransaction();

            // Handle thumbnail upload
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('lms/thumbnails', 'public');
                $validated['thumbnail'] = $thumbnailPath;
            }


            // Update course
            $course->update($validated);

            // Handle target divisions
            if (($validated['target_type'] ?? '') === 'multiple' && !empty($validated['target_divisions'] ?? [])) {
                $course->targetDivisions()->sync($validated['target_divisions']);
            } else {
                $course->targetDivisions()->detach();
            }

            // Handle target jabatans
            if (!empty($validated['target_jabatan_ids'] ?? [])) {
                $course->targetJabatans()->sync($validated['target_jabatan_ids']);
            } else {
                $course->targetJabatans()->detach();
            }

            // Handle target outlets
            if (!empty($validated['target_outlet_ids'] ?? [])) {
                $course->targetOutlets()->sync($validated['target_outlet_ids']);
            } else {
                $course->targetOutlets()->detach();
            }

            // Handle new competencies first
            $newCompetencyIds = [];
            if (isset($validated['new_competencies']) && !empty($validated['new_competencies'])) {
                foreach ($validated['new_competencies'] as $newCompetency) {
                    $competency = \App\Models\Competency::create([
                        'name' => $newCompetency['name'],
                        'description' => null,
                        'category' => null,
                        'level' => 'beginner',
                        'is_active' => true,
                    ]);
                    $newCompetencyIds[] = $competency->id;
                }
            }

            // Sync competencies for many-to-many relationship
            $competencyData = [];
            
            // Add existing competencies
            if (isset($validated['competencies']) && !empty($validated['competencies'])) {
                foreach ($validated['competencies'] as $competency) {
                    $competencyData[$competency['competency_id']] = [];
                }
            }
            
            // Add new competencies
            if (!empty($validated['new_competencies'])) {
                foreach ($validated['new_competencies'] as $index => $newCompetency) {
                    if (isset($newCompetencyIds[$index])) {
                        $competencyData[$newCompetencyIds[$index]] = [];
                    }
                }
            }
            
            if (!empty($competencyData)) {
                $course->competencies()->sync($competencyData);
            } else {
                $course->competencies()->detach();
            }

            // Handle curriculum (lessons) - REMOVED - using sessions instead
            // if (!empty($validated['curriculum'])) {
            //     // Delete existing lessons
            //     $course->lessons()->delete();
            //     
            //     // Create new lessons
            //     foreach ($validated['curriculum'] as $lessonData) {
            //         $course->lessons()->create($lessonData);
            //     }
            // }

            // Handle requirements - REMOVED - requirements field removed
            // if (!empty($validated['requirements'])) {
            //     // Delete existing requirements
            //     $course->requirements()->delete();
            //     // Create new requirements
            //     foreach ($validated['requirements'] as $requirement) {
            //         $course->requirements()->create([
            //             'requirement' => $requirement,
            //             'order_number' => 1 // Default order
            //         ]);
            //     }
            // }

            DB::commit();

            return redirect()->route('lms.courses.show', $course)->with('success', 'Course berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Gagal memperbarui course: ' . $e->getMessage()]);
        }
    }

    public function archiveCourse(LmsCourse $course)
    {
        // Check if user can archive course
        $user = auth()->user();
        $canArchive = false;
        
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canArchive = true;
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canArchive = true;
        }
        
        if (!$canArchive) {
            abort(403, 'Unauthorized');
        }

        try {
            $course->update(['status' => 'archived']);
            return back()->with('success', 'Course berhasil diarchive!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengarchive course: ' . $e->getMessage()]);
        }
    }

    public function publishCourse(LmsCourse $course)
    {
        // Check if user can publish course
        $user = auth()->user();
        $canPublish = false;
        
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canPublish = true;
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canPublish = true;
        }
        
        if (!$canPublish) {
            abort(403, 'Unauthorized');
        }

        try {
            $course->update(['status' => 'published']);
            return back()->with('success', 'Course berhasil dipublish kembali!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mempublish course: ' . $e->getMessage()]);
        }
    }

    public function attachQuiz(Request $request, LmsCourse $course)
    {
        $request->validate([
            'quiz_id' => 'required|exists:lms_quizzes,id'
        ]);

        try {
            $quiz = \App\Models\LmsQuiz::findOrFail($request->quiz_id);
            $quiz->update(['course_id' => $course->id]);

            return back()->with('success', 'Quiz berhasil ditambahkan ke course!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menambahkan quiz ke course: ' . $e->getMessage()]);
        }
    }

    public function attachQuestionnaire(Request $request, LmsCourse $course)
    {
        $request->validate([
            'questionnaire_id' => 'required|exists:lms_questionnaires,id'
        ]);

        try {
            $questionnaire = \App\Models\LmsQuestionnaire::findOrFail($request->questionnaire_id);
            $questionnaire->update(['course_id' => $course->id]);

            return back()->with('success', 'Kuesioner berhasil ditambahkan ke course!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menambahkan kuesioner ke course: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Helper method to determine file type from MIME type
     */
    private function getFileType($mimeType)
    {
        $mimeToType = [
            'application/pdf' => 'pdf',
            'application/msword' => 'document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
            'application/vnd.ms-powerpoint' => 'document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'document',
            'image/jpeg' => 'image',
            'image/jpg' => 'image',
            'image/png' => 'image',
            'image/gif' => 'image',
            'video/mp4' => 'video',
            'video/avi' => 'video',
            'video/quicktime' => 'video',
        ];
        
        return $mimeToType[$mimeType] ?? 'document';
    }
    
    /**
     * Helper method to validate and clean file paths
     */
    private function validateFilePaths($filePaths, $fileTypes)
    {
        $validPaths = [];
        $validTypes = [];
        $errors = [];
        
        \Log::info('=== VALIDATE FILE PATHS START ===');
        \Log::info('Input data:', [
            'filePaths' => $filePaths,
            'fileTypes' => $fileTypes,
            'filePaths_type' => gettype($filePaths),
            'fileTypes_type' => gettype($fileTypes)
        ]);
        
        if (empty($filePaths)) {
            \Log::warning('No file paths provided');
            return ['paths' => [], 'types' => [], 'errors' => ['No files provided']];
        }
        
        // Decode if JSON
        if (is_string($filePaths)) {
            \Log::info('File paths is string, attempting JSON decode...');
            try {
                $decodedPaths = json_decode($filePaths, true);
                \Log::info('JSON decode result:', [
                    'original' => $filePaths,
                    'decoded' => $decodedPaths,
                    'json_last_error' => json_last_error(),
                    'json_last_error_msg' => json_last_error_msg()
                ]);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = 'JSON decode error: ' . json_last_error_msg();
                    \Log::error('JSON decode failed:', [
                        'error' => json_last_error_msg(),
                        'input' => $filePaths
                    ]);
                    return ['paths' => [], 'types' => [], 'errors' => $errors];
                }
                
                $filePaths = $decodedPaths;
            } catch (\Exception $e) {
                $errors[] = 'Invalid file paths format: ' . $e->getMessage();
                \Log::error('Exception during JSON decode:', [
                    'error' => $e->getMessage(),
                    'input' => $filePaths
                ]);
                return ['paths' => [], 'types' => [], 'errors' => $errors];
            }
        }
        
        if (is_string($fileTypes)) {
            \Log::info('File types is string, attempting JSON decode...');
            try {
                $decodedTypes = json_decode($fileTypes, true);
                \Log::info('JSON decode result for types:', [
                    'original' => $fileTypes,
                    'decoded' => $decodedTypes,
                    'json_last_error' => json_last_error(),
                    'json_last_error_msg' => json_last_error_msg()
                ]);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = 'JSON decode error for types: ' . json_last_error_msg();
                    \Log::error('JSON decode failed for types:', [
                        'error' => json_last_error_msg(),
                        'input' => $fileTypes
                    ]);
                    return ['paths' => [], 'types' => [], 'errors' => $errors];
                }
                
                $fileTypes = $decodedTypes;
            } catch (\Exception $e) {
                $errors[] = 'Invalid file types format: ' . $e->getMessage();
                \Log::error('Exception during JSON decode for types:', [
                    'error' => $e->getMessage(),
                    'input' => $fileTypes
                ]);
                return ['paths' => [], 'types' => [], 'errors' => $errors];
            }
        }
        
        if (!is_array($filePaths)) {
            $errors[] = 'File paths must be an array, got: ' . gettype($filePaths);
            \Log::error('File paths is not array:', [
                'type' => gettype($filePaths),
                'value' => $filePaths
            ]);
            return ['paths' => [], 'types' => [], 'errors' => $errors];
        }
        
        \Log::info('After JSON decode:', [
            'filePaths' => $filePaths,
            'fileTypes' => $fileTypes,
            'filePaths_count' => count($filePaths),
            'fileTypes_count' => count($fileTypes ?? [])
        ]);
        
        foreach ($filePaths as $index => $filePath) {
            if (empty($filePath)) {
                continue;
            }
            
            // Check if file exists and is readable
            $fullPath = storage_path('app/public/' . $filePath);
            if (file_exists($fullPath) && is_readable($fullPath)) {
                $validPaths[] = $filePath;
                $validTypes[] = $fileTypes[$index] ?? 'document';
            } else {
                $errors[] = "File not found or not readable: {$filePath}";
                \Log::warning('File validation failed:', [
                    'file_path' => $filePath,
                    'full_path' => $fullPath,
                    'exists' => file_exists($fullPath),
                    'readable' => is_readable($fullPath)
                ]);
            }
        }
        
        \Log::info('=== VALIDATE FILE PATHS COMPLETED ===');
        \Log::info('Final results:', [
            'valid_paths_count' => count($validPaths),
            'valid_types_count' => count($validTypes),
            'errors_count' => count($errors),
            'valid_paths' => $validPaths,
            'valid_types' => $validTypes,
            'errors' => $errors
        ]);
        
        return [
            'paths' => $validPaths,
            'types' => $validTypes,
            'errors' => $errors
        ];
    }
    
    /**
     * Helper method to get safe file URL
     */
    private function getSafeFileUrl($filePath)
    {
        if (empty($filePath)) {
            return null;
        }
        
        try {
            $fullPath = storage_path('app/public/' . $filePath);
            if (file_exists($fullPath) && is_readable($fullPath)) {
                return asset('storage/' . $filePath);
            }
        } catch (\Exception $e) {
            \Log::error('Error getting file URL:', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
    
    /**
     * Helper method to get file size safely
     */
    private function getFileSize($filePath)
    {
        if (empty($filePath)) {
            return null;
        }
        
        try {
            $fullPath = storage_path('app/public/' . $filePath);
            if (file_exists($fullPath) && is_readable($fullPath)) {
                $size = filesize($fullPath);
                return $size ? $this->formatFileSize($size) : null;
            }
        } catch (\Exception $e) {
            \Log::error('Error getting file size:', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
    
    /**
     * Helper method to format file size
     */
    private function formatFileSize($bytes)
    {
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > $k && $i < count($sizes) - 1; $i++) {
            $bytes /= $k;
        }
        
        return round($bytes, 2) . ' ' . $sizes[$i];
    }
    
    /**
     * Method to clean up invalid file references
     */
    public function cleanupInvalidFiles()
    {
        try {
            $materials = \App\Models\LmsCurriculumMaterial::whereNotNull('file_path')->get();
            $cleanedCount = 0;
            $errors = [];
            
            foreach ($materials as $material) {
                $validation = $this->validateFilePaths($material->file_path, $material->file_type);
                
                if (!empty($validation['errors'])) {
                    // Update material with only valid files
                    if (!empty($validation['paths'])) {
                        $material->file_path = json_encode($validation['paths']);
                        $material->file_type = json_encode($validation['types']);
                        $material->save();
                        $cleanedCount++;
                        
                        \Log::info('Cleaned material files:', [
                            'material_id' => $material->id,
                            'valid_files' => count($validation['paths']),
                            'removed_files' => count($validation['errors'])
                        ]);
                    } else {
                        // No valid files left, clear file references
                        $material->file_path = null;
                        $material->file_type = null;
                        $material->save();
                        
                        \Log::warning('Cleared all file references for material:', [
                            'material_id' => $material->id,
                            'title' => $material->title
                        ]);
                    }
                }
            }
            
            return [
                'success' => true,
                'materials_processed' => $materials->count(),
                'materials_cleaned' => $cleanedCount,
                'message' => "Successfully cleaned {$cleanedCount} materials"
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error during file cleanup:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get trainer ratings for a specific course
     */
    public function getCourseTrainerRatings($courseId)
    {
        try {
            $course = LmsCourse::findOrFail($courseId);
            
            // Check if user can view trainer ratings
            $user = auth()->user();
            $canView = false;
            
            if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
                $canView = true;
            } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
                $canView = true;
            }
            
            if (!$canView) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk melihat rating trainer course ini'
                ], 403);
            }

            // Get all training schedules for this course
            $trainingSchedules = $course->trainingSchedules()->with(['outlet'])->get();
            
            if ($trainingSchedules->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'training' => [
                        'id' => $course->id,
                        'course_title' => $course->title,
                        'scheduled_date' => null,
                        'start_time' => null,
                        'end_time' => null,
                        'outlet_name' => 'Belum ada jadwal training'
                    ],
                    'trainer_ratings' => [],
                    'statistics' => [
                        'total_ratings' => 0,
                        'average_rating' => 0,
                        'excellent_count' => 0,
                        'poor_count' => 0
                    ]
                ]);
            }

            // Get trainer ratings from all training schedules of this course
            $scheduleIds = $trainingSchedules->pluck('id');
            
            $trainerRatings = DB::table('training_reviews')
                ->join('users', 'training_reviews.user_id', '=', 'users.id')
                ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
                ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
                ->leftJoin('training_schedule_trainers', function($join) {
                    $join->on('training_reviews.training_schedule_id', '=', 'training_schedule_trainers.schedule_id')
                         ->where('training_schedule_trainers.trainer_type', '=', 'internal');
                })
                ->leftJoin('users as trainers', 'training_schedule_trainers.trainer_id', '=', 'trainers.id')
                ->whereIn('training_reviews.training_schedule_id', $scheduleIds)
                ->whereNotNull('training_reviews.training_rating')
                ->select(
                    'training_reviews.id as review_id',
                    'training_reviews.training_rating as rating',
                    'training_reviews.material_suggestions as comment',
                    'training_reviews.material_needs',
                    'training_reviews.created_at',
                    'users.nama_lengkap as participant_name',
                    'tbl_data_jabatan.nama_jabatan as participant_position',
                    'trainers.nama_lengkap as trainer_name',
                    'training_schedule_trainers.external_trainer_name',
                    // Trainer ratings
                    'training_reviews.trainer_mastery',
                    'training_reviews.trainer_language',
                    'training_reviews.trainer_intonation',
                    'training_reviews.trainer_presentation',
                    'training_reviews.trainer_qna',
                    // Material ratings
                    'training_reviews.material_benefit',
                    'training_reviews.material_clarity',
                    'training_reviews.material_display'
                )
                ->orderBy('training_reviews.created_at', 'desc')
                ->get();

            // Process trainer data for each rating
            $trainerRatings->each(function ($rating) {
                if ($rating->trainer_name) {
                    $rating->trainer_name_final = $rating->trainer_name;
                } elseif ($rating->external_trainer_name) {
                    $rating->trainer_name_final = $rating->external_trainer_name;
                } else {
                    $rating->trainer_name_final = 'Trainer tidak tersedia';
                }
                
                // Add structured trainer ratings
                $rating->trainer_ratings = [
                    'mastery' => $rating->trainer_mastery,
                    'language' => $rating->trainer_language,
                    'intonation' => $rating->trainer_intonation,
                    'presentation' => $rating->trainer_presentation,
                    'qna' => $rating->trainer_qna
                ];
                
                // Add structured material ratings
                $rating->material_ratings = [
                    'benefit' => $rating->material_benefit,
                    'clarity' => $rating->material_clarity,
                    'display' => $rating->material_display
                ];
            });

            // Calculate statistics
            $totalRatings = $trainerRatings->count();
            $averageRating = $totalRatings > 0 ? round($trainerRatings->avg('rating'), 2) : 0;
            $excellentCount = $trainerRatings->where('rating', 5)->count();
            $poorCount = $trainerRatings->where('rating', 1)->count();

            // Get the most recent training schedule for display
            $latestSchedule = $trainingSchedules->sortByDesc('scheduled_date')->first();

            return response()->json([
                'success' => true,
                'training' => [
                    'id' => $course->id,
                    'course_title' => $course->title,
                    'scheduled_date' => $latestSchedule->scheduled_date,
                    'start_time' => $latestSchedule->start_time,
                    'end_time' => $latestSchedule->end_time,
                    'outlet_name' => $latestSchedule->outlet->nama_outlet ?? 'Venue tidak ditentukan'
                ],
                'trainer_ratings' => $trainerRatings,
                'statistics' => [
                    'total_ratings' => $totalRatings,
                    'average_rating' => $averageRating,
                    'excellent_count' => $excellentCount,
                    'poor_count' => $poorCount
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching course trainer ratings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat rating trainer course'
            ], 500);
        }
    }

    /**
     * Get trainer report with ratings and training details
     */
    public function getAvailableTrainings()
    {
        try {
            \Log::info('=== GET AVAILABLE TRAININGS START ===');
            $user = auth()->user();
            
            if (!$user) {
                \Log::error('User not authenticated');
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Load user relationships
            $user->load(['divisi', 'jabatan', 'outlet']);

            \Log::info('User authenticated', [
                'user_id' => $user->id,
                'division_id' => $user->division_id,
                'id_jabatan' => $user->id_jabatan,
                'id_outlet' => $user->id_outlet,
                'division_name' => $user->divisi?->nama_divisi,
                'jabatan_name' => $user->jabatan?->nama_jabatan,
                'outlet_name' => $user->outlet?->nama_outlet
            ]);

            // Simple query first - get all published courses
            \Log::info('Getting all published courses...');
            $allCourses = LmsCourse::where('status', 'published')
                ->with(['targetDivision', 'targetDivisions', 'category'])
                ->get();

            \Log::info('All published courses found', [
                'count' => $allCourses->count(),
                'courses' => $allCourses->map(function($course) {
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'target_type' => $course->target_type,
                        'target_division_id' => $course->target_division_id,
                        'target_jabatan_ids' => $course->target_jabatan_ids,
                        'target_outlet_ids' => $course->target_outlet_ids
                    ];
                })
            ]);

            // Filter courses that match user criteria
            $availableCourses = $allCourses->filter(function($course) use ($user) {
                // Target type = 'all' - semua user
                if ($course->target_type === 'all') {
                    return true;
                }
                
                // Target type = 'single' - single division match
                if ($course->target_type === 'single' && $course->target_division_id) {
                    return $course->target_division_id == $user->division_id;
                }
                
                // Target type = 'multiple' - multiple divisions match
                if ($course->target_type === 'multiple' && $course->targetDivisions) {
                    return $course->targetDivisions->contains('id', $user->division_id);
                }
                
                // Target jabatan match
                if ($course->target_jabatan_ids && is_array($course->target_jabatan_ids)) {
                    return in_array($user->id_jabatan, $course->target_jabatan_ids);
                }
                
                // Target outlet match
                if ($course->target_outlet_ids && is_array($course->target_outlet_ids)) {
                    return in_array($user->id_outlet, $course->target_outlet_ids);
                }
                
                return false;
            });

            \Log::info('Filtered available courses', [
                'count' => $availableCourses->count()
            ]);

            // Get user's training history
            \Log::info('Getting user training history for user_id: ' . $user->id);
            $userTrainingHistory = DB::table('training_invitations')
                ->join('training_schedules', 'training_invitations.schedule_id', '=', 'training_schedules.id')
                ->where('training_invitations.user_id', $user->id)
                ->where('training_invitations.status', 'attended')
                ->whereNotNull('training_invitations.check_out_time')
                ->pluck('training_schedules.course_id')
                ->toArray();
            
            \Log::info('User training history result', [
                'user_id' => $user->id,
                'completed_course_ids' => $userTrainingHistory
            ]);

            // Process courses and add participation status
            $coursesWithStatus = $availableCourses->map(function($course) use ($userTrainingHistory) {
                $isCompleted = in_array($course->id, $userTrainingHistory);
                
                \Log::info('Processing course status', [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'is_completed' => $isCompleted,
                    'completed_course_ids' => $userTrainingHistory
                ]);
                
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description,
                    'short_description' => $course->short_description,
                    'duration_minutes' => $course->duration_minutes,
                    'duration_formatted' => $course->duration_formatted ?? $course->duration_hours . ' jam',
                    'thumbnail_url' => $course->thumbnail_url,
                    'difficulty_level' => $course->difficulty_level,
                    'type' => $course->type,
                    'specification' => $course->specification,
                    'course_type' => $course->course_type,
                    'category' => $course->category ? [
                        'id' => $course->category->id,
                        'name' => $course->category->name
                    ] : null,
                    'target_info' => [
                        'type' => $course->target_type,
                        'divisions' => $course->targetDivision ? [$course->targetDivision->nama_divisi] : ($course->targetDivisions ? $course->targetDivisions->pluck('nama_divisi')->toArray() : []),
                        'jabatans' => [],
                        'outlets' => []
                    ],
                    'is_completed' => $isCompleted,
                    'current_invitations' => [],
                    'participation_status' => $isCompleted ? 'completed' : 'available',
                    'created_at' => $course->created_at,
                    'updated_at' => $course->updated_at
                ];
            });

            \Log::info('=== GET AVAILABLE TRAININGS SUCCESS ===', [
                'total_courses' => $coursesWithStatus->count(),
                'total_completed' => $coursesWithStatus->where('is_completed', true)->count()
            ]);

            return response()->json([
                'success' => true,
                'courses' => $coursesWithStatus,
                'user_info' => [
                    'id' => $user->id,
                    'division_id' => $user->division_id,
                    'id_jabatan' => $user->id_jabatan,
                    'id_outlet' => $user->id_outlet,
                    'division_name' => $user->divisi?->nama_divisi,
                    'jabatan_name' => $user->jabatan?->nama_jabatan,
                    'outlet_name' => $user->outlet?->nama_outlet
                ],
                'total_available' => $coursesWithStatus->count(),
                'total_completed' => $coursesWithStatus->where('is_completed', true)->count(),
                'total_invited' => 0
            ]);

        } catch (\Exception $e) {
            \Log::error('=== GET AVAILABLE TRAININGS ERROR ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat training yang tersedia: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEmployeeTrainingReport(Request $request)
    {
        try {
            \Log::info('=== GET EMPLOYEE TRAINING REPORT START ===', [
                'filters' => $request->all()
            ]);
            
            // Check permission
            $user = auth()->user();
            if (!$user || ($user->id_role !== '5af56935b011a' && $user->id_jabatan !== 170)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk melihat laporan training karyawan'
                ], 403);
            }

            // Get filter parameters
            $divisionId = $request->get('division_id');
            $jabatanId = $request->get('jabatan_id');
            $outletId = $request->get('outlet_id');
            $specification = $request->get('specification');

            // Build query for users with filters
            $usersQuery = \App\Models\User::where('status', 'A')
                ->with(['divisi', 'jabatan', 'outlet']);

            // Apply filters
            if ($divisionId) {
                $usersQuery->where('division_id', $divisionId);
            }
            if ($jabatanId) {
                $usersQuery->where('id_jabatan', $jabatanId);
            }
            if ($outletId) {
                $usersQuery->where('id_outlet', $outletId);
            }

            $users = $usersQuery->orderBy('nama_lengkap')->get();

            \Log::info('Found users for training report', ['count' => $users->count()]);

            // Get all published courses
            $allCoursesQuery = LmsCourse::where('status', 'published')
                ->with(['targetDivision', 'targetDivisions', 'category', 'competencies']);
            
            // Apply specification filter if provided
            if ($specification) {
                $allCoursesQuery->where('specification', $specification);
            }
            
            $allCourses = $allCoursesQuery->get();

            \Log::info('Found published courses', ['count' => $allCourses->count()]);

            // Process each user
            $employeeReports = $users->map(function($employee) use ($allCourses) {
                \Log::info('Processing employee', [
                    'user_id' => $employee->id,
                    'nama' => $employee->nama_lengkap,
                    'division_id' => $employee->division_id,
                    'jabatan_id' => $employee->id_jabatan,
                    'outlet_id' => $employee->id_outlet
                ]);

                // Get user's completed training history with trainer and location info
                $completedTrainings = DB::table('training_invitations')
                    ->join('training_schedules', 'training_invitations.schedule_id', '=', 'training_schedules.id')
                    ->join('lms_courses', 'training_schedules.course_id', '=', 'lms_courses.id')
                    ->leftJoin('training_schedule_trainers', 'training_schedules.id', '=', 'training_schedule_trainers.schedule_id')
                    ->leftJoin('users as trainers', function($join) {
                        $join->on('training_schedule_trainers.trainer_id', '=', 'trainers.id')
                             ->where('training_schedule_trainers.trainer_type', '=', 'internal');
                    })
                    ->leftJoin('tbl_data_outlet as outlets', 'training_schedules.outlet_id', '=', 'outlets.id_outlet')
                    ->where('training_invitations.user_id', $employee->id)
                    ->where('training_invitations.status', 'attended')
                    ->whereNotNull('training_invitations.check_out_time')
                    ->select([
                        'lms_courses.id as course_id',
                        'lms_courses.title as course_title',
                        'lms_courses.duration_minutes',
                        'lms_courses.difficulty_level',
                        'lms_courses.type',
                        'lms_courses.specification',
                        'lms_courses.course_type',
                        'lms_courses.category_id',
                        'training_schedules.scheduled_date',
                        'training_schedules.start_time',
                        'training_schedules.end_time',
                        'training_schedules.outlet_id',
                        'training_invitations.check_in_time',
                        'training_invitations.check_out_time',
                        'training_schedule_trainers.trainer_type',
                        'training_schedule_trainers.external_trainer_name',
                        'trainers.nama_lengkap as internal_trainer_name',
                        'trainers.email as trainer_email',
                        'outlets.nama_outlet as outlet_name'
                    ])
                    ->get();

                // Calculate total duration
                $totalDurationMinutes = $completedTrainings->sum('duration_minutes');
                $totalDurationHours = round($totalDurationMinutes / 60, 1);

                // SIMPLIFIED: Get all published courses for this employee
                // For now, let's just get all courses and let frontend handle filtering
                $availableCourses = $allCourses;
                
                \Log::info('Available courses for employee (SIMPLIFIED)', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->nama_lengkap,
                    'total_courses' => $availableCourses->count(),
                    'course_titles' => $availableCourses->pluck('title')->toArray()
                ]);

                // Get completed course IDs
                $completedCourseIds = $completedTrainings->pluck('course_id')->toArray();

                \Log::info('Employee training analysis', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->nama_lengkap,
                    'total_available_courses' => $availableCourses->count(),
                    'completed_course_ids' => $completedCourseIds,
                    'completed_trainings_count' => $completedTrainings->count()
                ]);

                // SIMPLIFIED: Separate completed and available courses
                $completedCourses = collect();
                $remainingAvailableCourses = collect();
                
                foreach ($availableCourses as $course) {
                    if (in_array($course->id, $completedCourseIds)) {
                        $completedCourses->push($course);
                    } else {
                        $remainingAvailableCourses->push($course);
                    }
                }

                \Log::info('Course separation result', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->nama_lengkap,
                    'completed_courses_count' => $completedCourses->count(),
                    'remaining_available_courses_count' => $remainingAvailableCourses->count(),
                    'remaining_course_titles' => $remainingAvailableCourses->pluck('title')->toArray(),
                    'all_available_course_titles' => $availableCourses->pluck('title')->toArray(),
                    'completed_course_titles' => $completedCourses->pluck('title')->toArray()
                ]);

                return [
                    'employee' => [
                        'id' => $employee->id,
                        'nama_lengkap' => $employee->nama_lengkap,
                        'email' => $employee->email,
                        'division' => $employee->divisi ? [
                            'id' => $employee->divisi->id,
                            'nama_divisi' => $employee->divisi->nama_divisi
                        ] : null,
                        'jabatan' => $employee->jabatan ? [
                            'id_jabatan' => $employee->jabatan->id_jabatan,
                            'nama_jabatan' => $employee->jabatan->nama_jabatan
                        ] : null,
                        'outlet' => $employee->outlet ? [
                            'id_outlet' => $employee->outlet->id_outlet,
                            'nama_outlet' => $employee->outlet->nama_outlet
                        ] : null
                    ],
                    'training_summary' => [
                        'total_available' => $remainingAvailableCourses->count() + $completedCourses->count(),
                        'total_completed' => $completedCourses->count(),
                        'total_available_remaining' => $remainingAvailableCourses->count(),
                        'total_duration_minutes' => $totalDurationMinutes,
                        'total_duration_hours' => $totalDurationHours,
                        'completion_rate' => $remainingAvailableCourses->count() + $completedCourses->count() > 0 
                            ? round(($completedCourses->count() / ($remainingAvailableCourses->count() + $completedCourses->count())) * 100, 1)
                            : 0
                    ],
                    'completed_trainings' => $completedTrainings->map(function($training) use ($allCourses) {
                        // Find the course to get competencies
                        $course = $allCourses->firstWhere('id', $training->course_id);
                        
                        // Determine trainer name
                        $trainerName = null;
                        if ($training->trainer_type === 'internal' && $training->internal_trainer_name) {
                            $trainerName = $training->internal_trainer_name;
                        } elseif ($training->trainer_type === 'external' && $training->external_trainer_name) {
                            $trainerName = $training->external_trainer_name;
                        }

                        return [
                            'course_id' => $training->course_id,
                            'title' => $training->course_title,
                            'duration_minutes' => $training->duration_minutes,
                            'difficulty_level' => $training->difficulty_level,
                            'type' => $training->type,
                            'specification' => $training->specification,
                            'course_type' => $training->course_type,
                            'scheduled_date' => $training->scheduled_date,
                            'start_time' => $training->start_time,
                            'end_time' => $training->end_time,
                            'check_in_time' => $training->check_in_time,
                            'check_out_time' => $training->check_out_time,
                            'competencies' => $course ? $course->competencies->map(function($competency) {
                                return [
                                    'id' => $competency->id,
                                    'name' => $competency->name
                                ];
                            })->toArray() : [],
                            'trainer_info' => [
                                'name' => $trainerName,
                                'type' => $training->trainer_type,
                                'email' => $training->trainer_email
                            ],
                            'location_info' => [
                                'outlet_id' => $training->outlet_id,
                                'outlet_name' => $training->outlet_name
                            ]
                        ];
                    })->toArray(),
                    'available_trainings' => $remainingAvailableCourses->map(function($course) {
                        return [
                            'id' => $course->id,
                            'title' => $course->title,
                            'description' => $course->description,
                            'short_description' => $course->short_description,
                            'duration_minutes' => $course->duration_minutes,
                            'duration_formatted' => $course->duration_formatted ?? $course->duration_hours . ' jam',
                            'difficulty_level' => $course->difficulty_level,
                            'type' => $course->type,
                            'specification' => $course->specification,
                            'course_type' => $course->course_type,
                            'competencies' => $course->competencies->map(function($competency) {
                                return [
                                    'id' => $competency->id,
                                    'name' => $competency->name
                                ];
                            })->toArray(),
                            'category' => $course->category ? [
                                'id' => $course->category->id,
                                'name' => $course->category->name
                            ] : null,
                            'target_info' => [
                                'type' => $course->target_type,
                                'divisions' => $course->targetDivision ? [$course->targetDivision->nama_divisi] : ($course->targetDivisions ? $course->targetDivisions->pluck('nama_divisi')->toArray() : []),
                                'jabatans' => [],
                                'outlets' => []
                            ]
                        ];
                    })->toArray()
                    
                    // TEST: Add hardcoded available training for testing
                    + ($employee->id == 2 ? [[
                        'id' => 999,
                        'title' => 'Test Available Training',
                        'description' => 'This is a test training to verify available trainings display',
                        'short_description' => 'Test training for debugging',
                        'duration_minutes' => 60,
                        'duration_formatted' => '1j 0m',
                        'difficulty_level' => 'beginner',
                        'type' => 'offline',
                        'course_type' => 'mandatory',
                        'category' => [
                            'id' => 1,
                            'name' => 'Test Category'
                        ],
                        'target_info' => [
                            'type' => 'all',
                            'divisions' => [],
                            'jabatans' => [],
                            'outlets' => []
                        ]
                    ]] : [])
                ];
            });

            \Log::info('=== GET EMPLOYEE TRAINING REPORT SUCCESS ===', [
                'total_employees' => $employeeReports->count()
            ]);

            return response()->json([
                'success' => true,
                'employees' => $employeeReports,
                'summary' => [
                    'total_employees' => $employeeReports->count(),
                    'total_completed_trainings' => $employeeReports->sum('training_summary.total_completed'),
                    'total_available_trainings' => $employeeReports->sum('training_summary.total_available'),
                    'average_completion_rate' => $employeeReports->avg('training_summary.completion_rate')
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('=== GET EMPLOYEE TRAINING REPORT ERROR ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat laporan training karyawan: ' . $e->getMessage()
            ], 500);
        }
    }


    public function employeeTrainingReportPage()
    {
        // Check permission
        $user = auth()->user();
        if (!$user || ($user->id_role !== '5af56935b011a' && $user->id_jabatan !== 170)) {
            abort(403, 'Anda tidak memiliki akses untuk melihat laporan training karyawan');
        }

        // Get master data for filters (same as Courses page)
        $divisions = \App\Models\Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']);
        $jabatans = \App\Models\Jabatan::active()->with(['divisi', 'level'])->orderBy('nama_jabatan')->get(['id_jabatan', 'nama_jabatan', 'id_divisi', 'id_level']);
        $outlets = \App\Models\DataOutlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);

        return Inertia::render('Lms/EmployeeTrainingReport', [
            'divisions' => $divisions,
            'jabatans' => $jabatans,
            'outlets' => $outlets,
            'user' => $user
        ]);
    }

    private function getCourseTargetInfo($course)
    {
        try {
            $targetInfo = [
                'type' => $course->target_type,
                'divisions' => [],
                'jabatans' => [],
                'outlets' => []
            ];

            // Get division info
            if ($course->target_type === 'single' && $course->target_division_id && $course->targetDivision) {
                $targetInfo['divisions'] = [$course->targetDivision->nama_divisi];
            } elseif ($course->target_type === 'multiple' && $course->targetDivisions) {
                $targetInfo['divisions'] = $course->targetDivisions->pluck('nama_divisi')->toArray();
            }

            // Get jabatan info
            if ($course->target_jabatan_ids && is_array($course->target_jabatan_ids)) {
                $jabatanNames = \App\Models\Jabatan::whereIn('id_jabatan', $course->target_jabatan_ids)
                    ->pluck('nama_jabatan')
                    ->toArray();
                $targetInfo['jabatans'] = $jabatanNames;
            }

            // Get outlet info
            if ($course->target_outlet_ids && is_array($course->target_outlet_ids)) {
                $outletNames = \App\Models\DataOutlet::whereIn('id_outlet', $course->target_outlet_ids)
                    ->pluck('nama_outlet')
                    ->toArray();
                $targetInfo['outlets'] = $outletNames;
            }

            return $targetInfo;
        } catch (\Exception $e) {
            \Log::error('Error in getCourseTargetInfo: ' . $e->getMessage());
            return [
                'type' => $course->target_type ?? 'unknown',
                'divisions' => [],
                'jabatans' => [],
                'outlets' => []
            ];
        }
    }

    public function getTrainerReport()
    {
        try {
            // Check if user can view trainer reports
            $user = auth()->user();
            $canView = false;
            
            if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
                $canView = true;
            } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
                $canView = true;
            }
            
            if (!$canView) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk melihat report trainer'
                ], 403);
            }

            // Get all trainers with their training data
            $trainers = DB::table('training_schedule_trainers')
                ->leftJoin('users', function($join) {
                    $join->on('training_schedule_trainers.trainer_id', '=', 'users.id')
                         ->where('training_schedule_trainers.trainer_type', '=', 'internal');
                })
                ->leftJoin('training_schedules', 'training_schedule_trainers.schedule_id', '=', 'training_schedules.id')
                ->leftJoin('lms_courses', 'training_schedules.course_id', '=', 'lms_courses.id')
                ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
                ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
                ->leftJoin('tbl_data_outlet', 'training_schedules.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->select(
                    'training_schedule_trainers.trainer_id',
                    'training_schedule_trainers.external_trainer_name',
                    'training_schedule_trainers.trainer_type',
                    'users.nama_lengkap as internal_trainer_name',
                    'users.email as trainer_email',
                    'users.avatar as trainer_avatar',
                    'tbl_data_jabatan.nama_jabatan as trainer_position',
                    'tbl_data_divisi.nama_divisi as trainer_division',
                    'training_schedules.id as schedule_id',
                    'training_schedules.scheduled_date',
                    'training_schedules.start_time',
                    'training_schedules.end_time',
                    'training_schedules.status as schedule_status',
                    'lms_courses.id as course_id',
                    'lms_courses.title as course_title',
                    'lms_courses.duration_minutes',
                    'tbl_data_outlet.nama_outlet as outlet_name'
                )
                ->whereNotNull('training_schedules.id')
                ->orderBy('training_schedule_trainers.trainer_id')
                ->orderBy('training_schedules.scheduled_date', 'desc')
                ->get();

            // Group by trainer and calculate statistics
            $trainerStats = [];
            $trainerDetails = [];

            foreach ($trainers as $training) {
                $trainerId = $training->trainer_id;
                $trainerName = $training->trainer_type === 'internal' 
                    ? $training->internal_trainer_name 
                    : $training->external_trainer_name;

                // Initialize trainer stats if not exists
                if (!isset($trainerStats[$trainerId])) {
                    $trainerStats[$trainerId] = [
                        'trainer_id' => $trainerId,
                        'trainer_name' => $trainerName,
                        'trainer_type' => $training->trainer_type,
                        'trainer_email' => $training->trainer_email,
                        'trainer_avatar' => $training->trainer_avatar,
                        'trainer_position' => $training->trainer_position,
                        'trainer_division' => $training->trainer_division,
                        'total_trainings' => 0,
                        'total_duration_minutes' => 0,
                        'total_duration_hours' => 0,
                        'average_rating' => 0,
                        'total_ratings' => 0,
                        'completed_trainings' => 0,
                        'cancelled_trainings' => 0
                    ];
                    $trainerDetails[$trainerId] = [];
                }

                // Add training details
                $trainingDetail = [
                    'schedule_id' => $training->schedule_id,
                    'course_id' => $training->course_id,
                    'course_title' => $training->course_title,
                    'scheduled_date' => $training->scheduled_date,
                    'start_time' => $training->start_time,
                    'end_time' => $training->end_time,
                    'duration_minutes' => $training->duration_minutes,
                    'outlet_name' => $training->outlet_name,
                    'status' => $training->schedule_status
                ];

                $trainerDetails[$trainerId][] = $trainingDetail;

                // Update statistics
                $trainerStats[$trainerId]['total_trainings']++;
                $trainerStats[$trainerId]['total_duration_minutes'] += $training->duration_minutes ?? 0;
                
                if ($training->schedule_status === 'completed') {
                    $trainerStats[$trainerId]['completed_trainings']++;
                } elseif ($training->schedule_status === 'cancelled') {
                    $trainerStats[$trainerId]['cancelled_trainings']++;
                }
            }

            // Calculate total duration in hours and get ratings
            foreach ($trainerStats as $trainerId => &$stats) {
                $stats['total_duration_hours'] = round($stats['total_duration_minutes'] / 60, 2);

                // Get trainer ratings
                $ratings = DB::table('training_reviews')
                    ->join('training_schedules', 'training_reviews.training_schedule_id', '=', 'training_schedules.id')
                    ->join('training_schedule_trainers', function($join) {
                        $join->on('training_schedules.id', '=', 'training_schedule_trainers.schedule_id')
                             ->where('training_schedule_trainers.trainer_type', '=', 'internal');
                    })
                    ->where('training_schedule_trainers.trainer_id', $trainerId)
                    ->whereNotNull('training_reviews.training_rating')
                    ->select('training_reviews.training_rating')
                    ->get();

                if ($ratings->count() > 0) {
                    $stats['total_ratings'] = $ratings->count();
                    $stats['average_rating'] = round($ratings->avg('training_rating'), 2);
                }
            }

            // Convert to array and sort by total trainings
            $trainerReport = array_values($trainerStats);
            usort($trainerReport, function($a, $b) {
                return $b['total_trainings'] - $a['total_trainings'];
            });

            return response()->json([
                'success' => true,
                'trainers' => $trainerReport,
                'trainer_details' => $trainerDetails
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching trainer report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat report trainer'
            ], 500);
        }
    }

    /**
     * Get training report with comprehensive statistics
     */
    public function getTrainingReport(Request $request)
    {
        try {
            // Check if user can view training reports
            $user = auth()->user();
            $canView = false;
            
            if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
                $canView = true;
            } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
                $canView = true;
            }
            
            if (!$canView) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk melihat report training'
                ], 403);
            }

            // Get filter parameters
            $filters = [
                'division_id' => $request->get('division_id'),
                'outlet_id' => $request->get('outlet_id'),
                'jabatan_id' => $request->get('jabatan_id'),
                'level_id' => $request->get('level_id'),
                'category_id' => $request->get('category_id'),
                'specification' => $request->get('specification'),
                'trainer_type' => $request->get('trainer_type'),
                'from_date' => $request->get('from_date'),
                'to_date' => $request->get('to_date'),
            ];

            // Get Man Power (MP) - Active users
            $mpQuery = DB::table('users')
                ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
                ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
                ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
                ->leftJoin('tbl_data_level', 'tbl_data_jabatan.id_level', '=', 'tbl_data_level.id')
                ->where('users.status', 'A');

            // Apply filters for MP
            if ($filters['division_id']) {
                $mpQuery->where('users.division_id', $filters['division_id']);
            }
            if ($filters['outlet_id']) {
                $mpQuery->where('users.id_outlet', $filters['outlet_id']);
            }
            if ($filters['jabatan_id']) {
                $mpQuery->where('users.id_jabatan', $filters['jabatan_id']);
            }
            if ($filters['level_id']) {
                $mpQuery->where('tbl_data_jabatan.id_level', $filters['level_id']);
            }

            $manPower = $mpQuery->count();

            // Get training data
            $trainingQuery = DB::table('training_schedules as ts')
                ->leftJoin('lms_courses as c', 'ts.course_id', '=', 'c.id')
                ->leftJoin('lms_categories as cc', 'c.category_id', '=', 'cc.id')
                ->leftJoin('training_schedule_trainers as tst', 'ts.id', '=', 'tst.schedule_id')
                ->leftJoin('training_invitations as ti', 'ts.id', '=', 'ti.schedule_id')
                ->leftJoin('users as u', 'ti.user_id', '=', 'u.id')
                ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
                ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
                ->where('ts.status', 'completed')
                ->where('u.status', 'A');

            // Apply filters for training data
            if ($filters['division_id']) {
                $trainingQuery->where('u.division_id', $filters['division_id']);
            }
            if ($filters['outlet_id']) {
                $trainingQuery->where('u.id_outlet', $filters['outlet_id']);
            }
            if ($filters['jabatan_id']) {
                $trainingQuery->where('u.id_jabatan', $filters['jabatan_id']);
            }
            if ($filters['level_id']) {
                $trainingQuery->where('j.id_level', $filters['level_id']);
            }
            if ($filters['category_id']) {
                $trainingQuery->where('c.category_id', $filters['category_id']);
            }
            if ($filters['specification']) {
                $trainingQuery->where('c.specification', $filters['specification']);
            }
            if ($filters['trainer_type']) {
                $trainingQuery->where('tst.trainer_type', $filters['trainer_type']);
            }
            if ($filters['from_date']) {
                $trainingQuery->where('ts.scheduled_date', '>=', $filters['from_date']);
            }
            if ($filters['to_date']) {
                $trainingQuery->where('ts.scheduled_date', '<=', $filters['to_date']);
            }

            $trainingData = $trainingQuery->select(
                'ts.id as schedule_id',
                'c.id as course_id',
                'c.title as course_title',
                'c.duration_minutes',
                'cc.name as category_name',
                'tst.trainer_type',
                'ts.scheduled_date',
                'ti.user_id',
                'u.nama_lengkap',
                'd.nama_divisi',
                'o.nama_outlet',
                'j.nama_jabatan',
                'l.nama_level'
            )->get();

            // Calculate statistics
            $qty = $trainingData->groupBy('schedule_id')->count(); // Jumlah training yang sudah dilaksanakan
            $pax = $trainingData->groupBy('user_id')->count(); // Man Power yang sudah mengikuti training
            $totalHours = $trainingData->sum('duration_minutes') / 60; // Total jam training
            $percentage = $manPower > 0 ? round(($pax / $manPower) * 100, 2) : 0; // Persentase MP yang sudah ikut training

            // Get filter options
            $divisions = DB::table('tbl_data_divisi')->where('status', 'A')->orderBy('nama_divisi')->get();
            $outlets = DB::table('tbl_data_outlet')->where('status', 'A')->orderBy('nama_outlet')->get();
            $jabatans = DB::table('tbl_data_jabatan')->where('status', 'A')->orderBy('nama_jabatan')->get();
            $levels = DB::table('tbl_data_level')->where('status', 'A')->orderBy('nama_level')->get();
            $categories = DB::table('lms_categories')->where('status', 'A')->orderBy('name')->get();

            // Get detailed data for modals
            $manPowerDetails = $this->getManPowerDetails($request);
            $qtyDetails = $this->getQTYDetails($request);
            $paxDetails = $this->getPaxDetails($request);
            $hoursDetails = $this->getHoursDetails($request);

            return response()->json([
                'success' => true,
                'data' => [
                    'man_power' => $manPower,
                    'qty' => $qty,
                    'pax' => $pax,
                    'hours' => round($totalHours, 2),
                    'percentage' => $percentage,
                    'man_power_details' => $manPowerDetails,
                    'qty_details' => $qtyDetails,
                    'pax_details' => $paxDetails,
                    'hours_details' => $hoursDetails,
                    'filters' => $filters,
                    'filter_options' => [
                        'divisions' => $divisions,
                        'outlets' => $outlets,
                        'jabatans' => $jabatans,
                        'levels' => $levels,
                        'categories' => $categories,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getTrainingReport: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat data training report'
            ], 500);
        }
    }

    private function getManPowerDetails($request)
    {
        $filters = $request->only(['division_id', 'outlet_id', 'jabatan_id', 'level_id', 'category_id', 'specification', 'trainer_type', 'from_date', 'to_date']);
        
        $query = DB::table('users as u')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
            ->where('u.status', 'A')
            ->select('u.id', 'u.nama_lengkap', 'u.avatar', 'd.nama_divisi', 'o.nama_outlet', 'j.nama_jabatan', 'l.nama_level');

        // Apply filters
        if (!empty($filters['division_id'])) {
            $query->where('u.division_id', $filters['division_id']);
        }
        if (!empty($filters['outlet_id'])) {
            $query->where('u.id_outlet', $filters['outlet_id']);
        }
        if (!empty($filters['jabatan_id'])) {
            $query->where('u.id_jabatan', $filters['jabatan_id']);
        }
        if (!empty($filters['level_id'])) {
            $query->where('j.id_level', $filters['level_id']);
        }

        return $query->orderBy('u.nama_lengkap')->get();
    }

    private function getQTYDetails($request)
    {
        $filters = $request->only(['division_id', 'outlet_id', 'jabatan_id', 'level_id', 'category_id', 'specification', 'trainer_type', 'from_date', 'to_date']);
        
        $query = DB::table('training_schedules as ts')
            ->leftJoin('lms_courses as c', 'ts.course_id', '=', 'c.id')
            ->leftJoin('lms_categories as cc', 'c.category_id', '=', 'cc.id')
            ->leftJoin('training_schedule_trainers as tst', 'ts.id', '=', 'tst.schedule_id')
            ->leftJoin('training_invitations as ti', 'ts.id', '=', 'ti.schedule_id')
            ->leftJoin('users as u', 'ti.user_id', '=', 'u.id')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
            ->where('ts.status', 'completed')
            ->where('u.status', 'A')
            ->select('c.id as course_id', 'c.title as course_title', 'c.duration_minutes', 'cc.name as category_name')
            ->selectRaw('COUNT(DISTINCT ts.id) as count');

        // Apply filters
        if (!empty($filters['division_id'])) {
            $query->where('u.division_id', $filters['division_id']);
        }
        if (!empty($filters['outlet_id'])) {
            $query->where('u.id_outlet', $filters['outlet_id']);
        }
        if (!empty($filters['jabatan_id'])) {
            $query->where('u.id_jabatan', $filters['jabatan_id']);
        }
        if (!empty($filters['level_id'])) {
            $query->where('j.id_level', $filters['level_id']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('c.category_id', $filters['category_id']);
        }
        if (!empty($filters['specification'])) {
            $query->where('c.specification', $filters['specification']);
        }
        if (!empty($filters['trainer_type'])) {
            $query->where('tst.trainer_type', $filters['trainer_type']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('ts.scheduled_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('ts.scheduled_date', '<=', $filters['to_date']);
        }

        return $query->groupBy('c.id', 'c.title', 'c.duration_minutes', 'cc.name')
                    ->orderBy('count', 'desc')
                    ->get();
    }

    private function getPaxDetails($request)
    {
        $filters = $request->only(['division_id', 'outlet_id', 'jabatan_id', 'level_id', 'category_id', 'specification', 'trainer_type', 'from_date', 'to_date']);
        
        // Get participants with their training count
        $participants = DB::table('training_schedules as ts')
            ->leftJoin('lms_courses as c', 'ts.course_id', '=', 'c.id')
            ->leftJoin('lms_categories as cc', 'c.category_id', '=', 'cc.id')
            ->leftJoin('training_schedule_trainers as tst', 'ts.id', '=', 'tst.schedule_id')
            ->leftJoin('training_invitations as ti', 'ts.id', '=', 'ti.schedule_id')
            ->leftJoin('users as u', 'ti.user_id', '=', 'u.id')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
            ->where('ts.status', 'completed')
            ->where('u.status', 'A')
            ->select('u.id as user_id', 'u.nama_lengkap', 'u.avatar', 'd.nama_divisi', 'o.nama_outlet')
            ->selectRaw('COUNT(DISTINCT ts.id) as training_count')
            ->groupBy('u.id', 'u.nama_lengkap', 'u.avatar', 'd.nama_divisi', 'o.nama_outlet');

        // Apply filters
        if (!empty($filters['division_id'])) {
            $participants->where('u.division_id', $filters['division_id']);
        }
        if (!empty($filters['outlet_id'])) {
            $participants->where('u.id_outlet', $filters['outlet_id']);
        }
        if (!empty($filters['jabatan_id'])) {
            $participants->where('u.id_jabatan', $filters['jabatan_id']);
        }
        if (!empty($filters['level_id'])) {
            $participants->where('j.id_level', $filters['level_id']);
        }
        if (!empty($filters['category_id'])) {
            $participants->where('c.category_id', $filters['category_id']);
        }
        if (!empty($filters['trainer_type'])) {
            $participants->where('tst.trainer_type', $filters['trainer_type']);
        }
        if (!empty($filters['from_date'])) {
            $participants->where('ts.scheduled_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $participants->where('ts.scheduled_date', '<=', $filters['to_date']);
        }

        $participants = $participants->orderBy('u.nama_lengkap')->get();

        // Get training details for each participant
        foreach ($participants as $participant) {
            $trainings = DB::table('training_schedules as ts')
                ->leftJoin('lms_courses as c', 'ts.course_id', '=', 'c.id')
                ->leftJoin('lms_categories as cc', 'c.category_id', '=', 'cc.id')
                ->leftJoin('training_schedule_trainers as tst', 'ts.id', '=', 'tst.schedule_id')
                ->leftJoin('training_invitations as ti', 'ts.id', '=', 'ti.schedule_id')
                ->leftJoin('users as u', 'ti.user_id', '=', 'u.id')
                ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
                ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
                ->where('ts.status', 'completed')
                ->where('u.id', $participant->user_id)
                ->where('u.status', 'A')
                ->select('ts.id as schedule_id', 'c.title as course_title', 'cc.name as category_name', 'ts.scheduled_date');

            // Apply same filters
            if (!empty($filters['division_id'])) {
                $trainings->where('u.division_id', $filters['division_id']);
            }
            if (!empty($filters['outlet_id'])) {
                $trainings->where('u.id_outlet', $filters['outlet_id']);
            }
            if (!empty($filters['jabatan_id'])) {
                $trainings->where('u.id_jabatan', $filters['jabatan_id']);
            }
            if (!empty($filters['level_id'])) {
                $trainings->where('j.id_level', $filters['level_id']);
            }
            if (!empty($filters['category_id'])) {
                $trainings->where('c.category_id', $filters['category_id']);
            }
            if (!empty($filters['trainer_type'])) {
                $trainings->where('tst.trainer_type', $filters['trainer_type']);
            }
            if (!empty($filters['from_date'])) {
                $trainings->where('ts.scheduled_date', '>=', $filters['from_date']);
            }
            if (!empty($filters['to_date'])) {
                $trainings->where('ts.scheduled_date', '<=', $filters['to_date']);
            }

            $participant->trainings = $trainings->orderBy('ts.scheduled_date', 'desc')->get();
        }

        return $participants;
    }

    private function getHoursDetails($request)
    {
        $filters = $request->only(['division_id', 'outlet_id', 'jabatan_id', 'level_id', 'category_id', 'specification', 'trainer_type', 'from_date', 'to_date']);
        
        $query = DB::table('training_schedules as ts')
            ->leftJoin('lms_courses as c', 'ts.course_id', '=', 'c.id')
            ->leftJoin('lms_categories as cc', 'c.category_id', '=', 'cc.id')
            ->leftJoin('training_schedule_trainers as tst', 'ts.id', '=', 'tst.schedule_id')
            ->leftJoin('training_invitations as ti', 'ts.id', '=', 'ti.schedule_id')
            ->leftJoin('users as u', 'ti.user_id', '=', 'u.id')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
            ->where('ts.status', 'completed')
            ->where('u.status', 'A')
            ->select('c.id as course_id', 'c.title as course_title', 'c.duration_minutes', 'cc.name as category_name')
            ->selectRaw('COUNT(DISTINCT ts.id) as count');

        // Apply filters
        if (!empty($filters['division_id'])) {
            $query->where('u.division_id', $filters['division_id']);
        }
        if (!empty($filters['outlet_id'])) {
            $query->where('u.id_outlet', $filters['outlet_id']);
        }
        if (!empty($filters['jabatan_id'])) {
            $query->where('u.id_jabatan', $filters['jabatan_id']);
        }
        if (!empty($filters['level_id'])) {
            $query->where('j.id_level', $filters['level_id']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('c.category_id', $filters['category_id']);
        }
        if (!empty($filters['specification'])) {
            $query->where('c.specification', $filters['specification']);
        }
        if (!empty($filters['trainer_type'])) {
            $query->where('tst.trainer_type', $filters['trainer_type']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('ts.scheduled_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('ts.scheduled_date', '<=', $filters['to_date']);
        }

        return $query->groupBy('c.id', 'c.title', 'c.duration_minutes', 'cc.name')
                    ->orderBy('count', 'desc')
                    ->get();
    }

    public function getQuizReport(Request $request)
    {
        try {
            $filters = $request->only(['division_id', 'outlet_id', 'jabatan_id', 'level_id', 'from_date', 'to_date']);
            
            // Get quiz attempts with user and course details through enrollment
            $query = DB::table('lms_quiz_attempts as qa')
                ->leftJoin('users as u', 'qa.user_id', '=', 'u.id')
                ->leftJoin('lms_quizzes as q', 'qa.quiz_id', '=', 'q.id')
                ->leftJoin('lms_enrollments as e', 'qa.enrollment_id', '=', 'e.id')
                ->leftJoin('lms_courses as c', 'e.course_id', '=', 'c.id')
                ->leftJoin('lms_categories as cc', 'c.category_id', '=', 'cc.id')
                ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
                ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->leftJoin('tbl_data_level as l', 'j.id_level', '=', 'l.id')
                ->where('u.status', 'A')
                ->where('qa.status', 'completed')
                ->select(
                    'qa.id as attempt_id',
                    'qa.user_id',
                    'u.nama_lengkap',
                    'u.avatar',
                    'd.nama_divisi',
                    'o.nama_outlet',
                    'j.nama_jabatan',
                    'l.nama_level',
                    'q.title as quiz_title',
                    'q.passing_score',
                    'qa.score',
                    'qa.is_passed',
                    'qa.completed_at',
                    'cc.name as category_name'
                );

            // Apply filters
            if (!empty($filters['division_id'])) {
                $query->where('u.division_id', $filters['division_id']);
            }
            if (!empty($filters['outlet_id'])) {
                $query->where('u.id_outlet', $filters['outlet_id']);
            }
            if (!empty($filters['jabatan_id'])) {
                $query->where('u.id_jabatan', $filters['jabatan_id']);
            }
            if (!empty($filters['level_id'])) {
                $query->where('j.id_level', $filters['level_id']);
            }
            if (!empty($filters['from_date'])) {
                $query->where('qa.completed_at', '>=', $filters['from_date']);
            }
            if (!empty($filters['to_date'])) {
                $query->where('qa.completed_at', '<=', $filters['to_date'] . ' 23:59:59');
            }

            $quizAttempts = $query->orderBy('qa.completed_at', 'desc')->get();

            // Get filter options
            $divisions = DB::table('tbl_data_divisi')->where('status', 'A')->orderBy('nama_divisi')->get();
            $outlets = DB::table('tbl_data_outlet')->where('status', 'A')->orderBy('nama_outlet')->get();
            $jabatans = DB::table('tbl_data_jabatan')->where('status', 'A')->orderBy('nama_jabatan')->get();
            $levels = DB::table('tbl_data_level')->where('status', 'A')->orderBy('nama_level')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'quiz_attempts' => $quizAttempts,
                    'filters' => $filters,
                    'filter_options' => [
                        'divisions' => $divisions,
                        'outlets' => $outlets,
                        'jabatans' => $jabatans,
                        'levels' => $levels,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getQuizReport: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat data quiz report'
            ], 500);
        }
    }
} 