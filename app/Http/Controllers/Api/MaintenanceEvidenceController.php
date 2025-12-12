<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceEvidence;
use App\Models\MaintenanceEvidencePhoto;
use App\Models\MaintenanceEvidenceVideo;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaintenanceEvidenceController extends Controller
{
    public function store(Request $request)
    {
        // Log detail file upload sebelum validasi
        \Log::info('FILES', $request->allFiles());
        foreach ($request->file('media', []) as $idx => $file) {
            if ($file) {
                \Log::info("media[$idx]", [
                    'name' => $file->getClientOriginalName(),
                    'type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'valid' => $file->isValid(),
                ]);
            } else {
                \Log::warning("media[$idx] is null or invalid");
            }
        }

        try {
            $validated = $request->validate([
                'task_id' => 'required|exists:maintenance_tasks,id',
                'notes' => 'nullable|string',
                'media' => 'required|array',
                'media.*' => 'mimetypes:video/webm,video/mp4,video/x-matroska,image/jpeg,image/png|max:51200',
            ]);

            $evidence = MaintenanceEvidence::create([
                'task_id' => $validated['task_id'],
                'created_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $mimeType = $file->getMimeType();
                    $fileName = $file->getClientOriginalName();
                    $fileSize = $file->getSize();
                    if (str_starts_with($mimeType, 'image/')) {
                        // Simpan sebagai photo
                        $path = $file->store('maintenance/photos', 'public');
                        $evidence->photos()->create([
                            'path' => $path,
                            'file_name' => $fileName,
                            'file_type' => $mimeType,
                            'file_size' => $fileSize,
                        ]);
                    } elseif (str_starts_with($mimeType, 'video/')) {
                        // Simpan sebagai video
                        $path = $file->store('maintenance/videos', 'public');
                        $evidence->videos()->create([
                            'path' => $path,
                            'file_name' => $fileName,
                            'file_type' => $mimeType,
                            'file_size' => $fileSize,
                        ]);
                    }
                }
            }

            // Ambil data user, task, dan outlet
            $user = auth()->user();
            $task = \DB::table('maintenance_tasks')->where('id', $validated['task_id'])->first();
            $outlet = \DB::table('tbl_data_outlet')->where('id_outlet', $task->id_outlet)->first();

            // Insert ke maintenance_activity_logs
            \DB::table('maintenance_activity_logs')->insert([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'activity_type' => 'add_evidence',
                'description' => "{$user->nama_lengkap} menambahkan evidence pada task {$task->task_number} - {$task->title} (Outlet: {$outlet->nama_outlet})",
                'created_at' => now(),
            ]);

            // Ambil seluruh member & commenter
            $taskMembers = \DB::table('maintenance_members')->where('task_id', $task->id)->pluck('user_id');
            $commentUsers = \DB::table('maintenance_comments')->where('task_id', $task->id)->pluck('user_id');
            $notifyUsers = $taskMembers->merge($commentUsers)->unique();

            // Kirim notifikasi ke semua (kecuali pembuat evidence)
            foreach ($notifyUsers as $userId) {
                if ($userId == $user->id) continue;
                NotificationService::insert([
                    'user_id' => $userId,
                    'task_id' => $task->id,
                    'type' => 'add_evidence',
                    'message' => "{$user->nama_lengkap} menambahkan evidence pada task {$task->task_number} - {$task->title} (Outlet: {$outlet->nama_outlet})",
                    'url' => '/maintenance-order/' . $task->id,
                    'is_read' => 0,
                ]);
            }

            return response()->json([
                'message' => 'Evidence created successfully',
                'data' => $evidence->load(['photos', 'videos'])
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error creating evidence: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create evidence',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $evidence = MaintenanceEvidence::with(['photos', 'videos'])
            ->where('task_id', $id)
            ->latest()
            ->get();

        return response()->json($evidence);
    }
} 