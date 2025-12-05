<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TrainingHistoryController extends Controller
{
    /**
     * Save training history when user checks out
     */
    public function saveTrainingHistory(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:training_schedules,id',
            'user_type' => 'required|in:participant,trainer',
            'checkout_time' => 'nullable|date'
        ]);

        $userId = Auth::id();
        $scheduleId = $validated['schedule_id'];
        $userType = $validated['user_type'];
        $checkoutTime = $validated['checkout_time'] ? Carbon::parse($validated['checkout_time']) : now();

        DB::beginTransaction();
        try {
            // Get training schedule details
            $schedule = DB::table('training_schedules')
                ->join('lms_courses', 'training_schedules.course_id', '=', 'lms_courses.id')
                ->leftJoin('tbl_data_outlet', 'training_schedules.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('training_schedules.id', $scheduleId)
                ->select(
                    'training_schedules.*',
                    'lms_courses.title as course_title',
                    'lms_courses.description as course_description',
                    'tbl_data_outlet.nama_outlet',
                    'tbl_data_outlet.alamat_outlet'
                )
                ->first();

            if (!$schedule) {
                throw new \Exception('Training schedule not found');
            }

            // Get user details
            $user = DB::table('users')
                ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
                ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
                ->where('users.id', $userId)
                ->select(
                    'users.nama_lengkap',
                    'users.email',
                    'tbl_data_jabatan.nama_jabatan',
                    'tbl_data_divisi.nama_divisi'
                )
                ->first();

            if (!$user) {
                throw new \Exception('User not found');
            }

            // Get check-in time
            $checkinTime = null;
            if ($userType === 'participant') {
                $invitation = DB::table('training_invitations')
                    ->where('schedule_id', $scheduleId)
                    ->where('user_id', $userId)
                    ->where('status', 'invited')
                    ->first();
                $checkinTime = $invitation ? Carbon::parse($invitation->checkin_time) : null;
            } else {
                // For trainers, we might need to track their check-in differently
                // For now, we'll use the training start time
                $checkinTime = Carbon::parse($schedule->scheduled_date . ' ' . $schedule->start_time);
            }

            // Calculate durations
            $startTime = Carbon::parse($schedule->scheduled_date . ' ' . $schedule->start_time);
            $endTime = Carbon::parse($schedule->scheduled_date . ' ' . $schedule->end_time);
            $plannedDurationMinutes = $startTime->diffInMinutes($endTime);
            
            $actualDurationMinutes = null;
            $userDurationMinutes = null;
            
            if ($checkinTime && $checkoutTime) {
                $actualDurationMinutes = $checkinTime->diffInMinutes($checkoutTime);
                $userDurationMinutes = $actualDurationMinutes;
            }

            // Calculate completion percentage
            $completionPercentage = $this->calculateCompletionPercentage($userId, $scheduleId, $userType);

            // Create training history record
            $trainingHistoryId = DB::table('training_history')->insertGetId([
                'schedule_id' => $scheduleId,
                'user_id' => $userId,
                'user_type' => $userType,
                'course_title' => $schedule->course_title,
                'course_description' => $schedule->course_description,
                'scheduled_date' => $schedule->scheduled_date,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'outlet_name' => $schedule->nama_outlet,
                'outlet_address' => $schedule->alamat_outlet,
                'user_name' => $user->nama_lengkap,
                'user_email' => $user->email,
                'user_jabatan' => $user->nama_jabatan,
                'user_divisi' => $user->nama_divisi,
                'planned_duration_minutes' => $plannedDurationMinutes,
                'actual_duration_minutes' => $actualDurationMinutes,
                'checkin_time' => $checkinTime,
                'checkout_time' => $checkoutTime,
                'user_duration_minutes' => $userDurationMinutes,
                'training_status' => $completionPercentage >= 80 ? 'completed' : 'incomplete',
                'completion_percentage' => $completionPercentage,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Save session history
            $this->saveSessionHistory($trainingHistoryId, $scheduleId, $userId, $userType);

            DB::commit();

            return response()->json([
                'message' => 'Training history saved successfully',
                'training_history_id' => $trainingHistoryId,
                'completion_percentage' => $completionPercentage,
                'user_duration_minutes' => $userDurationMinutes
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to save training history: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save session history for a training
     */
    private function saveSessionHistory($trainingHistoryId, $scheduleId, $userId, $userType)
    {
        // Get course sessions
        $schedule = DB::table('training_schedules')->where('id', $scheduleId)->first();
        $sessions = DB::table('lms_sessions')
            ->where('course_id', $schedule->course_id)
            ->where('status', 'active')
            ->orderBy('order_number')
            ->get();

        foreach ($sessions as $session) {
            // Create session history record
            $sessionHistoryId = DB::table('training_session_history')->insertGetId([
                'training_history_id' => $trainingHistoryId,
                'session_id' => $session->id,
                'session_title' => $session->session_title,
                'session_description' => $session->session_description,
                'session_order_number' => $session->order_number,
                'is_required' => $session->is_required,
                'estimated_duration_minutes' => $session->estimated_duration_minutes,
                'session_status' => $this->getSessionStatus($userId, $scheduleId, $session->id, $userType),
                'started_at' => $this->getSessionStartTime($userId, $scheduleId, $session->id, $userType),
                'completed_at' => $this->getSessionCompletionTime($userId, $scheduleId, $session->id, $userType),
                'actual_duration_minutes' => $this->getSessionDuration($userId, $scheduleId, $session->id, $userType),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Save session item history
            $this->saveSessionItemHistory($sessionHistoryId, $session->id, $userId, $scheduleId, $userType);
        }
    }

    /**
     * Save session item history
     */
    private function saveSessionItemHistory($sessionHistoryId, $sessionId, $userId, $scheduleId, $userType)
    {
        $sessionItems = DB::table('lms_session_items')
            ->where('session_id', $sessionId)
            ->where('status', 'active')
            ->orderBy('order_number')
            ->get();

        foreach ($sessionItems as $item) {
            $itemData = [
                'training_session_history_id' => $sessionHistoryId,
                'item_id' => $item->id,
                'item_type' => $item->item_type,
                'item_title' => $item->title,
                'item_description' => $item->description,
                'item_order_number' => $item->order_number,
                'is_required' => $item->is_required,
                'estimated_duration_minutes' => $item->estimated_duration_minutes,
                'item_status' => $this->getItemStatus($userId, $scheduleId, $item, $userType),
                'started_at' => $this->getItemStartTime($userId, $scheduleId, $item, $userType),
                'completed_at' => $this->getItemCompletionTime($userId, $scheduleId, $item, $userType),
                'actual_duration_minutes' => $this->getItemDuration($userId, $scheduleId, $item, $userType),
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Add quiz specific data
            if ($item->item_type === 'quiz') {
                $quizData = $this->getQuizData($userId, $item->item_id);
                $itemData['quiz_score'] = $quizData['score'];
                $itemData['quiz_attempts'] = $quizData['attempts'];
                $itemData['quiz_passed'] = $quizData['passed'];
            }

            // Add questionnaire specific data
            if ($item->item_type === 'questionnaire') {
                $questionnaireData = $this->getQuestionnaireData($userId, $item->item_id);
                $itemData['questionnaire_completed'] = $questionnaireData['completed'];
            }

            DB::table('training_session_item_history')->insert($itemData);
        }
    }

    /**
     * Calculate completion percentage
     */
    private function calculateCompletionPercentage($userId, $scheduleId, $userType)
    {
        // For trainers, they don't need to complete sessions - just check-in/check-out
        if ($userType === 'trainer') {
            return 100.00; // Trainers are always considered 100% completed
        }
        
        // For participants, calculate based on session and item completion
        $schedule = DB::table('training_schedules')->where('id', $scheduleId)->first();
        $totalRequiredSessions = DB::table('lms_sessions')
            ->where('course_id', $schedule->course_id)
            ->where('status', 'active')
            ->where('is_required', 1)
            ->count();

        $totalRequiredItems = DB::table('lms_sessions')
            ->join('lms_session_items', 'lms_sessions.id', '=', 'lms_session_items.session_id')
            ->where('lms_sessions.course_id', $schedule->course_id)
            ->where('lms_sessions.status', 'active')
            ->where('lms_session_items.status', 'active')
            ->where('lms_session_items.is_required', 1)
            ->count();

        $totalRequired = $totalRequiredSessions + $totalRequiredItems;

        if ($totalRequired === 0) {
            return 100.00;
        }

        // Count completed items
        $completedItems = 0;

        // Check quiz completions
        $completedQuizzes = DB::table('lms_quiz_attempts')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->where('is_passed', 1)
            ->count();
        $completedItems += $completedQuizzes;

        // Check questionnaire completions
        $completedQuestionnaires = DB::table('lms_questionnaire_responses')
            ->where('user_id', $userId)
            ->whereNotNull('submitted_at')
            ->count();
        $completedItems += $completedQuestionnaires;

        return round(($completedItems / $totalRequired) * 100, 2);
    }

    /**
     * Get session status
     */
    private function getSessionStatus($userId, $scheduleId, $sessionId, $userType)
    {
        // For trainers, they don't need to complete sessions - just check-in/check-out
        if ($userType === 'trainer') {
            return 'completed'; // Trainers are always considered completed
        }
        
        // For participants, check item completion
        $requiredItems = DB::table('lms_session_items')
            ->where('session_id', $sessionId)
            ->where('is_required', 1)
            ->where('status', 'active')
            ->get();

        $completedItems = 0;
        foreach ($requiredItems as $item) {
            if ($this->isItemCompleted($userId, $scheduleId, $item)) {
                $completedItems++;
            }
        }

        if ($completedItems === 0) {
            return 'not_started';
        } elseif ($completedItems === count($requiredItems)) {
            return 'completed';
        } else {
            return 'in_progress';
        }
    }

    /**
     * Check if item is completed
     */
    private function isItemCompleted($userId, $scheduleId, $item)
    {
        switch ($item->item_type) {
            case 'quiz':
                $attempt = DB::table('lms_quiz_attempts')
                    ->where('user_id', $userId)
                    ->where('quiz_id', $item->item_id)
                    ->where('status', 'completed')
                    ->where('is_passed', 1)
                    ->first();
                return $attempt !== null;

            case 'questionnaire':
                $response = DB::table('lms_questionnaire_responses')
                    ->where('user_id', $userId)
                    ->where('questionnaire_id', $item->item_id)
                    ->whereNotNull('submitted_at')
                    ->first();
                return $response !== null;

            case 'material':
                // For materials, we'll consider them completed if user has accessed them
                // This might need to be enhanced based on your material tracking system
                return true;

            default:
                return false;
        }
    }

    /**
     * Get quiz data for history
     */
    private function getQuizData($userId, $quizId)
    {
        $attempt = DB::table('lms_quiz_attempts')
            ->where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();

        return [
            'score' => $attempt ? $attempt->score : null,
            'attempts' => DB::table('lms_quiz_attempts')
                ->where('user_id', $userId)
                ->where('quiz_id', $quizId)
                ->count(),
            'passed' => $attempt ? $attempt->is_passed : false
        ];
    }

    /**
     * Get questionnaire data for history
     */
    private function getQuestionnaireData($userId, $questionnaireId)
    {
        $response = DB::table('lms_questionnaire_responses')
            ->where('user_id', $userId)
            ->where('questionnaire_id', $questionnaireId)
            ->whereNotNull('submitted_at')
            ->first();

        return [
            'completed' => $response !== null
        ];
    }

    /**
     * Get session start time
     */
    private function getSessionStartTime($userId, $scheduleId, $sessionId, $userType)
    {
        // For now, we'll use the training start time
        // This can be enhanced to track actual session start time
        $schedule = DB::table('training_schedules')->where('id', $scheduleId)->first();
        return $schedule ? Carbon::parse($schedule->scheduled_date . ' ' . $schedule->start_time) : null;
    }

    /**
     * Get session completion time
     */
    private function getSessionCompletionTime($userId, $scheduleId, $sessionId, $userType)
    {
        // For now, we'll use the training end time
        // This can be enhanced to track actual session completion time
        $schedule = DB::table('training_schedules')->where('id', $scheduleId)->first();
        return $schedule ? Carbon::parse($schedule->scheduled_date . ' ' . $schedule->end_time) : null;
    }

    /**
     * Get session duration
     */
    private function getSessionDuration($userId, $scheduleId, $sessionId, $userType)
    {
        $startTime = $this->getSessionStartTime($userId, $scheduleId, $sessionId, $userType);
        $endTime = $this->getSessionCompletionTime($userId, $scheduleId, $sessionId, $userType);
        
        if ($startTime && $endTime) {
            return $startTime->diffInMinutes($endTime);
        }
        
        return null;
    }

    /**
     * Get item status
     */
    private function getItemStatus($userId, $scheduleId, $item, $userType)
    {
        // For trainers, they don't need to complete items - just check-in/check-out
        if ($userType === 'trainer') {
            return 'completed'; // Trainers are always considered completed
        }
        
        // For participants, check item completion
        if ($this->isItemCompleted($userId, $scheduleId, $item)) {
            return 'completed';
        }
        
        // Check if item has been started
        if ($item->item_type === 'quiz') {
            $attempt = DB::table('lms_quiz_attempts')
                ->where('user_id', $userId)
                ->where('quiz_id', $item->item_id)
                ->first();
            return $attempt ? 'in_progress' : 'not_started';
        }
        
        if ($item->item_type === 'questionnaire') {
            $response = DB::table('lms_questionnaire_responses')
                ->where('user_id', $userId)
                ->where('questionnaire_id', $item->item_id)
                ->first();
            return $response ? 'in_progress' : 'not_started';
        }
        
        return 'not_started';
    }

    /**
     * Get item start time
     */
    private function getItemStartTime($userId, $scheduleId, $item, $userType)
    {
        if ($item->item_type === 'quiz') {
            $attempt = DB::table('lms_quiz_attempts')
                ->where('user_id', $userId)
                ->where('quiz_id', $item->item_id)
                ->orderBy('created_at', 'asc')
                ->first();
            return $attempt ? Carbon::parse($attempt->started_at) : null;
        }
        
        if ($item->item_type === 'questionnaire') {
            $response = DB::table('lms_questionnaire_responses')
                ->where('user_id', $userId)
                ->where('questionnaire_id', $item->item_id)
                ->orderBy('created_at', 'asc')
                ->first();
            return $response ? Carbon::parse($response->created_at) : null;
        }
        
        return null;
    }

    /**
     * Get item completion time
     */
    private function getItemCompletionTime($userId, $scheduleId, $item, $userType)
    {
        if ($item->item_type === 'quiz') {
            $attempt = DB::table('lms_quiz_attempts')
                ->where('user_id', $userId)
                ->where('quiz_id', $item->item_id)
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->first();
            return $attempt ? Carbon::parse($attempt->completed_at) : null;
        }
        
        if ($item->item_type === 'questionnaire') {
            $response = DB::table('lms_questionnaire_responses')
                ->where('user_id', $userId)
                ->where('questionnaire_id', $item->item_id)
                ->whereNotNull('submitted_at')
                ->first();
            return $response ? Carbon::parse($response->submitted_at) : null;
        }
        
        return null;
    }

    /**
     * Get item duration
     */
    private function getItemDuration($userId, $scheduleId, $item, $userType)
    {
        $startTime = $this->getItemStartTime($userId, $scheduleId, $item, $userType);
        $endTime = $this->getItemCompletionTime($userId, $scheduleId, $item, $userType);
        
        if ($startTime && $endTime) {
            return $startTime->diffInMinutes($endTime);
        }
        
        return null;
    }

    /**
     * Get training history for a user
     */
    public function getUserTrainingHistory(Request $request)
    {
        $userId = Auth::id();
        $userType = $request->get('user_type', 'participant');
        
        $history = DB::table('training_history')
            ->where('user_id', $userId)
            ->where('user_type', $userType)
            ->orderBy('scheduled_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($history);
    }

    /**
     * Get training history details
     */
    public function getTrainingHistoryDetails(Request $request, $historyId)
    {
        $userId = Auth::id();
        
        $history = DB::table('training_history')
            ->where('id', $historyId)
            ->where('user_id', $userId)
            ->first();

        if (!$history) {
            return response()->json(['error' => 'Training history not found'], 404);
        }

        // Get session history
        $sessionHistory = DB::table('training_session_history')
            ->where('training_history_id', $historyId)
            ->orderBy('session_order_number')
            ->get();

        // Get session item history for each session
        foreach ($sessionHistory as $session) {
            $session->items = DB::table('training_session_item_history')
                ->where('training_session_history_id', $session->id)
                ->orderBy('item_order_number')
                ->get();
        }

        $history->sessions = $sessionHistory;

        return response()->json($history);
    }
}
