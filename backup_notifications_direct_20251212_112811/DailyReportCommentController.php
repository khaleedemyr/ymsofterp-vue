<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\DailyReportComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;

class DailyReportCommentController extends Controller
{
    /**
     * Get comments for a daily report
     */
    public function index(Request $request, $reportId)
    {
        try {
            $comments = DailyReportComment::with(['user.jabatan', 'replies.user.jabatan'])
                ->byReport($reportId)
                ->topLevel()
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $comments
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching comments', [
                'report_id' => $reportId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil komentar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new comment
     */
    public function store(Request $request, $reportId)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:daily_report_comments,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Check if report exists
            $report = DailyReport::findOrFail($reportId);

            // Create comment
            $comment = DailyReportComment::create([
                'daily_report_id' => $reportId,
                'user_id' => auth()->id(),
                'parent_id' => $request->parent_id,
                'comment' => $request->comment,
            ]);

            // Load relationships
            $comment->load(['user.jabatan']);

            // Send notifications
            $this->sendCommentNotifications($comment, $report);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil ditambahkan',
                'data' => $comment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating comment', [
                'report_id' => $reportId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan komentar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a comment
     */
    public function update(Request $request, $commentId)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $comment = DailyReportComment::findOrFail($commentId);

            // Check if user can edit this comment
            if ($comment->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk mengedit komentar ini'
                ], 403);
            }

            $comment->update([
                'comment' => $request->comment
            ]);

            $comment->load(['user.jabatan']);

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil diperbarui',
                'data' => $comment
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating comment', [
                'comment_id' => $commentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui komentar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a comment
     */
    public function destroy($commentId)
    {
        try {
            $comment = DailyReportComment::findOrFail($commentId);

            // Check if user can delete this comment
            if ($comment->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk menghapus komentar ini'
                ], 403);
            }

            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error deleting comment', [
                'comment_id' => $commentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus komentar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notifications for new comments
     */
    private function sendCommentNotifications($comment, $report)
    {
        try {
            $user = auth()->user();
            $outlet = $report->outlet;

            // Get users to notify
            $notifyUsers = collect();

            // 1. Report creator
            if ($report->user_id !== $user->id) {
                $notifyUsers->push($report->user_id);
            }

            // 2. All users who commented on this report (excluding current user)
            $commentUsers = DailyReportComment::where('daily_report_id', $report->id)
                ->where('user_id', '!=', $user->id)
                ->distinct()
                ->pluck('user_id');
            
            $notifyUsers = $notifyUsers->merge($commentUsers);

            // 3. If this is a reply, notify the parent comment author
            if ($comment->parent_id) {
                $parentComment = DailyReportComment::find($comment->parent_id);
                if ($parentComment && $parentComment->user_id !== $user->id) {
                    $notifyUsers->push($parentComment->user_id);
                }
            }

            // Remove duplicates
            $notifyUsers = $notifyUsers->unique();

            // Create notification message
            $message = $comment->isReply() 
                ? "Balasan baru dari {$user->nama_lengkap} pada Daily Report {$outlet->nama_outlet}"
                : "Komentar baru dari {$user->nama_lengkap} pada Daily Report {$outlet->nama_outlet}";

            // Send notifications
            foreach ($notifyUsers as $userId) {
                DB::table('notifications')->insert([
                    'user_id' => $userId,
                    'type' => 'daily_report_comment',
                    'message' => $message,
                    'url' => config('app.url') . '/daily-report/' . $report->id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            \Log::info('Comment notifications sent', [
                'comment_id' => $comment->id,
                'report_id' => $report->id,
                'notified_users' => $notifyUsers->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error sending comment notifications', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
