<?php

namespace App\Http\Controllers;

use App\Models\LmsQuestionnaire;
use App\Models\LmsQuestionnaireResponse;
use App\Models\LmsQuestionnaireAnswer;
use App\Models\LmsQuestionnaireQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class QuestionnaireController extends Controller
{
    /**
     * Start a new questionnaire response
     */
    public function startResponse(Request $request)
    {
        $validated = $request->validate([
            'questionnaire_id' => 'required|exists:lms_questionnaires,id',
            'schedule_id' => 'nullable|exists:training_schedules,id'
        ]);

        $userId = Auth::id();
        $questionnaireId = $validated['questionnaire_id'];

        // Get questionnaire data
        $questionnaire = LmsQuestionnaire::where('id', $questionnaireId)
            ->where('status', 'published')
            ->first();

        if (!$questionnaire) {
            return response()->json(['error' => 'Questionnaire not found or not published'], 404);
        }

        // Check if questionnaire is active
        if (!$questionnaire->is_active) {
            return response()->json(['error' => 'Questionnaire is not active'], 400);
        }

        // Check if user can respond
        if (!$questionnaire->canBeRespondedByUser($userId)) {
            return response()->json(['error' => 'Cannot respond to this questionnaire'], 400);
        }

        // Check if there's an existing draft response
        $existingResponse = LmsQuestionnaireResponse::where('questionnaire_id', $questionnaireId)
            ->where('user_id', $userId)
            ->whereNull('submitted_at')
            ->first();

        if ($existingResponse) {
            // Load existing answers
            $existingAnswers = $this->getExistingAnswers($existingResponse->id);
            return response()->json([
                'response' => $existingResponse,
                'existing_answers' => $existingAnswers,
                'message' => 'Continuing existing response'
            ]);
        }

        // Create new response
        $response = LmsQuestionnaireResponse::create([
            'questionnaire_id' => $questionnaireId,
            'user_id' => $userId,
            'respondent_name' => $questionnaire->is_anonymous ? null : Auth::user()->nama_lengkap,
            'respondent_email' => $questionnaire->is_anonymous ? null : Auth::user()->email,
        ]);

        return response()->json([
            'response' => $response,
            'existing_answers' => [],
            'message' => 'New response started'
        ]);
    }

    /**
     * Submit questionnaire response
     */
    public function submitResponse(Request $request)
    {
        $validated = $request->validate([
            'response_id' => 'required|exists:lms_questionnaire_responses,id',
            'answers' => 'required|array'
        ]);

        $responseId = $validated['response_id'];
        $answers = $validated['answers'];

        // Get response
        $response = LmsQuestionnaireResponse::where('id', $responseId)
            ->where('user_id', Auth::id())
            ->whereNull('submitted_at')
            ->first();

        if (!$response) {
            return response()->json(['error' => 'Response not found or already submitted'], 404);
        }

        DB::beginTransaction();
        try {
            $answeredQuestions = 0;

            // Process each answer
            foreach ($answers as $questionId => $answer) {
                $question = LmsQuestionnaireQuestion::find($questionId);
                if (!$question || $question->questionnaire_id !== $response->questionnaire_id) {
                    continue;
                }

                // Skip if no answer provided
                if ($answer === null || $answer === '' || (is_array($answer) && empty($answer))) {
                    continue;
                }

                $answeredQuestions++;

                // Create or update answer record
                $answerRecord = LmsQuestionnaireAnswer::updateOrCreate(
                    [
                        'response_id' => $responseId,
                        'question_id' => $questionId
                    ],
                    [
                        'answer_text' => is_string($answer) ? $answer : null,
                        'selected_option_id' => is_numeric($answer) ? $answer : null,
                        'rating_value' => is_numeric($answer) && $question->question_type === 'rating' ? $answer : null,
                    ]
                );
            }

            // Update response
            $response->update([
                'submitted_at' => now()
            ]);

            DB::commit();

            // Get updated questionnaire data
            $updatedResponses = LmsQuestionnaireResponse::where('questionnaire_id', $response->questionnaire_id)
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();

            $latestResponse = $updatedResponses->first();
            $canRespond = $response->questionnaire->canBeRespondedByUser(Auth::id());

            return response()->json([
                'result' => [
                    'response_id' => $response->id,
                    'submitted_at' => $response->submitted_at,
                    'answered_questions' => $answeredQuestions,
                    'total_questions' => count($answers)
                ],
                'updated_responses' => $updatedResponses,
                'latest_response' => $latestResponse,
                'can_respond' => $canRespond
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to submit questionnaire: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get questionnaire results
     */
    public function getResults(Request $request, $responseId)
    {
        $response = LmsQuestionnaireResponse::where('id', $responseId)
            ->where('user_id', Auth::id())
            ->with(['questionnaire', 'answers.question', 'answers.selectedOption'])
            ->first();

        if (!$response) {
            return response()->json(['error' => 'Response not found'], 404);
        }

        return response()->json([
            'response' => $response,
            'questionnaire' => $response->questionnaire,
            'answers' => $response->answers
        ]);
    }

    /**
     * Get existing answers for a response
     */
    private function getExistingAnswers($responseId)
    {
        $answers = LmsQuestionnaireAnswer::where('response_id', $responseId)
            ->get()
            ->keyBy('question_id');

        $formattedAnswers = [];
        foreach ($answers as $questionId => $answer) {
            if ($answer->answer_text) {
                $formattedAnswers[$questionId] = $answer->answer_text;
            } elseif ($answer->selected_option_id) {
                $formattedAnswers[$questionId] = $answer->selected_option_id;
            } elseif ($answer->rating_value) {
                $formattedAnswers[$questionId] = $answer->rating_value;
            }
        }

        return $formattedAnswers;
    }
}
