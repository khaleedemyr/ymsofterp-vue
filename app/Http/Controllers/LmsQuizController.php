<?php

namespace App\Http\Controllers;

use App\Models\LmsQuiz;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LmsQuizController extends Controller
{
    public function index()
    {
        $quizzes = LmsQuiz::with(['questions', 'attempts'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Calculate statistics for each quiz
        $quizzes->getCollection()->transform(function ($quiz) {
            $questionsCount = $quiz->questions()->count();
            $attemptsCount = $quiz->attempts()->count();
            
            // Calculate average score
            $averageScore = 0;
            if ($attemptsCount > 0) {
                $averageScore = round($quiz->attempts()->avg('score') ?? 0, 1);
            }
            
            // Calculate pass rate
            $passRate = 0;
            if ($attemptsCount > 0) {
                $passedAttempts = $quiz->attempts()
                    ->when($quiz->passing_score !== null, function($query) use ($quiz) {
                        return $query->where('score', '>=', $quiz->passing_score);
                    })
                    ->count();
                $passRate = round(($passedAttempts / $attemptsCount) * 100, 1);
            }

            $quiz->questions_count = $questionsCount;
            $quiz->attempts_count = $attemptsCount;
            $quiz->average_score = $averageScore;
            $quiz->pass_rate = $passRate;

            return $quiz;
        });

        return Inertia::render('Lms/Quizzes/Index', [
            'quizzes' => $quizzes
        ]);
    }

    public function create()
    {
        return Inertia::render('Lms/Quizzes/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'time_limit_type' => 'nullable|in:total,per_question',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'time_per_question_seconds' => 'nullable|integer|min:1',
            'passing_score' => 'required|numeric|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'is_randomized' => 'boolean',
            'show_results' => 'boolean',
            'status' => 'required|in:draft,published,archived'
        ]);

        // Set default values
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        LmsQuiz::create($validated);

        return redirect()->route('lms.quizzes.index')
            ->with('success', 'Quiz berhasil dibuat');
    }

    public function show(LmsQuiz $quiz)
    {
        $quiz->load(['questions.options', 'attempts.user']);

        // Calculate statistics
        $questionsCount = $quiz->questions()->count();
        $attemptsCount = $quiz->attempts()->count();
        
        // Calculate average score
        $averageScore = 0;
        if ($attemptsCount > 0) {
            $averageScore = round($quiz->attempts()->avg('score') ?? 0, 1);
        }
        
        // Calculate pass rate
        $passRate = 0;
        if ($attemptsCount > 0) {
            $passedAttempts = $quiz->attempts()
                ->when($quiz->passing_score !== null, function($query) use ($quiz) {
                    return $query->where('score', '>=', $quiz->passing_score);
                })
                ->count();
            $passRate = round(($passedAttempts / $attemptsCount) * 100, 1);
        }

        // Add calculated statistics to quiz object
        $quiz->questions_count = $questionsCount;
        $quiz->attempts_count = $attemptsCount;
        $quiz->average_score = $averageScore;
        $quiz->pass_rate = $passRate;

        return Inertia::render('Lms/Quizzes/Show', [
            'quiz' => $quiz
        ]);
    }

    public function edit(LmsQuiz $quiz)
    {
        return Inertia::render('Lms/Quizzes/Edit', [
            'quiz' => $quiz
        ]);
    }

    public function update(Request $request, LmsQuiz $quiz)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'time_limit_type' => 'nullable|in:total,per_question',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'time_per_question_seconds' => 'nullable|integer|min:1',
            'passing_score' => 'required|numeric|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'is_randomized' => 'boolean',
            'show_results' => 'boolean',
            'status' => 'required|in:draft,published,archived'
        ]);

        // Set updated_by
        $validated['updated_by'] = auth()->id();

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