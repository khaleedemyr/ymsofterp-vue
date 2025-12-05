<?php

namespace App\Http\Controllers;

use App\Models\LmsQuiz;
use App\Models\LmsQuizAttempt;
use App\Models\LmsQuizAnswer;
use App\Models\LmsQuizQuestion;
use App\Models\LmsQuizOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class QuizController extends Controller
{
    /**
     * Start a new quiz attempt
     */
    public function startAttempt(Request $request)
    {
        $validated = $request->validate([
            'quiz_id' => 'required|exists:lms_quizzes,id',
            'schedule_id' => 'nullable|exists:training_schedules,id'
        ]);

        $userId = Auth::id();
        $quizId = $validated['quiz_id'];

        // Get quiz data
        $quiz = LmsQuiz::where('id', $quizId)
            ->where('status', 'published')
            ->first();

        if (!$quiz) {
            return response()->json(['error' => 'Quiz not found or not published'], 404);
        }

        // Check if user can attempt the quiz
        $existingAttempts = LmsQuizAttempt::where('quiz_id', $quizId)
            ->where('user_id', $userId)
            ->get();

        // Check max attempts
        if ($quiz->max_attempts && $existingAttempts->where('status', 'completed')->count() >= $quiz->max_attempts) {
            return response()->json(['error' => 'Maximum attempts reached'], 400);
        }

        // Check if there's an in-progress attempt
        $inProgressAttempt = $existingAttempts->where('status', 'in_progress')->first();
        if ($inProgressAttempt) {
            return response()->json([
                'attempt' => $inProgressAttempt,
                'message' => 'Continuing existing attempt'
            ]);
        }

        // Create new attempt
        $attempt = LmsQuizAttempt::create([
            'quiz_id' => $quizId,
            'user_id' => $userId,
            'started_at' => now(),
            'status' => 'in_progress',
            'attempt_number' => $existingAttempts->count() + 1
        ]);

        return response()->json([
            'attempt' => $attempt,
            'message' => 'New attempt started'
        ]);
    }

    /**
     * Submit quiz attempt
     */
    public function submitAttempt(Request $request)
    {
        $validated = $request->validate([
            'attempt_id' => 'required|exists:lms_quiz_attempts,id',
            'answers' => 'required|array'
        ]);

        $attemptId = $validated['attempt_id'];
        $answers = $validated['answers'];

        // Get attempt
        $attempt = LmsQuizAttempt::where('id', $attemptId)
            ->where('user_id', Auth::id())
            ->where('status', 'in_progress')
            ->first();

        if (!$attempt) {
            return response()->json(['error' => 'Attempt not found or already completed'], 404);
        }

        DB::beginTransaction();
        try {
            $totalPoints = 0;
            $earnedPoints = 0;
            $correctAnswers = 0;

            // Process each answer
            foreach ($answers as $questionId => $answer) {
                $question = LmsQuizQuestion::find($questionId);
                if (!$question || $question->quiz_id !== $attempt->quiz_id) {
                    continue;
                }

                $totalPoints += $question->points;

                // Create answer record
                $answerRecord = LmsQuizAnswer::create([
                    'attempt_id' => $attemptId,
                    'question_id' => $questionId,
                    'selected_option_id' => is_numeric($answer) ? $answer : null,
                    'essay_answer' => !is_numeric($answer) ? $answer : null,
                ]);

                // Check if answer is correct
                if ($question->question_type === 'multiple_choice' || $question->question_type === 'true_false') {
                    $correctOption = LmsQuizOption::where('question_id', $questionId)
                        ->where('is_correct', true)
                        ->first();

                    if ($correctOption && $answer == $correctOption->id) {
                        $answerRecord->is_correct = true;
                        $answerRecord->points_earned = $question->points;
                        $earnedPoints += $question->points;
                        $correctAnswers++;
                    } else {
                        $answerRecord->is_correct = false;
                        $answerRecord->points_earned = 0;
                    }
                } else {
                    // Essay questions need manual grading
                    $answerRecord->is_correct = null;
                    $answerRecord->points_earned = null;
                }

                $answerRecord->save();
            }

            // Calculate score
            $score = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;

            // Update attempt
            $attempt->update([
                'completed_at' => now(),
                'score' => $score,
                'is_passed' => $score >= $attempt->quiz->passing_score,
                'status' => 'completed'
            ]);

            \Log::info('Attempt status updated', [
                'attempt_id' => $attempt->id,
                'status' => $attempt->status,
                'completed_at' => $attempt->completed_at
            ]);

            DB::commit();

            \Log::info('Quiz submitted successfully', [
                'attempt_id' => $attempt->id,
                'user_id' => Auth::id(),
                'score' => $score,
                'correct_answers' => $correctAnswers,
                'total_questions' => count($answers)
            ]);

            // Get updated quiz data
            $updatedAttempts = LmsQuizAttempt::where('quiz_id', $attempt->quiz_id)
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();

            $latestAttempt = $updatedAttempts->first();
            $canAttempt = $this->canUserAttemptQuiz($attempt->quiz, $updatedAttempts);

            $responseData = [
                'result' => [
                    'score' => $score,
                    'is_passed' => $score >= $attempt->quiz->passing_score,
                    'correct_answers' => $correctAnswers,
                    'total_questions' => count($answers),
                    'time_taken' => $attempt->started_at->diffInSeconds($attempt->completed_at),
                    'attempt_id' => $attempt->id
                ],
                'updated_attempts' => $updatedAttempts,
                'latest_attempt' => $latestAttempt,
                'can_attempt' => $canAttempt
            ];

            \Log::info('Returning submit response', [
                'response_data' => $responseData
            ]);

            return response()->json($responseData);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to submit quiz: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get quiz results
     */
    public function getResults(Request $request, $attemptId)
    {
        $attempt = LmsQuizAttempt::where('id', $attemptId)
            ->where('user_id', Auth::id())
            ->with(['quiz', 'answers.question', 'answers.selectedOption'])
            ->first();

        if (!$attempt) {
            return response()->json(['error' => 'Attempt not found'], 404);
        }

        return response()->json([
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'answers' => $attempt->answers
        ]);
    }

    /**
     * Show quiz attempt page
     */
    public function showAttempt($quizId, $attemptId)
    {
        $attempt = LmsQuizAttempt::where('id', $attemptId)
            ->where('quiz_id', $quizId)
            ->where('user_id', Auth::id())
            ->with(['quiz.questions.options'])
            ->first();

        if (!$attempt) {
            abort(404, 'Quiz attempt not found');
        }

        // Check if attempt is still in progress
        if ($attempt->status !== 'in_progress') {
            return redirect()->route('lms.quiz.results', $attemptId);
        }

        // Randomize questions and options if quiz is randomized
        $quiz = $attempt->quiz;
        if ($quiz->is_randomized) {
            // Randomize questions order
            $questions = $quiz->questions->shuffle();
            
            // Randomize options for each question
            $questions->each(function ($question) {
                $question->options = $question->options->shuffle();
            });
            
            $quiz->questions = $questions;
        }

        return Inertia::render('Lms/Quiz/Attempt', [
            'attempt' => $attempt,
            'quiz' => $quiz
        ]);
    }

    /**
     * Show quiz results page
     */
    public function showResults($attemptId)
    {
        $attempt = LmsQuizAttempt::where('id', $attemptId)
            ->where('user_id', Auth::id())
            ->with(['quiz.questions.options', 'answers'])
            ->first();

        if (!$attempt) {
            abort(404, 'Quiz attempt not found');
        }

        // Check if attempt is completed
        if ($attempt->status !== 'completed') {
            return redirect()->route('lms.quiz.attempt', [$attempt->quiz_id, $attemptId]);
        }

        // Get quiz with questions and correct answers
        $quiz = $attempt->quiz;
        
        // Add correct answer information to each question
        $quiz->questions->each(function ($question) {
            $question->correct_option = $question->options->where('is_correct', true)->first();
        });

        return Inertia::render('Lms/Quiz/Results', [
            'attempt' => $attempt,
            'quiz' => $quiz,
            'showResults' => $quiz->show_results
        ]);
    }

    /**
     * Check if user can attempt quiz
     */
    private function canUserAttemptQuiz($quiz, $attempts)
    {
        if (!$quiz->max_attempts) {
            return true;
        }

        $completedAttempts = $attempts->where('status', 'completed')->count();
        return $completedAttempts < $quiz->max_attempts;
    }
}
