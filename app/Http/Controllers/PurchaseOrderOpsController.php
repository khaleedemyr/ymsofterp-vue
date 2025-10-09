<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\PurchaseOrderOps;
use App\Models\PurchaseOrderOpsItem;
use App\Models\PurchaseOrderOpsApprovalFlow;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PurchaseOrderOpsController extends Controller
{
    public function index()
    {
        $query = DB::table('purchase_order_ops as po')
            ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('users as creator', 'po.created_by', '=', 'creator.id')
            ->leftJoin('users as pm', 'po.purchasing_manager_approved_by', '=', 'pm.id')
            ->leftJoin('users as gm', 'po.gm_finance_approved_by', '=', 'gm.id')
            ->leftJoin('purchase_requisitions as pr', 'po.source_id', '=', 'pr.id')
            ->select(
                'po.*',
                's.name as supplier_name',
                'creator.nama_lengkap as creator_name',
                'pm.nama_lengkap as purchasing_manager_name',
                'gm.nama_lengkap as gm_finance_name',
                'pr.pr_number as source_pr_number'
            )
            ->orderBy('po.created_at', 'desc');

        if (request('search')) {
            $search = request('search');
            $query->where(function($q) use ($search) {
                $q->where('po.number', 'like', '%' . $search . '%')
                  ->orWhere('s.name', 'like', '%' . $search . '%')
                  ->orWhere('pr.pr_number', 'like', '%' . $search . '%');
            });
        }
        if (request('status')) {
            $query->where('po.status', request('status'));
        }
        if (request('from')) {
            $query->whereDate('po.date', '>=', request('from'));
        }
        if (request('to')) {
            $query->whereDate('po.date', '<=', request('to'));
        }

        $perPage = request('perPage', 10);
        $purchaseOrders = $query->paginate($perPage)->withQueryString();
        $purchaseOrders->getCollection()->transform(function ($po) {
            // Convert to object if it's an array
            if (is_array($po)) {
                $po = (object) $po;
            }
            
            // Format supplier data
            $po->supplier = (object) [
                'name' => $po->supplier_name
            ];
            
            // Format creator data
            $po->creator = (object) [
                'nama_lengkap' => $po->creator_name
            ];
            
            // Format purchasing manager data
            $po->purchasing_manager = $po->purchasing_manager_name ? (object) [
                'nama_lengkap' => $po->purchasing_manager_name
            ] : null;
            
            // Format GM finance data
            $po->gm_finance = $po->gm_finance_name ? (object) [
                'nama_lengkap' => $po->gm_finance_name
            ] : null;

            // Source PR information
            $po->source_pr_number = $po->source_pr_number;
            
            // Debug logging for source PR
            \Log::info('PO Source PR Debug:', [
                'po_id' => $po->id,
                'po_number' => $po->number,
                'source_id' => $po->source_id,
                'source_type' => $po->source_type,
                'source_pr_number' => $po->source_pr_number
            ]);
            
            return $po;
        });

        return inertia('PurchaseOrderOps/Index', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => request()->only(['search', 'status', 'from', 'to', 'perPage']),
            'user' => [
                'id' => auth()->user()->id,
                'id_jabatan' => auth()->user()->id_jabatan,
                'id_role' => auth()->user()->id_role,
                'status' => auth()->user()->status,
                'nama_lengkap' => auth()->user()->nama_lengkap,
            ],
        ]);
    }

    public function create()
    {
        return inertia('PurchaseOrderOps/Create');
    }

    public function getAvailablePR()
    {
        // Get all PR item IDs that are already in PO
        $poPrItemIds = DB::table('purchase_order_ops_items')
            ->whereNotNull('pr_ops_item_id')
            ->pluck('pr_ops_item_id')
            ->toArray();

        // Get PRs that have been fully processed (all items are in PO)
        $fullyProcessedPRs = DB::table('purchase_requisitions as pr')
            ->join('purchase_requisition_items as items', 'pr.id', '=', 'items.purchase_requisition_id')
            ->leftJoin('purchase_order_ops_items as po_items', 'items.id', '=', 'po_items.pr_ops_item_id')
            ->where('pr.status', 'APPROVED')
            ->groupBy('pr.id')
            ->havingRaw('COUNT(items.id) = COUNT(po_items.id)')
            ->pluck('pr.id')
            ->toArray();

        // Get available PRs that are approved and not fully processed
        $prs = DB::table('purchase_requisitions as pr')
            ->join('purchase_requisition_items as items', 'pr.id', '=', 'items.purchase_requisition_id')
            ->leftJoin('tbl_data_divisi as d', 'pr.division_id', '=', 'd.id')
            ->where('pr.status', 'APPROVED') // Only approved PRs
            ->whereNotIn('pr.id', $fullyProcessedPRs) // Exclude fully processed PRs
            ->whereNotIn('items.id', $poPrItemIds) // Exclude individual items already in PO
            ->select(
                'pr.id',
                'pr.pr_number',
                'pr.date',
                'pr.division_id',
                'pr.title',
                'pr.description',
                'pr.amount',
                'items.id as item_id',
                'items.item_name',
                'items.qty',
                'items.unit',
                'items.unit_price',
                'items.subtotal',
                'd.nama_divisi as division_name'
            )
            ->get()
            ->groupBy('id')
            ->map(function ($group) {
                $first = $group->first();
                
                // Get attachments for this PR
                $attachments = DB::table('purchase_requisition_attachments as pra')
                    ->leftJoin('users as u', 'pra.uploaded_by', '=', 'u.id')
                    ->where('pra.purchase_requisition_id', $first->id)
                    ->select(
                        'pra.id',
                        'pra.file_name',
                        'pra.file_path',
                        'pra.file_size',
                        'pra.mime_type',
                        'pra.created_at',
                        'u.nama_lengkap as uploader_name'
                    )
                    ->get()
                    ->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'file_name' => $attachment->file_name,
                            'file_path' => $attachment->file_path,
                            'file_size' => $attachment->file_size,
                            'mime_type' => $attachment->mime_type,
                            'created_at' => $attachment->created_at,
                            'uploader' => [
                                'nama_lengkap' => $attachment->uploader_name
                            ]
                        ];
                    });
                
                return [
                    'id' => $first->id,
                    'number' => $first->pr_number,
                    'date' => Carbon::parse($first->date)->format('d/m/Y'),
                    'division_id' => $first->division_id,
                    'division_name' => $first->division_name,
                    'title' => $first->title,
                    'description' => $first->description,
                    'amount' => $first->amount,
                    'attachments' => $attachments,
                    'items' => $group->map(function ($item) use ($first) {
                        return [
                            'id' => $item->item_id,
                            'item_name' => $item->item_name,
                            'qty' => $item->qty,
                            'unit' => $item->unit,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                            'supplier_id' => null,
                            'price' => null,
                            'pr_id' => $first->id, // Add PR ID to each item
                        ];
                    })->values(),
                ];
            })
            ->values();

        return response()->json($prs);
    }

    public function generatePO(Request $request)
    {
        // Debug logging
        \Log::info('=== START GENERATE PO OPS ===');
        \Log::info('Generate PO Request Data:', [
            'items_by_supplier' => $request->items_by_supplier,
            'ppn_enabled' => $request->ppn_enabled,
            'notes' => $request->notes
        ]);

        // Validate the request data - items_by_supplier is {itemId: [rows]}
        $request->validate([
            'items_by_supplier' => 'required|array',
            'items_by_supplier.*' => 'required|array',
            'items_by_supplier.*.*.supplier_id' => 'required|exists:suppliers,id',
            'items_by_supplier.*.*.qty' => 'required|numeric|min:0',
            'items_by_supplier.*.*.price' => 'required|numeric|min:0',
            'ppn_enabled' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Collect all PR IDs that will be processed
            $prIds = collect($request->items_by_supplier)
                ->flatMap(function ($items) {
                    return collect($items)
                        ->pluck('pr_id')
                        ->filter();
                })
                ->unique()
                ->values();

            \Log::info('PR IDs collected:', ['pr_ids' => $prIds->toArray()]);

            // Group items by supplier - frontend sends {itemId: [rows]}, we need to group by supplier
            $itemsBySupplier = [];
            foreach ($request->items_by_supplier as $itemId => $rows) {
                foreach ($rows as $row) {
                    if ($row['supplier_id'] && $row['qty'] && $row['price']) {
                        if (!isset($itemsBySupplier[$row['supplier_id']])) {
                            $itemsBySupplier[$row['supplier_id']] = [];
                        }
                        $itemsBySupplier[$row['supplier_id']][] = [
                            'id' => $row['id'],  // Use row's id, not the key
                            'supplier_id' => $row['supplier_id'],
                            'qty' => $row['qty'],
                            'price' => $row['price'],
                            'pr_id' => $row['pr_id'] ?? null,
                        ];
                    }
                }
            }
            
            \Log::info('Items grouped by supplier:', ['items_by_supplier' => $itemsBySupplier]);

            $createdPOs = [];

            foreach ($itemsBySupplier as $supplierId => $items) {
                if (empty($items)) {
                    \Log::info('Skipping empty supplier items', ['supplier_id' => $supplierId]);
                    continue;
                }

                \Log::info('Processing supplier:', ['supplier_id' => $supplierId, 'items_count' => count($items)]);

                // Generate PO number
                $poNumber = $this->generatePONumber();

                // Calculate subtotal for this PO
                $subtotal = collect($items)->sum(function ($item) {
                    return $item['price'] * $item['qty'];
                });

                // Calculate PPN if enabled
                $ppnAmount = 0;
                $grandTotal = $subtotal;
                if ($request->ppn_enabled) {
                    $ppnAmount = $subtotal * 0.11; // 11% PPN
                    $grandTotal = $subtotal + $ppnAmount;
                }

                // Get arrival_date from items (use the earliest arrival date)
                $arrivalDates = collect($items)->pluck('arrival_date')->filter()->map(function($date) {
                    return Carbon::parse($date);
                })->sort();
                
                $poArrivalDate = $arrivalDates->isNotEmpty() ? $arrivalDates->first()->format('Y-m-d') : null;

                // Create PO
                $poData = [
                    'number' => $poNumber,
                    'date' => now(),
                    'supplier_id' => $supplierId,
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                    'notes' => $request->notes,
                    'arrival_date' => $poArrivalDate,
                    'ppn_enabled' => $request->ppn_enabled ?? false,
                    'ppn_amount' => $ppnAmount,
                    'subtotal' => $subtotal,
                    'grand_total' => $grandTotal,
                    'source_type' => 'purchase_requisition_ops',
                    'source_id' => $prIds->first(),
                ];

                $po = PurchaseOrderOps::create($poData);
                $createdPOs[] = $po;

                // Get PR items for this supplier
                $prItemIds = collect($items)
                    ->pluck('id')  // Frontend sends 'id', not 'item_id'
                    ->filter();
                
                \Log::info('PR Item IDs for supplier ' . $supplierId . ':', ['pr_item_ids' => $prItemIds->toArray()]);
                
                $prItems = collect();
                if ($prItemIds->isNotEmpty()) {
                    $prItems = PurchaseRequisitionItem::whereIn('id', $prItemIds)->get();
                }

                foreach ($items as $itemData) {
                    \Log::info('Processing item data:', ['item_data' => $itemData]);
                    
                    // Get PR item data
                    $prItem = $prItems->firstWhere('id', $itemData['id']);
                    if (!$prItem) {
                        \Log::error('PR item not found:', ['item_id' => $itemData['id']]);
                        continue;
                    }
                    
                    $quantity = floatval($itemData['qty']);
                    $price = floatval($itemData['price']);
                    $total = round($quantity * $price, 2);

                    $poItemData = [
                        'purchase_order_ops_id' => $po->id,
                        'item_name' => $prItem->item_name,
                        'quantity' => $quantity,
                        'unit' => $prItem->unit,
                        'price' => $price,
                        'total' => $total,
                        'created_by' => auth()->id(),
                        'arrival_date' => $prItem->arrival_date,
                        'pr_ops_item_id' => $prItem->id,
                        'source_type' => 'purchase_requisition_ops',
                        'source_id' => $prItem->purchase_requisition_id,
                    ];

                    \Log::info('Creating PO item with data:', ['po_item_data' => $poItemData]);
                    PurchaseOrderOpsItem::create($poItemData);
                }

                // Update PR status to 'PROCESSED' (since it's now in PO)
                foreach ($prIds as $prId) {
                    PurchaseRequisition::where('id', $prId)->update(['status' => 'PROCESSED']);
                }

                // Log activity
                ActivityLog::create([
                    'user_id' => Auth::id(),
                    'activity_type' => 'create',
                    'module' => 'purchase_order_ops',
                    'description' => 'Create PO Ops: ' . $po->number,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => null,
                    'new_data' => $po->toArray(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PO berhasil dibuat',
                'data' => $createdPOs
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('PO Ops Generation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $po = PurchaseOrderOps::with([
            'supplier',
            'creator',
            'items',
            'purchasing_manager',
            'gm_finance',
            'source_pr.attachments.uploader',
            'approvalFlows.approver',
            'attachments.uploader'
        ])->findOrFail($id);

        // Debug logging for items
        \Log::info('PO Show Debug:', [
            'po_id' => $po->id,
            'po_number' => $po->number,
            'items_count' => $po->items->count(),
            'items' => $po->items->toArray()
        ]);

        $user = auth()->user();
        $userData = [
            'id' => $user->id,
            'id_jabatan' => $user->id_jabatan,
            'id_role' => $user->id_role,
            'status' => $user->status,
            'nama_lengkap' => $user->nama_lengkap,
        ];

        // Check if this is an API request (for modal)
        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json([
                'success' => true,
                'po' => $po,
                'user' => $userData
            ]);
        }

        return inertia('PurchaseOrderOps/Show', [
            'po' => $po,
            'user' => $userData
        ]);
    }

    public function edit($id)
    {
        $po = PurchaseOrderOps::with([
            'supplier',
            'items'
        ])->findOrFail($id);

        // Only allow editing if status is draft or approved
        if (!in_array($po->status, ['draft', 'approved'])) {
            return redirect()->route('po-ops.show', $po->id)
                ->with('error', 'PO tidak dapat diedit karena status sudah received');
        }

        // Get suppliers
        $suppliers = \App\Models\Supplier::whereIn('status', ['A', 'active'])->get(['id', 'name']);

        return inertia('PurchaseOrderOps/Edit', [
            'po' => $po,
            'suppliers' => $suppliers,
        ]);
    }

    public function update(Request $request, $id)
    {
        $po = PurchaseOrderOps::findOrFail($id);

        // Only allow updating if status is draft or approved
        if (!in_array($po->status, ['draft', 'approved'])) {
            return response()->json([
                'success' => false,
                'message' => 'PO tidak dapat diupdate karena status sudah received'
            ], 422);
        }

        $request->validate([
            'notes' => 'nullable|string',
            'ppn_enabled' => 'boolean',
            'items' => 'required|array',
            'items.*.id' => 'nullable|exists:purchase_order_ops_items,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'new_items' => 'nullable|array',
            'new_items.*.item_name' => 'required|string',
            'new_items.*.quantity' => 'required|numeric|min:0',
            'new_items.*.unit' => 'required|string',
            'new_items.*.price' => 'required|numeric|min:0',
            'deleted_items' => 'nullable|array',
            'deleted_items.*' => 'exists:purchase_order_ops_items,id',
        ]);

        try {
            DB::beginTransaction();

            // Handle deleted items
            if ($request->deleted_items) {
                PurchaseOrderOpsItem::whereIn('id', $request->deleted_items)->delete();
            }

            // Update existing items
            foreach ($request->items as $item) {
                if ($item['id']) {
                    PurchaseOrderOpsItem::where('id', $item['id'])
                        ->update([
                            'price' => $item['price'],
                            'total' => $item['total'],
                        ]);
                }
            }

            // Add new items
            if ($request->new_items) {
                foreach ($request->new_items as $newItem) {
                    PurchaseOrderOpsItem::create([
                        'purchase_order_ops_id' => $po->id,
                        'item_name' => $newItem['item_name'],
                        'quantity' => $newItem['quantity'],
                        'unit' => $newItem['unit'],
                        'price' => $newItem['price'],
                        'total' => round(floatval($newItem['quantity']) * floatval($newItem['price']), 2),
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            // Recalculate totals
            $allItems = PurchaseOrderOpsItem::where('purchase_order_ops_id', $po->id)->get();
            $subtotal = $allItems->sum('total');
            
            // Calculate PPN if enabled
            $ppnAmount = 0;
            $grandTotal = $subtotal;
            if ($request->ppn_enabled) {
                $ppnAmount = $subtotal * 0.11; // 11% PPN
                $grandTotal = $subtotal + $ppnAmount;
            }

            // Update PO notes and PPN
            $po->update([
                'notes' => $request->notes,
                'ppn_enabled' => $request->ppn_enabled ?? false,
                'ppn_amount' => $ppnAmount,
                'subtotal' => $subtotal,
                'grand_total' => $grandTotal,
            ]);

            // Log activity
            ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'update',
                'module' => 'purchase_order_ops',
                'description' => 'Update PO Ops: ' . $po->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $po->fresh()->toArray(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PO berhasil diupdate'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate PO: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $po = PurchaseOrderOps::with('items')->findOrFail($id);
        if (!in_array($po->status, ['draft', 'approved'])) {
            return back()->with('error', 'PO hanya bisa dihapus jika status draft atau approved');
        }
        try {
            DB::beginTransaction();
            
            // Update related Purchase Requisition Ops status back to APPROVED
            if ($po->source_type === 'purchase_requisition_ops' && $po->source_id) {
                PurchaseRequisition::where('id', $po->source_id)->update(['status' => 'APPROVED']);
            }
            
            // Delete items
            foreach ($po->items as $item) {
                $item->delete();
            }
            $po->delete();
            // Log activity
            ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'delete',
                'module' => 'purchase_order_ops',
                'description' => 'Hapus PO Ops: ' . $po->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => $po->toArray(),
                'new_data' => null,
            ]);
            DB::commit();
            return redirect()->route('po-ops.index')->with('success', 'PO berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus PO: ' . $e->getMessage());
        }
    }

    public function approvePurchasingManager(Request $request, $id)
    {
        $po = PurchaseOrderOps::findOrFail($id);
        $updateData = [
            'purchasing_manager_approved_at' => now(),
            'purchasing_manager_approved_by' => Auth::id(),
            'purchasing_manager_note' => $request->note,
        ];
        if (!$request->approved) {
            $updateData['status'] = 'rejected';
        }
        $po->update($updateData);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => $request->approved ? 'approve' : 'reject',
            'module' => 'purchase_order_ops',
            'description' => ($request->approved ? 'Approve' : 'Reject') . ' PO Ops (Purchasing Manager): ' . $po->number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $po->fresh()->toArray(),
        ]);

        if ($request->approved) {
            // Notifikasi ke GM Finance
            $gmFinances = DB::table('users')
                ->whereIn('id_jabatan', [152, 381])
                ->where('status', 'A')
                ->pluck('id');
            $this->sendNotification(
                $gmFinances,
                'po_approval',
                'Approval PO Ops',
                "PO {$po->number} menunggu approval Anda.",
                route('po-ops.show', $po->id)
            );
        }

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'PO berhasil diapprove']);
        }
        return redirect()->route('po-ops.show', $po->id);
    }

    public function approveGMFinance(Request $request, $id)
    {
        $po = PurchaseOrderOps::findOrFail($id);
        $updateData = [
            'gm_finance_approved_at' => now(),
            'gm_finance_approved_by' => Auth::id(),
            'gm_finance_note' => $request->note,
        ];
        if ($request->approved) {
            $updateData['status'] = 'approved';
        } else {
            $updateData['status'] = 'rejected';
        }
        $po->update($updateData);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => $request->approved ? 'approve' : 'reject',
            'module' => 'purchase_order_ops',
            'description' => ($request->approved ? 'Approve' : 'Reject') . ' PO Ops (GM Finance): ' . $po->number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $po->fresh()->toArray(),
        ]);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'PO berhasil diapprove']);
        }
        return redirect()->route('po-ops.show', $po->id);
    }

    public function markPrinted($id)
    {
        $po = PurchaseOrderOps::findOrFail($id);
        $po->printed_at = now();
        $po->save();
        return response()->json(['success' => true, 'printed_at' => $po->printed_at]);
    }

    // Helper untuk insert notifikasi
    private function sendNotification($userIds, $type, $title, $message, $url) {
        $now = now();
        $data = [];
        foreach ($userIds as $uid) {
            $data[] = [
                'user_id' => $uid,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('notifications')->insert($data);
    }

    private function generatePONumber()
    {
        $prefix = 'POO';
        $date = date('ym', strtotime(now()));
        $lastNumber = PurchaseOrderOps::where('number', 'like', $prefix . $date . '%')
            ->orderBy('number', 'desc')
            ->value('number');

        if ($lastNumber) {
            $lastSequence = intval(substr($lastNumber, -4));
            $sequence = $lastSequence + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get available approvers for PO Ops
     */
    public function getApprovers(Request $request)
    {
        \Log::info('getApprovers called', ['request' => $request->all()]);
        
        $search = $request->get('search', '');
        
        // Query with jabatan join
        $users = User::where('users.status', 'A')
            ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where(function($query) use ($search) {
                $query->where('users.nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%")
                      ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            })
            ->select('users.id', 'users.nama_lengkap as name', 'users.email', 'tbl_data_jabatan.nama_jabatan as jabatan')
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();
        
        \Log::info('getApprovers result', ['count' => $users->count(), 'users' => $users->toArray()]);
        
        return response()->json(['success' => true, 'users' => $users]);
    }

    /**
     * Submit PO for approval
     */
    public function submitForApproval(Request $request, $id)
    {
        $po = PurchaseOrderOps::findOrFail($id);
        
        if ($po->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'PO tidak dapat diajukan untuk approval karena status bukan draft'
            ], 400);
        }

        $request->validate([
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            // Create approval flows
            foreach ($request->approvers as $index => $approverId) {
                PurchaseOrderOpsApprovalFlow::create([
                    'purchase_order_ops_id' => $po->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            // Update PO status
            $po->update(['status' => 'submitted']);

            // Send notification to first approver
            $firstApprover = PurchaseOrderOpsApprovalFlow::where('purchase_order_ops_id', $po->id)
                ->where('approval_level', 1)
                ->with('approver')
                ->first();

            if ($firstApprover) {
                $this->sendNotification(
                    [$firstApprover->approver_id],
                    'po_approval_required',
                    'Approval PO Ops Required',
                    "PO {$po->number} menunggu approval Anda.",
                    route('po-ops.show', $po->id)
                );
            }

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'submit',
                'module' => 'purchase_order_ops',
                'description' => 'Submit PO Ops for approval: ' . $po->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $po->fresh()->toArray(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PO berhasil diajukan untuk approval'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve PO by approver
     */
    public function approve(Request $request, $id)
    {
        $po = PurchaseOrderOps::findOrFail($id);
        $approverId = Auth::id();

        // Find current approval flow for this approver
        $approvalFlow = PurchaseOrderOpsApprovalFlow::where('purchase_order_ops_id', $po->id)
            ->where('approver_id', $approverId)
            ->where('status', 'PENDING')
            ->first();

        if (!$approvalFlow) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak untuk approve PO ini'
            ], 403);
        }

        $request->validate([
            'approved' => 'required|boolean',
            'comments' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Update approval flow
            $approvalFlow->update([
                'status' => $request->approved ? 'APPROVED' : 'REJECTED',
                'approved_at' => $request->approved ? now() : null,
                'rejected_at' => !$request->approved ? now() : null,
                'comments' => $request->comments,
            ]);

            if ($request->approved) {
                // Check if this is the last approval
                $pendingApprovals = PurchaseOrderOpsApprovalFlow::where('purchase_order_ops_id', $po->id)
                    ->where('status', 'PENDING')
                    ->count();

                if ($pendingApprovals == 0) {
                    // All approvals completed
                    $po->update(['status' => 'approved']);
                    
                    // Send comprehensive completion notification to creator
                    $po = $po->load(['supplier', 'creator', 'items']);
                    $currentApprover = Auth::user();
                    $totalItems = $po->items->count();
                    $grandTotal = number_format($po->grand_total, 0, ',', '.');
                    
                    $completionMessage = "🎉 Purchase Order Ops telah disetujui sepenuhnya:\n\n";
                    $completionMessage .= "📋 Detail PO:\n";
                    $completionMessage .= "• Nomor: {$po->number}\n";
                    $completionMessage .= "• Supplier: {$po->supplier->name}\n";
                    $completionMessage .= "• Total Items: {$totalItems} item\n";
                    $completionMessage .= "• Grand Total: Rp {$grandTotal}\n\n";
                    $completionMessage .= "✅ Status: DISETUJUI\n";
                    $completionMessage .= "• Final approval oleh: {$currentApprover->nama_lengkap}\n";
                    $completionMessage .= "• Level: {$approvalFlow->approval_level}\n\n";
                    $completionMessage .= "🚀 PO siap untuk diproses lebih lanjut sesuai dengan workflow perusahaan.";
                    
                    $this->sendNotification(
                        [$po->created_by],
                        'po_approved',
                        'PO Ops Disetujui',
                        $completionMessage,
                        route('po-ops.show', $po->id)
                    );
                } else {
                    // Send notification to next approver
                    $nextApproval = PurchaseOrderOpsApprovalFlow::where('purchase_order_ops_id', $po->id)
                        ->where('status', 'PENDING')
                        ->orderBy('approval_level')
                        ->first();

                    if ($nextApproval) {
                        // Get approver details
                        $nextApprover = User::find($nextApproval->approver_id);
                        $currentApprover = Auth::user();
                        
                        // Get PO details for comprehensive message
                        $po = $po->load(['supplier', 'creator', 'items']);
                        $totalItems = $po->items->count();
                        $grandTotal = number_format($po->grand_total, 0, ',', '.');
                        
                        // Create comprehensive notification message
                        $message = "Purchase Order Ops membutuhkan approval Anda:\n\n";
                        $message .= "📋 Detail PO:\n";
                        $message .= "• Nomor: {$po->number}\n";
                        $message .= "• Supplier: {$po->supplier->name}\n";
                        $message .= "• Total Items: {$totalItems} item\n";
                        $message .= "• Grand Total: Rp {$grandTotal}\n";
                        $message .= "• Dibuat oleh: {$po->creator->nama_lengkap}\n\n";
                        $message .= "👤 Approval Flow:\n";
                        $message .= "• Level: {$nextApproval->approval_level}\n";
                        $message .= "• Approved oleh: {$currentApprover->nama_lengkap}\n";
                        $message .= "• Menunggu approval dari: {$nextApprover->nama_lengkap}\n\n";
                        $message .= "⏰ Silakan segera lakukan approval untuk melanjutkan proses pembelian.";
                        
                        $this->sendNotification(
                            [$nextApproval->approver_id],
                            'po_approval_required',
                            'Approval PO Ops Required',
                            $message,
                            route('po-ops.show', $po->id)
                        );
                    }
                }
            } else {
                // Rejected - update PO status
                $po->update(['status' => 'rejected']);
                
                // Send comprehensive rejection notification to creator
                $po = $po->load(['supplier', 'creator', 'items']);
                $currentApprover = Auth::user();
                $totalItems = $po->items->count();
                $grandTotal = number_format($po->grand_total, 0, ',', '.');
                
                $rejectionMessage = "Purchase Order Ops telah ditolak:\n\n";
                $rejectionMessage .= "📋 Detail PO:\n";
                $rejectionMessage .= "• Nomor: {$po->number}\n";
                $rejectionMessage .= "• Supplier: {$po->supplier->name}\n";
                $rejectionMessage .= "• Total Items: {$totalItems} item\n";
                $rejectionMessage .= "• Grand Total: Rp {$grandTotal}\n\n";
                $rejectionMessage .= "❌ Status: DITOLAK\n";
                $rejectionMessage .= "• Ditolak oleh: {$currentApprover->nama_lengkap}\n";
                $rejectionMessage .= "• Level: {$approvalFlow->approval_level}\n";
                if ($request->comments) {
                    $rejectionMessage .= "• Alasan: {$request->comments}\n";
                }
                $rejectionMessage .= "\n📝 Silakan periksa dan perbaiki PO sebelum mengajukan ulang.";
                
                $this->sendNotification(
                    [$po->created_by],
                    'po_rejected',
                    'PO Ops Ditolak',
                    $rejectionMessage,
                    route('po-ops.show', $po->id)
                );
            }

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => $request->approved ? 'approve' : 'reject',
                'module' => 'purchase_order_ops',
                'description' => ($request->approved ? 'Approve' : 'Reject') . ' PO Ops: ' . $po->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $po->fresh()->toArray(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $request->approved ? 'PO berhasil diapprove' : 'PO berhasil ditolak'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending approvals for current user
     */
    public function getPendingApprovals()
    {
        $userId = Auth::id();
        
        $pendingPOs = PurchaseOrderOps::whereHas('approvalFlows', function($query) use ($userId) {
            $query->where('approver_id', $userId)
                  ->where('status', 'PENDING');
        })
        ->with([
            'supplier', 
            'creator', 
            'source_pr.attachments.uploader',
            'attachments.uploader',
            'approvalFlows' => function($query) use ($userId) {
                $query->where('approver_id', $userId);
            }
        ])
        ->get();

        return response()->json(['success' => true, 'data' => $pendingPOs]);
    }

    /**
     * Upload attachment for Purchase Order Ops
     */
    public function uploadAttachment(Request $request, PurchaseOrderOps $purchaseOrderOps)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . $originalName;
            $filePath = $file->storeAs('purchase_order_ops/attachments', $fileName, 'public');
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            $attachment = \App\Models\PurchaseOrderOpsAttachment::create([
                'purchase_order_ops_id' => $purchaseOrderOps->id,
                'file_name' => $originalName,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'attachment' => $attachment->load('uploader'),
            ]);

        } catch (\Exception $e) {
            \Log::error('Upload attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete attachment
     */
    public function deleteAttachment(\App\Models\PurchaseOrderOpsAttachment $attachment)
    {
        try {
            // Check if user can delete this attachment
            if ($attachment->uploaded_by !== auth()->id() && !auth()->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to delete this file',
                ], 403);
            }

            // Delete file from storage
            if (\Storage::disk('public')->exists($attachment->file_path)) {
                \Storage::disk('public')->delete($attachment->file_path);
            }

            // Delete database record
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
            ]);

        } catch (\Exception $e) {
            \Log::error('Delete attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download attachment
     */
    public function downloadAttachment(\App\Models\PurchaseOrderOpsAttachment $attachment)
    {
        try {
            if (!\Storage::disk('public')->exists($attachment->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            return \Storage::disk('public')->download($attachment->file_path, $attachment->file_name);

        } catch (\Exception $e) {
            \Log::error('Download attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to download file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View attachment (for images)
     */
    public function viewAttachment(\App\Models\PurchaseOrderOpsAttachment $attachment)
    {
        try {
            if (!\Storage::disk('public')->exists($attachment->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            // Check if it's an image
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            $extension = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION));
            
            if (!in_array($extension, $imageExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is not an image',
                ], 400);
            }

            $file = \Storage::disk('public')->get($attachment->file_path);
            $mimeType = \Storage::disk('public')->mimeType($attachment->file_path);

            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $attachment->file_name . '"');

        } catch (\Exception $e) {
            \Log::error('View attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to view file: ' . $e->getMessage(),
            ], 500);
        }
    }
}
