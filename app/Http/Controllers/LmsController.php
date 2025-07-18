<?php

namespace App\Http\Controllers;

use App\Models\LmsCourse;
use App\Models\LmsCategory;
use App\Models\LmsEnrollment;
use App\Models\LmsLesson;
use App\Models\User;
use App\Models\Divisi;
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
        $query = LmsCourse::with(['category', 'instructor', 'targetDivisions'])
            ->where('status', 'published')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->category, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->difficulty, function ($query, $difficulty) {
                $query->where('difficulty_level', $difficulty);
            })
            ->when($request->division, function ($query, $divisionId) {
                $query->where(function ($q) use ($divisionId) {
                    $q->where('target_type', 'all')
                      ->orWhere('target_division_id', $divisionId)
                      ->orWhereJsonContains('target_divisions', $divisionId);
                });
            });

        $courses = $query->orderBy('created_at', 'desc')->get();

        $categories = LmsCategory::active()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get divisions from tbl_data_divisi
        $divisions = Divisi::active()
            ->orderBy('nama_divisi')
            ->get(['id', 'nama_divisi']);

        return Inertia::render('Lms/Courses', [
            'courses' => $courses,
            'categories' => $categories,
            'divisions' => $divisions,
        ]);
    }

    public function storeCourse(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'description' => 'required|string',
            'category_id' => 'required|exists:lms_categories,id',
            'target_type' => 'required|in:single,multiple,all',
            'target_division_id' => 'nullable|exists:tbl_data_divisi,id',
            'target_divisions' => 'nullable|array',
            'target_divisions.*' => 'exists:tbl_data_divisi,id',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'duration_minutes' => 'required|integer|min:1',
            'status' => 'required|in:draft,published,archived'
        ]);

        // Set default values
        $validated['instructor_id'] = auth()->id(); // Current user as instructor
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();
        $validated['is_featured'] = false;

        // Create slug from title
        $validated['slug'] = Str::slug($validated['title']);

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

        // Create the course
        $course = LmsCourse::create($validated);

        // Sync target divisions for many-to-many relationship
        if ($validated['target_type'] === 'multiple' && !empty($targetDivisions)) {
            $course->targetDivisions()->sync($targetDivisions);
        }

        return redirect()->back()->with('success', 'Course berhasil dibuat!');
    }

    public function showCourse(LmsCourse $course)
    {
        $user = auth()->user();
        
        $course->load(['category', 'instructor', 'lessons' => function ($query) {
            $query->orderBy('order_number');
        }]);

        // Get user enrollment
        $enrollment = LmsEnrollment::where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->first();

        // Add mock data for learning objectives and requirements
        $course->learning_objectives = [
            'Memahami kebijakan dan prosedur perusahaan',
            'Menguasai skill yang diperlukan untuk pekerjaan',
            'Meningkatkan produktivitas dan efisiensi kerja',
            'Mengembangkan kompetensi sesuai standar perusahaan',
            'Memenuhi persyaratan compliance dan regulasi'
        ];

        $course->requirements = [
            'Karyawan aktif perusahaan',
            'Komputer dengan koneksi intranet',
            'Waktu belajar sesuai jadwal yang ditentukan',
            'Kemauan untuk mengikuti training'
        ];

        return Inertia::render('Lms/CourseDetail', [
            'course' => $course,
            'isEnrolled' => $enrollment ? true : false,
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
} 