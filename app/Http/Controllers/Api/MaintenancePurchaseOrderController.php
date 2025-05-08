<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceTask;
use App\Models\MaintenancePurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenancePurchaseOrderController extends Controller
{
    public function index($taskId)
    {
        $task = MaintenanceTask::findOrFail($taskId);
        
        $purchaseOrders = MaintenancePurchaseOrder::with(['items', 'supplier'])
            ->where('task_id', $taskId)
            ->get();

        return response()->json($purchaseOrders);
    }

    public function store(Request $request, $taskId)
    {
        $task = MaintenanceTask::findOrFail($taskId);
        
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array',
            'items.*.item_name' => 'required|string',
            'items.*.description' => 'nullable|string',
            'items.*.specifications' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.supplier_price' => 'required|numeric|min:0',
            'items.*.pr_id' => 'required|exists:maintenance_purchase_requisitions,id',
            'items.*.pr_item_id' => 'required|exists:maintenance_purchase_requisition_items,id',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Generate PO number
            $poNumber = 'PO-' . date('Ymd') . '-' . str_pad(MaintenancePurchaseOrder::count() + 1, 4, '0', STR_PAD_LEFT);

            // Create PO
            $po = MaintenancePurchaseOrder::create([
                'po_number' => $poNumber,
                'task_id' => $taskId,
                'supplier_id' => $validated['supplier_id'],
                'status' => 'DRAFT',
                'total_amount' => 0, // Will be calculated from items
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Create PO items and calculate total
            $totalAmount = 0;
            $prIds = [];

            foreach ($validated['items'] as $item) {
                $subtotal = $item['quantity'] * $item['supplier_price'];
                $totalAmount += $subtotal;

                $po->items()->create([
                    'supplier_id' => $validated['supplier_id'],
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'specifications' => $item['specifications'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'],
                    'price' => $item['price'],
                    'supplier_price' => $item['supplier_price'],
                    'subtotal' => $subtotal,
                ]);

                // Collect unique PR IDs
                $prIds[$item['pr_id']] = true;
            }

            // Update PO total amount
            $po->update(['total_amount' => $totalAmount]);

            // Update PR status to 'PO'
            DB::table('maintenance_purchase_requisitions')
                ->whereIn('id', array_keys($prIds))
                ->update(['status' => 'PO', 'updated_at' => now()]);

            DB::commit();
            return response()->json($po->load('items', 'supplier'), 201);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function show($taskId, $poId)
    {
        try {
            $po = MaintenancePurchaseOrder::with([
                'items.unit',
                'supplier',
                'maintenanceTask.outlet',
                'createdBy'
            ])
            ->where('task_id', $taskId)
            ->findOrFail($poId);

            // Add approval info
            $po->gm_finance_approved_at = $po->gm_finance_approval_date;
            $po->managing_director_approved_at = $po->managing_director_approval_date;
            $po->president_director_approved_at = $po->president_director_approval_date;

            return response()->json($po);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch PO details: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $taskId, $poId)
    {
        $po = MaintenancePurchaseOrder::where('task_id', $taskId)
            ->findOrFail($poId);

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array',
            'items.*.id' => 'nullable|exists:maintenance_purchase_order_items,id',
            'items.*.item_name' => 'required|string',
            'items.*.description' => 'nullable|string',
            'items.*.specifications' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.supplier_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Update PO
        $po->update([
            'supplier_id' => $validated['supplier_id'],
            'notes' => $validated['notes'] ?? null,
        ]);

        // Update or create items and calculate total
        $totalAmount = 0;
        foreach ($validated['items'] as $item) {
            $subtotal = $item['quantity'] * $item['price'];
            $totalAmount += $subtotal;

            if (isset($item['id'])) {
                $po->items()->where('id', $item['id'])->update([
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'specifications' => $item['specifications'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'],
                    'price' => $item['price'],
                    'supplier_price' => $item['supplier_price'],
                    'subtotal' => $subtotal,
                ]);
            } else {
                $po->items()->create([
                    'supplier_id' => $validated['supplier_id'],
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'specifications' => $item['specifications'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'],
                    'price' => $item['price'],
                    'supplier_price' => $item['supplier_price'],
                    'subtotal' => $subtotal,
                ]);
            }
        }

        // Update PO total amount
        $po->update(['total_amount' => $totalAmount]);

        return response()->json($po->load('items', 'supplier'));
    }

    public function destroy($taskId, $poId)
    {
        $po = MaintenancePurchaseOrder::where('task_id', $taskId)
            ->findOrFail($poId);

        $po->delete();

        return response()->json(null, 204);
    }

    public function approve(Request $request, $taskId, $poId)
    {
        $request->validate([
            'level' => 'required|in:purchasing_manager,gm_finance,coo,ceo',
            'status' => 'required|in:APPROVED,REJECTED',
            'notes' => 'nullable|string',
        ]);

        $po = MaintenancePurchaseOrder::with(['maintenanceTask.outlet'])->where('task_id', $taskId)->findOrFail($poId);
        $user = auth()->user();
        $level = $request->level;
        $status = $request->status;
        $notes = $request->notes;

        // Role check
        $canApprove = false;
        if ($user->status === 'A') {
            if (
                ($level === 'purchasing_manager' && $user->id_jabatan == 168) ||
                ($level === 'gm_finance' && $user->id_jabatan == 152) ||
                ($level === 'coo' && $user->id_jabatan == 151) ||
                ($level === 'ceo' && $user->id_jabatan == 149) ||
                ($user->id_jabatan == 217) || // sekretaris
                ($user->id_role == '5af56935b011a') // superadmin
            ) {
                $canApprove = true;
            }
        }
        if (!$canApprove) {
            return response()->json(['message' => 'You do not have permission to approve at this level'], 403);
        }

        // Approval flow check
        if ($level === 'gm_finance' && $po->purchasing_manager_approval !== 'APPROVED') {
            return response()->json(['message' => 'Purchasing Manager approval is required first'], 400);
        }
        if ($level === 'coo' && $po->gm_finance_approval !== 'APPROVED') {
            return response()->json(['message' => 'GM Finance approval is required first'], 400);
        }
        if ($level === 'ceo') {
            if ($po->coo_approval !== 'APPROVED') {
                return response()->json(['message' => 'COO approval is required first'], 400);
            }
            if ($po->total_amount < 5000000) {
                return response()->json(['message' => 'CEO approval is not required for PO < 5.000.000'], 400);
            }
        }

        // Update approval status & notes
        $approvalFields = [
            $level . '_approval' => $status,
            $level . '_approval_date' => now(),
            $level . '_approval_by' => $user->id,
            $level . '_approval_notes' => $notes,
        ];

        // Jika REJECTED, set status PO jadi REJECTED
        if ($status === 'REJECTED') {
            $approvalFields['status'] = 'REJECTED';
        } else {
            // Jika CEO, atau COO (dan PO < 5jt), set status APPROVED
            if ($level === 'ceo' || ($level === 'coo' && $po->total_amount < 5000000)) {
                $approvalFields['status'] = 'APPROVED';
            }
        }
        $po->update($approvalFields);

        // Jika COO approve dan PO < 5jt, auto-approve CEO
        if ($level === 'coo' && $status === 'APPROVED' && $po->total_amount < 5000000) {
            $po->update([
                'ceo_approval' => 'APPROVED',
                'ceo_approval_date' => now(),
                'ceo_approval_by' => $user->id,
                'ceo_approval_notes' => '[AUTO APPROVED]'
            ]);
        }

        // Notifikasi
        $task = $po->maintenanceTask;
        $outlet = $task->outlet->nama_outlet ?? '-';
        $taskNumber = $task->task_number ?? '-';
        $taskTitle = $task->title ?? '-';
        $poNumber = $po->po_number;
        $userName = $user->nama_lengkap ?? $user->name ?? '-';
        $notifUrl = '/maintenance-order/' . $task->id;

        // Ambil seluruh member dan commenter
        $memberIds = DB::table('maintenance_members')->where('task_id', $task->id)->pluck('user_id')->toArray();
        $commenterIds = DB::table('maintenance_comments')->where('task_id', $task->id)->pluck('user_id')->toArray();
        $allNotifUserIds = array_unique(array_merge($memberIds, $commenterIds));

        // Helper untuk insert notifikasi
        $insertNotif = function($userIds, $type, $message) use ($task, $notifUrl) {
            $now = now();
            $rows = [];
            foreach ($userIds as $uid) {
                $rows[] = [
                    'user_id' => $uid,
                    'task_id' => $task->id,
                    'type' => $type,
                    'message' => $message,
                    'url' => $notifUrl,
                    'is_read' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows) DB::table('notifications')->insert($rows);
        };

        // Notifikasi sesuai flow
        if ($level === 'purchasing_manager') {
            // Setelah approve oleh purchasing manager
            if ($status === 'APPROVED') {
                $insertNotif($allNotifUserIds, 'po_approved', "PO $poNumber untuk task $taskNumber - $taskTitle di outlet $outlet telah di-approve oleh $userName");
                // Notif ke GM Finance
                $gmFinanceIds = DB::table('users')->where('id_jabatan', 152)->where('status', 'A')->pluck('id')->toArray();
                $insertNotif($gmFinanceIds, 'po_approval_request', "PO $poNumber untuk task $taskNumber - $taskTitle di outlet $outlet menunggu approval GM Finance");
            }
        } elseif ($level === 'gm_finance') {
            if ($status === 'APPROVED') {
                $insertNotif($allNotifUserIds, 'po_approved', "PO $poNumber untuk task $taskNumber - $taskTitle di outlet $outlet telah di-approve oleh $userName");
                // Notif ke COO & Sekretaris
                $cooIds = DB::table('users')->where('id_jabatan', 151)->where('status', 'A')->pluck('id')->toArray();
                $sekretarisIds = DB::table('users')->where('id_jabatan', 217)->where('status', 'A')->pluck('id')->toArray();
                $insertNotif(array_merge($cooIds, $sekretarisIds), 'po_approval_request', "PO $poNumber untuk task $taskNumber - $taskTitle di outlet $outlet menunggu approval COO");
            }
            if ($status === 'APPROVED') {
                $insertNotif($allNotifUserIds, 'po_approved', "PO $poNumber untuk task $taskNumber - $taskTitle di outlet $outlet telah di-approve oleh $userName");
                if ($po->total_amount >= 5000000) {
                    // Notif ke CEO
                    $ceoIds = DB::table('users')->where('id_jabatan', 149)->where('status', 'A')->pluck('id')->toArray();
                    $insertNotif($ceoIds, 'po_approval_request', "PO $poNumber untuk task $taskNumber - $taskTitle di outlet $outlet menunggu approval CEO");
                }
            }
        } elseif ($level === 'coo') {
            if ($status === 'APPROVED') {
                $insertNotif($allNotifUserIds, 'po_approved', "PO $poNumber untuk task $taskNumber - $taskTitle di outlet $outlet telah di-approve oleh $userName");
                if ($po->total_amount >= 5000000) {
                    // Notif ke CEO
                    $ceoIds = DB::table('users')->where('id_jabatan', 149)->where('status', 'A')->pluck('id')->toArray();
                    $insertNotif($ceoIds, 'po_approval_request', "PO $poNumber untuk task $taskNumber - $taskTitle di outlet $outlet menunggu approval CEO");
                }
            }
        } elseif ($level === 'ceo') {
            if ($status === 'APPROVED') {
                $insertNotif($allNotifUserIds, 'po_approved', "PO $poNumber untuk task $taskNumber - $taskTitle di outlet $outlet telah di-approve oleh $userName");
            }
        }

        // Jika REJECTED, notif ke semua member & commenter
        if ($status === 'REJECTED') {
            $insertNotif($allNotifUserIds, 'po_rejected', "PO $poNumber untuk task $taskNumber - $taskTitle di outlet $outlet telah DITOLAK oleh $userName");
        }

        // Notifikasi ke Finance Manager jika PO sudah di-approve
        if ($status === 'APPROVED') {
            $financeManagerIds = DB::table('users')->where('id_jabatan', 160)->where('status', 'A')->pluck('id')->toArray();
            $notifMsg = "PO $poNumber untuk task $taskNumber - $taskTitle di outlet $outlet telah di-approve dan siap untuk payment.";
            foreach ($financeManagerIds as $fmId) {
                DB::table('notifications')->insert([
                    'user_id' => $fmId,
                    'task_id' => $po->task_id,
                    'type' => 'po_approved_for_payment',
                    'message' => $notifMsg,
                    'url' => '/maintenance-order/' . $po->task_id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        return response()->json([
            'message' => 'Purchase order approval status updated successfully',
            'po' => $po->fresh()
        ]);
    }
} 