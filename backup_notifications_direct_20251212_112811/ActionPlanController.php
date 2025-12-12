<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;

class ActionPlanController extends Controller
{
    /**
     * Store a newly created action plan in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|exists:maintenance_tasks,id',
            'description' => 'required|string',
            'media' => 'nullable|array',
            'media.*' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi,webm|max:10240'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah user memiliki akses
        $user = Auth::user();
        $hasAccess = false;

        // Cek apakah user adalah superadmin
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $hasAccess = true;
        }
        // Cek apakah user memiliki division_id=20 dan status=A
        else if ($user->division_id === 20 && $user->status === 'A') {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk membuat action plan'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Simpan action plan
            $actionPlanId = DB::table('action_plans')->insertGetId([
                'task_id' => $request->task_id,
                'description' => $request->description,
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Simpan media jika ada
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $media) {
                    $filePath = $media->store('action_plans', 'public');
                    $mediaType = strpos($media->getMimeType(), 'video') !== false ? 'video' : 'image';
                    
                    DB::table('action_plan_media')->insert([
                        'action_plan_id' => $actionPlanId,
                        'file_path' => $filePath,
                        'media_type' => $mediaType,
                        'created_at' => now()
                    ]);
                }
            }

            // Simpan log aktivitas
            DB::table('maintenance_activity_logs')->insert([
                'task_id' => $request->task_id,
                'user_id' => $user->id,
                'activity_type' => 'ACTION_PLAN_CREATED',
                'description' => 'Membuat action plan baru',
                'created_at' => now()
            ]);

            // Simpan log aktivitas umum
            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => 'create',
                'module' => 'action_plan',
                'description' => 'Membuat action plan baru untuk task #' . $request->task_id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode([
                    'task_id' => $request->task_id,
                    'description' => $request->description
                ]),
                'created_at' => now()
            ]);

            // Kirim notifikasi ke semua member task
            $taskMembers = DB::table('maintenance_members')
                ->where('task_id', $request->task_id)
                ->pluck('user_id');

            // Kirim notifikasi ke semua user yang berkomentar di task
            $commentUsers = DB::table('maintenance_comments')
                ->where('task_id', $request->task_id)
                ->pluck('user_id');

            // Gabungkan dan hapus duplikat
            $notifyUsers = $taskMembers->merge($commentUsers)->unique();

            foreach ($notifyUsers as $userId) {
                // Skip jika user adalah pembuat action plan
                if ($userId == $user->id) continue;

                DB::table('notifications')->insert([
                    'user_id' => $userId,
                    'task_id' => $request->task_id,
                    'type' => 'action_plan_created',
                    'message' => 'Action plan baru telah dibuat untuk task #' . $request->task_id,
                    'url' => '/maintenance-order/' . $request->task_id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Action plan berhasil disimpan',
                'data' => [
                    'action_plan_id' => $actionPlanId
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan action plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get action plans for a specific task.
     *
     * @param  int  $taskId
     * @return \Illuminate\Http\Response
     */
    public function getByTask($taskId)
    {
        try {
            $actionPlans = DB::table('action_plans')
                ->join('users', 'action_plans.created_by', '=', 'users.id')
                ->where('action_plans.task_id', $taskId)
                ->select(
                    'action_plans.id',
                    'action_plans.description',
                    'action_plans.created_at',
                    'users.nama_lengkap as created_by_name'
                )
                ->orderBy('action_plans.created_at', 'desc')
                ->get();

            // Ambil media untuk setiap action plan
            foreach ($actionPlans as $actionPlan) {
                $actionPlan->media = DB::table('action_plan_media')
                    ->where('action_plan_id', $actionPlan->id)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $actionPlans
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil action plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a specific action plan media.
     *
     * @param  int  $mediaId
     * @return \Illuminate\Http\Response
     */
    public function deleteMedia($mediaId)
    {
        try {
            $media = DB::table('action_plan_media')->where('id', $mediaId)->first();
            
            if (!$media) {
                return response()->json([
                    'success' => false,
                    'message' => 'Media tidak ditemukan'
                ], 404);
            }

            // Hapus file dari storage
            Storage::disk('public')->delete($media->file_path);
            
            // Hapus record dari database
            DB::table('action_plan_media')->where('id', $mediaId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Media berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus media: ' . $e->getMessage()
            ], 500);
        }
    }
} 