<?php

namespace App\Http\Controllers;

use App\Models\LmsEnrollment;
use App\Models\LmsCourse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LmsEnrollmentController extends Controller
{
    public function index()
    {
        $enrollments = LmsEnrollment::with(['course', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Lms/Enrollments/Index', [
            'enrollments' => $enrollments
        ]);
    }

    public function create()
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Enrollments/Create', [
            'courses' => $courses
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'user_id' => 'required|exists:users,id',
            'status' => 'required|in:enrolled,in_progress,completed,dropped',
            'progress_percentage' => 'required|numeric|min:0|max:100',
            'enrolled_at' => 'nullable|date',
            'completed_at' => 'nullable|date'
        ]);

        LmsEnrollment::create($validated);

        return redirect()->route('lms.enrollments.index')
            ->with('success', 'Enrollment berhasil dibuat');
    }

    public function show(LmsEnrollment $enrollment)
    {
        $enrollment->load(['course', 'user']);

        return Inertia::render('Lms/Enrollments/Show', [
            'enrollment' => $enrollment
        ]);
    }

    public function edit(LmsEnrollment $enrollment)
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Enrollments/Edit', [
            'enrollment' => $enrollment,
            'courses' => $courses
        ]);
    }

    public function update(Request $request, LmsEnrollment $enrollment)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'user_id' => 'required|exists:users,id',
            'status' => 'required|in:enrolled,in_progress,completed,dropped',
            'progress_percentage' => 'required|numeric|min:0|max:100',
            'enrolled_at' => 'nullable|date',
            'completed_at' => 'nullable|date'
        ]);

        $enrollment->update($validated);

        return redirect()->route('lms.enrollments.index')
            ->with('success', 'Enrollment berhasil diperbarui');
    }

    public function destroy(LmsEnrollment $enrollment)
    {
        $enrollment->delete();

        return redirect()->route('lms.enrollments.index')
            ->with('success', 'Enrollment berhasil dihapus');
    }
} 