<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    /**
     * Submit training feedback
     */
    public function submitFeedback(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:training_schedules,id',
            'training_rating' => 'required|integer|min:1|max:5',
            'trainer_ratings' => 'nullable|array',
            'trainer_ratings.*' => 'integer|min:1|max:5',
            'comments' => 'nullable|string|max:2000',
            'suggestions' => 'nullable|string|max:2000'
        ]);

        $userId = Auth::id();
        $scheduleId = $validated['schedule_id'];

        // Check if user has already given feedback
        $existingFeedback = DB::table('training_feedback')
            ->where('schedule_id', $scheduleId)
            ->where('user_id', $userId)
            ->first();

        if ($existingFeedback) {
            return response()->json(['error' => 'Feedback sudah pernah diberikan'], 400);
        }

        // Check if user is a participant of this training
        $invitation = DB::table('training_invitations')
            ->where('schedule_id', $scheduleId)
            ->where('user_id', $userId)
            ->where('status', 'invited')
            ->first();

        if (!$invitation) {
            return response()->json(['error' => 'Anda tidak terdaftar sebagai peserta training ini'], 403);
        }

        DB::beginTransaction();
        try {
            // Create main feedback record
            $feedbackId = DB::table('training_feedback')->insertGetId([
                'schedule_id' => $scheduleId,
                'user_id' => $userId,
                'training_rating' => $validated['training_rating'],
                'comments' => $validated['comments'],
                'suggestions' => $validated['suggestions'],
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create trainer feedback records
            if (!empty($validated['trainer_ratings'])) {
                foreach ($validated['trainer_ratings'] as $trainerId => $rating) {
                    // Verify trainer is assigned to this training
                    $trainerAssignment = DB::table('training_schedule_trainers')
                        ->where('schedule_id', $scheduleId)
                        ->where('trainer_id', $trainerId)
                        ->where('trainer_type', 'internal')
                        ->first();

                    if ($trainerAssignment) {
                        DB::table('training_trainer_feedback')->insert([
                            'feedback_id' => $feedbackId,
                            'trainer_id' => $trainerId,
                            'rating' => $rating,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Feedback berhasil dikirim',
                'feedback_id' => $feedbackId
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal mengirim feedback: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get feedback for a training schedule
     */
    public function getFeedback(Request $request, $scheduleId)
    {
        $userId = Auth::id();

        // Check if user is a participant of this training
        $invitation = DB::table('training_invitations')
            ->where('schedule_id', $scheduleId)
            ->where('user_id', $userId)
            ->where('status', 'invited')
            ->first();

        if (!$invitation) {
            return response()->json(['error' => 'Anda tidak terdaftar sebagai peserta training ini'], 403);
        }

        // Get feedback
        $feedback = DB::table('training_feedback')
            ->where('schedule_id', $scheduleId)
            ->where('user_id', $userId)
            ->first();

        if (!$feedback) {
            return response()->json(['feedback' => null]);
        }

        // Get trainer feedback
        $trainerFeedback = DB::table('training_trainer_feedback')
            ->join('users', 'training_trainer_feedback.trainer_id', '=', 'users.id')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->where('training_trainer_feedback.feedback_id', $feedback->id)
            ->select(
                'users.id',
                'users.nama_lengkap',
                'tbl_data_jabatan.nama_jabatan',
                'tbl_data_divisi.nama_divisi',
                'training_trainer_feedback.rating'
            )
            ->get();

        return response()->json([
            'feedback' => $feedback,
            'trainer_feedback' => $trainerFeedback
        ]);
    }

    /**
     * Get training feedback statistics (for trainers/admins)
     */
    public function getFeedbackStats(Request $request, $scheduleId)
    {
        // Check if user has permission to view feedback stats
        $canViewStats = $this->canViewFeedbackStats($scheduleId);
        
        if (!$canViewStats) {
            return response()->json(['error' => 'Anda tidak memiliki izin untuk melihat statistik feedback'], 403);
        }

        // Get overall training feedback stats
        $trainingStats = DB::table('training_feedback')
            ->where('schedule_id', $scheduleId)
            ->selectRaw('
                COUNT(*) as total_responses,
                AVG(training_rating) as average_rating,
                MIN(training_rating) as min_rating,
                MAX(training_rating) as max_rating
            ')
            ->first();

        // Get trainer feedback stats
        $trainerStats = DB::table('training_trainer_feedback')
            ->join('users', 'training_trainer_feedback.trainer_id', '=', 'users.id')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->join('training_feedback', 'training_trainer_feedback.feedback_id', '=', 'training_feedback.id')
            ->where('training_feedback.schedule_id', $scheduleId)
            ->selectRaw('
                users.id,
                users.nama_lengkap,
                tbl_data_jabatan.nama_jabatan,
                tbl_data_divisi.nama_divisi,
                COUNT(*) as total_ratings,
                AVG(training_trainer_feedback.rating) as average_rating,
                MIN(training_trainer_feedback.rating) as min_rating,
                MAX(training_trainer_feedback.rating) as max_rating
            ')
            ->groupBy('users.id', 'users.nama_lengkap', 'tbl_data_jabatan.nama_jabatan', 'tbl_data_divisi.nama_divisi')
            ->get();

        return response()->json([
            'training_stats' => $trainingStats,
            'trainer_stats' => $trainerStats
        ]);
    }

    /**
     * Check if user can view feedback statistics
     */
    private function canViewFeedbackStats($scheduleId)
    {
        $userId = Auth::id();

        // Check if user is a trainer for this training
        $isTrainer = DB::table('training_schedule_trainers')
            ->where('schedule_id', $scheduleId)
            ->where('trainer_id', $userId)
            ->where('trainer_type', 'internal')
            ->exists();

        if ($isTrainer) {
            return true;
        }

        // Check if user is admin or has permission to view feedback
        // This can be enhanced based on your permission system
        $user = Auth::user();
        if ($user && in_array($user->role, ['admin', 'hr', 'training_manager'])) {
            return true;
        }

        return false;
    }
}
