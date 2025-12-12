<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\NotificationService;

class MaintenancePurchaseOrderInvoiceController extends Controller
{
    // List all invoices for a PO
    public function index($poId)
    {
        $invoices = DB::table('maintenance_purchase_order_invoices')
            ->where('po_id', $poId)
            ->orderByDesc('invoice_date')
            ->get();
        // Tambahkan full url untuk file
        foreach ($invoices as $inv) {
            $inv->invoice_file_path = $inv->invoice_file_path ? Storage::url($inv->invoice_file_path) : null;
        }
        return response()->json($invoices);
    }

    // Upload invoice for a PO
    public function store(Request $request, $poId)
    {
        $request->validate([
            'invoice_number' => 'required|string',
            'invoice_date' => 'required|date',
            'invoice_file' => 'required|file|mimes:pdf,jpg,jpeg,png',
        ]);
        $file = $request->file('invoice_file');
        $path = $file->store('po_invoices', 'public');
        $id = DB::table('maintenance_purchase_order_invoices')->insertGetId([
            'po_id' => $poId,
            'invoice_number' => $request->invoice_number,
            'invoice_date' => $request->invoice_date,
            'invoice_file_path' => $path,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $invoice = DB::table('maintenance_purchase_order_invoices')->where('id', $id)->first();
        $invoice->invoice_file_path = $invoice->invoice_file_path ? Storage::url($invoice->invoice_file_path) : null;
        
        // Insert ke maintenance_activity_logs
        $po = DB::table('maintenance_purchase_orders')->where('id', $poId)->first();
        $task = DB::table('maintenance_tasks')->where('id', $po->task_id)->first();
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $task->id_outlet)->first();
        $user = auth()->user();
        DB::table('maintenance_activity_logs')->insert([
            'task_id' => $po->task_id,
            'user_id' => $user->id,
            'activity_type' => 'upload_invoice',
            'description' => 'Upload invoice oleh ' . $user->nama_lengkap,
            'created_at' => now(),
        ]);
        // Kirim notifikasi ke seluruh member & commenter
        $memberIds = DB::table('maintenance_members')->where('task_id', $po->task_id)->pluck('user_id');
        $commenterIds = DB::table('maintenance_comments')->where('task_id', $po->task_id)->pluck('user_id');
        $notifyUsers = $memberIds->merge($commenterIds)->unique();
        $notifMsg = "Invoice telah diupload oleh {$user->nama_lengkap} untuk task {$task->task_number} - {$task->title} (PO: {$po->po_number}, Outlet: {$outlet->nama_outlet})";
        foreach ($notifyUsers as $uid) {
            DB::table('notifications')->insert([
                'user_id' => $uid,
                'task_id' => $po->task_id,
                'type' => 'upload_invoice',
                'message' => $notifMsg,
                'url' => '/maintenance-order/' . $po->task_id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json($invoice, 201);
    }
} 