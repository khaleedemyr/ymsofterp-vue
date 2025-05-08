<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceTask;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class MaintenanceTaskController extends Controller
{
    public function updateStatus(MaintenanceTask $task, Request $request)
    {
        $user = auth()->user();
        
        // Validasi user yang bisa move task
        if ($user->status !== 'A' || 
            !($user->division_id === 20 || 
              $user->id_role === '5af56935b011a' || 
              $user->id_jabatan === 217)) {
            return back()->with('error', 'Anda tidak memiliki akses untuk memindahkan task');
        }

        // Validasi status yang valid
        $validStatuses = ['TASK', 'PR', 'PO', 'IN_PROGRESS', 'IN_REVIEW', 'DONE'];
        if (!in_array($request->status, $validStatuses)) {
            return back()->with('error', 'Status tidak valid');
        }

        $oldStatus = $task->status;
        $updateData = [
            'status' => $request->status
        ];
        if ($request->status === 'DONE') {
            $updateData['completed_at'] = now();
        }
        $task->update($updateData);

        // Ambil data task dan outlet
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $task->id_outlet)
            ->first();

        // Ambil semua member task
        $taskMembers = DB::table('maintenance_members')
            ->where('task_id', $task->id)
            ->pluck('user_id');

        // Ambil semua user yang pernah komentar di task
        $commentUsers = DB::table('maintenance_comments')
            ->where('task_id', $task->id)
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

        // Kirim notifikasi ke semua member dan commenter (termasuk user yang memindahkan task)
        foreach ($notifyUsers as $userId) {
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'task_id' => $task->id,
                'type' => 'task_status_changed',
                'message' => "Task {$task->task_number} - {$task->title} di outlet {$outlet->nama_outlet} telah dipindahkan dari {$statusMessages[$oldStatus]} ke {$statusMessages[$request->status]} oleh {$user->nama_lengkap}",
                'url' => '/maintenance-order/' . $task->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Jika status berubah dari PR ke PO, kirim notifikasi khusus ke purchasing manager & supervisor
        if ($oldStatus === 'PR' && $request->status === 'PO') {
            // Ambil data PR untuk task ini
            $prs = DB::table('maintenance_purchase_requisitions')
                ->where('task_id', $task->id)
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
                    'task_id' => $task->id,
                    'type' => 'pr_approved_for_po',
                    'message' => $message,
                    'url' => '/maintenance-order/' . $task->id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        return back()->with('success', 'Status task berhasil diupdate');
    }

    public function timeline($taskId)
    {
        $logs = DB::table('maintenance_activity_logs')
            ->leftJoin('users', 'maintenance_activity_logs.user_id', '=', 'users.id')
            ->where('maintenance_activity_logs.task_id', $taskId)
            ->orderBy('maintenance_activity_logs.created_at', 'asc')
            ->select([
                'maintenance_activity_logs.id',
                'maintenance_activity_logs.activity_type',
                'maintenance_activity_logs.description',
                'maintenance_activity_logs.old_value',
                'maintenance_activity_logs.new_value',
                'maintenance_activity_logs.created_at',
                'users.nama_lengkap as user_name',
            ])->get();

        return response()->json($logs);
    }

    public function purchaseRequisitions($taskId)
    {
        $prs = DB::table('maintenance_purchase_requisitions')
            ->leftJoin('users as chief_engineering', 'chief_engineering.id', '=', 'maintenance_purchase_requisitions.chief_engineering_approval_by')
            ->leftJoin('users as purchasing_manager', 'purchasing_manager.id', '=', 'maintenance_purchase_requisitions.purchasing_manager_approval_by')
            ->leftJoin('users as coo', 'coo.id', '=', 'maintenance_purchase_requisitions.coo_approval_by')
            ->leftJoin('users as ceo', 'ceo.id', '=', 'maintenance_purchase_requisitions.ceo_approval_by')
            ->select(
                'maintenance_purchase_requisitions.*',
                'chief_engineering.nama_lengkap as chief_engineering_approval_by_user_nama_lengkap',
                'purchasing_manager.nama_lengkap as purchasing_manager_approval_by_user_nama_lengkap',
                'coo.nama_lengkap as coo_approval_by_user_nama_lengkap',
                'ceo.nama_lengkap as ceo_approval_by_user_nama_lengkap'
            )
            ->where('maintenance_purchase_requisitions.task_id', $taskId)
            ->orderBy('maintenance_purchase_requisitions.created_at', 'desc')
            ->get();

        // Fetch items for each PR
        foreach ($prs as $pr) {
            $pr->items = DB::table('maintenance_purchase_requisition_items as pri')
                ->leftJoin('units as u', 'pri.unit_id', '=', 'u.id')
                ->where('pri.pr_id', $pr->id)
                ->select('pri.*', 'u.name as unit_name')
                ->get();

            // Tambahkan data approver dalam format yang diharapkan
            $pr->chief_engineering_approval_by_user = $pr->chief_engineering_approval_by_user_nama_lengkap ? [
                'nama_lengkap' => $pr->chief_engineering_approval_by_user_nama_lengkap
            ] : null;
            $pr->purchasing_manager_approval_by_user = $pr->purchasing_manager_approval_by_user_nama_lengkap ? [
                'nama_lengkap' => $pr->purchasing_manager_approval_by_user_nama_lengkap
            ] : null;
            $pr->coo_approval_by_user = $pr->coo_approval_by_user_nama_lengkap ? [
                'nama_lengkap' => $pr->coo_approval_by_user_nama_lengkap
            ] : null;
            $pr->ceo_approval_by_user = $pr->ceo_approval_by_user_nama_lengkap ? [
                'nama_lengkap' => $pr->ceo_approval_by_user_nama_lengkap
            ] : null;

            // Hapus field temporary
            unset($pr->chief_engineering_approval_by_user_nama_lengkap);
            unset($pr->purchasing_manager_approval_by_user_nama_lengkap);
            unset($pr->coo_approval_by_user_nama_lengkap);
            unset($pr->ceo_approval_by_user_nama_lengkap);
        }

        return response()->json($prs);
    }

    public function storePurchaseRequisition(Request $request, $taskId)
    {
        $request->validate([
            'pr_number' => 'required|string',
            'description' => 'nullable|string',
            'specifications' => 'nullable|string',
            'total_amount' => 'nullable|numeric',
            // Tambahkan validasi lain sesuai kebutuhan
        ]);

        $prId = DB::table('maintenance_purchase_requisitions')->insertGetId([
            'pr_number' => $request->pr_number,
            'task_id' => $taskId,
            'created_by' => auth()->id(),
            'status' => 'DRAFT',
            'description' => $request->description,
            'specifications' => $request->specifications,
            'total_amount' => $request->total_amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Jika ada items
        if ($request->items && is_array($request->items)) {
            foreach ($request->items as $item) {
                DB::table('maintenance_purchase_requisition_items')->insert([
                    'pr_id' => $prId,
                    'item_name' => $item['item_name'] ?? '',
                    'description' => $item['description'] ?? '',
                    'specifications' => $item['specifications'] ?? '',
                    'quantity' => $item['quantity'] ?? 0,
                    'unit_id' => $item['unit_id'] ?? null,
                    'price' => $item['price'] ?? 0,
                    'subtotal' => $item['subtotal'] ?? 0,
                    'notes' => $item['notes'] ?? '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Ambil data task dan outlet untuk notifikasi
        $task = DB::table('maintenance_tasks as mt')
            ->join('tbl_data_outlet as do', 'mt.id_outlet', '=', 'do.id_outlet')
            ->where('mt.id', $taskId)
            ->select('mt.task_number', 'mt.title', 'do.nama_outlet')
            ->first();

        // Ambil semua member task
        $taskMembers = DB::table('maintenance_members')
            ->where('task_id', $taskId)
            ->pluck('user_id');

        // Ambil semua user yang pernah komentar di task
        $commentUsers = DB::table('maintenance_comments')
            ->where('task_id', $taskId)
            ->pluck('user_id');

        // Gabungkan dan hapus duplikat
        $notifyUsers = $taskMembers->merge($commentUsers)->unique();

        // Kirim notifikasi ke semua member dan commenter
        foreach ($notifyUsers as $userId) {
            // Skip jika user adalah yang membuat PR
            if ($userId == auth()->id()) continue;

            DB::table('notifications')->insert([
                'user_id' => $userId,
                'task_id' => $taskId,
                'type' => 'pr_created',
                'message' => "PR {$request->pr_number} telah dibuat untuk task {$task->task_number} - {$task->title} di outlet {$task->nama_outlet}",
                'url' => '/maintenance-order/' . $taskId,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Kirim notifikasi ke Chief Engineering untuk approval
        $chiefEngineerings = DB::table('users')
            ->whereIn('id_jabatan', [165, 263]) // ID jabatan chief engineering
            ->where('status', 'A') // Hanya user dengan status aktif
            ->pluck('id');

        foreach ($chiefEngineerings as $userId) {
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'task_id' => $taskId,
                'type' => 'pr_approval_request',
                'message' => "PR {$request->pr_number} untuk task {$task->task_number} - {$task->title} di outlet {$task->nama_outlet} membutuhkan persetujuan Anda",
                'url' => '/maintenance-order/' . $taskId,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Insert activity log
        DB::table('maintenance_activity_logs')->insert([
            'task_id' => $taskId,
            'user_id' => auth()->id(),
            'activity_type' => 'PR_CREATED',
            'description' => "PR {$request->pr_number} telah dibuat",
            'created_at' => now()
        ]);

        return response()->json(['success' => true, 'id' => $prId]);
    }

    public function generatePrNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'PR-' . $date . '-';
        $lastPr = DB::table('maintenance_purchase_requisitions')
            ->where('pr_number', 'like', $prefix . '%')
            ->orderBy('pr_number', 'desc')
            ->first();
        $next = 1;
        if ($lastPr && preg_match('/PR-\d{8}-(\d+)/', $lastPr->pr_number, $m)) {
            $next = intval($m[1]) + 1;
        }
        $prNumber = $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
        return response()->json(['pr_number' => $prNumber]);
    }

    public function getUnits()
    {
        $units = DB::table('units')->where('status', 'active')->orderBy('name')->get();
        return response()->json($units);
    }

    public function deletePurchaseRequisition($id)
    {
        // Ambil data PR sebelum dihapus
        $pr = DB::table('maintenance_purchase_requisitions')->where('id', $id)->first();
        if (!$pr) {
            return response()->json(['success' => false, 'message' => 'PR tidak ditemukan']);
        }
        $taskId = $pr->task_id;

        // Hapus items dulu
        DB::table('maintenance_purchase_requisition_items')->where('pr_id', $id)->delete();
        // Hapus PR
        DB::table('maintenance_purchase_requisitions')->where('id', $id)->delete();

        // Log aktivitas
        DB::table('maintenance_activity_logs')->insert([
            'task_id' => $taskId,
            'user_id' => auth()->id(),
            'activity_type' => 'PR_DELETED',
            'description' => "PR {$pr->pr_number} telah dihapus",
            'created_at' => now()
        ]);

        // Ambil data task dan outlet untuk notifikasi
        $task = DB::table('maintenance_tasks as mt')
            ->join('tbl_data_outlet as do', 'mt.id_outlet', '=', 'do.id_outlet')
            ->where('mt.id', $taskId)
            ->select('mt.task_number', 'mt.title', 'do.nama_outlet')
            ->first();

        // Ambil semua member task
        $taskMembers = DB::table('maintenance_members')
            ->where('task_id', $taskId)
            ->pluck('user_id');

        // Ambil semua user yang pernah komentar di task
        $commentUsers = DB::table('maintenance_comments')
            ->where('task_id', $taskId)
            ->pluck('user_id');

        // Gabungkan dan hapus duplikat
        $notifyUsers = $taskMembers->merge($commentUsers)->unique();

        // Kirim notifikasi ke semua member dan commenter
        foreach ($notifyUsers as $userId) {
            // Skip jika user adalah yang menghapus PR
            if ($userId == auth()->id()) continue;

            DB::table('notifications')->insert([
                'user_id' => $userId,
                'task_id' => $taskId,
                'type' => 'pr_deleted',
                'message' => "PR {$pr->pr_number} untuk task {$task->task_number} - {$task->title} di outlet {$task->nama_outlet} telah dihapus",
                'url' => '/maintenance-order/' . $taskId,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function exportBiddingItemsPdf($taskId, Request $request)
    {
        $itemIds = $request->input('item_ids', []);
        if (empty($itemIds)) {
            return response()->json(['error' => 'No items selected'], 400);
        }
        $items = DB::table('maintenance_purchase_requisition_items as pri')
            ->leftJoin('units as u', 'pri.unit_id', '=', 'u.id')
            ->whereIn('pri.id', $itemIds)
            ->select('pri.item_name', 'pri.specifications', 'pri.quantity', 'u.name as unit_name')
            ->get();
        $pdf = Pdf::loadView('pdf.bidding-items', [
            'items' => $items
        ]);
        return $pdf->download('bidding-items.pdf');
    }

    public function storeBiddingOffer(Request $request)
    {
        $supplier_id = $request->input('supplier_id');
        $offers = $request->input('offers', []);
        if (is_string($offers)) {
            $offers = json_decode($offers, true);
        }
        $file = $request->file('file');

        $file_path = null;
        if ($file) {
            $file_path = $file->store('bidding_offers', 'public');
        }

        foreach ($offers as $pr_item_id => $price) {
            \DB::table('maintenance_bidding_offers')->insert([
                'pr_item_id' => $pr_item_id,
                'supplier_id' => $supplier_id,
                'price' => $price,
                'file_path' => $file_path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function getBiddingOffers(Request $request)
    {
        $task_id = $request->input('task_id');
        $offers = DB::table('maintenance_bidding_offers as o')
            ->join('maintenance_purchase_requisition_items as i', 'o.pr_item_id', '=', 'i.id')
            ->join('maintenance_purchase_requisitions as pr', 'i.pr_id', '=', 'pr.id')
            ->join('suppliers as s', 'o.supplier_id', '=', 's.id')
            ->leftJoin('units as u', 'i.unit_id', '=', 'u.id')
            ->where('pr.task_id', $task_id)
            ->select(
                'o.id',
                'o.price',
                'o.file_path',
                'o.created_at',
                's.name as supplier_name',
                's.id as supplier_id',
                'i.item_name',
                'i.id as item_id',
                'i.quantity',
                'u.name as unit_name'
            )
            ->orderBy('s.name')
            ->orderBy('i.item_name')
            ->get();
        return response()->json($offers);
    }

    private function generatePoNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'PO-' . $date . '-';
        $lastPo = \DB::table('maintenance_purchase_orders')
            ->where('po_number', 'like', $prefix . '%')
            ->orderBy('po_number', 'desc')
            ->first();
        $next = 1;
        if ($lastPo && preg_match('/PO-\d{8}-(\d+)/', $lastPo->po_number, $m)) {
            $next = intval($m[1]) + 1;
        }
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function createPOFromBidding(Request $request)
    {
        $task_id = $request->input('task_id');
        $items = $request->input('items', []);

        if (!$task_id || empty($items)) {
            return response()->json(['message' => 'Data tidak lengkap'], 400);
        }

        try {
            DB::beginTransaction();

            // Group items by supplier
            $grouped = [];
            $allPrItemIds = [];
            $supplierNames = [];
            foreach ($items as $item) {
                $grouped[$item['supplier_id']][] = $item;
                $allPrItemIds[] = $item['pr_item_id'];
            }

            $createdPOs = [];
            foreach ($grouped as $supplierId => $supplierItems) {
                $poNumber = $this->generatePoNumber();

                // Ambil nama supplier
                $supplier = DB::table('suppliers')->where('id', $supplierId)->first();
                if ($supplier) {
                    $supplierNames[] = $supplier->name;
                }

                // Hitung total_amount untuk PO ini
                $totalAmount = 0;
                foreach ($supplierItems as $item) {
                    $prItem = DB::table('maintenance_purchase_requisition_items')->where('id', $item['pr_item_id'])->first();
                    $qty = $prItem ? $prItem->quantity : 0;
                    $price = $item['price'];
                    $totalAmount += $qty * $price;
                }

                // Create PO
                $poId = DB::table('maintenance_purchase_orders')->insertGetId([
                    'po_number' => $poNumber,
                    'task_id' => $task_id,
                    'supplier_id' => $supplierId,
                    'status' => 'DRAFT',
                    'created_by' => auth()->id(),
                    'total_amount' => $totalAmount,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Create PO items (gunakan field sesuai struktur tabel)
                foreach ($supplierItems as $item) {
                    $prItem = DB::table('maintenance_purchase_requisition_items')->where('id', $item['pr_item_id'])->first();
                    DB::table('maintenance_purchase_order_items')->insert([
                        'po_id' => $poId,
                        'pr_id' => $prItem ? $prItem->pr_id : null,
                        'supplier_id' => $supplierId,
                        'item_name' => $prItem ? $prItem->item_name : '',
                        'description' => $prItem ? $prItem->description : '',
                        'specifications' => $prItem ? $prItem->specifications : '',
                        'quantity' => $prItem ? $prItem->quantity : 0,
                        'unit_id' => $prItem ? $prItem->unit_id : null,
                        'price' => $item['price'],
                        'supplier_price' => $item['price'],
                        'subtotal' => $prItem ? $prItem->subtotal : 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Save to bidding history
                    DB::table('maintenance_bidding_history')->insert([
                        'task_id' => $task_id,
                        'pr_item_id' => $item['pr_item_id'],
                        'supplier_id' => $supplierId,
                        'price' => $item['price'],
                        'status' => 'selected',
                        'created_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                $createdPOs[] = $poId;
            }

            // Update status of rejected bids
            DB::table('maintenance_bidding_history')
                ->where('task_id', $task_id)
                ->whereIn('pr_item_id', $allPrItemIds)
                ->where('status', 'active')
                ->update(['status' => 'rejected']);

            // Get task and outlet info for notification
            $task = DB::table('maintenance_tasks')
                ->join('tbl_data_outlet', 'maintenance_tasks.id_outlet', '=', 'tbl_data_outlet.id_outlet')
                ->where('maintenance_tasks.id', $task_id)
                ->select('maintenance_tasks.*', 'tbl_data_outlet.nama_outlet')
                ->first();

            // Get all users to notify
            $memberIds = DB::table('maintenance_members')
                ->where('task_id', $task_id)
                ->pluck('user_id');

            $commenterIds = DB::table('maintenance_comments')
                ->where('task_id', $task_id)
                ->pluck('user_id');

            $notifyUsers = $memberIds->merge($commenterIds)->unique();

            // Notifikasi ke seluruh member dan commenter
            $supplierList = implode(', ', $supplierNames);
            foreach ($notifyUsers as $userId) {
                if ($userId == auth()->id()) continue;
                DB::table('notifications')->insert([
                    'user_id' => $userId,
                    'task_id' => $task_id,
                    'type' => 'bidding_completed',
                    'message' => "Bidding untuk task {$task->task_number} - {$task->title} di outlet {$task->nama_outlet} telah selesai. Pemenang bidding: {$supplierList}. Silakan review dan approve PO yang telah dibuat.",
                    'url' => '/maintenance-order/' . $task_id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Log ke maintenance_activity_logs
            DB::table('maintenance_activity_logs')->insert([
                'task_id' => $task_id,
                'user_id' => auth()->id(),
                'activity_type' => 'BIDDING_COMPLETED',
                'description' => "Bidding selesai. Pemenang: {$supplierList}",
                'created_at' => now()
            ]);

            DB::commit();
            return response()->json(['success' => true, 'po_ids' => $createdPOs]);

        } catch (\Exception $e) {
            \Log::error('Gagal membuat PO dari bidding', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat PO: ' . $e->getMessage()], 500);
        }
    }

    public function getPrPoCoverage($taskId)
    {
        $prs = \DB::table('maintenance_purchase_requisitions')
            ->where('task_id', $taskId)
            ->get();
        $result = [];
        foreach ($prs as $pr) {
            $totalItems = \DB::table('maintenance_purchase_requisition_items')->where('pr_id', $pr->id)->count();
            $poItems = \DB::table('maintenance_purchase_order_items')->where('pr_id', $pr->id)->count();
            $result[] = [
                'pr_id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'status' => $pr->status,
                'total_items' => $totalItems,
                'po_items' => $poItems,
                'all_poed' => $totalItems > 0 && $totalItems == $poItems
            ];
        }
        return response()->json($result);
    }

    public function getBiddingHistory(Request $request)
    {
        $task_id = $request->input('task_id');
        
        $history = DB::table('maintenance_bidding_history as bh')
            ->join('maintenance_purchase_requisition_items as pri', 'bh.pr_item_id', '=', 'pri.id')
            ->join('suppliers as s', 'bh.supplier_id', '=', 's.id')
            ->join('users as u', 'bh.created_by', '=', 'u.id')
            ->leftJoin('units as un', 'pri.unit_id', '=', 'un.id')
            ->where('bh.task_id', $task_id)
            ->select(
                'bh.id',
                'bh.pr_item_id',
                'bh.supplier_id',
                'bh.price',
                'bh.status',
                'bh.created_at',
                'pri.item_name',
                'pri.quantity',
                's.name as supplier_name',
                'u.nama_lengkap as created_by_name',
                'un.name as unit_name'
            )
            ->orderBy('bh.created_at', 'desc')
            ->get();

        return response()->json($history);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $task = DB::table('maintenance_tasks')->where('id', $id)->first();
            if (!$task) {
                return response()->json(['success' => false, 'message' => 'Task tidak ditemukan'], 404);
            }

            // Hapus evidence dan turunannya
            $evidences = DB::table('maintenance_evidence')->where('task_id', $id)->get();
            foreach ($evidences as $evidence) {
                DB::table('maintenance_evidence_photos')->where('evidence_id', $evidence->id)->delete();
                DB::table('maintenance_evidence_videos')->where('evidence_id', $evidence->id)->delete();
            }
            DB::table('maintenance_evidence')->where('task_id', $id)->delete();

            // Hapus data terkait lain
            DB::table('maintenance_activity_logs')->where('task_id', $id)->delete();
            DB::table('maintenance_members')->where('task_id', $id)->delete();
            DB::table('maintenance_comments')->where('task_id', $id)->delete();
            DB::table('maintenance_media')->where('task_id', $id)->delete();
            DB::table('maintenance_documents')->where('task_id', $id)->delete();
            DB::table('maintenance_bidding_history')->where('task_id', $id)->delete();
            DB::table('notifications')->where('task_id', $id)->delete();

            // Hapus PR & itemnya
            $prs = DB::table('maintenance_purchase_requisitions')->where('task_id', $id)->get();
            foreach ($prs as $pr) {
                DB::table('maintenance_purchase_requisition_items')->where('pr_id', $pr->id)->delete();
            }
            DB::table('maintenance_purchase_requisitions')->where('task_id', $id)->delete();

            // Hapus PO & itemnya
            $pos = DB::table('maintenance_purchase_orders')->where('task_id', $id)->get();
            foreach ($pos as $po) {
                DB::table('maintenance_purchase_order_items')->where('po_id', $po->id)->delete();
            }
            DB::table('maintenance_purchase_orders')->where('task_id', $id)->delete();

            // Hapus retail jika ada
            if (DB::getSchemaBuilder()->hasTable('retail')) {
                DB::table('retail')->where('task_id', $id)->delete();
            }

            // Hapus task terakhir
            DB::table('maintenance_tasks')->where('id', $id)->delete();

            // Notifikasi ke member & commenter
            $taskMembers = DB::table('maintenance_members')->where('task_id', $id)->pluck('user_id');
            $commentUsers = DB::table('maintenance_comments')->where('task_id', $id)->pluck('user_id');
            $notifyUsers = $taskMembers->merge($commentUsers)->unique();
            $user = auth()->user();
            foreach ($notifyUsers as $userId) {
                DB::table('notifications')->insert([
                    'user_id' => $userId,
                    'task_id' => $id,
                    'type' => 'task_deleted',
                    'message' => "Task {$task->task_number} - {$task->title} telah dihapus oleh {$user->nama_lengkap}",
                    'url' => '/maintenance-order',
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Activity log
            DB::table('maintenance_activity_logs')->insert([
                'task_id' => $id,
                'user_id' => auth()->id(),
                'activity_type' => 'TASK_DELETED',
                'description' => 'Task dihapus',
                'created_at' => now()
            ]);

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function schedule(Request $request)
    {
        try {
            $tasks = \App\Models\MaintenanceTask::where('status', '!=', 'DONE')
                ->get()
                ->map(function($task) {
                    $members = \DB::table('maintenance_members')
                        ->join('users', 'maintenance_members.user_id', '=', 'users.id')
                        ->where('maintenance_members.task_id', $task->id)
                        ->where('maintenance_members.role', 'ASSIGNEE')
                        ->select('users.nama_lengkap')
                        ->get();
                    
                    $end = $task->due_date;
                    if ($end) {
                        // FullCalendar end exclusive, tambah 1 hari
                        $end = \Carbon\Carbon::parse($end)->addDay()->toDateString();
                    } else {
                        $end = $task->created_at->toDateString();
                    }
                    if (now()->gt($task->due_date) && $task->status != 'DONE') {
                        $end = now()->addDay()->toDateString();
                    }
                    
                    $outlet = \DB::table('tbl_data_outlet')
                        ->where('id_outlet', $task->id_outlet)
                        ->select('nama_outlet')
                        ->first();
                    
                    $label = \DB::table('maintenance_labels')
                        ->where('id', $task->label_id)
                        ->select('name', 'color')
                        ->first();
                    
                    $title = $task->title . ' - ' . ($outlet ? $outlet->nama_outlet : 'Unknown Outlet');
                    
                    $color = match($task->status) {
                        'OVERDUE' => '#f87171', // red
                        'IN_PROGRESS' => '#2563eb', // blue
                        'IN_REVIEW' => '#fbbf24', // yellow
                        default => '#4ade80', // green
                    };
                    
                    return [
                        'id' => $task->id,
                        'title' => $title,
                        'start' => $task->created_at->toDateString(),
                        'end' => $end,
                        'assignees' => $members->pluck('nama_lengkap')->join(', '),
                        'status' => $task->status,
                        'color' => $color,
                        'label' => $label ? $label->name : null,
                        'label_color' => $label ? $label->color : null
                    ];
                })->toArray();
                
            return response()->json($tasks);
        } catch (\Exception $e) {
            \Log::error('Error in schedule endpoint: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
} 