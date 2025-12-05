<?php

namespace App\Http\Controllers;

use App\Models\LmsQuestionnaire;
use App\Models\LmsQuestionnaireQuestion;
use App\Models\LmsQuestionnaireOption;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LmsQuestionnaireQuestionController extends Controller
{
    public function index(LmsQuestionnaire $questionnaire)
    {
        $questionnaire->load(['questions.options', 'responses.user']);
        
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
        
        return Inertia::render('Lms/Questionnaires/ManageQuestions', [
            'questionnaire' => $questionnaire,
            'questions' => $questionnaire->questions
        ]);
    }

    public function store(Request $request, LmsQuestionnaire $questionnaire)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,essay,true_false,rating,checkbox',
            'is_required' => 'boolean',
            'options' => 'nullable|array',
        ]);

        // Create question
        $question = LmsQuestionnaireQuestion::create([
            'questionnaire_id' => $questionnaire->id,
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'is_required' => $validated['is_required'] ?? true,
            'order_number' => $questionnaire->questions()->count() + 1
        ]);

        // Handle options based on question type
        if (in_array($validated['question_type'], ['multiple_choice', 'true_false', 'checkbox']) && $validated['options']) {
            foreach ($validated['options'] as $index => $optionData) {
                LmsQuestionnaireOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionData['option_text'],
                    'order_number' => $index + 1
                ]);
            }
        } elseif ($validated['question_type'] === 'true_false' && !$validated['options']) {
            // Create default Benar/Salah options for true_false
            LmsQuestionnaireOption::create([
                'question_id' => $question->id,
                'option_text' => 'Benar',
                'order_number' => 1
            ]);
            LmsQuestionnaireOption::create([
                'question_id' => $question->id,
                'option_text' => 'Salah',
                'order_number' => 2
            ]);
        }

        return redirect()->back()->with('success', 'Pertanyaan berhasil ditambahkan');
    }

    public function update(Request $request, LmsQuestionnaire $questionnaire, LmsQuestionnaireQuestion $question)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,essay,true_false,rating,checkbox',
            'is_required' => 'boolean',
            'options' => 'nullable|array',
        ]);

        // Update question
        $question->update([
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'is_required' => $validated['is_required'] ?? true
        ]);

        // Delete existing options
        $question->options()->delete();

        // Handle options based on question type
        if (in_array($validated['question_type'], ['multiple_choice', 'true_false', 'checkbox']) && $validated['options']) {
            foreach ($validated['options'] as $index => $optionData) {
                LmsQuestionnaireOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionData['option_text'],
                    'order_number' => $index + 1
                ]);
            }
        } elseif ($validated['question_type'] === 'true_false' && !$validated['options']) {
            // Create default Benar/Salah options for true_false
            LmsQuestionnaireOption::create([
                'question_id' => $question->id,
                'option_text' => 'Benar',
                'order_number' => 1
            ]);
            LmsQuestionnaireOption::create([
                'question_id' => $question->id,
                'option_text' => 'Salah',
                'order_number' => 2
            ]);
        }

        return redirect()->back()->with('success', 'Pertanyaan berhasil diperbarui');
    }

    public function destroy(LmsQuestionnaire $questionnaire, LmsQuestionnaireQuestion $question)
    {
        // Delete options first (cascade)
        $question->options()->delete();
        
        // Delete question
        $question->delete();

        return redirect()->back()->with('success', 'Pertanyaan berhasil dihapus');
    }
}
