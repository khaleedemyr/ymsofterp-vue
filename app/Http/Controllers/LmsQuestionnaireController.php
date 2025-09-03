<?php

namespace App\Http\Controllers;

use App\Models\LmsQuestionnaire;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LmsQuestionnaireController extends Controller
{
    public function index()
    {
        $questionnaires = LmsQuestionnaire::with(['questions', 'responses'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Calculate statistics for each questionnaire
        $questionnaires->getCollection()->transform(function ($questionnaire) {
            $questionsCount = $questionnaire->questions()->count();
            $responsesCount = $questionnaire->responses()->count();
            
            // Calculate completion rate
            $completionRate = 0;
            if ($responsesCount > 0 && $questionsCount > 0) {
                $totalAnsweredQuestions = 0;
                foreach ($questionnaire->responses as $response) {
                    $totalAnsweredQuestions += $response->answers()->count();
                }
                $completionRate = round(($totalAnsweredQuestions / ($questionsCount * $responsesCount)) * 100, 1);
            }

            $questionnaire->questions_count = $questionsCount;
            $questionnaire->responses_count = $responsesCount;
            $questionnaire->completion_rate = $completionRate;

            return $questionnaire;
        });

        return Inertia::render('Lms/Questionnaires/Index', [
            'questionnaires' => $questionnaires
        ]);
    }

    public function create()
    {
        return Inertia::render('Lms/Questionnaires/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'is_anonymous' => 'boolean',
            'allow_multiple_responses' => 'boolean',
            'status' => 'required|in:draft,published,archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        // Set default values
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        LmsQuestionnaire::create($validated);

        return redirect()->route('lms.questionnaires.index')
            ->with('success', 'Kuesioner berhasil dibuat');
    }

    public function show(LmsQuestionnaire $questionnaire)
    {
        $questionnaire->load(['questions.options', 'responses.user', 'responses.answers']);

        // Calculate statistics
        $questionsCount = $questionnaire->questions()->count();
        $responsesCount = $questionnaire->responses()->count();
        
        // Calculate completion rate
        $completionRate = 0;
        if ($responsesCount > 0 && $questionsCount > 0) {
            $totalAnsweredQuestions = 0;
            foreach ($questionnaire->responses as $response) {
                $totalAnsweredQuestions += $response->answers()->count();
            }
            $completionRate = round(($totalAnsweredQuestions / ($questionsCount * $responsesCount)) * 100, 1);
        }

        // Add calculated statistics to questionnaire object
        $questionnaire->questions_count = $questionsCount;
        $questionnaire->responses_count = $responsesCount;
        $questionnaire->completion_rate = $completionRate;

        return Inertia::render('Lms/Questionnaires/Show', [
            'questionnaire' => $questionnaire,
            'questions' => $questionnaire->questions
        ]);
    }

    public function edit(LmsQuestionnaire $questionnaire)
    {
        return Inertia::render('Lms/Questionnaires/Edit', [
            'questionnaire' => $questionnaire
        ]);
    }

    public function update(Request $request, LmsQuestionnaire $questionnaire)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'is_anonymous' => 'boolean',
            'allow_multiple_responses' => 'boolean',
            'status' => 'required|in:draft,published,archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        // Set updated_by
        $validated['updated_by'] = auth()->id();

        $questionnaire->update($validated);

        return redirect()->route('lms.questionnaires.index')
            ->with('success', 'Kuesioner berhasil diperbarui');
    }

    public function destroy(LmsQuestionnaire $questionnaire)
    {
        $questionnaire->delete();

        return redirect()->route('lms.questionnaires.index')
            ->with('success', 'Kuesioner berhasil dihapus');
    }
}
