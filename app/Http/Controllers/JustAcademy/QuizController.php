<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaQuiz;
use App\Models\JustAcademy\JaQuizOption;
use App\Models\JustAcademy\JaQuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QuizController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = JaQuiz::withCount('questions')->orderByDesc('id');
        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        return Inertia::render('JustAcademy/Quizzes/Index', [
            'quizzes' => $query->paginate(15)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function create()
    {
        return Inertia::render('JustAcademy/Quizzes/Form', ['quiz' => null]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateQuiz($request);

        DB::transaction(function () use ($request, $validated) {
            $quiz = JaQuiz::create([
                'title' => $validated['title'],
                'pass_score' => $validated['pass_score'],
                'time_limit_min' => $validated['time_limit_min'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'created_by' => $request->user()->id,
            ]);

            $this->syncQuestions($quiz, $validated['questions']);
        });

        return redirect()
            ->route('just-academy.quizzes.index')
            ->with('success', 'Quiz berhasil ditambahkan.');
    }

    public function edit(JaQuiz $quiz)
    {
        $quiz->load('questions.options');

        return Inertia::render('JustAcademy/Quizzes/Form', ['quiz' => $quiz]);
    }

    public function update(Request $request, JaQuiz $quiz)
    {
        $validated = $this->validateQuiz($request);

        DB::transaction(function () use ($request, $quiz, $validated) {
            $quiz->update([
                'title' => $validated['title'],
                'pass_score' => $validated['pass_score'],
                'time_limit_min' => $validated['time_limit_min'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $quiz->questions()->each(function (JaQuizQuestion $q) {
                $q->options()->delete();
            });
            $quiz->questions()->delete();
            $this->syncQuestions($quiz, $validated['questions']);
        });

        return redirect()
            ->route('just-academy.quizzes.index')
            ->with('success', 'Quiz berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $quiz = JaQuiz::findOrFail($id);

        if ($quiz->programItems()->exists()) {
            return back()->with('error', 'Quiz masih dipakai di program. Hapus dari curriculum program terlebih dahulu.');
        }

        $quiz->delete();

        return redirect()
            ->route('just-academy.quizzes.index')
            ->with('success', 'Quiz berhasil dihapus.');
    }

    private function validateQuiz(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'pass_score' => 'required|numeric|min:0|max:100',
            'time_limit_min' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*.question' => 'required|string',
            'questions.*.type' => 'required|in:mcq,essay',
            'questions.*.points' => 'nullable|numeric|min:0',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*.option_text' => 'required_with:questions.*.options|string|max:500',
            'questions.*.options.*.is_correct' => 'boolean',
        ]);
    }

    private function syncQuestions(JaQuiz $quiz, array $questions): void
    {
        foreach ($questions as $i => $qData) {
            $question = JaQuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question' => $qData['question'],
                'type' => $qData['type'],
                'sort_order' => $i,
                'points' => $qData['points'] ?? 1,
            ]);

            if ($qData['type'] === 'mcq' && !empty($qData['options'])) {
                foreach ($qData['options'] as $j => $opt) {
                    if (trim((string) ($opt['option_text'] ?? '')) === '') {
                        continue;
                    }
                    JaQuizOption::create([
                        'question_id' => $question->id,
                        'option_text' => $opt['option_text'],
                        'is_correct' => !empty($opt['is_correct']),
                        'sort_order' => $j,
                    ]);
                }
            }
        }
    }
}
