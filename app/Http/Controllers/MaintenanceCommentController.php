<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class MaintenanceCommentController extends Controller
{
    public function index($taskId)
    {
        try {
            $comments = DB::table('maintenance_comments')
                ->select(
                    'maintenance_comments.*',
                    'users.nama_lengkap as user_name'
                )
                ->leftJoin('users', 'maintenance_comments.user_id', '=', 'users.id')
                ->where('task_id', $taskId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Ambil attachments untuk setiap komentar
            foreach ($comments as $comment) {
                $comment->attachments = DB::table('maintenance_comment_attachments')
                    ->where('comment_id', $comment->id)
                    ->get();
            }

            return response()->json($comments);
        } catch (\Exception $e) {
            \Log::error('Error fetching comments:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal mengambil komentar'], 500);
        }
    }

    public function count($taskId)
    {
        try {
            $count = DB::table('maintenance_comments')
                ->where('task_id', $taskId)
                ->count();

            return response()->json(['count' => $count]);
        } catch (\Exception $e) {
            \Log::error('Error counting comments:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal menghitung komentar'], 500);
        }
    }

    public function store(Request $request)
    {
        \DB::beginTransaction();
        try {
            // Validasi request
            if (!$request->task_id) {
                throw new \Exception('Task ID tidak valid');
            }

            // Insert komentar
            $commentId = DB::table('maintenance_comments')->insertGetId([
                'task_id' => $request->task_id,
                'user_id' => Auth::id(),
                'comment' => $request->comment,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Upload dan simpan attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('maintenance/comments', 'public');
                    DB::table('maintenance_comment_attachments')->insert([
                        'comment_id' => $commentId,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Notifikasi ke seluruh member & user yang pernah komentar
            $task = DB::table('maintenance_tasks')->where('id', $request->task_id)->first();
            $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $task->id_outlet)->first();
            $commenterIds = DB::table('maintenance_comments')
                ->where('task_id', $request->task_id)
                ->pluck('user_id')->unique()->toArray();
            $memberIds = DB::table('maintenance_members')
                ->where('task_id', $request->task_id)
                ->pluck('user_id')->unique()->toArray();
            $notifUserIds = array_unique(array_merge($commenterIds, $memberIds));
            foreach ($notifUserIds as $uid) {
                if ($uid == Auth::id()) continue; // Tidak perlu notif ke diri sendiri
                DB::table('notifications')->insert([
                    'user_id' => $uid,
                    'task_id' => $request->task_id,
                    'type' => 'comment',
                    'message' => 'Komentar baru oleh ' . (Auth::user()->nama_lengkap ?? Auth::user()->name) . ' pada task: ' . $task->title . ' | No: ' . $task->task_number . ' | Outlet: ' . ($outlet->nama_outlet ?? '-'),
                    'url' => '/maintenance-order/' . $request->task_id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Log ke maintenance_activity_logs
            DB::table('maintenance_activity_logs')->insert([
                'task_id' => $request->task_id,
                'user_id' => Auth::id(),
                'activity_type' => 'COMMENT_ADDED',
                'description' => 'Menambah komentar',
                'old_value' => null,
                'new_value' => $request->comment,
                'created_at' => now(),
            ]);
            // Log ke activity_logs
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'COMMENT_ADDED',
                'module' => 'maintenance',
                'description' => 'Menambah komentar pada task maintenance',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);

            \DB::commit();
            return response()->json(['success' => true, 'message' => 'Komentar berhasil ditambahkan']);
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error('Error storing comment:', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        \DB::beginTransaction();
        try {
            $comment = DB::table('maintenance_comments')->where('id', $id)->first();
            if (!$comment) {
                throw new \Exception('Komentar tidak ditemukan');
            }
            if ($comment->user_id != Auth::id()) {
                return response()->json(['error' => 'Anda tidak berhak menghapus komentar ini'], 403);
            }
            // Hapus attachments dari storage dan database
            $attachments = DB::table('maintenance_comment_attachments')
                ->where('comment_id', $id)
                ->get();
            foreach ($attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            DB::table('maintenance_comment_attachments')
                ->where('comment_id', $id)
                ->delete();
            // Hapus komentar
            DB::table('maintenance_comments')
                ->where('id', $id)
                ->delete();
            // Log ke maintenance_activity_logs
            DB::table('maintenance_activity_logs')->insert([
                'task_id' => $comment->task_id,
                'user_id' => Auth::id(),
                'activity_type' => 'COMMENT_DELETED',
                'description' => 'Menghapus komentar',
                'old_value' => $comment->comment,
                'new_value' => null,
                'created_at' => now(),
            ]);
            // Log ke activity_logs
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'COMMENT_DELETED',
                'module' => 'maintenance',
                'description' => 'Menghapus komentar pada task maintenance',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($comment),
                'new_data' => null,
                'created_at' => now(),
            ]);
            \DB::commit();
            return response()->json(['success' => true, 'message' => 'Komentar berhasil dihapus']);
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error('Error deleting comment:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal menghapus komentar'], 500);
        }
    }
} 