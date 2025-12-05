<?php

namespace App\Http\Controllers;

use App\Models\LmsAssignment;
use App\Models\LmsCourse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LmsAssignmentController extends Controller
{
    public function index()
    {
        $assignments = LmsAssignment::with(['course'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Lms/Assignments/Index', [
            'assignments' => $assignments
        ]);
    }

    public function create()
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Assignments/Create', [
            'courses' => $courses
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'instructions' => 'nullable|string',
            'due_date' => 'nullable|date|after:now',
            'max_score' => 'required|numeric|min:1',
            'file_required' => 'boolean',
            'allowed_file_types' => 'nullable|string',
            'max_file_size' => 'nullable|integer|min:1',
            'status' => 'required|in:draft,published,archived'
        ]);

        LmsAssignment::create($validated);

        return redirect()->route('lms.assignments.index')
            ->with('success', 'Assignment berhasil dibuat');
    }

    public function show(LmsAssignment $assignment)
    {
        $assignment->load(['course', 'submissions']);

        return Inertia::render('Lms/Assignments/Show', [
            'assignment' => $assignment
        ]);
    }

    public function edit(LmsAssignment $assignment)
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Assignments/Edit', [
            'assignment' => $assignment,
            'courses' => $courses
        ]);
    }

    public function update(Request $request, LmsAssignment $assignment)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'instructions' => 'nullable|string',
            'due_date' => 'nullable|date|after:now',
            'max_score' => 'required|numeric|min:1',
            'file_required' => 'boolean',
            'allowed_file_types' => 'nullable|string',
            'max_file_size' => 'nullable|integer|min:1',
            'status' => 'required|in:draft,published,archived'
        ]);

        $assignment->update($validated);

        return redirect()->route('lms.assignments.index')
            ->with('success', 'Assignment berhasil diperbarui');
    }

    public function destroy(LmsAssignment $assignment)
    {
        $assignment->delete();

        return redirect()->route('lms.assignments.index')
            ->with('success', 'Assignment berhasil dihapus');
    }
} 