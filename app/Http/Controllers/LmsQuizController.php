<?php

namespace App\Http\Controllers;

use App\Models\LmsQuiz;
use App\Models\LmsCourse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LmsQuizController extends Controller
{
    public function index()
    {
        $quizzes = LmsQuiz::with(['course'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Lms/Quizzes/Index', [
            'quizzes' => $quizzes
        ]);
    }

    public function create()
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Quizzes/Create', [
            'courses' => $courses
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'time_limit' => 'nullable|integer|min:1',
            'passing_score' => 'required|numeric|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'is_randomized' => 'boolean',
            'status' => 'required|in:draft,published,archived'
        ]);

        LmsQuiz::create($validated);

        return redirect()->route('lms.quizzes.index')
            ->with('success', 'Quiz berhasil dibuat');
    }

    public function show(LmsQuiz $quiz)
    {
        $quiz->load(['course', 'questions.options']);

        return Inertia::render('Lms/Quizzes/Show', [
            'quiz' => $quiz
        ]);
    }

    public function edit(LmsQuiz $quiz)
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Quizzes/Edit', [
            'quiz' => $quiz,
            'courses' => $courses
        ]);
    }

    public function update(Request $request, LmsQuiz $quiz)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'time_limit' => 'nullable|integer|min:1',
            'passing_score' => 'required|numeric|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'is_randomized' => 'boolean',
            'status' => 'required|in:draft,published,archived'
        ]);

        $quiz->update($validated);

        return redirect()->route('lms.quizzes.index')
            ->with('success', 'Quiz berhasil diperbarui');
    }

    public function destroy(LmsQuiz $quiz)
    {
        $quiz->delete();

        return redirect()->route('lms.quizzes.index')
            ->with('success', 'Quiz berhasil dihapus');
    }
} 