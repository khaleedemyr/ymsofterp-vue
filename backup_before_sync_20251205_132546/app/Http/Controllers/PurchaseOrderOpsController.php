<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisitionOutletBudget;
use App\Models\RetailNonFood;
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
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->select(
                'po.*',
                's.name as supplier_name',
                'creator.nama_lengkap as creator_name',
                'pm.nama_lengkap as purchasing_manager_name',
                'gm.nama_lengkap as gm_finance_name',
                'pr.pr_number as source_pr_number',
                'o.nama_outlet as outlet_name'
            )
            ->orderBy('po.created_at', 'desc');

        if (request('search')) {
            $search = request('search');
            $query->where(function($q) use ($search) {
                $q->where('po.number', 'like', '%' . $search . '%')
                  ->orWhere('s.name', 'like', '%' . $search . '%')
                  ->orWhere('pr.pr_number', 'like', '%' . $search . '%')
                  ->orWhere('o.nama_outlet', 'like', '%' . $search . '%');
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
            
            // Outlet information from PR
            $po->outlet = $po->outlet_name ? (object) [
                'nama_outlet' => $po->outlet_name
            ] : null;
            
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
            ->where('pr.mode', 'pr_ops') // Only PR Ops mode
            ->groupBy('pr.id')
            ->havingRaw('COUNT(items.id) = COUNT(po_items.id)')
            ->pluck('pr.id')
            ->toArray();

        // Get available PRs that are approved and not fully processed
        // Note: PRs that are on hold will still be shown but cannot be expanded/selected
        $prs = DB::table('purchase_requisitions as pr')
            ->join('purchase_requisition_items as items', 'pr.id', '=', 'items.purchase_requisition_id')
            ->leftJoin('tbl_data_divisi as d', 'pr.division_id', '=', 'd.id')
            ->leftJoin('tbl_data_outlet as o', 'items.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_outlet as pr_o', 'pr.outlet_id', '=', 'pr_o.id_outlet') // PR outlet for fallback
            ->leftJoin('purchase_requisition_categories as c', 'items.category_id', '=', 'c.id')
            ->leftJoin('purchase_requisition_categories as pr_c', 'pr.category_id', '=', 'pr_c.id') // PR category for fallback
            ->where('pr.status', 'APPROVED') // Only approved PRs
            ->where('pr.mode', 'pr_ops') // Only PR Ops mode
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
                'pr.mode',
                'pr.status',
                'pr.is_held',
                'pr.hold_reason',
                'pr.outlet_id as pr_outlet_id',
                'pr.category_id as pr_category_id',
                'items.id as item_id',
                'items.item_name',
                'items.qty',
                'items.unit',
                'items.unit_price',
                'items.subtotal',
                'items.outlet_id',
                'items.category_id',
                'o.nama_outlet as outlet_name',
                'pr_o.nama_outlet as pr_outlet_name',
                'c.name as category_name',
                'pr_c.name as pr_category_name',
                'd.nama_divisi as division_name'
            )
            ->get()
            ->groupBy('id')
            ->map(function ($group) {
                $first = $group->first();
                
                // Get PR outlet and category for fallback
                $prOutlet = $first->pr_outlet_id ? [
                    'id' => $first->pr_outlet_id,
                    'nama_outlet' => $first->pr_outlet_name
                ] : null;
                
                $prCategory = $first->pr_category_id ? [
                    'id' => $first->pr_category_id,
                    'name' => $first->pr_category_name
                ] : null;
                
                // Get attachments for this PR (with outlet info for new structure)
                $attachments = DB::table('purchase_requisition_attachments as pra')
                    ->leftJoin('users as u', 'pra.uploaded_by', '=', 'u.id')
                    ->leftJoin('tbl_data_outlet as o', 'pra.outlet_id', '=', 'o.id_outlet')
                    ->where('pra.purchase_requisition_id', $first->id)
                    ->select(
                        'pra.id',
                        'pra.file_name',
                        'pra.file_path',
                        'pra.file_size',
                        'pra.mime_type',
                        'pra.created_at',
                        'pra.outlet_id',
                        'o.nama_outlet as outlet_name',
                        'u.nama_lengkap as uploader_name'
                    )
                    ->get()
                    ->map(function ($attachment) use ($prOutlet) {
                        // For legacy attachments without outlet_id, use PR outlet as fallback
                        $outlet = null;
                        if ($attachment->outlet_id && $attachment->outlet_name) {
                            $outlet = [
                                'id' => $attachment->outlet_id,
                                'nama_outlet' => $attachment->outlet_name
                            ];
                        } elseif ($prOutlet) {
                            // Fallback to PR outlet for legacy data
                            $outlet = $prOutlet;
                        }
                        
                        return [
                            'id' => $attachment->id,
                            'file_name' => $attachment->file_name,
                            'file_path' => $attachment->file_path,
                            'file_size' => $attachment->file_size,
                            'mime_type' => $attachment->mime_type,
                            'created_at' => $attachment->created_at,
                            'outlet_id' => $attachment->outlet_id ?? ($prOutlet ? $prOutlet['id'] : null),
                            'outlet' => $outlet,
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
                    'mode' => $first->mode ?? 'pr_ops',
                    'status' => $first->status ?? 'APPROVED',
                    'is_held' => (bool)($first->is_held ?? false),
                    'hold_reason' => $first->hold_reason ?? null,
                    'outlet' => $prOutlet, // PR outlet for reference
                    'category' => $prCategory, // PR category for reference
                    'attachments' => $attachments,
                    'items' => $group->map(function ($item) use ($first, $prOutlet, $prCategory) {
                        // For legacy items without outlet_id, use PR outlet as fallback
                        $outlet = null;
                        if ($item->outlet_id && $item->outlet_name) {
                            $outlet = [
                                'id' => $item->outlet_id,
                                'nama_outlet' => $item->outlet_name
                            ];
                        } elseif ($prOutlet) {
                            // Fallback to PR outlet for legacy data
                            $outlet = $prOutlet;
                        }
                        
                        // For legacy items without category_id, use PR category as fallback
                        $category = null;
                        if ($item->category_id && $item->category_name) {
                            $category = [
                                'id' => $item->category_id,
                                'name' => $item->category_name
                            ];
                        } elseif ($prCategory) {
                            // Fallback to PR category for legacy data
                            $category = $prCategory;
                        }
                        
                        return [
                            'id' => $item->item_id,
                            'item_name' => $item->item_name,
                            'qty' => $item->qty,
                            'unit' => $item->unit,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                            'outlet_id' => $item->outlet_id ?? $prOutlet['id'] ?? null,
                            'category_id' => $item->category_id ?? $prCategory['id'] ?? null,
                            'outlet' => $outlet,
                            'category' => $category,
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

        // Validate the request data - items_by_supplier is {itemId: [rows]}
        $request->validate([
            'items_by_supplier' => 'required|array',
            'items_by_supplier.*' => 'required|array',
            'items_by_supplier.*.*.supplier_id' => 'required|exists:suppliers,id',
            'items_by_supplier.*.*.qty' => 'required|numeric|min:0',
            'items_by_supplier.*.*.price' => 'required|numeric|min:0',
            'items_by_supplier.*.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items_by_supplier.*.*.discount_amount' => 'nullable|numeric|min:0',
            'discount_total_percent' => 'nullable|numeric|min:0|max:100',
            'discount_total_amount' => 'nullable|numeric|min:0',
            'ppn_enabled' => 'boolean',
            'notes' => 'nullable|string',
            'payment_type' => 'required|in:lunas,termin',
            'payment_terms' => 'nullable|string|max:500',
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

            // Check if any PR is on hold
            $heldPRs = PurchaseRequisition::whereIn('id', $prIds)
                ->where('is_held', true)
                ->get();
            
            if ($heldPRs->isNotEmpty()) {
                $heldPRNumbers = $heldPRs->pluck('pr_number')->join(', ');
                return response()->json([
                    'success' => false,
                    'message' => "Tidak dapat membuat PO karena PR berikut sedang di-hold: {$heldPRNumbers}. Silakan release PR terlebih dahulu."
                ], 400);
            }


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
                            'discount_percent' => $row['discount_percent'] ?? 0,
                            'discount_amount' => $row['discount_amount'] ?? 0,
                            'pr_id' => $row['pr_id'] ?? null,
                        ];
                    }
                }
            }
            

            $createdPOs = [];

            foreach ($itemsBySupplier as $supplierId => $items) {
                if (empty($items)) {
                    continue;
                }


                // Generate PO number
                $poNumber = $this->generatePONumber();

                // Calculate subtotal for this PO (after discount per item)
                $subtotal = collect($items)->sum(function ($item) {
                    $itemSubtotal = $item['price'] * $item['qty'];
                    // Apply discount per item
                    $discountPercent = isset($item['discount_percent']) ? floatval($item['discount_percent']) : 0;
                    $discountAmount = isset($item['discount_amount']) ? floatval($item['discount_amount']) : 0;
                    
                    if ($discountPercent > 0) {
                        $discountAmount = $itemSubtotal * ($discountPercent / 100);
                    }
                    
                    return $itemSubtotal - $discountAmount;
                });

                // Apply discount total
                $discountTotalPercent = isset($request->discount_total_percent) ? floatval($request->discount_total_percent) : 0;
                $discountTotalAmount = isset($request->discount_total_amount) ? floatval($request->discount_total_amount) : 0;
                
                if ($discountTotalPercent > 0) {
                    $discountTotalAmount = $subtotal * ($discountTotalPercent / 100);
                }
                
                $subtotalAfterDiscount = $subtotal - $discountTotalAmount;

                // Calculate PPN if enabled (PPN calculated after discount)
                $ppnAmount = 0;
                $grandTotal = $subtotalAfterDiscount;
                if ($request->ppn_enabled) {
                    $ppnAmount = $subtotalAfterDiscount * 0.11; // 11% PPN
                    $grandTotal = $subtotalAfterDiscount + $ppnAmount;
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
                    'discount_total_percent' => $discountTotalPercent,
                    'discount_total_amount' => $discountTotalAmount,
                    'grand_total' => $grandTotal,
                    'payment_type' => $request->payment_type ?? 'lunas',
                    'payment_terms' => $request->payment_terms ?? null,
                    'source_type' => 'purchase_requisition_ops',
                    'source_id' => $prIds->first(),
                ];

                $po = PurchaseOrderOps::create($poData);
                $createdPOs[] = $po;

                // Get PR items for this supplier
                $prItemIds = collect($items)
                    ->pluck('id')  // Frontend sends 'id', not 'item_id'
                    ->filter();
                
                
                $prItems = collect();
                if ($prItemIds->isNotEmpty()) {
                    $prItems = PurchaseRequisitionItem::whereIn('id', $prItemIds)->get();
                }

                foreach ($items as $itemData) {
                    
                    // Get PR item data
                    $prItem = $prItems->firstWhere('id', $itemData['id']);
                    if (!$prItem) {
                        \Log::error('PR item not found:', ['item_id' => $itemData['id']]);
                        continue;
                    }
                    
                    $quantity = floatval($itemData['qty']);
                    $price = floatval($itemData['price']);
                    $itemSubtotal = $quantity * $price;
                    
                    // Calculate discount per item
                    $discountPercent = isset($itemData['discount_percent']) ? floatval($itemData['discount_percent']) : 0;
                    $discountAmount = isset($itemData['discount_amount']) ? floatval($itemData['discount_amount']) : 0;
                    
                    if ($discountPercent > 0) {
                        $discountAmount = $itemSubtotal * ($discountPercent / 100);
                    }
                    
                    $total = round($itemSubtotal - $discountAmount, 2);

                    $poItemData = [
                        'purchase_order_ops_id' => $po->id,
                        'item_name' => $prItem->item_name,
                        'quantity' => $quantity,
                        'unit' => $prItem->unit,
                        'price' => $price,
                        'discount_percent' => $discountPercent,
                        'discount_amount' => $discountAmount,
                        'total' => $total,
                        'created_by' => auth()->id(),
                        'arrival_date' => $prItem->arrival_date,
                        'pr_ops_item_id' => $prItem->id,
                        'source_type' => 'purchase_requisition_ops',
                        'source_id' => $prItem->purchase_requisition_id,
                    ];

                    PurchaseOrderOpsItem::create($poItemData);
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

            // Check and update PR status only if ALL items have been made into PO
            // Only update PR status to 'PROCESSED' when all items are in PO
            foreach ($prIds as $prId) {
                $pr = PurchaseRequisition::find($prId);
                if (!$pr) {
                    continue;
                }

                // Only process PR Ops mode (PO only for purchase requisition, not for payment/travel/kasbon)
                if ($pr->mode !== 'pr_ops') {
                    continue;
                }

                // Get all items for this PR
                $allPrItems = PurchaseRequisitionItem::where('purchase_requisition_id', $prId)->get();
                
                if ($allPrItems->isEmpty()) {
                    continue;
                }

                // Get all PR item IDs that are already in PO
                $prItemIdsInPO = DB::table('purchase_order_ops_items')
                    ->whereNotNull('pr_ops_item_id')
                    ->whereIn('pr_ops_item_id', $allPrItems->pluck('id')->toArray())
                    ->pluck('pr_ops_item_id')
                    ->toArray();

                // Check if all items are in PO
                $allItemsInPO = $allPrItems->every(function ($item) use ($prItemIdsInPO) {
                    return in_array($item->id, $prItemIdsInPO);
                });

                // Only update to PROCESSED if all items are in PO
                if ($allItemsInPO && $pr->status === 'APPROVED') {
                    $pr->update(['status' => 'PROCESSED']);
                    \Log::info('PR status updated to PROCESSED - all items are in PO', [
                        'pr_id' => $prId,
                        'pr_number' => $pr->pr_number,
                        'total_items' => $allPrItems->count(),
                        'items_in_po' => count($prItemIdsInPO)
                    ]);
                } else {
                    \Log::info('PR status kept as APPROVED - not all items are in PO yet', [
                        'pr_id' => $prId,
                        'pr_number' => $pr->pr_number,
                        'total_items' => $allPrItems->count(),
                        'items_in_po' => count($prItemIdsInPO),
                        'current_status' => $pr->status
                    ]);
                }
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
            'source_pr.category',
            'source_pr.division',
            'source_pr.outlet',
            'source_pr.creator',
            'source_pr.attachments.uploader',
            'approvalFlows.approver',
            'attachments.uploader'
        ])->findOrFail($id);


        $user = auth()->user();
        $userData = [
            'id' => $user->id,
            'id_jabatan' => $user->id_jabatan,
            'id_role' => $user->id_role,
            'status' => $user->status,
            'nama_lengkap' => $user->nama_lengkap,
        ];

        // Get budget information if PO has source PR
        // Use getBudgetInfo method to ensure consistency with Purchase Requisition
        $budgetInfo = null;
        if ($po->source_type === 'purchase_requisition_ops' && $po->source_id && $po->source_pr) {
            try {
                $budgetInfo = $this->getBudgetInfo($po->source_pr);
            } catch (\Exception $e) {
                \Log::warning('Failed to get budget info for PR ' . $po->source_pr->id . ': ' . $e->getMessage());
                $budgetInfo = null;
            }
        }

        // Check if this is an API request (for modal)
        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json([
                'success' => true,
                'po' => $po,
                'user' => $userData,
                'budgetInfo' => $budgetInfo
            ]);
        }

        return inertia('PurchaseOrderOps/Show', [
            'po' => $po,
            'user' => $userData,
            'budgetInfo' => $budgetInfo
        ]);
    }

    public function edit($id)
    {
        $po = PurchaseOrderOps::with([
            'supplier',
            'items',
            'source_pr.category'
        ])->findOrFail($id);

        // Only allow editing if status is draft or approved
        if (!in_array($po->status, ['draft', 'approved'])) {
            return redirect()->route('po-ops.show', $po->id)
                ->with('error', 'PO tidak dapat diedit karena status sudah received');
        }

        // Get suppliers
        $suppliers = \App\Models\Supplier::whereIn('status', ['A', 'active'])->get(['id', 'name']);

        // Get budget information if PO has source PR
        // Use getBudgetInfo method to ensure consistency with Purchase Requisition
        $budgetInfo = null;
        if ($po->source_type === 'purchase_requisition_ops' && $po->source_id && $po->source_pr) {
            try {
                $budgetInfo = $this->getBudgetInfo($po->source_pr);
            } catch (\Exception $e) {
                \Log::warning('Failed to get budget info for PR ' . $po->source_pr->id . ': ' . $e->getMessage());
                $budgetInfo = null;
            }
        }

        return inertia('PurchaseOrderOps/Edit', [
            'po' => $po,
            'suppliers' => $suppliers,
            'budgetInfo' => $budgetInfo,
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
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'new_items' => 'nullable|array',
            'new_items.*.item_name' => 'required|string',
            'new_items.*.quantity' => 'required|numeric|min:0',
            'new_items.*.unit' => 'required|string',
            'new_items.*.price' => 'required|numeric|min:0',
            'new_items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'new_items.*.discount_amount' => 'nullable|numeric|min:0',
            'discount_total_percent' => 'nullable|numeric|min:0|max:100',
            'discount_total_amount' => 'nullable|numeric|min:0',
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
                    $poItem = PurchaseOrderOpsItem::find($item['id']);
                    if ($poItem) {
                        $quantity = floatval($poItem->quantity);
                        $price = floatval($item['price']);
                        $itemSubtotal = $quantity * $price;
                        
                        // Calculate discount per item
                        $discountPercent = isset($item['discount_percent']) ? floatval($item['discount_percent']) : 0;
                        $discountAmount = isset($item['discount_amount']) ? floatval($item['discount_amount']) : 0;
                        
                        if ($discountPercent > 0) {
                            $discountAmount = $itemSubtotal * ($discountPercent / 100);
                        }
                        
                        $total = round($itemSubtotal - $discountAmount, 2);
                        
                        $poItem->update([
                            'price' => $price,
                            'discount_percent' => $discountPercent,
                            'discount_amount' => $discountAmount,
                            'total' => $total,
                        ]);
                    }
                }
            }

            // Add new items
            if ($request->new_items) {
                foreach ($request->new_items as $newItem) {
                    $quantity = floatval($newItem['quantity']);
                    $price = floatval($newItem['price']);
                    $itemSubtotal = $quantity * $price;
                    
                    // Calculate discount per item
                    $discountPercent = isset($newItem['discount_percent']) ? floatval($newItem['discount_percent']) : 0;
                    $discountAmount = isset($newItem['discount_amount']) ? floatval($newItem['discount_amount']) : 0;
                    
                    if ($discountPercent > 0) {
                        $discountAmount = $itemSubtotal * ($discountPercent / 100);
                    }
                    
                    $total = round($itemSubtotal - $discountAmount, 2);
                    
                    PurchaseOrderOpsItem::create([
                        'purchase_order_ops_id' => $po->id,
                        'item_name' => $newItem['item_name'],
                        'quantity' => $quantity,
                        'unit' => $newItem['unit'],
                        'price' => $price,
                        'discount_percent' => $discountPercent,
                        'discount_amount' => $discountAmount,
                        'total' => $total,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            // Recalculate totals (after discount per item)
            $allItems = PurchaseOrderOpsItem::where('purchase_order_ops_id', $po->id)->get();
            $subtotal = $allItems->sum('total');
            
            // Apply discount total
            $discountTotalPercent = isset($request->discount_total_percent) ? floatval($request->discount_total_percent) : 0;
            $discountTotalAmount = isset($request->discount_total_amount) ? floatval($request->discount_total_amount) : 0;
            
            if ($discountTotalPercent > 0) {
                $discountTotalAmount = $subtotal * ($discountTotalPercent / 100);
            }
            
            $subtotalAfterDiscount = $subtotal - $discountTotalAmount;
            
            // Calculate PPN if enabled (PPN calculated after discount)
            $ppnAmount = 0;
            $grandTotal = $subtotalAfterDiscount;
            if ($request->ppn_enabled) {
                $ppnAmount = $subtotalAfterDiscount * 0.11; // 11% PPN
                $grandTotal = $subtotalAfterDiscount + $ppnAmount;
            }

            // Update PO notes and PPN
            $po->update([
                'notes' => $request->notes,
                'ppn_enabled' => $request->ppn_enabled ?? false,
                'ppn_amount' => $ppnAmount,
                'subtotal' => $subtotal,
                'discount_total_percent' => $discountTotalPercent,
                'discount_total_amount' => $discountTotalAmount,
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
            
            // Delete items first
            foreach ($po->items as $item) {
                $item->delete();
            }
            
            // Update related Purchase Requisition Ops status based on remaining items in PO
            if ($po->source_type === 'purchase_requisition_ops' && $po->source_id) {
                $pr = PurchaseRequisition::find($po->source_id);
                if ($pr && $pr->mode === 'pr_ops') {
                    // Get all items for this PR
                    $allPrItems = PurchaseRequisitionItem::where('purchase_requisition_id', $pr->id)->get();
                    
                    if ($allPrItems->isNotEmpty()) {
                        // Get all PR item IDs that are still in PO (after this deletion)
                        $prItemIdsInPO = DB::table('purchase_order_ops_items')
                            ->whereNotNull('pr_ops_item_id')
                            ->whereIn('pr_ops_item_id', $allPrItems->pluck('id')->toArray())
                            ->pluck('pr_ops_item_id')
                            ->toArray();

                        // Check if all items are still in PO
                        $allItemsInPO = $allPrItems->every(function ($item) use ($prItemIdsInPO) {
                            return in_array($item->id, $prItemIdsInPO);
                        });

                        // Only update to APPROVED if not all items are in PO anymore
                        if (!$allItemsInPO && $pr->status === 'PROCESSED') {
                            $pr->status = 'APPROVED';
                            $pr->save();
                            
                            \Log::info('Purchase Requisition status updated to APPROVED after PO deletion - not all items in PO', [
                                'pr_id' => $pr->id,
                                'pr_number' => $pr->pr_number,
                                'po_id' => $po->id,
                                'po_number' => $po->number,
                                'total_items' => $allPrItems->count(),
                                'items_in_po' => count($prItemIdsInPO)
                            ]);
                        } else if ($allItemsInPO) {
                            \Log::info('Purchase Requisition status kept as PROCESSED - all items still in PO', [
                                'pr_id' => $pr->id,
                                'pr_number' => $pr->pr_number,
                                'po_id' => $po->id,
                                'po_number' => $po->number,
                                'total_items' => $allPrItems->count(),
                                'items_in_po' => count($prItemIdsInPO)
                            ]);
                        }
                    }
                }
            }
            
            // Store PO data for logging before deletion
            $poData = $po->toArray();
            $poNumber = $po->number;
            
            $po->delete();
            
            // Log activity
            try {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'activity_type' => 'delete',
                    'module' => 'purchase_order_ops',
                    'description' => 'Hapus PO Ops: ' . $poNumber . ' - PR status diupdate ke APPROVED',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => json_encode($poData),
                    'new_data' => null,
                ]);
            } catch (\Exception $logError) {
                \Log::warning('Failed to create activity log', ['error' => $logError->getMessage()]);
            }
            
            DB::commit();
            return redirect()->route('po-ops.index')->with('success', 'PO berhasil dihapus dan status Purchase Requisition diupdate ke APPROVED');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting Purchase Order Ops', [
                'po_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
        // Only include POs where:
        // - There is a PENDING step for current user
        // - No approval flow is REJECTED (stop chain)
        // - Overall PO status is not rejected/cancelled/approved/received
        $pendingPOs = PurchaseOrderOps::whereHas('approvalFlows', function($query) use ($userId) {
            $query->where('approver_id', $userId)
                  ->where('status', 'PENDING');
        })
        ->whereNotIn('status', ['rejected', 'approved', 'received', 'cancelled'])
        ->whereDoesntHave('approvalFlows', function($q) {
            $q->where('status', 'REJECTED');
        })
        ->with([
            'supplier', 
            'creator', 
            'source_pr.category',
            'source_pr.division',
            'source_pr.outlet',
            'source_pr.creator',
            'source_pr.attachments.uploader',
            'attachments.uploader',
            // include all flows to allow server-side filtering by level order
            'approvalFlows.approver'
        ])
        ->get();

        // Enforce level order: only show if current user's pending flow is the lowest pending level
        $pendingPOs = $pendingPOs->filter(function ($po) use ($userId) {
            $flows = collect($po->approvalFlows ?? []);
            if ($flows->contains(function ($f) { return strtoupper($f->status) === 'REJECTED'; })) {
                return false;
            }
            // Normalize statuses
            $pending = $flows->filter(function ($f) { return strtoupper($f->status) === 'PENDING'; })
                             ->sortBy('approval_level');
            if ($pending->isEmpty()) {
                return false;
            }
            $nextFlow = $pending->first();
            return intval($nextFlow->approver_id) === intval($userId);
        })->map(function ($po) {
            // Get approver name from the next pending approval flow
            $flows = collect($po->approvalFlows ?? []);
            $pending = $flows->filter(function ($f) { return strtoupper($f->status) === 'PENDING'; })
                             ->sortBy('approval_level');
            
            if ($pending->isNotEmpty()) {
                $nextFlow = $pending->first();
                if ($nextFlow->approver) {
                    $po->approver_name = $nextFlow->approver->nama_lengkap;
                } else {
                    $po->approver_name = null;
                }
            } else {
                $po->approver_name = null;
            }
            
            return $po;
        })->values();

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

    /**
     * View attachment via API (supports Bearer token)
     * Supports both images and other file types (PDF, etc.)
     */
    public function viewAttachmentApi($attachmentId)
    {
        try {
            \Log::info('View PO Ops attachment API called with ID: ' . $attachmentId);
            
            $attachment = \App\Models\PurchaseOrderOpsAttachment::findOrFail($attachmentId);
            \Log::info('Attachment found: ' . $attachment->file_name . ', path: ' . $attachment->file_path);
            
            if (!\Storage::disk('public')->exists($attachment->file_path)) {
                \Log::error('File not found in storage: ' . $attachment->file_path);
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            $file = \Storage::disk('public')->get($attachment->file_path);
            $mimeType = \Storage::disk('public')->mimeType($attachment->file_path);
            
            \Log::info('Serving file: ' . $attachment->file_name . ', mime: ' . $mimeType);

            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $attachment->file_name . '"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET')
                ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Attachment not found with ID: ' . $attachmentId);
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('View attachment API error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to view file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Print preview for Purchase Orders
     */
    public function printPreview(Request $request)
    {
        try {
            
            $ids = $request->get('ids', '');
            
            if (empty($ids)) {
                \Log::warning('No IDs provided in PO printPreview');
                return response()->json(['error' => 'No IDs provided'], 400);
            }

            $poIds = explode(',', $ids);
            
            // Validate that all IDs are numeric
            foreach ($poIds as $id) {
                if (!is_numeric($id)) {
                    \Log::warning('Invalid PO ID format', ['id' => $id]);
                    return response()->json(['error' => 'Invalid ID format: ' . $id], 400);
                }
            }
            
            $purchaseOrders = PurchaseOrderOps::with([
                'supplier',
                'creator',
                'items',
                'purchase_requisition.division',
                'purchase_requisition.category',
                'purchase_requisition.outlet',
                'purchase_requisition.creator',
                'approvalFlows.approver.jabatan'
            ])->whereIn('id', $poIds)->get();

            // Add budget info for each PO that has a purchase requisition
            foreach ($purchaseOrders as $po) {
                if ($po->purchase_requisition) {
                    try {
                        // Get budget info using the same logic as in PurchaseRequisitionController
                        $budgetInfo = $this->getBudgetInfo($po->purchase_requisition);
                        $po->purchase_requisition->budget_info = $budgetInfo;
                    } catch (\Exception $e) {
                        \Log::warning('Failed to get budget info for PR ' . $po->purchase_requisition->id . ': ' . $e->getMessage());
                        $po->purchase_requisition->budget_info = null;
                    }
                }
            }
            
            return view('purchase-order-ops.print-preview', [
                'purchaseOrders' => $purchaseOrders,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in PO printPreview method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ids' => $request->get('ids', '')
            ]);
            
            return response()->json([
                'error' => 'Failed to generate print preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get budget info for a purchase requisition
     */
    private function getBudgetInfo($purchaseRequisition)
    {
        $categoryId = $purchaseRequisition->category_id;
        $outletId = $purchaseRequisition->outlet_id;
        $currentAmount = $purchaseRequisition->amount;
        $year = $purchaseRequisition->created_at->year;
        $month = $purchaseRequisition->created_at->month;

        if (!$categoryId) {
            return null;
        }

        // Get category budget
        $category = PurchaseRequisitionCategory::find($categoryId);
        if (!$category) {
            return null;
        }

        // Calculate date range for the month (BUDGET IS MONTHLY)
        $dateFrom = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
        $dateTo = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

        $budgetInfo = [];

        if ($category->isGlobalBudget()) {
            // GLOBAL BUDGET: Calculate across all outlets
            // Used amount = Paid (from non_food_payments) + Unpaid PR + Retail Non Food (same logic as approval)
            $categoryBudget = $category->budget_limit;
            
            // Get PR IDs in this category for the month (BUDGET IS MONTHLY - filter by month)
            // Support both old structure (category at PR level) and new structure (category at items level)
            $prIds = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('pr.is_held', false) // Exclude held PRs
                ->distinct()
                ->pluck('pr.id')
                ->toArray();
            
            // Get PO IDs linked to PRs in this category
            $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poi.source_id', $prIds)
                ->distinct()
                ->pluck('poi.purchase_order_ops_id')
                ->toArray();
            
            // Get paid amount from non_food_payments (BUDGET IS MONTHLY - filter by payment_date)
            // IMPORTANT: Only count NFP with status 'paid' (not 'approved')
            $paidAmountFromPo = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                ->where('nfp.status', 'paid') // Only 'paid' status, not 'approved'
                ->where('nfp.status', '!=', 'cancelled')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->sum('nfp.amount');
            
            // Get Retail Non Food amounts (BUDGET IS MONTHLY - filter by transaction_date)
            $retailNonFoodApproved = RetailNonFood::where('category_budget_id', $categoryId)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                ->where('status', 'approved')
                ->sum('total_amount');
            
            // Get unpaid PR data
            // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO dan belum jadi NFP
            // Support both old structure (category at PR level) and new structure (category at items level)
            $prIdsForUnpaid = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('pr.id', '=', 'poi.source_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED']) // Only SUBMITTED and APPROVED
                ->where('pr.is_held', false) // Exclude held PRs
                ->whereNull('poo.id') // PR yang belum jadi PO (belum ada PO)
                ->whereNull('nfp.id') // PR yang belum jadi NFP (baik langsung maupun melalui PO)
                ->distinct()
                ->pluck('pr.id')
                ->toArray();
            
            $allPrs = \App\Models\PurchaseRequisition::whereIn('id', $prIdsForUnpaid)->get();
            
            // Get PO totals per PR - untuk cek apakah PR sudah jadi PO
            $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                         ->where(function($q) {
                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                               ->orWhere(function($q2) {
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name');
                               });
                         });
                })
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                ->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                ->pluck('po_total', 'pr_id')
                ->toArray();
            
            // Calculate unpaid for each PR
            // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO
            // PR yang sudah difilter di query (belum jadi PO, status SUBMITTED/APPROVED)
            $prUnpaidAmount = 0;
            foreach ($allPrs as $pr) {
                // PR yang sudah difilter di query (belum jadi PO, status SUBMITTED/APPROVED)
                $prUnpaidAmount += $pr->amount;
            }
            
            // Get unpaid PO data
            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
            $allPOs = DB::table('purchase_order_ops as poo')
                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                         ->where(function($q) {
                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                               ->orWhere(function($q2) {
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name');
                               });
                         });
                })
                ->leftJoin('non_food_payments as nfp', 'poo.id', '=', 'nfp.purchase_order_ops_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                ->whereNull('nfp.id') // PO yang belum jadi NFP (belum ada NFP)
                ->groupBy('poo.id')
                ->select('poo.id as po_id', DB::raw('SUM(poi.total) as po_total'))
                ->get();
            
            // Calculate unpaid for each PO
            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
            // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
            $poUnpaidAmount = 0;
            foreach ($allPOs as $po) {
                // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                $poUnpaidAmount += $po->po_total ?? 0;
            }
            
            // Calculate unpaid NFP
            // NEW LOGIC: NFP unpaid = NFP dengan status pending dan approved
            // Mencakup NFP yang langsung dari PR (tanpa PO) dan NFP yang melalui PO
            // Case 1: NFP langsung dari PR
            $nfpUnpaidFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id') // NFP langsung dari PR (tanpa PO)
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->whereIn('nfp.status', ['pending', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            // Case 2: NFP melalui PO
            $nfpUnpaidFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                         ->where(function($q) {
                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                               ->orWhere(function($q2) {
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name');
                               });
                         });
                })
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id') // NFP melalui PO
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->whereIn('nfp.status', ['pending', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpUnpaidAmount = ($nfpUnpaidFromPr ?? 0) + ($nfpUnpaidFromPo ?? 0);
            
            // Get NFP breakdown by status (submitted, approved, paid)
            // NFP Submitted
            $nfpSubmittedFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'submitted')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpSubmittedFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                         ->where(function($q) {
                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                               ->orWhere(function($q2) {
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name');
                               });
                         });
                })
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'submitted')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpSubmittedAmount = ($nfpSubmittedFromPr ?? 0) + ($nfpSubmittedFromPo ?? 0);
            
            // NFP Approved (status = 'approved', belum paid)
            $nfpApprovedFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'approved')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpApprovedFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                         ->where(function($q) {
                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                               ->orWhere(function($q2) {
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name');
                               });
                         });
                })
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'approved')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpApprovedAmount = ($nfpApprovedFromPr ?? 0) + ($nfpApprovedFromPo ?? 0);
            
            // NFP Paid (already calculated in paidAmountFromPo)
            $nfpPaidAmount = $paidAmountFromPo;
            
            // Total unpaid = PR unpaid + PO unpaid + NFP unpaid
            $unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount;
            
            // Total used = Paid (from non_food_payments 'paid' + RNF 'approved') + Unpaid (PR + PO + NFP 'approved')
            $paidAmount = $paidAmountFromPo + $retailNonFoodApproved;
            $categoryUsedAmount = $paidAmount + $unpaidAmount;

            $budgetInfo = [
                'budget_type' => 'GLOBAL',
                'current_year' => $year,
                'current_month' => $month,
                'category_budget' => $categoryBudget,
                'category_used_amount' => $categoryUsedAmount,
                'category_remaining_amount' => $categoryBudget - $categoryUsedAmount,
                'breakdown' => [
                    'pr_unpaid' => $prUnpaidAmount, // PR Submitted & Approved yang belum dibuat PO
                    'po_unpaid' => $poUnpaidAmount, // PO Submitted & Approved yang belum dibuat NFP
                    'nfp_submitted' => $nfpSubmittedAmount, // NFP Submitted
                    'nfp_approved' => $nfpApprovedAmount, // NFP Approved (unpaid)
                    'nfp_paid' => $nfpPaidAmount, // NFP Paid
                    'retail_non_food' => $retailNonFoodApproved, // Retail Non Food Approved
                ],
            ];

        } else if ($category->isPerOutletBudget()) {
            // PER_OUTLET BUDGET: Calculate per specific outlet
            // Used amount = Paid (from non_food_payments) + Unpaid PR + Retail Non Food (same logic as approval)
            if (!$outletId) {
                return null;
            }

            // Get outlet budget allocation
            $outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->first();

            if (!$outletBudget) {
                return null;
            }

            // Get PR IDs for this outlet
            // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
            $prIds = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('pr.is_held', false) // Exclude held PRs
                ->distinct()
                ->pluck('pr.id')
                ->toArray();
            
            // Get PO IDs linked to PRs in this outlet
            $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poi.source_id', $prIds)
                ->distinct()
                ->pluck('poi.purchase_order_ops_id')
                ->toArray();
            
            // Get paid amount from non_food_payments for this outlet (BUDGET IS MONTHLY - filter by payment_date)
            // IMPORTANT: Only count NFP with status 'paid' (not 'approved')
            $paidAmountFromPo = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                ->where('nfp.status', 'paid') // Only 'paid' status, not 'approved'
                ->where('nfp.status', '!=', 'cancelled')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->sum('nfp.amount');
            
            // Get Retail Non Food for this outlet (BUDGET IS MONTHLY - filter by transaction_date)
            $outletRetailNonFoodApproved = RetailNonFood::where('category_budget_id', $categoryId)
                ->where('outlet_id', $outletId)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                ->where('status', 'approved')
                ->sum('total_amount');
            
            // Get unpaid PR data for this outlet
            // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO dan belum jadi NFP
            // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
            $prIdsForUnpaid = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('pr.id', '=', 'poi.source_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED']) // Only SUBMITTED and APPROVED
                ->where('pr.is_held', false) // Exclude held PRs
                ->whereNull('poo.id') // PR yang belum jadi PO (belum ada PO)
                ->whereNull('nfp.id') // PR yang belum jadi NFP (baik langsung maupun melalui PO)
                ->distinct()
                ->pluck('pr.id')
                ->toArray();
            
            $allPrs = \App\Models\PurchaseRequisition::whereIn('id', $prIdsForUnpaid)->get();
            
            // Get PO totals per PR - untuk cek apakah PR sudah jadi PO
            $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                         ->where(function($q) {
                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                               ->orWhere(function($q2) {
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name');
                               });
                         });
                })
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                ->groupBy('pr.id')
                ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                ->pluck('po_total', 'pr_id')
                ->toArray();
            
            // Calculate unpaid for each PR
            // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO
            // PR yang sudah difilter di query (belum jadi PO, status SUBMITTED/APPROVED)
            $prUnpaidAmount = 0;
            foreach ($allPrs as $pr) {
                // PR yang sudah difilter di query (belum jadi PO, status SUBMITTED/APPROVED)
                $prUnpaidAmount += $pr->amount;
            }
            
            // Get unpaid PO data for this outlet
            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
            $allPOs = DB::table('purchase_order_ops as poo')
                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                         ->where(function($q) {
                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                               ->orWhere(function($q2) {
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name');
                               });
                         });
                })
                ->leftJoin('non_food_payments as nfp', 'poo.id', '=', 'nfp.purchase_order_ops_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->where('poi.source_type', 'purchase_requisition_ops')
                ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                ->whereNull('nfp.id') // PO yang belum jadi NFP (belum ada NFP)
                ->groupBy('poo.id')
                ->select('poo.id as po_id', DB::raw('SUM(poi.total) as po_total'))
                ->get();
            
            // Calculate unpaid for each PO
            // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
            // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
            $poUnpaidAmount = 0;
            foreach ($allPOs as $po) {
                // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                $poUnpaidAmount += $po->po_total ?? 0;
            }
            
            // Calculate unpaid NFP
            // NEW LOGIC: NFP unpaid = NFP dengan status pending dan approved
            // Mencakup NFP yang langsung dari PR (tanpa PO) dan NFP yang melalui PO
            // Case 1: NFP langsung dari PR untuk outlet ini
            $nfpUnpaidFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id') // NFP langsung dari PR (tanpa PO)
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->whereIn('nfp.status', ['pending', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            // Case 2: NFP melalui PO untuk outlet ini
            $nfpUnpaidFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                         ->where(function($q) {
                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                               ->orWhere(function($q2) {
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name');
                               });
                         });
                })
                ->where(function($q) use ($categoryId, $outletId) {
                    // Old structure: category and outlet at PR level
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    // New structure: category and outlet at items level
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id') // NFP melalui PO
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->whereIn('nfp.status', ['pending', 'approved'])
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpUnpaidAmount = ($nfpUnpaidFromPr ?? 0) + ($nfpUnpaidFromPo ?? 0);
            
            // Get NFP breakdown by status (submitted, approved, paid) for this outlet
            // NFP Submitted
            $nfpSubmittedFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'submitted')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpSubmittedFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                         ->where(function($q) {
                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                               ->orWhere(function($q2) {
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name');
                               });
                         });
                })
                ->where(function($q) use ($categoryId, $outletId) {
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'submitted')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpSubmittedAmount = ($nfpSubmittedFromPr ?? 0) + ($nfpSubmittedFromPo ?? 0);
            
            // NFP Approved (status = 'approved', belum paid)
            $nfpApprovedFromPr = DB::table('non_food_payments as nfp')
                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId, $outletId) {
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'approved')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpApprovedFromPo = DB::table('non_food_payments as nfp')
                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                })
                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                         ->where(function($q) {
                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                               ->orWhere(function($q2) {
                                   $q2->whereNull('poi.pr_ops_item_id')
                                      ->whereColumn('poi.item_name', 'pri.item_name');
                               });
                         });
                })
                ->where(function($q) use ($categoryId, $outletId) {
                    $q->where(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pr.category_id', $categoryId)
                           ->where('pr.outlet_id', $outletId);
                    })
                    ->orWhere(function($q2) use ($categoryId, $outletId) {
                        $q2->where('pri.category_id', $categoryId)
                           ->where('pri.outlet_id', $outletId);
                    });
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->where('pr.is_held', false)
                ->whereNotNull('nfp.purchase_order_ops_id')
                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                ->where('nfp.status', 'approved')
                ->where('nfp.status', '!=', 'cancelled')
                ->sum('nfp.amount');
            
            $nfpApprovedAmount = ($nfpApprovedFromPr ?? 0) + ($nfpApprovedFromPo ?? 0);
            
            // NFP Paid (already calculated in paidAmountFromPo)
            $nfpPaidAmount = $paidAmountFromPo;
            
            // Total unpaid = PR unpaid + PO unpaid + NFP unpaid
            $unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount;
            
            // Total used = Paid (from non_food_payments 'paid' + RNF 'approved') + Unpaid (PR + PO + NFP 'approved')
            $paidAmount = $paidAmountFromPo + $outletRetailNonFoodApproved;
            $outletUsedAmount = $paidAmount + $unpaidAmount;

            $budgetInfo = [
                'budget_type' => 'PER_OUTLET',
                'current_year' => $year,
                'current_month' => $month,
                'category_budget' => $category->budget_limit, // Global budget for reference
                'outlet_budget' => $outletBudget->allocated_budget,
                'outlet_used_amount' => $outletUsedAmount,
                'outlet_remaining_amount' => $outletBudget->allocated_budget - $outletUsedAmount,
                'outlet_info' => [
                    'id' => $outletBudget->outlet_id,
                    'name' => $outletBudget->outlet->nama_outlet ?? 'Unknown Outlet',
                ],
                'breakdown' => [
                    'pr_unpaid' => $prUnpaidAmount, // PR Submitted & Approved yang belum dibuat PO
                    'po_unpaid' => $poUnpaidAmount, // PO Submitted & Approved yang belum dibuat NFP
                    'nfp_submitted' => $nfpSubmittedAmount, // NFP Submitted
                    'nfp_approved' => $nfpApprovedAmount, // NFP Approved (unpaid)
                    'nfp_paid' => $nfpPaidAmount, // NFP Paid
                    'retail_non_food' => $outletRetailNonFoodApproved, // Retail Non Food Approved
                ],
            ];
        }

        return $budgetInfo;
    }
}
