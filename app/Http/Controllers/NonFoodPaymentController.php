<?php

namespace App\Http\Controllers;

use App\Models\NonFoodPayment;
use App\Models\PurchaseOrderOps;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class NonFoodPaymentController extends Controller
{
    public function index(Request $request)
    {
        // Get filter parameters
        $supplier = $request->input('supplier');
        $status = $request->input('status');
        $date = $request->input('date');
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10);

        // Build query with search and filters
        $query = NonFoodPayment::query()
            ->leftJoin('suppliers as s', 'non_food_payments.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'non_food_payments.created_by', '=', 'u.id')
            ->leftJoin('purchase_order_ops as poo', 'non_food_payments.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_requisitions as pr', 'non_food_payments.purchase_requisition_id', '=', 'pr.id')
            ->select(
                'non_food_payments.*',
                's.name as supplier_name',
                'u.nama_lengkap as creator_name',
                'poo.number as po_number',
                'poo.date as po_date',
                'pr.pr_number as pr_number',
                'pr.date as pr_date'
            );

        // Apply filters
        if ($supplier) {
            $query->where('non_food_payments.supplier_id', $supplier);
        }
        
        if ($status) {
            $query->where('non_food_payments.status', $status);
        }
        
        if ($date) {
            $query->whereDate('non_food_payments.payment_date', $date);
        }

        // Apply search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('non_food_payments.payment_number', 'like', "%{$search}%")
                  ->orWhere('s.name', 'like', "%{$search}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('poo.number', 'like', "%{$search}%")
                  ->orWhere('pr.pr_number', 'like', "%{$search}%");
            });
        }

        $payments = $query->latest('non_food_payments.payment_date')->paginate($perPage)->withQueryString();
        
        // Transform payments to show per outlet
        $payments->getCollection()->transform(function($payment) {
            if ($payment->purchase_order_ops_id) {
                $payment->payment_type = 'PO';
                
                // Get outlet breakdown for PO payments
                try {
                    $outletBreakdown = DB::table('purchase_order_ops_items as poi')
                        ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                        ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
                        ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
                        ->where('poi.purchase_order_ops_id', $payment->purchase_order_ops_id)
                        ->select(
                            'pr.outlet_id',
                            'o.nama_outlet as outlet_name',
                            'prc.name as category_name',
                            'prc.division as category_division',
                            'prc.subcategory as category_subcategory',
                            'prc.budget_type as category_budget_type',
                            'pr.pr_number',
                            'pr.title as pr_title',
                            DB::raw('SUM(poi.total) as outlet_total')
                        )
                        ->groupBy('pr.outlet_id', 'o.nama_outlet', 'prc.name', 'prc.division', 'prc.subcategory', 'prc.budget_type', 'pr.pr_number', 'pr.title')
                        ->get();
                    
                    $payment->outlet_breakdown = $outletBreakdown;
                } catch (\Exception $e) {
                    // Fallback if outlet data not available
                    $payment->outlet_breakdown = collect([[
                        'outlet_id' => null,
                        'outlet_name' => 'Unknown Outlet',
                        'category_name' => null,
                        'category_division' => null,
                        'category_subcategory' => null,
                        'category_budget_type' => null,
                        'pr_number' => null,
                        'pr_title' => null,
                        'outlet_total' => $payment->amount
                    ]]);
                }
            } else {
                $payment->payment_type = 'PR';
                // For PR payments, show as single outlet
                $payment->outlet_breakdown = collect([[
                    'outlet_id' => null,
                    'outlet_name' => 'Direct PR Payment',
                    'category_name' => null,
                    'category_division' => null,
                    'category_subcategory' => null,
                    'category_budget_type' => null,
                    'pr_number' => $payment->pr_number,
                    'pr_title' => null,
                    'outlet_total' => $payment->amount
                ]]);
            }
            return $payment;
        });

        // Get suppliers for filter dropdown
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('NonFoodPayment/Index', [
            'payments' => $payments,
            'suppliers' => $suppliers,
            'filters' => $request->only(['supplier', 'status', 'date', 'search', 'per_page'])
        ]);
    }

    public function create(Request $request)
    {
        // Get filter parameters
        $supplierId = $request->input('supplier_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Get suppliers
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Get available Purchase Order Ops that don't have payments yet
        $poQuery = DB::table('purchase_order_ops as poo')
            ->leftJoin('suppliers as s', 'poo.supplier_id', '=', 's.id')
            ->leftJoin('purchase_requisitions as pr', 'poo.source_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('non_food_payments as nfp', function($join) {
                $join->on('poo.id', '=', 'nfp.purchase_order_ops_id')
                     ->where('nfp.status', '!=', 'cancelled');
            })
            ->where('poo.status', 'approved')
            ->whereNull('nfp.id')
            ->select(
                'poo.id',
                'poo.number',
                'poo.date',
                'poo.grand_total',
                'poo.supplier_id',
                'poo.notes',
                's.name as supplier_name',
                'pr.pr_number as source_pr_number',
                'pr.title as pr_title',
                'pr.description as pr_description',
                'pr.is_held',
                'pr.hold_reason',
                'o.nama_outlet as pr_outlet_name'
            );

        // Apply filters
        if ($supplierId) {
            $poQuery->where('poo.supplier_id', $supplierId);
        }
        if ($dateFrom) {
            $poQuery->whereDate('poo.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $poQuery->whereDate('poo.date', '<=', $dateTo);
        }

        $availablePOs = $poQuery->orderBy('poo.date', 'desc')
            ->limit(50)
            ->get();

        // Get available Purchase Requisitions (mode purchase_payment, travel_application, kasbon) that don't have payments yet
        $prQuery = DB::table('purchase_requisitions as pr')
            ->leftJoin('non_food_payments as nfp', function($join) {
                $join->on('pr.id', '=', 'nfp.purchase_requisition_id')
                     ->where('nfp.status', '!=', 'cancelled');
            })
            ->where('pr.status', 'APPROVED')
            ->whereIn('pr.mode', ['purchase_payment', 'travel_application', 'kasbon'])
            ->whereNull('nfp.id')
            ->select(
                'pr.id',
                'pr.pr_number',
                'pr.date',
                'pr.amount',
                'pr.title',
                'pr.description',
                'pr.is_held',
                'pr.hold_reason',
                'pr.division_id'
            );

        // Apply filters
        if ($dateFrom) {
            $prQuery->whereDate('pr.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $prQuery->whereDate('pr.date', '<=', $dateTo);
        }

        $availablePRs = $prQuery->orderBy('pr.date', 'desc')
            ->limit(50)
            ->get();

        return Inertia::render('NonFoodPayment/Create', [
            'suppliers' => $suppliers,
            'availablePOs' => $availablePOs,
            'availablePRs' => $availablePRs,
            'filters' => $request->only(['supplier_id', 'date_from', 'date_to'])
        ]);
    }

    public function getPOItems($poId)
    {
        try {
            // Get basic PO info first
            $po = DB::table('purchase_order_ops as poo')
                ->leftJoin('suppliers as s', 'poo.supplier_id', '=', 's.id')
                ->where('poo.id', $poId)
                ->select(
                    'poo.*',
                    's.name as supplier_name'
                )
                ->first();

            // Get PO attachments with description from purchase_order_ops
            $poAttachments = [];
            try {
                $poAttachments = DB::table('purchase_order_ops_attachments as pooa')
                    ->where('pooa.purchase_order_ops_id', $poId)
                    ->select(
                        'pooa.id', 
                        'pooa.file_name', 
                        'pooa.file_path', 
                        'pooa.mime_type as file_type', 
                        'pooa.file_size', 
                        'pooa.created_at'
                    )
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                // Table might not exist, continue without attachments
            }

            if (!$po) {
                return response()->json(['error' => 'Purchase Order not found'], 404);
            }

            // Try to get PR info if table exists
            try {
                $prInfo = DB::table('purchase_requisitions as pr')
                    ->leftJoin('tbl_data_divisi as d', 'pr.division_id', '=', 'd.id')
                    ->where('pr.id', $po->source_id)
                    ->select(
                        'pr.pr_number as source_pr_number',
                        'pr.title as pr_title',
                        'pr.description as pr_description',
                        'd.nama_divisi as division_name'
                    )
                    ->first();
                
                if ($prInfo) {
                    $po->source_pr_number = $prInfo->source_pr_number;
                    $po->pr_title = $prInfo->pr_title;
                    $po->pr_description = $prInfo->pr_description;
                    $po->division_name = $prInfo->division_name;
                    
                    // Get PR attachments with description from purchase_requisitions
                    try {
                        $prAttachments = DB::table('purchase_requisition_attachments as pra')
                            ->leftJoin('purchase_requisitions as pr', 'pra.purchase_requisition_id', '=', 'pr.id')
                            ->where('pra.purchase_requisition_id', $po->source_id)
                            ->select(
                                'pra.id', 
                                'pra.file_name', 
                                'pra.file_path', 
                                'pra.mime_type as file_type', 
                                'pra.file_size', 
                                'pra.created_at',
                                'pr.description as pr_description'
                            )
                            ->get()
                            ->toArray();
                    } catch (\Exception $e) {
                        // Table might not exist, continue without attachments
                    }
                }
            } catch (\Exception $e) {
                // PR table might not exist, continue without PR info
            }

            // Get items with outlet and category info
            try {
                $items = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
                    ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
                    ->where('poi.purchase_order_ops_id', $poId)
                    ->select(
                        'poi.id',
                        'poi.item_name',
                        'poi.quantity',
                        'poi.unit',
                        'poi.price',
                        'poi.total',
                        'pr.id as pr_id',
                        'pr.outlet_id',
                        'o.nama_outlet as outlet_name',
                        'pr.category_id',
                        'prc.name as category_name',
                        'prc.division as category_division',
                        'prc.subcategory as category_subcategory',
                        'prc.budget_type as category_budget_type',
                        'pr.pr_number',
                        'pr.title as pr_title'
                    )
                    ->get();
            } catch (\Exception $e) {
                // Fallback to basic items without outlet info
                $items = DB::table('purchase_order_ops_items as poi')
                    ->where('poi.purchase_order_ops_id', $poId)
                    ->select(
                        'poi.id',
                        'poi.item_name',
                        'poi.quantity',
                        'poi.unit',
                        'poi.price',
                        'poi.total'
                    )
                    ->get();
                
                // Add default values for outlet info
                $items = $items->map(function ($item) {
                    $item->outlet_id = null;
                    $item->outlet_name = 'Unknown Outlet';
                    $item->category_id = null;
                    $item->category_name = null;
                    $item->category_division = null;
                    $item->category_subcategory = null;
                    $item->category_budget_type = null;
                    $item->pr_number = null;
                    $item->pr_title = null;
                    return $item;
                });
            }

            // Group items by outlet and add PR attachments and description
            $itemsByOutlet = $items->groupBy('outlet_id')->map(function ($outletItems, $outletId) {
                $firstItem = $outletItems->first();
                $prId = $firstItem->pr_id ?? null;
                
                // Get PR attachments for this specific PR
                $prAttachments = [];
                $prDescription = null;
                if ($prId) {
                    try {
                        $prAttachments = DB::table('purchase_requisition_attachments as pra')
                            ->leftJoin('purchase_requisitions as pr', 'pra.purchase_requisition_id', '=', 'pr.id')
                            ->where('pra.purchase_requisition_id', $prId)
                            ->select(
                                'pra.id', 
                                'pra.file_name', 
                                'pra.file_path', 
                                'pra.mime_type as file_type', 
                                'pra.file_size', 
                                'pra.created_at',
                                'pr.description as pr_description'
                            )
                            ->get()
                            ->toArray();
                        
                        // Get PR description
                        $prInfo = DB::table('purchase_requisitions')
                            ->where('id', $prId)
                            ->select('description')
                            ->first();
                        $prDescription = $prInfo ? $prInfo->description : null;
                    } catch (\Exception $e) {
                        // Continue without attachments if error
                    }
                }
                
                return [
                    'outlet_id' => $outletId,
                    'outlet_name' => $firstItem->outlet_name ?? 'Unknown Outlet',
                    'category_id' => $firstItem->category_id ?? null,
                    'category_name' => $firstItem->category_name ?? null,
                    'category_division' => $firstItem->category_division ?? null,
                    'category_subcategory' => $firstItem->category_subcategory ?? null,
                    'category_budget_type' => $firstItem->category_budget_type ?? null,
                    'pr_number' => $firstItem->pr_number ?? null,
                    'pr_title' => $firstItem->pr_title ?? null,
                    'pr_description' => $prDescription,
                    'pr_attachments' => $prAttachments,
                    'items' => $outletItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'item_name' => $item->item_name,
                            'quantity' => $item->quantity,
                            'unit' => $item->unit,
                            'price' => $item->price,
                            'total' => $item->total,
                        ];
                    }),
                    'subtotal' => $outletItems->sum('total')
                ];
            });

            return response()->json([
                'po' => $po,
                'items_by_outlet' => $itemsByOutlet,
                'total_amount' => $items->sum('total'),
                'po_attachments' => $poAttachments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load PO items: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPRItems($prId)
    {
        try {
            // Get basic PR info
            $pr = DB::table('purchase_requisitions as pr')
                ->leftJoin('tbl_data_divisi as d', 'pr.division_id', '=', 'd.id')
                ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
                ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
                ->where('pr.id', $prId)
                ->select(
                    'pr.*',
                    'd.nama_divisi as division_name',
                    'o.nama_outlet as outlet_name',
                    'prc.name as category_name',
                    'prc.division as category_division',
                    'prc.subcategory as category_subcategory',
                    'prc.budget_type as category_budget_type'
                )
                ->first();

            if (!$pr) {
                return response()->json(['error' => 'Purchase Requisition not found'], 404);
            }

            // Get PR items with outlet and category info (for new structure)
            $items = DB::table('purchase_requisition_items as pri')
                ->leftJoin('tbl_data_outlet as o', 'pri.outlet_id', '=', 'o.id_outlet')
                ->leftJoin('purchase_requisition_categories as prc', 'pri.category_id', '=', 'prc.id')
                ->where('pri.purchase_requisition_id', $prId)
                ->select(
                    'pri.id',
                    'pri.item_name',
                    'pri.qty as quantity',
                    'pri.unit',
                    'pri.unit_price as price',
                    'pri.subtotal as total',
                    'pri.outlet_id',
                    'pri.category_id',
                    'pri.item_type',
                    'pri.allowance_recipient_name',
                    'pri.allowance_account_number',
                    'pri.others_notes',
                    'o.nama_outlet as item_outlet_name',
                    'prc.name as item_category_name',
                    'prc.division as item_category_division',
                    'prc.subcategory as item_category_subcategory',
                    'prc.budget_type as item_category_budget_type'
                )
                ->get();

            // Get PR attachments with outlet info (for new structure)
            $prAttachments = DB::table('purchase_requisition_attachments as pra')
                ->leftJoin('users as u', 'pra.uploaded_by', '=', 'u.id')
                ->leftJoin('tbl_data_outlet as o', 'pra.outlet_id', '=', 'o.id_outlet')
                ->where('pra.purchase_requisition_id', $prId)
                ->select(
                    'pra.id',
                    'pra.file_name',
                    'pra.file_path',
                    'pra.mime_type as file_type',
                    'pra.file_size',
                    'pra.created_at',
                    'pra.outlet_id as attachment_outlet_id',
                    'u.nama_lengkap as uploader_name',
                    'o.nama_outlet as attachment_outlet_name'
                )
                ->get();

            // Check if this is new structure (items have outlet_id/category_id) or old structure
            $hasNewStructure = $items->whereNotNull('outlet_id')->count() > 0 || 
                              $items->whereNotNull('category_id')->count() > 0;
            
            $itemsByOutlet = [];
            
            if ($hasNewStructure && in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
                // New structure: Group items by outlet and category
                $grouped = $items->groupBy(function($item) {
                    $outletId = $item->outlet_id ?? 'no-outlet';
                    $categoryId = $item->category_id ?? 'no-category';
                    return $outletId . '-' . $categoryId;
                });
                
                foreach ($grouped as $key => $groupItems) {
                    $firstItem = $groupItems->first();
                    $outletId = $firstItem->outlet_id ?? $pr->outlet_id ?? 'global';
                    $categoryId = $firstItem->category_id ?? $pr->category_id;
                    
                    // Get outlet name
                    $outletName = $firstItem->item_outlet_name ?? $pr->outlet_name ?? 'Global / All Outlets';
                    
                    // Get category info
                    $categoryName = $firstItem->item_category_name ?? $pr->category_name;
                    $categoryDivision = $firstItem->item_category_division ?? $pr->category_division;
                    $categorySubcategory = $firstItem->item_category_subcategory ?? $pr->category_subcategory;
                    $categoryBudgetType = $firstItem->item_category_budget_type ?? $pr->category_budget_type;
                    
                    // Get attachments for this outlet
                    $outletAttachments = $prAttachments->where('attachment_outlet_id', $outletId)->values();
                    
                    $itemsByOutlet[$outletId . '-' . $categoryId] = [
                        'outlet_id' => $outletId,
                        'outlet_name' => $outletName,
                        'category_id' => $categoryId,
                        'category_name' => $categoryName,
                        'category_division' => $categoryDivision,
                        'category_subcategory' => $categorySubcategory,
                        'category_budget_type' => $categoryBudgetType,
                        'pr_number' => $pr->pr_number,
                        'pr_title' => $pr->title,
                        'pr_description' => $pr->description,
                        'pr_attachments' => $outletAttachments->map(function ($attachment) {
                            return [
                                'id' => $attachment->id,
                                'file_name' => $attachment->file_name,
                                'file_path' => $attachment->file_path,
                                'file_type' => $attachment->file_type,
                                'file_size' => $attachment->file_size,
                                'created_at' => $attachment->created_at,
                                'uploader_name' => $attachment->uploader_name
                            ];
                        }),
                        'items' => $groupItems->map(function ($item) {
                            // Format item_name for allowance type
                            $itemName = $item->item_name;
                            if ($item->item_type === 'allowance' && $item->allowance_recipient_name && $item->allowance_account_number) {
                                $itemName = 'Allowance - ' . $item->allowance_recipient_name . ' - ' . $item->allowance_account_number;
                            } elseif ($item->item_type === 'allowance' && $item->allowance_recipient_name) {
                                $itemName = 'Allowance - ' . $item->allowance_recipient_name;
                            }
                            
                            return [
                                'id' => $item->id,
                                'item_name' => $itemName,
                                'quantity' => $item->quantity,
                                'unit' => $item->unit,
                                'price' => $item->price,
                                'total' => $item->total,
                                'item_type' => $item->item_type,
                                'allowance_recipient_name' => $item->allowance_recipient_name,
                                'allowance_account_number' => $item->allowance_account_number,
                            ];
                        }),
                        'subtotal' => $groupItems->sum('total')
                    ];
                }
            } else {
                // Old structure or other modes: Group by main PR outlet/category
                $outletId = $pr->outlet_id ?? 'global';
                $outletName = $pr->outlet_name ?? 'Global / All Outlets';
                
                // Get all attachments (for old structure, no outlet_id in attachments)
                $allAttachments = $prAttachments->values();
                
                $itemsByOutlet[$outletId] = [
                    'outlet_id' => $pr->outlet_id,
                    'outlet_name' => $outletName,
                    'category_id' => $pr->category_id,
                    'category_name' => $pr->category_name,
                    'category_division' => $pr->category_division,
                    'category_subcategory' => $pr->category_subcategory,
                    'category_budget_type' => $pr->category_budget_type,
                    'pr_number' => $pr->pr_number,
                    'pr_title' => $pr->title,
                    'pr_description' => $pr->description,
                    'pr_attachments' => $allAttachments->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'file_name' => $attachment->file_name,
                            'file_path' => $attachment->file_path,
                            'file_type' => $attachment->file_type,
                            'file_size' => $attachment->file_size,
                            'created_at' => $attachment->created_at,
                            'uploader_name' => $attachment->uploader_name
                        ];
                    }),
                    'items' => $items->map(function ($item) {
                        // Format item_name for allowance type
                        $itemName = $item->item_name;
                        if (isset($item->item_type) && $item->item_type === 'allowance' && $item->allowance_recipient_name && $item->allowance_account_number) {
                            $itemName = 'Allowance - ' . $item->allowance_recipient_name . ' - ' . $item->allowance_account_number;
                        } elseif (isset($item->item_type) && $item->item_type === 'allowance' && $item->allowance_recipient_name) {
                            $itemName = 'Allowance - ' . $item->allowance_recipient_name;
                        }
                        
                        return [
                            'id' => $item->id,
                            'item_name' => $itemName,
                            'quantity' => $item->quantity,
                            'unit' => $item->unit,
                            'price' => $item->price,
                            'total' => $item->total,
                            'item_type' => $item->item_type ?? null,
                            'allowance_recipient_name' => $item->allowance_recipient_name ?? null,
                            'allowance_account_number' => $item->allowance_account_number ?? null,
                        ];
                    }),
                    'subtotal' => $items->sum('total')
                ];
            }

            return response()->json([
                'pr' => $pr,
                'items_by_outlet' => $itemsByOutlet,
                'total_amount' => $items->sum('total'),
                'pr_attachments' => $prAttachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_path' => $attachment->file_path,
                        'file_type' => $attachment->file_type,
                        'file_size' => $attachment->file_size,
                        'created_at' => $attachment->created_at
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load PR items: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_ops_id' => 'nullable|exists:purchase_order_ops,id',
            'purchase_requisition_id' => 'nullable|exists:purchase_requisitions,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,check',
            'payment_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:payment_date',
            'description' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Validate that at least one transaction is selected
        if (empty($request->purchase_order_ops_id) && empty($request->purchase_requisition_id)) {
            return back()->with('error', 'Pilih minimal satu transaksi (Purchase Order atau Purchase Requisition).');
        }

        // Validate supplier_id is required for all payments
        if (empty($request->supplier_id)) {
            return back()->with('error', 'Supplier harus dipilih untuk payment.');
        }

        // Check if PO already has a payment
        if ($request->purchase_order_ops_id) {
            $existingPayment = NonFoodPayment::where('purchase_order_ops_id', $request->purchase_order_ops_id)
                ->where('status', '!=', 'cancelled')
                ->first();
            if ($existingPayment) {
                return back()->with('error', 'Purchase Order ini sudah memiliki payment yang aktif.');
            }
        }

        // Check if PR already has a payment
        if ($request->purchase_requisition_id) {
            $existingPayment = NonFoodPayment::where('purchase_requisition_id', $request->purchase_requisition_id)
                ->where('status', '!=', 'cancelled')
                ->first();
            if ($existingPayment) {
                return back()->with('error', 'Purchase Requisition ini sudah memiliki payment yang aktif.');
            }
            
            // Check if PR is on hold
            $pr = \App\Models\PurchaseRequisition::find($request->purchase_requisition_id);
            if ($pr && $pr->is_held) {
                return back()->with('error', "Purchase Requisition {$pr->pr_number} sedang di-hold. Silakan release PR terlebih dahulu sebelum membuat payment.");
            }
        }

        // Check if PO's PR is on hold
        if ($request->purchase_order_ops_id) {
            $po = \App\Models\PurchaseOrderOps::find($request->purchase_order_ops_id);
            if ($po && $po->source_type === 'purchase_requisition_ops' && $po->source_id) {
                $pr = \App\Models\PurchaseRequisition::find($po->source_id);
                if ($pr && $pr->is_held) {
                    return back()->with('error', "Purchase Requisition {$pr->pr_number} yang terkait dengan PO ini sedang di-hold. Silakan release PR terlebih dahulu sebelum membuat payment.");
                }
            }
        }

        try {
            DB::beginTransaction();

            // Generate payment number
            $paymentNumber = (new NonFoodPayment())->generatePaymentNumber();

            // Create payment
            $payment = NonFoodPayment::create([
                'payment_number' => $paymentNumber,
                'purchase_order_ops_id' => $request->purchase_order_ops_id,
                'purchase_requisition_id' => $request->purchase_requisition_id,
                'supplier_id' => $request->supplier_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'due_date' => $request->due_date,
                'status' => $request->status ?? 'pending', // Allow status to be set directly
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
            ]);

            // If payment is created with approved/paid status, update PR status
            if (in_array($payment->status, ['approved', 'paid'])) {
                $this->updatePRStatusIfAllPaid($payment);
            }

            DB::commit();

            return redirect()->route('non-food-payments.index')
                ->with('success', 'Non Food Payment berhasil dibuat.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal membuat Non Food Payment: ' . $e->getMessage());
        }
    }

    public function show(NonFoodPayment $nonFoodPayment)
    {
        $nonFoodPayment->load([
            'purchaseOrderOps.supplier',
            'purchaseOrderOps.items',
            'purchaseOrderOps.source_pr.outlet',
            'purchaseRequisition.division',
            'purchaseRequisition.creator',
            'purchaseRequisition.outlet',
            'supplier',
            'creator',
            'approver',
            'attachments.uploader'
        ]);

        // Get PO attachments if payment is for PO
        $poAttachments = [];
        if ($nonFoodPayment->purchase_order_ops_id) {
            try {
                $poAttachments = DB::table('purchase_order_ops_attachments as pooa')
                    ->where('pooa.purchase_order_ops_id', $nonFoodPayment->purchase_order_ops_id)
                    ->select(
                        'pooa.id', 
                        'pooa.file_name', 
                        'pooa.file_path', 
                        'pooa.mime_type as file_type', 
                        'pooa.file_size', 
                        'pooa.created_at'
                    )
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                // Table might not exist, continue without attachments
            }
        }

        // Get PR attachments if payment is for PR
        $prAttachments = [];
        if ($nonFoodPayment->purchase_requisition_id) {
            try {
                $prAttachments = DB::table('purchase_requisition_attachments as pra')
                    ->leftJoin('purchase_requisitions as pr', 'pra.purchase_requisition_id', '=', 'pr.id')
                    ->where('pra.purchase_requisition_id', $nonFoodPayment->purchase_requisition_id)
                    ->select(
                        'pra.id', 
                        'pra.file_name', 
                        'pra.file_path', 
                        'pra.mime_type as file_type', 
                        'pra.file_size', 
                        'pra.created_at',
                        'pr.description as pr_description'
                    )
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                // Table might not exist, continue without attachments
            }
        }

        // Get PR attachments from PO source if payment is for PO
        if ($nonFoodPayment->purchase_order_ops_id && $nonFoodPayment->purchaseOrderOps) {
            try {
                $sourcePRId = $nonFoodPayment->purchaseOrderOps->source_id;
                if ($sourcePRId) {
                    $prAttachments = DB::table('purchase_requisition_attachments as pra')
                        ->leftJoin('purchase_requisitions as pr', 'pra.purchase_requisition_id', '=', 'pr.id')
                        ->where('pra.purchase_requisition_id', $sourcePRId)
                        ->select(
                            'pra.id', 
                            'pra.file_name', 
                            'pra.file_path', 
                            'pra.mime_type as file_type', 
                            'pra.file_size', 
                            'pra.created_at',
                            'pr.description as pr_description'
                        )
                        ->get()
                        ->toArray();
                }
            } catch (\Exception $e) {
                // Table might not exist, continue without attachments
            }
        }

        return Inertia::render('NonFoodPayment/Show', [
            'payment' => $nonFoodPayment,
            'po_attachments' => $poAttachments,
            'pr_attachments' => $prAttachments
        ]);
    }

    public function edit(NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBeEdited()) {
            return back()->with('error', 'Payment ini tidak dapat diedit.');
        }

        $nonFoodPayment->load([
            'purchaseOrderOps.supplier',
            'purchaseRequisition.division',
            'supplier'
        ]);

        // Get suppliers
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('NonFoodPayment/Edit', [
            'payment' => $nonFoodPayment,
            'suppliers' => $suppliers
        ]);
    }

    public function update(Request $request, NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBeEdited()) {
            return back()->with('error', 'Payment ini tidak dapat diedit.');
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,check',
            'payment_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:payment_date',
            'description' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $updateData = [
                'supplier_id' => $request->supplier_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'due_date' => $request->due_date,
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
            ];

            $nonFoodPayment->update($updateData);

            return redirect()->route('non-food-payments.show', $nonFoodPayment->id)
                ->with('success', 'Non Food Payment berhasil diperbarui.');

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui Non Food Payment: ' . $e->getMessage());
        }
    }

    public function destroy(NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBeDeleted()) {
            return back()->with('error', 'Payment ini tidak dapat dihapus.');
        }

        try {
            $nonFoodPayment->delete();

            return redirect()->route('non-food-payments.index')
                ->with('success', 'Non Food Payment berhasil dihapus.');

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus Non Food Payment: ' . $e->getMessage());
        }
    }

    public function approve(NonFoodPayment $nonFoodPayment, Request $request = null)
    {
        if (!$nonFoodPayment->canBeApproved()) {
            if ($request && ($request->wantsJson() || $request->ajax() || $request->expectsJson())) {
                return response()->json(['success' => false, 'message' => 'Payment ini tidak dapat disetujui.'], 400);
            }
            return back()->with('error', 'Payment ini tidak dapat disetujui.');
        }

        try {
            DB::beginTransaction();
            
            $note = $request ? $request->input('note', '') : '';
            
            $nonFoodPayment->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Log activity
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'approve',
                'module' => 'non_food_payment',
                'description' => 'Approve Non Food Payment: ' . $nonFoodPayment->payment_number,
                'ip_address' => $request ? $request->ip() : request()->ip(),
            ]);

            // Send notification to creator
            if ($nonFoodPayment->created_by) {
                \App\Models\Notification::create([
                    'user_id' => $nonFoodPayment->created_by,
                    'type' => 'non_food_payment_approval',
                    'title' => 'Non Food Payment Disetujui',
                    'message' => "Non Food Payment {$nonFoodPayment->payment_number} telah disetujui oleh Finance Manager.",
                ]);
            }

            // Update PR status to PAID if all payments are completed
            $this->updatePRStatusIfAllPaid($nonFoodPayment);

            DB::commit();

            if ($request && ($request->wantsJson() || $request->ajax() || $request->expectsJson())) {
                return response()->json(['success' => true, 'message' => 'Non Food Payment berhasil disetujui.']);
            }

            return back()->with('success', 'Non Food Payment berhasil disetujui.');

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request && ($request->wantsJson() || $request->ajax() || $request->expectsJson())) {
                return response()->json(['success' => false, 'message' => 'Gagal menyetujui Non Food Payment: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal menyetujui Non Food Payment: ' . $e->getMessage());
        }
    }

    public function reject(NonFoodPayment $nonFoodPayment, Request $request = null)
    {
        if (!$nonFoodPayment->canBeRejected()) {
            if ($request && ($request->wantsJson() || $request->ajax() || $request->expectsJson())) {
                return response()->json(['success' => false, 'message' => 'Payment ini tidak dapat ditolak.'], 400);
            }
            return back()->with('error', 'Payment ini tidak dapat ditolak.');
        }

        try {
            $note = $request ? $request->input('note', '') : '';
            
            $nonFoodPayment->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Log activity
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'reject',
                'module' => 'non_food_payment',
                'description' => 'Reject Non Food Payment: ' . $nonFoodPayment->payment_number . ($note ? ' - ' . $note : ''),
                'ip_address' => $request ? $request->ip() : request()->ip(),
            ]);

            // Send notification to creator
            if ($nonFoodPayment->created_by) {
                \App\Models\Notification::create([
                    'user_id' => $nonFoodPayment->created_by,
                    'type' => 'non_food_payment_approval',
                    'title' => 'Non Food Payment Ditolak',
                    'message' => "Non Food Payment {$nonFoodPayment->payment_number} telah ditolak oleh Finance Manager." . ($note ? ' Alasan: ' . $note : ''),
                ]);
            }

            if ($request && ($request->wantsJson() || $request->ajax() || $request->expectsJson())) {
                return response()->json(['success' => true, 'message' => 'Non Food Payment berhasil ditolak.']);
            }

            return back()->with('success', 'Non Food Payment berhasil ditolak.');

        } catch (\Exception $e) {
            if ($request && ($request->wantsJson() || $request->ajax() || $request->expectsJson())) {
                return response()->json(['success' => false, 'message' => 'Gagal menolak Non Food Payment: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal menolak Non Food Payment: ' . $e->getMessage());
        }
    }

    public function markAsPaid(NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBePaid()) {
            return back()->with('error', 'Payment ini tidak dapat ditandai sebagai dibayar.');
        }

        try {
            DB::beginTransaction();
            
            $nonFoodPayment->update([
                'status' => 'paid',
            ]);

            // Update PR status to PAID if all payments are completed
            $this->updatePRStatusIfAllPaid($nonFoodPayment);

            DB::commit();

            return back()->with('success', 'Non Food Payment berhasil ditandai sebagai dibayar.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menandai Non Food Payment sebagai dibayar: ' . $e->getMessage());
        }
    }

    /**
     * Update PR status to PAID if all related POs have been paid
     */
    private function updatePRStatusIfAllPaid(NonFoodPayment $payment)
    {
        // Get PR IDs related to this payment
        $prIds = collect();

        // If payment is directly linked to PR
        if ($payment->purchase_requisition_id) {
            $prIds->push($payment->purchase_requisition_id);
        }

        // If payment is linked to PO, get PRs from PO items
        if ($payment->purchase_order_ops_id) {
            $po = \App\Models\PurchaseOrderOps::find($payment->purchase_order_ops_id);
            if ($po) {
                // Get PR IDs from PO items
                $prIdsFromPO = DB::table('purchase_order_ops_items')
                    ->where('purchase_order_ops_id', $po->id)
                    ->where('source_type', 'purchase_requisition_ops')
                    ->distinct()
                    ->pluck('source_id')
                    ->toArray();
                
                $prIds = $prIds->merge($prIdsFromPO);
            }
        }

        // Remove duplicates
        $prIds = $prIds->unique()->values();

        // Check each PR if all POs are paid
        foreach ($prIds as $prId) {
            $pr = \App\Models\PurchaseRequisition::find($prId);
            if (!$pr) {
                continue;
            }

            // Get all PO IDs related to this PR
            $poIds = DB::table('purchase_order_ops_items')
                ->where('source_type', 'purchase_requisition_ops')
                ->where('source_id', $prId)
                ->distinct()
                ->pluck('purchase_order_ops_id')
                ->toArray();

            // Update PR status to PAID if all POs are paid or if direct payment exists
            $shouldUpdateToPaid = false;
            $description = '';

            if (!empty($poIds)) {
                // PR has PO - check if all POs are paid
                $allPOsPaid = true;
                foreach ($poIds as $poId) {
                    $hasPaidPayment = DB::table('non_food_payments')
                        ->where('purchase_order_ops_id', $poId)
                        ->whereIn('status', ['approved', 'paid'])
                        ->where('status', '!=', 'cancelled')
                        ->exists();

                    if (!$hasPaidPayment) {
                        $allPOsPaid = false;
                        break;
                    }
                }

                if ($allPOsPaid && in_array($pr->status, ['PROCESSED', 'COMPLETED', 'APPROVED'])) {
                    $shouldUpdateToPaid = true;
                    $description = 'PR status diubah menjadi PAID karena semua PO sudah dibayar';
                }
            } else {
                // PR doesn't have PO - check if there's a direct payment
                $hasDirectPayment = DB::table('non_food_payments')
                    ->where('purchase_requisition_id', $prId)
                    ->whereIn('status', ['approved', 'paid'])
                    ->where('status', '!=', 'cancelled')
                    ->exists();

                if ($hasDirectPayment && in_array($pr->status, ['APPROVED', 'PROCESSED', 'COMPLETED'])) {
                    $shouldUpdateToPaid = true;
                    $description = 'PR status diubah menjadi PAID karena sudah dibayar';
                }
            }

            if ($shouldUpdateToPaid) {
                $oldStatus = $pr->status;
                $pr->update(['status' => 'PAID']);

                // Log history
                \App\Models\PurchaseRequisitionHistory::create([
                    'purchase_requisition_id' => $pr->id,
                    'user_id' => Auth::id(),
                    'action' => 'PAID',
                    'old_status' => $oldStatus,
                    'new_status' => 'PAID',
                    'description' => $description
                ]);
            }
        }
    }

    public function cancel(NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBeCancelled()) {
            return back()->with('error', 'Payment ini tidak dapat dibatalkan.');
        }

        try {
            $nonFoodPayment->update([
                'status' => 'cancelled',
            ]);

            return back()->with('success', 'Non Food Payment berhasil dibatalkan.');

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membatalkan Non Food Payment: ' . $e->getMessage());
        }
    }

    /**
     * Print preview for Non Food Payments
     */
    public function printPreview(Request $request)
    {
        try {
            $ids = $request->get('ids', '');
            
            if (empty($ids)) {
                \Log::warning('No IDs provided in Non Food Payment printPreview');
                return response()->json(['error' => 'No IDs provided'], 400);
            }

            $paymentIds = explode(',', $ids);
            
            // Validate that all IDs are numeric
            foreach ($paymentIds as $id) {
                if (!is_numeric($id)) {
                    \Log::warning('Invalid Payment ID format', ['id' => $id]);
                    return response()->json(['error' => 'Invalid ID format: ' . $id], 400);
                }
            }
            
            $payments = NonFoodPayment::with([
                'supplier',
                'creator',
                'purchaseOrderOps.supplier',
                'purchaseOrderOps.items',
                'purchaseOrderOps.source_pr.outlet',
                'purchaseRequisition.items',
                'purchaseRequisition.outlet',
                'purchaseRequisition.division',
                'purchaseRequisition.category'
            ])->whereIn('id', $paymentIds)->get();

            // Ensure items are loaded for each payment - use direct DB query to guarantee items are loaded
            $paymentItems = [];
            foreach ($payments as $payment) {
                $items = collect();
                
                // Load PO items if payment has PO
                if ($payment->purchase_order_ops_id) {
                    $poItems = DB::table('purchase_order_ops_items')
                        ->where('purchase_order_ops_id', $payment->purchase_order_ops_id)
                        ->get();
                    
                    if ($poItems && $poItems->count() > 0) {
                        $items = $poItems;
                        // Also set to relationship for consistency
                        if ($payment->purchaseOrderOps) {
                            $payment->purchaseOrderOps->setRelation('items', $poItems);
                            
                            // Load PR from PO if PO has source_type and source_id
                            if ($payment->purchaseOrderOps->source_type === 'purchase_requisition_ops' && $payment->purchaseOrderOps->source_id) {
                                $pr = \App\Models\PurchaseRequisition::with(['outlet', 'items', 'division', 'category'])
                                    ->find($payment->purchaseOrderOps->source_id);
                                if ($pr) {
                                    // Ensure date is cast to Carbon
                                    if ($pr->date && is_string($pr->date)) {
                                        $pr->date = \Carbon\Carbon::parse($pr->date);
                                    }
                                    // Load PR items if not already loaded
                                    if (!$pr->items || $pr->items->isEmpty()) {
                                        $prItems = DB::table('purchase_requisition_items')
                                            ->where('purchase_requisition_id', $pr->id)
                                            ->select('*', 'qty', 'unit_price', 'subtotal', 'item_type', 'allowance_recipient_name', 'allowance_account_number', 'others_notes')
                                            ->get();
                                        
                                        // Format item_name for allowance type
                                        $prItems = $prItems->map(function ($item) {
                                            if ($item->item_type === 'allowance' && $item->allowance_recipient_name && $item->allowance_account_number) {
                                                $item->item_name = 'Allowance - ' . $item->allowance_recipient_name . ' - ' . $item->allowance_account_number;
                                            } elseif ($item->item_type === 'allowance' && $item->allowance_recipient_name) {
                                                $item->item_name = 'Allowance - ' . $item->allowance_recipient_name;
                                            }
                                            return $item;
                                        });
                                        
                                        $pr->setRelation('items', $prItems);
                                    } else {
                                        // Format existing items if already loaded
                                        $prItems = $pr->items->map(function ($item) {
                                            if (isset($item->item_type) && $item->item_type === 'allowance' && isset($item->allowance_recipient_name) && isset($item->allowance_account_number) && $item->allowance_recipient_name && $item->allowance_account_number) {
                                                $item->item_name = 'Allowance - ' . $item->allowance_recipient_name . ' - ' . $item->allowance_account_number;
                                            } elseif (isset($item->item_type) && $item->item_type === 'allowance' && isset($item->allowance_recipient_name) && $item->allowance_recipient_name) {
                                                $item->item_name = 'Allowance - ' . $item->allowance_recipient_name;
                                            }
                                            return $item;
                                        });
                                        $pr->setRelation('items', $prItems);
                                    }
                                    // Set PR to payment for easy access in view
                                    $payment->setAttribute('pr_from_po', $pr);
                                }
                            }
                        }
                    }
                }
                
                // Load PR items if payment has PR and no PO items
                if ($items->isEmpty() && $payment->purchase_requisition_id) {
                    $prItems = DB::table('purchase_requisition_items')
                        ->where('purchase_requisition_id', $payment->purchase_requisition_id)
                        ->select('*', 'qty', 'unit_price', 'subtotal', 'item_type', 'allowance_recipient_name', 'allowance_account_number', 'others_notes')
                        ->get();
                    
                    // Format item_name for allowance type
                    $prItems = $prItems->map(function ($item) {
                        if ($item->item_type === 'allowance' && $item->allowance_recipient_name && $item->allowance_account_number) {
                            $item->item_name = 'Allowance - ' . $item->allowance_recipient_name . ' - ' . $item->allowance_account_number;
                        } elseif ($item->item_type === 'allowance' && $item->allowance_recipient_name) {
                            $item->item_name = 'Allowance - ' . $item->allowance_recipient_name;
                        }
                        return $item;
                    });
                    
                    if ($prItems && $prItems->count() > 0) {
                        $items = $prItems;
                        // Also set to relationship for consistency
                        if ($payment->purchaseRequisition) {
                            $payment->purchaseRequisition->setRelation('items', $prItems);
                        }
                    }
                }
                
                $paymentItems[$payment->id] = $items;
            }

            return view('non-food-payments.print-preview', [
                'payments' => $payments,
                'paymentItems' => $paymentItems,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Non Food Payment printPreview error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat generate print preview: ' . $e->getMessage()], 500);
        }
    }

    // API: Get pending Non Food Payment approvals
    public function getPendingApprovals(Request $request)
    {
        try {
            $user = Auth::user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            $query = NonFoodPayment::with(['supplier', 'creator', 'purchaseOrderOps', 'purchaseRequisition'])
                ->where('status', 'pending')
                ->orderByDesc('created_at');
            
            $pendingApprovals = [];
            
            // Finance Manager approvals (id_jabatan == 160) and Superadmin
            if (($user->id_jabatan == 160 && $user->status == 'A') || $isSuperadmin) {
                $financeManagerApprovals = $query->get();
                
                foreach ($financeManagerApprovals as $nfp) {
                    $paymentType = 'Unknown';
                    $sourceNumber = null;
                    
                    if ($nfp->purchase_order_ops_id && $nfp->purchaseOrderOps) {
                        $paymentType = 'PO';
                        $sourceNumber = $nfp->purchaseOrderOps->number;
                    } elseif ($nfp->purchase_requisition_id && $nfp->purchaseRequisition) {
                        $paymentType = 'PR';
                        $sourceNumber = $nfp->purchaseRequisition->pr_number;
                    }
                    
                    $pendingApprovals[] = [
                        'id' => $nfp->id,
                        'payment_number' => $nfp->payment_number,
                        'payment_date' => $nfp->payment_date,
                        'amount' => $nfp->amount,
                        'payment_method' => $nfp->payment_method,
                        'supplier' => $nfp->supplier ? ['name' => $nfp->supplier->name] : null,
                        'creator' => $nfp->creator ? ['nama_lengkap' => $nfp->creator->nama_lengkap] : null,
                        'payment_type' => $paymentType,
                        'source_number' => $sourceNumber,
                        'description' => $nfp->description,
                        'notes' => $nfp->notes,
                        'created_at' => $nfp->created_at
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'non_food_payments' => $pendingApprovals
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting pending Non Food Payment approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get pending approvals'
            ], 500);
        }
    }

    // API: Get Non Food Payment detail for approval modal
    public function getDetail($id)
    {
        try {
            $nonFoodPayment = NonFoodPayment::with([
                'supplier',
                'creator',
                'approver',
                'purchaseOrderOps.supplier',
                'purchaseOrderOps.items',
                'purchaseOrderOps.source_pr',
                'purchaseRequisition.division',
                'purchaseRequisition.creator',
                'purchaseRequisition.outlet',
                'purchaseRequisition.items',
                'attachments.uploader'
            ])->findOrFail($id);
            
            // Add payment type and source info
            $paymentType = 'Unknown';
            $sourceInfo = null;
            $itemsByOutlet = [];
            $poAttachments = [];
            $prAttachments = [];
            
            if ($nonFoodPayment->purchase_order_ops_id && $nonFoodPayment->purchaseOrderOps) {
                $paymentType = 'PO';
                $po = $nonFoodPayment->purchaseOrderOps;
                
                // Get PO attachments
                try {
                    $poAttachments = DB::table('purchase_order_ops_attachments as pooa')
                        ->where('pooa.purchase_order_ops_id', $po->id)
                        ->select(
                            'pooa.id', 
                            'pooa.file_name', 
                            'pooa.file_path', 
                            'pooa.mime_type as file_type', 
                            'pooa.file_size', 
                            'pooa.created_at'
                        )
                        ->get()
                        ->toArray();
                } catch (\Exception $e) {
                    // Table might not exist, continue without attachments
                }
                
                // Get items with outlet and category info
                try {
                    $items = DB::table('purchase_order_ops_items as poi')
                        ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                        ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
                        ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
                        ->where('poi.purchase_order_ops_id', $po->id)
                        ->select(
                            'poi.id',
                            'poi.item_name',
                            'poi.quantity',
                            'poi.unit',
                            'poi.price',
                            'poi.total',
                            'pr.id as pr_id',
                            'pr.outlet_id',
                            'o.nama_outlet as outlet_name',
                            'pr.category_id',
                            'prc.name as category_name',
                            'prc.division as category_division',
                            'prc.subcategory as category_subcategory',
                            'prc.budget_type as category_budget_type',
                            'pr.pr_number',
                            'pr.title as pr_title',
                            'pr.description as pr_description'
                        )
                        ->get();
                } catch (\Exception $e) {
                    // Fallback to basic items
                    $items = DB::table('purchase_order_ops_items as poi')
                        ->where('poi.purchase_order_ops_id', $po->id)
                        ->select('poi.*')
                        ->get();
                    
                    $items = $items->map(function ($item) {
                        $item->outlet_id = null;
                        $item->outlet_name = 'Unknown Outlet';
                        $item->pr_id = null;
                        $item->pr_number = null;
                        $item->pr_title = null;
                        $item->pr_description = null;
                        return $item;
                    });
                }
                
                // Group items by outlet and add PR attachments
                $itemsByOutlet = $items->groupBy('outlet_id')->map(function ($outletItems, $outletId) {
                    $firstItem = $outletItems->first();
                    $prId = $firstItem->pr_id ?? null;
                    
                    // Get PR attachments for this specific PR
                    $outletPrAttachments = [];
                    if ($prId) {
                        try {
                            $outletPrAttachments = DB::table('purchase_requisition_attachments as pra')
                                ->leftJoin('purchase_requisitions as pr', 'pra.purchase_requisition_id', '=', 'pr.id')
                                ->where('pra.purchase_requisition_id', $prId)
                                ->select(
                                    'pra.id', 
                                    'pra.file_name', 
                                    'pra.file_path', 
                                    'pra.mime_type as file_type', 
                                    'pra.file_size', 
                                    'pra.created_at',
                                    'pr.description as pr_description'
                                )
                                ->get()
                                ->toArray();
                        } catch (\Exception $e) {
                            // Continue without attachments if error
                        }
                    }
                    
                    return [
                        'outlet_id' => $outletId,
                        'outlet_name' => $firstItem->outlet_name ?? 'Unknown Outlet',
                        'category_id' => $firstItem->category_id ?? null,
                        'category_name' => $firstItem->category_name ?? null,
                        'category_division' => $firstItem->category_division ?? null,
                        'category_subcategory' => $firstItem->category_subcategory ?? null,
                        'category_budget_type' => $firstItem->category_budget_type ?? null,
                        'pr_number' => $firstItem->pr_number ?? null,
                        'pr_title' => $firstItem->pr_title ?? null,
                        'pr_description' => $firstItem->pr_description ?? null,
                        'pr_attachments' => $outletPrAttachments,
                        'items' => $outletItems->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'item_name' => $item->item_name,
                                'quantity' => $item->quantity,
                                'unit' => $item->unit,
                                'price' => $item->price,
                                'total' => $item->total,
                            ];
                        }),
                        'subtotal' => $outletItems->sum('total')
                    ];
                })->values();
                
                $sourceInfo = [
                    'type' => 'PO',
                    'number' => $po->number,
                    'date' => $po->date,
                    'grand_total' => $po->grand_total,
                    'supplier' => $po->supplier ? [
                        'name' => $po->supplier->name
                    ] : null,
                    'source_pr' => $po->source_pr ? [
                        'pr_number' => $po->source_pr->pr_number,
                        'title' => $po->source_pr->title
                    ] : null,
                    'items_by_outlet' => $itemsByOutlet
                ];
            } elseif ($nonFoodPayment->purchase_requisition_id && $nonFoodPayment->purchaseRequisition) {
                $paymentType = 'PR';
                $pr = $nonFoodPayment->purchaseRequisition;
                
                // Get PR attachments
                try {
                    $prAttachments = DB::table('purchase_requisition_attachments as pra')
                        ->leftJoin('purchase_requisitions as pr', 'pra.purchase_requisition_id', '=', 'pr.id')
                        ->where('pra.purchase_requisition_id', $pr->id)
                        ->select(
                            'pra.id', 
                            'pra.file_name', 
                            'pra.file_path', 
                            'pra.mime_type as file_type', 
                            'pra.file_size', 
                            'pra.created_at',
                            'pr.description as pr_description'
                        )
                        ->get()
                        ->toArray();
                } catch (\Exception $e) {
                    // Table might not exist, continue without attachments
                }
                
                // Get PR items with outlet and category info
                try {
                    $items = DB::table('purchase_requisition_items as pri')
                        ->leftJoin('tbl_data_outlet as o', 'pri.outlet_id', '=', 'o.id_outlet')
                        ->leftJoin('purchase_requisition_categories as prc', 'pri.category_id', '=', 'prc.id')
                        ->where('pri.purchase_requisition_id', $pr->id)
                        ->select(
                            'pri.id',
                            'pri.item_name',
                            'pri.qty as quantity',
                            'pri.unit',
                            'pri.unit_price as price',
                            'pri.subtotal as total',
                            'pri.outlet_id',
                            'pri.category_id',
                            'pri.item_type',
                            'pri.allowance_recipient_name',
                            'pri.allowance_account_number',
                            'pri.others_notes',
                            'o.nama_outlet as item_outlet_name',
                            'prc.name as item_category_name',
                            'prc.division as item_category_division',
                            'prc.subcategory as item_category_subcategory',
                            'prc.budget_type as item_category_budget_type'
                        )
                        ->get();
                } catch (\Exception $e) {
                    // Fallback to basic items
                    $items = DB::table('purchase_requisition_items as pri')
                        ->where('pri.purchase_requisition_id', $pr->id)
                        ->select('pri.*')
                        ->get();
                }
                
                // Group items by outlet
                $itemsByOutlet = $items->groupBy('outlet_id')->map(function ($outletItems, $outletId) use ($pr) {
                    $firstItem = $outletItems->first();
                    
                    return [
                        'outlet_id' => $outletId,
                        'outlet_name' => $firstItem->item_outlet_name ?? 'Unknown Outlet',
                        'category_id' => $firstItem->category_id ?? null,
                        'category_name' => $firstItem->item_category_name ?? null,
                        'category_division' => $firstItem->item_category_division ?? null,
                        'category_subcategory' => $firstItem->item_category_subcategory ?? null,
                        'category_budget_type' => $firstItem->item_category_budget_type ?? null,
                        'pr_number' => $pr->pr_number,
                        'pr_title' => $pr->title,
                        'pr_description' => $pr->description,
                        'pr_attachments' => $prAttachments,
                        'items' => $outletItems->map(function ($item) {
                            $itemName = $item->item_name;
                            if ($item->item_type === 'allowance' && $item->allowance_recipient_name && $item->allowance_account_number) {
                                $itemName = 'Allowance - ' . $item->allowance_recipient_name . ' - ' . $item->allowance_account_number;
                            } elseif ($item->item_type === 'allowance' && $item->allowance_recipient_name) {
                                $itemName = 'Allowance - ' . $item->allowance_recipient_name;
                            }
                            
                            return [
                                'id' => $item->id,
                                'item_name' => $itemName,
                                'quantity' => $item->quantity ?? $item->qty,
                                'unit' => $item->unit,
                                'price' => $item->price ?? $item->unit_price,
                                'total' => $item->total ?? $item->subtotal,
                                'item_type' => $item->item_type,
                                'allowance_recipient_name' => $item->allowance_recipient_name,
                                'allowance_account_number' => $item->allowance_account_number,
                                'others_notes' => $item->others_notes
                            ];
                        }),
                        'subtotal' => $outletItems->sum(function($item) {
                            return $item->total ?? $item->subtotal ?? 0;
                        })
                    ];
                })->values();
                
                $sourceInfo = [
                    'type' => 'PR',
                    'pr_number' => $pr->pr_number,
                    'date' => $pr->date,
                    'amount' => $pr->amount,
                    'title' => $pr->title,
                    'description' => $pr->description,
                    'division' => $pr->division ? [
                        'nama_divisi' => $pr->division->nama_divisi
                    ] : null,
                    'outlet' => $pr->outlet ? [
                        'nama_outlet' => $pr->outlet->nama_outlet
                    ] : null,
                    'items_by_outlet' => $itemsByOutlet
                ];
            }
            
            $nonFoodPayment->payment_type = $paymentType;
            $nonFoodPayment->source_info = $sourceInfo;
            $nonFoodPayment->po_attachments = $poAttachments;
            $nonFoodPayment->pr_attachments = $prAttachments;
            
            return response()->json([
                'success' => true,
                'non_food_payment' => $nonFoodPayment
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting Non Food Payment detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load Non Food Payment detail'
            ], 500);
        }
    }
}
