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
        \Log::info('=== LMS COURSES METHOD START ===', [
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->nama_lengkap ?? 'Unknown',
            'timestamp' => now()->toISOString()
        ]);
        
        $startTime = microtime(true);
        
        $user = auth()->user()->load('jabatan');
        
        // Check if user is admin/manager (can see all courses)
        $canSeeAllCourses = false;
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canSeeAllCourses = true;
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canSeeAllCourses = true;
        }

        // DEBUG: Log user data for troubleshooting
        \Log::info('User data for course filtering:', [
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap,
            'division_id' => $user->division_id,
            'id_jabatan' => $user->id_jabatan,
            'id_outlet' => $user->id_outlet,
            'canSeeAllCourses' => $canSeeAllCourses,
            'id_role' => $user->id_role,
            'status' => $user->status
        ]);

        $query = LmsCourse::with(['category', 'instructor.jabatan.divisi', 'instructor.jabatan.level', 'instructor.divisi', 'targetDivisions'])
            ->where('status', 'published');

        // DEBUG: Log total courses before filtering
        $totalCoursesBeforeFilter = LmsCourse::where('status', 'published')->count();
        \Log::info('Total published courses before filtering:', ['count' => $totalCoursesBeforeFilter]);

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

        // DEBUG: Log total courses after filtering
        $totalCoursesAfterFilter = $query->count();
        \Log::info('Total courses after filtering:', ['count' => $totalCoursesAfterFilter]);

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
            \Log::info('Starting courses query with timeout protection...');
            
            $courses = $query->orderBy('created_at', 'desc')
                ->with(['category:id,name', 'instructor:id,nama_lengkap'])
                ->paginate(20); // Limit to 20 courses per page
                
            // Add instructor_name to each course for frontend compatibility
            $courses->getCollection()->transform(function ($course) {
                $course->instructor_name = $course->instructor ? $course->instructor->nama_lengkap : 'Tidak ada';
                return $course;
            });
                
            \Log::info('Courses query completed successfully');
        } catch (\Exception $e) {
            \Log::error('Courses query failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        \Log::info('Courses query completed', [
            'total_courses' => $courses->total(),
            'current_page' => $courses->currentPage(),
            'per_page' => $courses->perPage()
        ]);

        // Optimize categories query
        $categories = LmsCategory::active()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        \Log::info('Categories query completed', ['count' => $categories->count()]);

        // Optimize divisions query
        $divisions = Divisi::active()
            ->select(['id', 'nama_divisi'])
            ->orderBy('nama_divisi')
            ->get();

        \Log::info('Divisions query completed', ['count' => $divisions->count()]);

        // Optimize jabatans query with limited fields
        $jabatans = Jabatan::active()
            ->select(['id_jabatan', 'nama_jabatan', 'id_divisi'])
            ->with(['divisi:id,nama_divisi'])
            ->orderBy('nama_jabatan')
            ->limit(100) // Limit to prevent memory issues
            ->get();

        \Log::info('Jabatans query completed', ['count' => $jabatans->count()]);

        // Optimize outlets query
        $outlets = DataOutlet::where('status', 'A')
            ->select(['id_outlet', 'nama_outlet'])
            ->orderBy('nama_outlet')
            ->limit(100) // Limit to prevent memory issues
            ->get();

        \Log::info('Outlets query completed', ['count' => $outlets->count()]);

        // Get internal trainers (users with specific jabatan that can be trainers)
        $internalTrainers = User::where('status', 'A')
            ->whereNotNull('id_jabatan')
            ->select(['id', 'nama_lengkap', 'id_jabatan'])
            ->with(['jabatan:id_jabatan,nama_jabatan'])
            ->orderBy('nama_lengkap')
            ->get(); // Removed limit to show all available trainers

        \Log::info('Internal trainers query completed', ['count' => $internalTrainers->count()]);
        
        // Debug log for trainers
        \Log::info('Available internal trainers:', [
            'trainers' => $internalTrainers->map(function($trainer) {
                return [
                    'id' => $trainer->id,
                    'nama' => $trainer->nama_lengkap,
                    'jabatan' => $trainer->jabatan->nama_jabatan ?? 'N/A'
                ];
            })->toArray()
        ]);

        // Optimize quizzes query
        $availableQuizzes = \App\Models\LmsQuiz::where('status', 'published')
            ->whereNull('course_id')
            ->select(['id', 'title', 'description'])
            ->orderBy('title')
            ->limit(50) // Limit to prevent memory issues
            ->get();
            
        \Log::info('Available quizzes query completed', ['count' => $availableQuizzes->count()]);

        // Optimize questionnaires query
        $availableQuestionnaires = \App\Models\LmsQuestionnaire::where('status', 'published')
            ->whereNull('course_id')
            ->select(['id', 'title', 'description'])
            ->orderBy('title')
            ->limit(50) // Limit to prevent memory issues
            ->get();

        \Log::info('Available questionnaires query completed', ['count' => $availableQuestionnaires->count()]);

        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
        
        \Log::info('=== LMS COURSES METHOD COMPLETED ===', [
            'execution_time_ms' => $executionTime,
            'total_courses' => $courses->total(),
            'categories_count' => $categories->count(),
            'divisions_count' => $divisions->count(),
            'jabatans_count' => $jabatans->count(),
            'outlets_count' => $outlets->count(),
            'trainers_count' => $internalTrainers->count(),
            'quizzes_count' => $availableQuizzes->count(),
            'questionnaires_count' => $availableQuestionnaires->count()
        ]);

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
        
        // Debug logging untuk request yang masuk
        \Log::info('=== STORE COURSE START ===');
        \Log::info('Request data:', [
            'all' => $request->all(),
            'files' => $request->allFiles(),
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->nama_lengkap ?? 'Unknown',
            'timestamp' => now()->toISOString()
        ]);
        
        // Debug: Check for material files specifically
        if ($request->has('sessions')) {
            foreach ($request->input('sessions', []) as $sessionIndex => $session) {
                if (isset($session['items'])) {
                    foreach ($session['items'] as $itemIndex => $item) {
                        if (isset($item['item_type']) && $item['item_type'] === 'material') {
                            \Log::info('Material item found:', [
                                'session_index' => $sessionIndex,
                                'item_index' => $itemIndex,
                                'item_data' => $item,
                                'has_material_files' => isset($item['material_files']),
                                'material_files_count' => isset($item['material_files']) ? count($item['material_files']) : 0,
                                'material_files_data' => $item['material_files'] ?? 'N/A'
                            ]);
                        } elseif (isset($item['item_type']) && $item['item_type'] === 'quiz') {
                            \Log::info('Quiz item found:', [
                                'session_index' => $sessionIndex,
                                'item_index' => $itemIndex,
                                'item_data' => $item,
                                'quiz_id' => $item['quiz_id'] ?? 'NOT SET',
                                'quiz_id_type' => gettype($item['quiz_id'] ?? null)
                            ]);
                        } elseif (isset($item['item_type']) && $item['item_type'] === 'questionnaire') {
                            \Log::info('Questionnaire item found:', [
                                'session_index' => $sessionIndex,
                                'item_index' => $itemIndex,
                                'item_data' => $item,
                                'questionnaire_id' => $item['questionnaire_id'] ?? 'NOT SET',
                                'questionnaire_id_type' => gettype($item['questionnaire_id'] ?? null)
                            ]);
                        }
                    }
                }
            }
        }
        
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
        
        \Log::info('Material files extracted:', [
            'total_material_files' => count($materialFiles),
            'files_keys' => array_keys($materialFiles)
        ]);
        
        \Log::info('=== REQUEST VALIDATION START ===');
        \Log::info('Starting validation process...');
        
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
                'type' => 'required|in:online,offline',
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
                'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);
            
            \Log::info('=== VALIDATION COMPLETED SUCCESSFULLY ===');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('=== VALIDATION FAILED ===');
            \Log::error('Validation errors:', $e->errors());
            \Log::error('Failed input data:', $request->all());
            throw $e;
        }
        
        \Log::info('Validated data structure:', [
            'has_title' => !empty($validated['title']),
            'has_category' => !empty($validated['category_id']),
            'has_sessions' => !empty($validated['sessions']),
            'sessions_count' => count($validated['sessions'] ?? []),
            'total_items' => array_sum(array_map(function($session) {
                return count($session['items'] ?? []);
            }, $validated['sessions'] ?? []))
        ]);

        // Set default values
        \Log::info('=== SETTING DEFAULT VALUES ===');
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();
        
        \Log::info('Default values set:', [
            'created_by' => $validated['created_by'],
            'updated_by' => $validated['updated_by']
        ]);
        
        \Log::info('Validated data after setting defaults:', $validated);


        // Create slug from title
        \Log::info('=== GENERATING SLUG ===');
        $validated['slug'] = Str::slug($validated['title']);
        \Log::info('Slug generated:', [
            'original_title' => $validated['title'],
            'generated_slug' => $validated['slug']
        ]);

        // Handle thumbnail upload
        \Log::info('=== HANDLING THUMBNAIL UPLOAD ===');
        if ($request->hasFile('thumbnail')) {
            \Log::info('Thumbnail file detected:', [
                'file_name' => $request->file('thumbnail')->getClientOriginalName(),
                'file_size' => $request->file('thumbnail')->getSize(),
                'mime_type' => $request->file('thumbnail')->getMimeType()
            ]);
            
            $thumbnailPath = $request->file('thumbnail')->store('lms/thumbnails', 'public');
            $validated['thumbnail'] = $thumbnailPath;
            \Log::info('Thumbnail uploaded successfully:', [
                'path' => $thumbnailPath,
                'full_url' => asset('storage/' . $thumbnailPath)
            ]);
        } else {
            \Log::info('No thumbnail file uploaded - skipping thumbnail processing');
        }

        // Handle target divisions based on target_type
        \Log::info('=== PROCESSING TARGET DIVISIONS ===');
        $targetDivisions = $request->input('target_divisions', []);
        \Log::info('Target divisions input:', [
            'target_type' => $validated['target_type'],
            'target_divisions_input' => $targetDivisions,
            'target_division_id' => $validated['target_division_id'] ?? null
        ]);
        
        if ($validated['target_type'] === 'all') {
            $validated['target_division_id'] = null;
            $validated['target_divisions'] = null;
            \Log::info('Target type "all" - clearing division targeting');
        } elseif ($validated['target_type'] === 'single') {
            $validated['target_divisions'] = null;
            \Log::info('Target type "single" - using single division ID');
        } elseif ($validated['target_type'] === 'multiple') {
            $validated['target_division_id'] = null;
            $validated['target_divisions'] = $targetDivisions;
            \Log::info('Target type "multiple" - using multiple divisions array');
        }
        
        \Log::info('Target divisions after processing:', [
            'target_division_id' => $validated['target_division_id'] ?? null,
            'target_divisions' => $validated['target_divisions'] ?? null,
            'target_type' => $validated['target_type'] ?? null
        ]);

        // Filter out empty learning objectives and requirements
        \Log::info('=== FILTERING EMPTY DATA ===');
        
        if (isset($validated['learning_objectives'])) {
            $originalCount = count($validated['learning_objectives']);
            $validated['learning_objectives'] = array_filter($validated['learning_objectives'], function($objective) {
                return !empty(trim($objective));
            });
            $filteredCount = count($validated['learning_objectives']);
            \Log::info('Learning objectives filtered:', [
                'original_count' => $originalCount,
                'filtered_count' => $filteredCount,
                'removed_count' => $originalCount - $filteredCount,
                'filtered_data' => $validated['learning_objectives']
            ]);
        }
        
        if (isset($validated['requirements'])) {
            $originalCount = count($validated['requirements']);
            $validated['requirements'] = array_filter($validated['requirements'], function($requirement) {
                return !empty(trim($requirement));
            });
            $filteredCount = count($validated['requirements']);
            \Log::info('Requirements filtered:', [
                'original_count' => $originalCount,
                'filtered_count' => $filteredCount,
                'removed_count' => $originalCount - $filteredCount,
                'filtered_data' => $validated['requirements']
            ]);
        }

        // Filter out empty target arrays
        if (isset($validated['target_jabatan_ids'])) {
            $originalCount = count($validated['target_jabatan_ids']);
            $validated['target_jabatan_ids'] = array_filter($validated['target_jabatan_ids'], function($id) {
                return !empty($id);
            });
            $filteredCount = count($validated['target_jabatan_ids']);
            \Log::info('Target jabatan IDs filtered:', [
                'original_count' => $originalCount,
                'filtered_count' => $filteredCount,
                'removed_count' => $originalCount - $filteredCount,
                'filtered_data' => $validated['target_jabatan_ids']
            ]);
        }
        
        if (isset($validated['target_outlet_ids'])) {
            $originalCount = count($validated['target_outlet_ids']);
            $validated['target_outlet_ids'] = array_filter($validated['target_outlet_ids'], function($id) {
                return !empty($id);
            });
            $filteredCount = count($validated['target_outlet_ids']);
            \Log::info('Target outlet IDs filtered:', [
                'original_count' => $originalCount,
                'filtered_count' => $filteredCount,
                'removed_count' => $originalCount - $filteredCount,
                'filtered_data' => $validated['target_outlet_ids']
            ]);
        }

        // Final data yang akan disimpan
        \Log::info('=== FINAL DATA SUMMARY ===');
        \Log::info('Final data structure summary:', [
            'title' => $validated['title'] ?? 'NOT SET',
            'category_id' => $validated['category_id'] ?? 'NOT SET',
            'target_type' => $validated['target_type'] ?? 'NOT SET',
            'has_thumbnail' => !empty($validated['thumbnail']),
            'learning_objectives_count' => count($validated['learning_objectives'] ?? []),
            'requirements_count' => count($validated['requirements'] ?? []),
            'sessions_count' => count($validated['sessions'] ?? []),
            'total_session_items' => array_sum(array_map(function($session) {
                return count($session['items'] ?? []);
            }, $validated['sessions'] ?? [])),
            'data_size_bytes' => strlen(serialize($validated))
        ]);
        
        \Log::info('=== FINAL DATA TO SAVE ===');
        \Log::info('Final validated data:', $validated);
        
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
            \Log::info('Attempting to create course...');
            \Log::info('Database operation start:', [
                'operation' => 'create_course',
                'timestamp' => now()->toISOString()
            ]);
            
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
                'course_type' => $validated['course_type'],
                'status' => $validated['status'],
                // 'max_students' => $validated['max_students'] ?? null, // REMOVED
                'thumbnail' => $validated['thumbnail'] ?? null,
                'certificate_template_id' => $validated['certificate_template_id'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id()
            ]);
            
            \Log::info('Course created successfully!', [
                'course_id' => $course->id,
                'course_title' => $course->title,
                'course_data' => $course->toArray()
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
                \Log::info('Syncing target divisions:', $targetDivisions);
                $course->targetDivisions()->sync($targetDivisions);
            }

            // Sync target jabatans for many-to-many relationship
            if (isset($validated['target_jabatan_ids']) && !empty($validated['target_jabatan_ids'])) {
                \Log::info('Syncing target jabatans:', $validated['target_jabatan_ids']);
                $course->targetJabatans()->sync($validated['target_jabatan_ids']);
            }

            // Sync target outlets for many-to-many relationship
            if (isset($validated['target_outlet_ids']) && !empty($validated['target_outlet_ids'])) {
                \Log::info('Syncing target outlets:', $validated['target_outlet_ids']);
                $course->targetOutlets()->sync($validated['target_outlet_ids']);
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
        }, 'targetJabatans', 'quizzes', 'questionnaires']);
        
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
            'type' => 'required|in:online,offline',
            'course_type' => 'required|in:mandatory,optional',
            // 'requirements' => 'nullable|array', // REMOVED - requirements field removed
            // 'requirements.*' => 'string|max:500', // REMOVED - requirements field removed
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
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
} 