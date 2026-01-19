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
use App\Services\NotificationService;
use App\Services\BudgetCalculationService;
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
        // Check regardless of PR status - if all items are in PO, exclude the PR
        $fullyProcessedPRs = DB::table('purchase_requisitions as pr')
            ->join('purchase_requisition_items as items', 'pr.id', '=', 'items.purchase_requisition_id')
            ->leftJoin('purchase_order_ops_items as po_items', 'items.id', '=', 'po_items.pr_ops_item_id')
            ->whereIn('pr.status', ['APPROVED', 'PROCESSED', 'COMPLETED', 'PAID']) // Include all active statuses
            ->where('pr.mode', 'pr_ops') // Only PR Ops mode
            ->groupBy('pr.id')
            ->havingRaw('COUNT(items.id) = COUNT(po_items.id)')
            ->pluck('pr.id')
            ->toArray();

        // Get available PRs that are approved and not fully processed
        // Note: PRs that are on hold will still be shown but cannot be expanded/selected
        // We need to show PRs that still have at least one item not in PO
        // So we'll get all PRs first, then filter items in the mapping
        $prs = DB::table('purchase_requisitions as pr')
            ->join('purchase_requisition_items as items', 'pr.id', '=', 'items.purchase_requisition_id')
            ->leftJoin('tbl_data_divisi as d', 'pr.division_id', '=', 'd.id')
            ->leftJoin('tbl_data_outlet as o', 'items.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('tbl_data_outlet as pr_o', 'pr.outlet_id', '=', 'pr_o.id_outlet') // PR outlet for fallback
            ->leftJoin('purchase_requisition_categories as c', 'items.category_id', '=', 'c.id')
            ->leftJoin('purchase_requisition_categories as pr_c', 'pr.category_id', '=', 'pr_c.id') // PR category for fallback
            ->whereIn('pr.status', ['APPROVED', 'PROCESSED', 'COMPLETED', 'PAID']) // Include PRs that are approved or already processed/paid but still have items not in PO
            ->where('pr.mode', 'pr_ops') // Only PR Ops mode
            ->whereNotIn('pr.id', $fullyProcessedPRs) // Exclude fully processed PRs (all items already in PO)
            // Don't exclude items here - we'll filter them in the mapping to show PR even if some items are already in PO
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
            ->groupBy(function ($item) {
                return $item->id; // Group by PR ID
            })
            ->filter(function ($group) use ($poPrItemIds) {
                // Only include PRs that have at least one item not in PO
                return $group->filter(function ($item) use ($poPrItemIds) {
                    return !in_array($item->item_id, $poPrItemIds);
                })->count() > 0;
            })
            ->map(function ($group) use ($poPrItemIds) {
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
                    'items' => $group->filter(function ($item) use ($poPrItemIds) {
                        // Filter out items that are already in PO
                        return !in_array($item->item_id, $poPrItemIds);
                    })->map(function ($item) use ($first, $prOutlet, $prCategory) {
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
                        'outlet_id' => $prItem->outlet_id,
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
            'items.prOpsItem.purchaseRequisition',
            'items.prOpsItem.outlet',
            'items.prOpsItem.category', // Category sudah memiliki division field (string), tidak perlu eager load relationship
            'purchasing_manager',
            'gm_finance',
            'source_pr.category', // Category sudah memiliki division field (string), tidak perlu eager load relationship
            'source_pr.division',
            'source_pr.outlet',
            'source_pr.creator',
            'source_pr.items' => function($query) {
                $query->with(['category', 'outlet']); // Category sudah memiliki division field (string), tidak perlu eager load relationship
            },
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
        // For PR Ops, category_id is at items level, not PR level
        // So we need to get category_id from first item to determine budget type
        $budgetInfo = null;
        if ($po->source_type === 'purchase_requisition_ops' && $po->source_id && $po->source_pr) {
            try {
                \Log::info("Getting budget info for PO in show method", [
                    'po_id' => $po->id,
                    'source_pr_id' => $po->source_pr->id ?? 'N/A',
                    'is_api_request' => request()->wantsJson() || request()->is('api/*'),
                ]);
                
                // For PR Ops, try to get category_id from first item
                $firstCategoryId = null;
                $firstOutletId = null;
                foreach ($po->items as $item) {
                    if ($item->pr_ops_item_id && $item->prOpsItem) {
                        $firstCategoryId = $item->prOpsItem->category_id;
                        $firstOutletId = $item->prOpsItem->outlet_id;
                        break;
                    }
                }
                
                // If we have category_id from items, use it to get budget info
                if ($firstCategoryId) {
                    $budgetInfo = $this->getBudgetInfo($po->source_pr, $firstOutletId, $firstCategoryId, $po->id);
                } else {
                    // Fallback: try without override (for old structure PRs)
                    $budgetInfo = $this->getBudgetInfo($po->source_pr, null, null, $po->id);
                }
                
                \Log::info("Budget info retrieved in show method", [
                    'has_budget_info' => !is_null($budgetInfo),
                    'budget_type' => $budgetInfo['budget_type'] ?? 'N/A',
                    'first_category_id' => $firstCategoryId,
                    'first_outlet_id' => $firstOutletId,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to get budget info for PR ' . $po->source_pr->id . ': ' . $e->getMessage());
                $budgetInfo = null;
            }
        }

        // Check if this is an API request (for modal)
        if (request()->wantsJson() || request()->is('api/*')) {
            // For PER_OUTLET budget type, calculate budget info per outlet+category for each item
            // Only calculate this for API requests to avoid unnecessary processing for web
            // Initialize as array (will be converted to object in JSON at the end if needed)
            $itemsBudgetInfo = [];
            
            \Log::info("Checking if should calculate per-outlet budget", [
                'has_budget_info' => !is_null($budgetInfo),
                'budget_type' => $budgetInfo['budget_type'] ?? 'N/A',
                'has_source_pr' => !is_null($po->source_pr),
                'is_api_request' => request()->wantsJson() || request()->is('api/*'),
            ]);
            
            // For GLOBAL budget type, we still need to provide itemsBudgetInfo structure
            // but it will be empty or contain single entry
            if ($budgetInfo && isset($budgetInfo['budget_type']) && $po->source_pr) {
                if ($budgetInfo['budget_type'] === 'PER_OUTLET') {
                // Get unique outlet+category combinations from items
                $outletCategoryCombos = [];
                foreach ($po->items as $item) {
                    if ($item->pr_ops_item_id && $item->prOpsItem) {
                        $prItem = $item->prOpsItem;
                        $outletId = $prItem->outlet_id;
                        $categoryId = $prItem->category_id;
                        
                        \Log::info("Processing PO item for budget calculation", [
                            'po_item_id' => $item->id ?? 'N/A',
                            'pr_ops_item_id' => $item->pr_ops_item_id,
                            'outlet_id' => $outletId,
                            'category_id' => $categoryId,
                        ]);
                        
                        if ($outletId && $categoryId) {
                            $key = "{$outletId}_{$categoryId}";
                            if (!isset($outletCategoryCombos[$key])) {
                                $outletCategoryCombos[$key] = [
                                    'outlet_id' => $outletId,
                                    'category_id' => $categoryId,
                                ];
                            }
                        } else {
                            \Log::warning("PO item missing outlet_id or category_id", [
                                'po_item_id' => $item->id ?? 'N/A',
                                'outlet_id' => $outletId,
                                'category_id' => $categoryId,
                            ]);
                        }
                    }
                }
                
                \Log::info("Outlet+Category combinations found", [
                    'combos' => $outletCategoryCombos,
                    'count' => count($outletCategoryCombos),
                ]);
                
            // Calculate budget info for each outlet+category combination
            foreach ($outletCategoryCombos as $key => $combo) {
                try {
                    // Use source_pr as base and override outlet_id and category_id
                    $basePr = $po->source_pr;
                    $overrideOutletId = $combo['outlet_id'] ?? null;
                    $overrideCategoryId = $combo['category_id'] ?? null;
                    
                    \Log::info("Calculating budget for outlet+category combo", [
                        'key' => $key,
                        'outlet_id' => $overrideOutletId,
                        'category_id' => $overrideCategoryId,
                        'base_pr_id' => $basePr->id ?? 'N/A',
                    ]);
                    
                    $itemBudgetInfo = $this->getBudgetInfo(
                        $basePr,
                        $overrideOutletId,
                        $overrideCategoryId,
                        $po->id // Exclude current PO to avoid double counting
                    );
                    if ($itemBudgetInfo) {
                        // Convert to array if it's a stdClass object
                        if (is_object($itemBudgetInfo) && !is_array($itemBudgetInfo)) {
                            $itemBudgetInfo = (array) $itemBudgetInfo;
                        }
                        
                        // Calculate current amount for this outlet+category from PO items
                        $currentAmount = 0;
                        foreach ($po->items as $poItem) {
                            if ($poItem->pr_ops_item_id && $poItem->prOpsItem) {
                                $prItem = $poItem->prOpsItem;
                                if ($prItem->outlet_id == $combo['outlet_id'] && 
                                    $prItem->category_id == $combo['category_id']) {
                                    $currentAmount += $poItem->total ?? 0;
                                }
                            }
                        }
                        
                        // Calculate real remaining budget (considering current PO amount)
                        // Since we excluded current PO from poUnpaidAmount, we need to add it back
                        if (isset($itemBudgetInfo['outlet_budget']) && isset($itemBudgetInfo['outlet_used_amount'])) {
                            $outletBudget = $itemBudgetInfo['outlet_budget'];
                            $outletUsedAmount = $itemBudgetInfo['outlet_used_amount'];
                            // Add current PO amount to used amount (since we excluded it from calculation)
                            $totalWithCurrent = $outletUsedAmount + $currentAmount;
                            $itemBudgetInfo['real_remaining_budget'] = $outletBudget - $totalWithCurrent;
                            $itemBudgetInfo['current_amount'] = $currentAmount;
                        }
                        
                        $itemsBudgetInfo[$key] = $itemBudgetInfo;
                    }
                } catch (\Exception $e) {
                    \Log::warning("Failed to get budget info for outlet {$combo['outlet_id']} category {$combo['category_id']}: " . $e->getMessage());
                }
            }
                }
            }
            
            // Ensure itemsBudgetInfo is always an object (not array) for JSON response
            // Convert empty array to empty object
            if (is_array($itemsBudgetInfo) && empty($itemsBudgetInfo)) {
                $itemsBudgetInfo = (object)[];
            }
            
            // Map approval flows to ensure status and dates are correctly formatted
            // Use the relationship directly to ensure data is correctly loaded
            $poData = $po->toArray();
            if ($po->relationLoaded('approvalFlows') && $po->approvalFlows) {
                $poData['approval_flows'] = $po->approvalFlows->map(function($flow) {
                    return [
                        'id' => $flow->id,
                        'approval_level' => $flow->approval_level,
                        'status' => $flow->status, // Status from database (APPROVED, PENDING, REJECTED)
                        'approved_at' => $flow->approved_at ? $flow->approved_at->format('Y-m-d H:i:s') : null,
                        'rejected_at' => $flow->rejected_at ? $flow->rejected_at->format('Y-m-d H:i:s') : null,
                        'comments' => $flow->comments,
                        'approver' => $flow->approver ? [
                            'id' => $flow->approver->id,
                            'nama_lengkap' => $flow->approver->nama_lengkap,
                        ] : null,
                    ];
                })->toArray();
            }
            
            return response()->json([
                'success' => true,
                'po' => $poData,
                'user' => $userData,
                'budgetInfo' => $budgetInfo, // Use camelCase for consistency with Flutter
                'itemsBudgetInfo' => $itemsBudgetInfo // Use camelCase for consistency with Flutter - always object, never array
            ]);
        }

        // For web (Inertia), return as before - no changes to web functionality
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
        $data = [];
        foreach ($userIds as $uid) {
            $data[] = [
                'user_id' => $uid,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
            ];
        }
        NotificationService::createMany($data);
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
        $currentApprover = Auth::user();
        $approverId = Auth::id();

        // Superadmin: user dengan id_role = '5af56935b011a' bisa approve semua level
        $isSuperadmin = $currentApprover && $currentApprover->id_role === '5af56935b011a';

        // Find current approval flow
        $approvalFlow = null;
        if ($isSuperadmin) {
            // Superadmin can approve any pending level - approve the next pending level
            $approvalFlow = PurchaseOrderOpsApprovalFlow::where('purchase_order_ops_id', $po->id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
        } else {
            // Regular users: only their own pending approval flow
            $approvalFlow = PurchaseOrderOpsApprovalFlow::where('purchase_order_ops_id', $po->id)
                ->where('approver_id', $approverId)
                ->where('status', 'PENDING')
                ->first();
        }

        if (!$approvalFlow) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak untuk approve PO ini'
            ], 403);
        }

        $request->validate([
            'approved' => 'required|boolean',
            'comments' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000', // Alias for comments
        ]);

        try {
            DB::beginTransaction();

            // Support both 'comments' and 'comment' parameters
            $comments = $request->input('comments') ?? $request->input('comment');

            // Update approval flow
            // For superadmin, update approver_id to superadmin to track who actually approved
            $updateData = [
                'status' => $request->approved ? 'APPROVED' : 'REJECTED',
                'approved_at' => $request->approved ? now() : null,
                'rejected_at' => !$request->approved ? now() : null,
                'comments' => $comments,
            ];
            
            if ($isSuperadmin) {
                $updateData['approver_id'] = $approverId; // Update approver_id to superadmin
            }
            
            $approvalFlow->update($updateData);

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
                if ($comments) {
                    $rejectionMessage .= "• Alasan: {$comments}\n";
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
        $user = Auth::user();
        $userId = Auth::id();
        
        // Superadmin: user dengan id_role = '5af56935b011a' bisa melihat semua approval
        $isSuperadmin = $user && $user->id_role === '5af56935b011a';
        
        // Only include POs where:
        // - There is a PENDING step for current user (or all if superadmin)
        // - No approval flow is REJECTED (stop chain)
        // - Overall PO status is not rejected/cancelled/approved/received
        $query = PurchaseOrderOps::whereNotIn('status', ['rejected', 'approved', 'received', 'cancelled'])
            ->whereDoesntHave('approvalFlows', function($q) {
                $q->where('status', 'REJECTED');
            });
        
        if ($isSuperadmin) {
            // Superadmin can see all pending POs
            $pendingPOs = $query->whereHas('approvalFlows', function($query) {
                $query->where('status', 'PENDING');
            });
        } else {
            // Regular users: only POs where they are the approver
            $pendingPOs = $query->whereHas('approvalFlows', function($query) use ($userId) {
                $query->where('approver_id', $userId)
                      ->where('status', 'PENDING');
            });
        }
        
        $pendingPOs = $pendingPOs->with([
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
        // Skip this filter for superadmin - they can see all pending approvals
        if (!$isSuperadmin) {
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
            });
        }
        
        $pendingPOs = $pendingPOs->map(function ($po) {
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
                'purchase_requisition.items' => function($query) {
                    $query->with(['category', 'outlet']); // Category sudah memiliki division field (string), tidak perlu eager load relationship
                },
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
     * PENTING: Menggunakan BudgetCalculationService untuk konsistensi dengan OpexReport
     */
    private function getBudgetInfo($purchaseRequisition, $overrideOutletId = null, $overrideCategoryId = null, $excludePoId = null)
    {
        \Log::info("getBudgetInfo called", [
            'pr_id' => $purchaseRequisition->id ?? 'N/A',
            'overrideOutletId' => $overrideOutletId,
            'overrideCategoryId' => $overrideCategoryId,
        ]);
        
        // Get category and outlet from PR items (new structure) or PR level (old structure)
        $categoryId = $overrideCategoryId;
        $outletId = $overrideOutletId;
        
        // If not provided, try to get from PR items first (new structure)
        if (!$categoryId || !$outletId) {
            $prItems = $purchaseRequisition->items ?? collect();
            if ($prItems->count() > 0) {
                $firstItem = $prItems->first();
                if (!$categoryId) {
                    $categoryId = $firstItem->category_id ?? $purchaseRequisition->category_id;
                }
                if (!$outletId) {
                    $outletId = $firstItem->outlet_id ?? $purchaseRequisition->outlet_id;
                }
            } else {
                // Fallback to PR level (old structure)
                if (!$categoryId) {
                    $categoryId = $purchaseRequisition->category_id;
                }
                if (!$outletId) {
                    $outletId = $purchaseRequisition->outlet_id;
                }
            }
        }
        
        $currentAmount = $purchaseRequisition->amount;
        $year = $purchaseRequisition->created_at->year;
        $month = $purchaseRequisition->created_at->month;

        if (!$categoryId) {
            return null;
        }

        // Calculate date range for the month (BUDGET IS MONTHLY)
        $dateFrom = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
        $dateTo = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
        
        // PENTING: Gunakan BudgetCalculationService untuk konsistensi dengan OpexReport
        $budgetService = new BudgetCalculationService();
        $budgetInfoResult = $budgetService->getBudgetInfo(
            categoryId: $categoryId,
            outletId: $outletId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            currentAmount: $currentAmount
        );
        
        if (!$budgetInfoResult['success']) {
            \Log::warning('Failed to get budget info from BudgetCalculationService: ' . ($budgetInfoResult['message'] ?? 'Unknown error'));
            return null;
        }
        
        // Convert BudgetCalculationService result to format expected by frontend
        $budgetInfo = [
            'budget_type' => $budgetInfoResult['budget_type'],
            'current_year' => $year,
            'current_month' => $month,
            'category_budget' => $budgetInfoResult['category_budget'],
            'category_used_amount' => $budgetInfoResult['category_used_amount'] ?? 0,
            'category_remaining_amount' => $budgetInfoResult['category_remaining_amount'] ?? 0,
            'breakdown' => $budgetInfoResult['breakdown'] ?? [],
        ];
        
        // Add outlet-specific fields for PER_OUTLET budget
        if ($budgetInfoResult['budget_type'] === 'PER_OUTLET') {
            $budgetInfo['outlet_budget'] = $budgetInfoResult['outlet_budget'] ?? 0;
            $budgetInfo['outlet_used_amount'] = $budgetInfoResult['outlet_used_amount'] ?? 0;
            $budgetInfo['outlet_remaining_amount'] = $budgetInfoResult['outlet_remaining_amount'] ?? 0;
            $budgetInfo['real_remaining_budget'] = $budgetInfoResult['outlet_remaining_amount'] ?? 0;
            $budgetInfo['outlet_info'] = $budgetInfoResult['outlet_info'] ?? null;
        }
        
        return $budgetInfo;
    }
}
