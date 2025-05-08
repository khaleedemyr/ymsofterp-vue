<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceOrderController extends Controller
{
    // Ambil semua outlet
    public function getOutlets()
    {
        $outlets = DB::table('tbl_data_outlet')->get();
        return response()->json($outlets);
    }

    // Ambil semua ruko berdasarkan outlet
    public function getRukos(Request $request)
    {
        $query = DB::table('tbl_data_ruko');
        if ($request->has('id_outlet')) {
            $query->where('id_outlet', $request->id_outlet);
        }
        $rukos = $query->get();
        return response()->json($rukos);
    }

    // Ambil semua task maintenance order (filter outlet & ruko)
    public function index(Request $request)
    {
        \Log::info('Fetching maintenance tasks', [
            'request_params' => $request->all()
        ]);

        try {
            $query = DB::table('maintenance_tasks')
                ->select(
                    'maintenance_tasks.*',
                    'users.nama_lengkap as created_by_name',
                    'maintenance_labels.name as label_name',
                    'maintenance_priorities.priority as priority_name'
                )
                ->leftJoin('users', 'maintenance_tasks.created_by', '=', 'users.id')
                ->leftJoin('maintenance_labels', 'maintenance_tasks.label_id', '=', 'maintenance_labels.id')
                ->leftJoin('maintenance_priorities', 'maintenance_tasks.priority_id', '=', 'maintenance_priorities.id');

            if ($request->has('id_outlet')) {
                $query->where('maintenance_tasks.id_outlet', $request->id_outlet);
                if ($request->id_outlet == 1 && $request->has('id_ruko')) {
                    $query->where('maintenance_tasks.id_ruko', $request->id_ruko);
                }
            }

            $tasks = $query->orderBy('maintenance_tasks.created_at', 'desc')->get();

            // Ambil media, dokumen, dan member untuk setiap task
            foreach ($tasks as $task) {
                $task->media = DB::table('maintenance_media')
                    ->where('task_id', $task->id)
                    ->get();
                $task->documents = DB::table('maintenance_documents')
                    ->where('task_id', $task->id)
                    ->get();
                // Ambil semua member (creator & ASSIGNEE)
                $task->members = DB::table('maintenance_members')
                    ->join('users', 'maintenance_members.user_id', '=', 'users.id')
                    ->where('maintenance_members.task_id', $task->id)
                    ->select('users.id', 'users.nama_lengkap', 'maintenance_members.role')
                    ->get();
            }

            \Log::info('Tasks fetched successfully', [
                'count' => $tasks->count(),
                'sample_task' => $tasks->first()
            ]);

            return response()->json($tasks);
        } catch (\Exception $e) {
            \Log::error('Error in MaintenanceOrderController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update status task (drag & drop)
    public function updateStatus(Request $request, $id)
    {
        \Log::info('Updating task status', [
            'task_id' => $id,
            'new_status' => $request->status,
            'user_id' => auth()->id()
        ]);

        try {
            $request->validate([
                'status' => 'required|string'
            ]);

            // 1. Ambil status lama
            $oldStatus = DB::table('maintenance_tasks')
                ->where('id', $id)
                ->value('status');

            if (!$oldStatus) {
                throw new \Exception('Task not found');
            }

            // 2. Update status di maintenance_tasks
            $updateData = [
                'status' => $request->status,
                'updated_at' => now()
            ];

            // Jika status berubah ke DONE, set completed_at
            if ($request->status === 'DONE') {
                $updateData['completed_at'] = now();
            }

            $updated = DB::table('maintenance_tasks')
                ->where('id', $id)
                ->update($updateData);

            if (!$updated) {
                throw new \Exception('Failed to update task status');
            }

            \Log::info('Task status updated successfully', [
                'task_id' => $id,
                'old_status' => $oldStatus,
                'new_status' => $request->status
            ]);

            try {
                // 3. Log aktivitas dengan old_value dan new_value
                DB::table('maintenance_activity_logs')->insert([
                    'task_id' => $id,
                    'user_id' => auth()->id(),
                    'activity_type' => 'STATUS_CHANGED',
                    'description' => 'Mengubah status task',
                    'old_value' => $oldStatus,
                    'new_value' => $request->status,
                    'created_at' => now()
                ]);
            } catch (\Exception $logError) {
                \Log::warning('Failed to log activity but status was updated', [
                    'task_id' => $id,
                    'error' => $logError->getMessage(),
                    'trace' => $logError->getTraceAsString()
                ]);
            }

            // --- NOTIFIKASI MOVE TASK ---
            // Ambil data task dan outlet
            $task = DB::table('maintenance_tasks')->where('id', $id)->first();
            $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $task->id_outlet)->first();

            // Ambil semua member task
            $taskMembers = DB::table('maintenance_members')
                ->where('task_id', $id)
                ->pluck('user_id');

            // Ambil semua user yang pernah komentar di task
            $commentUsers = DB::table('maintenance_comments')
                ->where('task_id', $id)
                ->pluck('user_id');

            // Gabungkan dan hapus duplikat
            $notifyUsers = $taskMembers->merge($commentUsers)->unique();

            // Status mapping untuk pesan notifikasi
            $statusMessages = [
                'TASK' => 'TO DO',
                'PR' => 'Purchase Requisition',
                'PO' => 'Purchase Order',
                'IN_PROGRESS' => 'In Progress',
                'IN_REVIEW' => 'In Review',
                'DONE' => 'Done'
            ];

            $user = auth()->user();
            // Kirim notifikasi ke semua member dan commenter (termasuk user yang memindahkan task)
            foreach ($notifyUsers as $userId) {
                DB::table('notifications')->insert([
                    'user_id' => $userId,
                    'task_id' => $id,
                    'type' => 'task_status_changed',
                    'message' => "Task {$task->task_number} - {$task->title} di outlet {$outlet->nama_outlet} telah dipindahkan dari {$statusMessages[$oldStatus]} ke {$statusMessages[$request->status]} oleh {$user->nama_lengkap}",
                    'url' => '/maintenance-order/' . $id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Jika status berubah dari PR ke PO, kirim notifikasi khusus ke purchasing manager & supervisor
            if ($oldStatus === 'PR' && $request->status === 'PO') {
                // Ambil data PR untuk task ini
                $prs = DB::table('maintenance_purchase_requisitions')
                    ->where('task_id', $id)
                    ->where('status', 'APPROVED')
                    ->get();

                $purchasingUsers = DB::table('users')
                    ->whereIn('id_jabatan', [168, 244]) // 168: manager, 244: supervisor
                    ->where('status', 'A')
                    ->pluck('id');

                foreach ($purchasingUsers as $userId) {
                    // Buat pesan notifikasi yang lebih detail
                    $prNumbers = $prs->pluck('pr_number')->implode(', ');
                    $message = "Task {$task->task_number} - {$task->title} di outlet {$outlet->nama_outlet} telah disetujui untuk PO.\n\n";
                    $message .= "PR yang perlu diproses: {$prNumbers}\n\n";
                    $message .= "Silakan segera lakukan:\n";
                    $message .= "1. Buat PO langsung jika sudah ada supplier tetap\n";
                    $message .= "2. Lakukan proses bidding jika belum ada supplier tetap\n\n";
                    $message .= "Diajukan oleh: {$user->nama_lengkap}";

                    DB::table('notifications')->insert([
                        'user_id' => $userId,
                        'task_id' => $id,
                        'type' => 'pr_approved_for_po',
                        'message' => $message,
                        'url' => '/maintenance-order/' . $id,
                        'is_read' => 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Status berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating task status', [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'sql_state' => $e->getCode()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Gagal memperbarui status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        \DB::beginTransaction();
        try {
            \Log::info('Creating maintenance task', [
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            // 1. Insert ke maintenance_tasks
            $taskId = \DB::table('maintenance_tasks')->insertGetId([
                'task_number' => $this->generateTaskNumber(),
                'title' => $request->title,
                'description' => $request->description,
                'status' => 'TASK',
                'priority_id' => $request->priority,
                'label_id' => $request->category,
                'id_outlet' => $request->id_outlet,
                'id_ruko' => $request->id_ruko,
                'created_by' => auth()->id(),
                'due_date' => $request->due_date,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \Log::info('Task created', ['task_id' => $taskId]);

            // 2. Insert ke maintenance_members (pembuat task)
            \DB::table('maintenance_members')->insert([
                'task_id' => $taskId,
                'user_id' => auth()->id(),
                'role' => 'creator',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Insert ke maintenance_media (foto/video)
            \Log::info('Isi $request->media:', ['media' => $request->media]);
            if ($request->media) {
                foreach ($request->media as $media) {
                    \Log::info('Tipe file media:', [
                        'name' => $media->getClientOriginalName(),
                        'type' => $media->getClientMimeType(),
                        'size' => $media->getSize()
                    ]);
                    $path = $media->store('maintenance/media', 'public');
                    \DB::table('maintenance_media')->insert([
                        'task_id' => $taskId,
                        'file_name' => $media->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $media->getClientMimeType(),
                        'file_size' => $media->getSize(),
                        'uploaded_by' => auth()->id(),
                        'created_at' => now(),
                    ]);
                }
            }

            // 4. Insert ke maintenance_documents (dokumen)
            if ($request->documents) {
                foreach ($request->documents as $doc) {
                    $path = $doc->store('maintenance/documents', 'public');
                    \DB::table('maintenance_documents')->insert([
                        'task_id' => $taskId,
                        'document_type' => 'other', // atau sesuai input
                        'file_name' => $doc->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $doc->getClientMimeType(),
                        'file_size' => $doc->getSize(),
                        'uploaded_by' => auth()->id(),
                        'created_at' => now(),
                    ]);
                }
            }

            // 5. Insert ke maintenance_activity_logs
            \DB::table('maintenance_activity_logs')->insert([
                'task_id' => $taskId,
                'user_id' => auth()->id(),
                'activity_type' => 'create',
                'description' => 'Membuat task baru',
                'created_at' => now(),
            ]);

            // 6. Insert ke activity_logs
            \DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'create',
                'module' => 'maintenance',
                'description' => 'Membuat task maintenance baru',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);

            // 7. Insert ke notifications
            $userIds = \DB::table('users')
                ->whereIn('id_jabatan', [209, 165, 262, 151])
                ->where('status', 'A')
                ->pluck('id');

            // Get outlet name
            $outlet = \DB::table('tbl_data_outlet')
                ->where('id_outlet', $request->id_outlet)
                ->first();

            // Get creator name
            $creator = auth()->user();

            foreach ($userIds as $uid) {
                \DB::table('notifications')->insert([
                    'user_id' => $uid,
                    'task_id' => $taskId,
                    'type' => 'task_created',
                    'message' => "Task baru telah dibuat:\n\nNo: {$request->task_number}\nJudul: {$request->title}\nOutlet: {$outlet->nama_outlet}\nDibuat oleh: {$creator->nama_lengkap}",
                    'url' => '/maintenance-order/' . $taskId,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            \DB::commit();
            return response()->json(['success' => true, 'task_id' => $taskId]);
        } catch (\Exception $e) {
            \Log::error('Error creating task', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            \DB::rollBack();
            return response()->json([
                'success' => false, 
                'error' => 'Gagal membuat task: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateTaskNumber()
    {
        $prefix = 'MT-' . date('Ym') . '-';
        // Ambil nomor urut terakhir bulan ini
        $last = \DB::table('maintenance_tasks')
            ->where('task_number', 'like', $prefix . '%')
            ->orderByDesc('task_number')
            ->value('task_number');

        if ($last) {
            $lastNumber = (int)substr($last, -6);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    // API: Ambil user yang bisa di-assign (hanya division_id=20 & status=A)
    public function assignableUsers()
    {
        $users = DB::table('users')
            ->where('division_id', 20)
            ->where('status', 'A')
            ->select('id', 'nama_lengkap')
            ->get();
        return response()->json($users);
    }

    // API: Ambil member yang sudah di-assign ke task
    public function getTaskMembers($taskId)
    {
        $members = DB::table('maintenance_members')
            ->join('users', 'maintenance_members.user_id', '=', 'users.id')
            ->where('maintenance_members.task_id', $taskId)
            ->where('maintenance_members.role', 'ASSIGNEE')
            ->select('users.id', 'users.nama_lengkap')
            ->get();
        return response()->json($members);
    }

    // API: Assign member ke task (replace all ASSIGNEE)
    public function assignMembers(Request $request)
    {
        $request->validate([
            'task_id' => 'required|integer',
            'user_ids' => 'required|array',
        ]);
        $taskId = $request->task_id;
        $userIds = $request->user_ids;
        // Filter: superadmin tidak boleh di-assign
        $userIds = DB::table('users')
            ->whereIn('id', $userIds)
            ->where(function($q) {
                $q->where('division_id', 20)->where('status', 'A');
            })
            ->pluck('id')
            ->toArray();
        // Hapus semua assignee lama
        DB::table('maintenance_members')
            ->where('task_id', $taskId)
            ->where('role', 'ASSIGNEE')
            ->delete();
        // Insert baru
        foreach ($userIds as $uid) {
            DB::table('maintenance_members')->insert([
                'task_id' => $taskId,
                'user_id' => $uid,
                'role' => 'ASSIGNEE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        // Kirim notifikasi ke user yang dipilih
        $task = DB::table('maintenance_tasks')->where('id', $taskId)->first();
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $task->id_outlet)->first();
        foreach ($userIds as $uid) {
            DB::table('notifications')->insert([
                'user_id' => $uid,
                'task_id' => $taskId,
                'type' => 'assign_member',
                'message' => 'Anda di-assign ke task: ' . $task->title . ' | Outlet: ' . ($outlet->nama_outlet ?? '-') . ' | No: ' . $task->task_number,
                'url' => '/maintenance-order/' . $taskId,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json(['success' => true]);
    }
}
