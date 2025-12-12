<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\NotificationService;

class MaintenancePurchaseOrderReceiveController extends Controller
{
    public function store(Request $request, $poId)
    {
        $request->validate([
            'receive_files' => 'required|array',
            'receive_files.*' => 'file|mimes:jpg,jpeg,png,webm,mp4,ogg,mov,qt,mkv|max:20480',
            'receive_notes' => 'nullable|string',
            'camera_facing_mode' => 'nullable|string',
        ]);

        $files = $request->file('receive_files');
        $po = DB::table('maintenance_purchase_orders')->where('id', $poId)->first();
        $task = DB::table('maintenance_tasks')->where('id', $po->task_id)->first();
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $task->id_outlet)->first();
        $user = auth()->user();
        foreach ($files as $file) {
            $path = $file->store('po_receives', 'public');
            DB::table('maintenance_purchase_order_receives')->insert([
                'po_id' => $poId,
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'notes' => $request->receive_notes,
                'camera_facing_mode' => $request->camera_facing_mode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        // Insert ke maintenance_activity_logs
        DB::table('maintenance_activity_logs')->insert([
            'task_id' => $po->task_id,
            'user_id' => $user->id,
            'activity_type' => 'good_receive_upload',
            'description' => 'Upload good receive oleh ' . $user->nama_lengkap,
            'created_at' => now(),
        ]);
        // Kirim notifikasi ke seluruh member & commenter
        $memberIds = DB::table('maintenance_members')->where('task_id', $po->task_id)->pluck('user_id');
        $commenterIds = DB::table('maintenance_comments')->where('task_id', $po->task_id)->pluck('user_id');
        $notifyUsers = $memberIds->merge($commenterIds)->unique();
        $notifMsg = "Good receive telah diupload oleh {$user->nama_lengkap} untuk task {$task->task_number} - {$task->title} (PO: {$po->po_number}, Outlet: {$outlet->nama_outlet})";
        foreach ($notifyUsers as $uid) {
            NotificationService::insert([
                'user_id' => $uid,
                'task_id' => $po->task_id,
                'type' => 'good_receive_upload',
                'message' => $notifMsg,
                'url' => config('app.url') . '/maintenance-order/' . $po->task_id,
                'is_read' => 0,
            ]);
        }
        return response()->json(['success' => true]);
    }

    public function index($poId)
    {
        $receives = DB::table('maintenance_purchase_order_receives')
            ->where('po_id', $poId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($row) {
                $row->file_url = $row->file_path ? Storage::url($row->file_path) : null;
                return $row;
            });
        return response()->json($receives);
    }
} 