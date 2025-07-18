<?php

namespace App\Http\Controllers;

use App\Models\LmsLesson;
use App\Models\LmsCourse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LmsLessonController extends Controller
{
    public function index()
    {
        $lessons = LmsLesson::with(['course'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Lms/Lessons/Index', [
            'lessons' => $lessons
        ]);
    }

    public function create()
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Lessons/Create', [
            'courses' => $courses
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'type' => 'required|in:video,document,quiz,assignment,discussion',
            'duration' => 'nullable|integer|min:1',
            'order_number' => 'required|integer|min:1',
            'is_preview' => 'boolean',
            'status' => 'required|in:draft,published,archived'
        ]);

        LmsLesson::create($validated);

        return redirect()->route('lms.lessons.index')
            ->with('success', 'Pelajaran berhasil dibuat');
    }

    public function show(LmsLesson $lesson)
    {
        $lesson->load(['course']);

        return Inertia::render('Lms/Lessons/Show', [
            'lesson' => $lesson
        ]);
    }

    public function edit(LmsLesson $lesson)
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Lessons/Edit', [
            'lesson' => $lesson,
            'courses' => $courses
        ]);
    }

    public function update(Request $request, LmsLesson $lesson)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'type' => 'required|in:video,document,quiz,assignment,discussion',
            'duration' => 'nullable|integer|min:1',
            'order_number' => 'required|integer|min:1',
            'is_preview' => 'boolean',
            'status' => 'required|in:draft,published,archived'
        ]);

        $lesson->update($validated);

        return redirect()->route('lms.lessons.index')
            ->with('success', 'Pelajaran berhasil diperbarui');
    }

    public function destroy(LmsLesson $lesson)
    {
        $lesson->delete();

        return redirect()->route('lms.lessons.index')
            ->with('success', 'Pelajaran berhasil dihapus');
    }
} 