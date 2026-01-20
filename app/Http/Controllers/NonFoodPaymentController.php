<?php

namespace App\Http\Controllers;

use App\Models\NonFoodPayment;
use App\Models\PurchaseOrderOps;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisitionOutletBudget;
use App\Models\RetailNonFood;
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
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');
        $loadData = $request->input('load_data', false); // Flag untuk load data
        $perPage = $request->input('per_page', 10);
        
        // Jika belum ada request untuk load data dan tidak ada filter/search, return empty
        // Ini untuk optimasi - tidak load data saat pertama kali masuk halaman
        if (!$loadData && !$supplier && !$status && !$dateFrom && !$dateTo && !$search) {
            // Get suppliers for filter dropdown (tetap load untuk dropdown)
            $suppliers = DB::table('suppliers')
                ->where('status', 'active')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
            
            return Inertia::render('NonFoodPayment/Index', [
                'payments' => new \Illuminate\Pagination\LengthAwarePaginator(
                    collect([]),
                    0,
                    $perPage,
                    1,
                    ['path' => $request->url(), 'query' => $request->query()]
                ),
                'suppliers' => $suppliers,
                'filters' => $request->only(['supplier', 'status', 'date_from', 'date_to', 'search', 'per_page']),
                'dataLoaded' => false
            ]);
        }

        // Build query with search and filters
        // IMPORTANT: No filter by created_by - ALL users can see ALL non food payments
        // All users should be able to view all non food payments regardless of who created them
        // Using withoutGlobalScopes() to ensure no hidden scopes are applied
        // CRITICAL: Do not filter by created_by - all users must see all payments
        $query = NonFoodPayment::withoutGlobalScopes()
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
            )
            ->distinct(); // Ensure no duplicate rows from joins
        
        // CRITICAL: Explicitly ensure NO filter by created_by is applied
        // This ensures all users can see all non food payments
        // DO NOT add: ->where('non_food_payments.created_by', Auth::id())
        // DO NOT add any condition that filters by created_by

        // Apply filters
        if ($supplier) {
            $query->where('non_food_payments.supplier_id', $supplier);
        }
        
        if ($status) {
            // Handle 'pending_finance_manager' filter (status is pending but Finance Manager already approved)
            if ($status === 'pending_finance_manager') {
                $query->where('non_food_payments.status', 'pending')
                      ->whereNotNull('non_food_payments.approved_finance_manager_by');
            } else {
                $query->where('non_food_payments.status', $status);
            }
        }
        
        // Filter by date range (date_from dan date_to)
        if ($dateFrom) {
            $query->whereDate('non_food_payments.payment_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('non_food_payments.payment_date', '<=', $dateTo);
        }

        // Apply search
        // Note: Search should not affect visibility - all data should be visible regardless
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('non_food_payments.payment_number', 'like', "%{$search}%")
                  ->orWhere('s.name', 'like', "%{$search}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('poo.number', 'like', "%{$search}%")
                  ->orWhere('pr.pr_number', 'like', "%{$search}%");
            });
        }
        
        // Ensure all non food payments are visible regardless of creator
        // No filter by created_by should be applied at any point
        // Log query for debugging
        \Log::info('NonFoodPayment Index Query', [
            'has_search' => !empty($search),
            'search' => $search,
            'user_id' => Auth::id(),
            'query_sql' => $query->toSql(),
            'query_bindings' => $query->getBindings()
        ]);
        
        $payments = $query->latest('non_food_payments.payment_date')->paginate($perPage)->withQueryString();
        
        // Log result count for debugging
        \Log::info('NonFoodPayment Index Result', [
            'total' => $payments->total(),
            'count' => $payments->count(),
            'user_id' => Auth::id()
        ]);
        
        // PERFORMANCE OPTIMIZATION: Batch query untuk outlet breakdown
        // Kumpulkan semua PO IDs yang perlu di-query (hanya untuk payment dengan purchase_order_ops_id)
        $poIds = $payments->getCollection()
            ->pluck('purchase_order_ops_id')
            ->filter() // Hapus null values
            ->unique()
            ->values()
            ->toArray();
        
        // Batch query sekali untuk semua outlet breakdown
        $outletBreakdownsMap = [];
        if (!empty($poIds)) {
            try {
                $allBreakdowns = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    // PR Ops: outlet/category at item level
                    ->leftJoin('purchase_requisition_items as pri', 'poi.pr_ops_item_id', '=', 'pri.id')
                    ->leftJoin('tbl_data_outlet as o', function ($join) {
                        $join->on(DB::raw('COALESCE(poi.outlet_id, pri.outlet_id)'), '=', 'o.id_outlet');
                    })
                    ->leftJoin('purchase_requisition_categories as prc', 'pri.category_id', '=', 'prc.id')
                    ->whereIn('poi.purchase_order_ops_id', $poIds) // WHERE IN instead of loop
                    ->select(
                        'poi.purchase_order_ops_id', // Tambahkan untuk grouping
                        DB::raw('COALESCE(poi.outlet_id, pri.outlet_id) as outlet_id'),
                        'o.nama_outlet as outlet_name',
                        'prc.name as category_name',
                        'prc.division as category_division',
                        'prc.subcategory as category_subcategory',
                        'prc.budget_type as category_budget_type',
                        'pr.pr_number',
                        'pr.title as pr_title',
                        DB::raw('SUM(poi.total) as outlet_total')
                    )
                    ->groupBy(
                        'poi.purchase_order_ops_id',
                        DB::raw('COALESCE(poi.outlet_id, pri.outlet_id)'),
                        'o.nama_outlet',
                        'prc.name',
                        'prc.division',
                        'prc.subcategory',
                        'prc.budget_type',
                        'pr.pr_number',
                        'pr.title'
                    )
                    ->get();
                
                // Group hasil query berdasarkan purchase_order_ops_id untuk easy lookup
                // Gunakan collection untuk konsistensi dengan format sebelumnya
                foreach ($allBreakdowns as $breakdown) {
                    $poId = $breakdown->purchase_order_ops_id;
                    if (!isset($outletBreakdownsMap[$poId])) {
                        $outletBreakdownsMap[$poId] = collect([]);
                    }
                    // Keep as stdClass object (sama seperti ->get() sebelumnya)
                    $outletBreakdownsMap[$poId]->push($breakdown);
                }
            } catch (\Exception $e) {
                \Log::error('Error batch query outlet breakdown', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Jika batch query gagal, fallback ke empty map (akan handle di transform)
            }
        }
        
        // Transform payments to show per outlet (tanpa query di loop)
        $payments->getCollection()->transform(function($payment) use ($outletBreakdownsMap) {
            // Pastikan outlet_breakdown selalu di-set untuk menghindari null/undefined di frontend
            if ($payment->purchase_order_ops_id) {
                $payment->payment_type = 'PO';
                
                // Gunakan hasil batch query dari map (tanpa query baru)
                if (isset($outletBreakdownsMap[$payment->purchase_order_ops_id]) && $outletBreakdownsMap[$payment->purchase_order_ops_id]->isNotEmpty()) {
                    // Gunakan collection langsung (sama seperti format sebelumnya dengan ->get())
                    $payment->outlet_breakdown = $outletBreakdownsMap[$payment->purchase_order_ops_id];
                } else {
                    // Fallback jika data tidak ditemukan (kemungkinan data tidak ada atau error)
                    // Gunakan collection untuk konsistensi dengan format sebelumnya
                    $payment->outlet_breakdown = collect([[
                        'outlet_id' => null,
                        'outlet_name' => 'Unknown Outlet',
                        'category_name' => null,
                        'category_division' => null,
                        'category_subcategory' => null,
                        'category_budget_type' => null,
                        'pr_number' => null,
                        'pr_title' => null,
                        'outlet_total' => $payment->amount ?? 0
                    ]]);
                }
            } else {
                $payment->payment_type = 'PR';
                // For PR payments, show as single outlet (logic tetap sama)
                // Gunakan collection untuk konsistensi dengan format sebelumnya
                $payment->outlet_breakdown = collect([[
                    'outlet_id' => null,
                    'outlet_name' => 'Direct PR Payment',
                    'category_name' => null,
                    'category_division' => null,
                    'category_subcategory' => null,
                    'category_budget_type' => null,
                    'pr_number' => $payment->pr_number ?? null,
                    'pr_title' => null,
                    'outlet_total' => $payment->amount ?? 0
                ]]);
            }
            
            // Safety check: Pastikan outlet_breakdown tidak null/undefined
            if (!isset($payment->outlet_breakdown) || $payment->outlet_breakdown === null) {
                $payment->outlet_breakdown = collect([[
                    'outlet_id' => null,
                    'outlet_name' => 'Unknown',
                    'category_name' => null,
                    'category_division' => null,
                    'category_subcategory' => null,
                    'category_budget_type' => null,
                    'pr_number' => null,
                    'pr_title' => null,
                    'outlet_total' => $payment->amount ?? 0
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
            'filters' => $request->only(['supplier', 'status', 'date_from', 'date_to', 'search', 'per_page', 'load_data']),
            'dataLoaded' => true
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

        // Get available Purchase Order Ops that don't have payments yet or not fully paid
        // For 'lunas' payment_type: exclude if has approved/paid payment
        // For 'termin' payment_type: exclude if total_paid >= grand_total (fully paid)
        $poQuery = DB::table('purchase_order_ops as poo')
            ->leftJoin('suppliers as s', 'poo.supplier_id', '=', 's.id')
            ->leftJoin('purchase_requisitions as pr', 'poo.source_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->where('poo.status', 'approved')
            ->select(
                'poo.id',
                'poo.number',
                'poo.date',
                'poo.grand_total',
                'poo.supplier_id',
                'poo.notes',
                'poo.payment_type',
                'poo.payment_terms',
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

        $allPOs = $poQuery->orderBy('poo.date', 'desc')
            ->limit(100) // Get more to filter
            ->get();

        // Filter PO based on payment status
        $availablePOs = $allPOs->filter(function($po) {
            $paymentType = $po->payment_type ?? null;
            
            // For 'lunas' payment_type: exclude if has any payment (except cancelled)
            // Once a payment is created, even if pending, it should not appear again
            if ($paymentType === 'lunas') {
                $hasPayment = DB::table('non_food_payments')
                    ->where('purchase_order_ops_id', $po->id)
                    // Count only active/non-rejected payments
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->exists();
                return !$hasPayment;
            }
            
            // For 'termin' payment_type: exclude if total_paid >= grand_total (fully paid)
            if ($paymentType === 'termin') {
                $totalPaid = DB::table('non_food_payments')
                    ->where('purchase_order_ops_id', $po->id)
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->sum('amount');
                $totalPaid = (float) $totalPaid;
                $grandTotal = (float) $po->grand_total;
                return $totalPaid < $grandTotal; // Only show if not fully paid
            }
            
            // For PO without payment_type or null: exclude if has any payment (except cancelled) - default to lunas behavior
            $hasPayment = DB::table('non_food_payments')
                ->where('purchase_order_ops_id', $po->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->exists();
            return !$hasPayment;
        })->take(50)->values();

        // Get available Purchase Requisitions (mode purchase_payment, travel_application, kasbon) that don't have payments yet
        // Exclude PRs that have any payment (except cancelled) or are fully paid
        $prQuery = DB::table('purchase_requisitions as pr')
            ->where('pr.status', 'APPROVED')
            ->whereIn('pr.mode', ['purchase_payment', 'travel_application', 'kasbon'])
            ->select(
                'pr.id',
                'pr.pr_number',
                'pr.date',
                'pr.amount',
                'pr.title',
                'pr.description',
                'pr.is_held',
                'pr.hold_reason',
                'pr.division_id',
                'pr.mode'
            );

        // Apply filters
        if ($dateFrom) {
            $prQuery->whereDate('pr.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $prQuery->whereDate('pr.date', '<=', $dateTo);
        }

        $allPRs = $prQuery->orderBy('pr.date', 'desc')
            ->limit(100) // Get more to filter
            ->get();

        // Filter PR based on payment status
        // Exclude if has any payment (except cancelled) or is fully paid
        $availablePRs = $allPRs->filter(function($pr) {
            // Check if PR has any payment (except cancelled)
            $hasPayment = DB::table('non_food_payments')
                ->where('purchase_requisition_id', $pr->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->exists();
            
            if ($hasPayment) {
                // Check if fully paid
                $totalPaid = DB::table('non_food_payments')
                    ->where('purchase_requisition_id', $pr->id)
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->sum('amount');
                $totalPaid = (float) $totalPaid;
                $prAmount = (float) $pr->amount;
                
                // Only show if not fully paid
                return $totalPaid < $prAmount;
            }
            
            // No payment yet, show it
            return true;
        })->take(50)->values();

        // Get available Retail Non Food with payment_method = contra_bon that don't have payments yet
        $retailNonFoodQuery = DB::table('retail_non_food as rnf')
            ->leftJoin('suppliers as s', 'rnf.supplier_id', '=', 's.id')
            ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
            ->where('rnf.payment_method', 'contra_bon')
            ->where('rnf.status', 'approved')
            ->whereNull('rnf.deleted_at')
            ->select(
                'rnf.id',
                'rnf.retail_number',
                'rnf.transaction_date',
                'rnf.total_amount',
                'rnf.supplier_id',
                'rnf.outlet_id',
                'rnf.notes',
                's.name as supplier_name',
                'o.nama_outlet as outlet_name'
            );

        // Apply filters
        if ($supplierId) {
            $retailNonFoodQuery->where('rnf.supplier_id', $supplierId);
        }
        if ($dateFrom) {
            $retailNonFoodQuery->whereDate('rnf.transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $retailNonFoodQuery->whereDate('rnf.transaction_date', '<=', $dateTo);
        }

        // Get banks for dropdown
        $banks = \App\Models\BankAccount::where('is_active', 1)
            ->with('outlet')
            ->orderBy('bank_name')
            ->get();
        
        // Transform untuk include outlet name (sama seperti di BankAccount/Index)
        $banks = $banks->map(function($bank) {
            return [
                'id' => $bank->id,
                'bank_name' => $bank->bank_name,
                'account_number' => $bank->account_number,
                'account_name' => $bank->account_name,
                'outlet_id' => $bank->outlet_id,
                'outlet' => $bank->outlet ? [
                    'id_outlet' => $bank->outlet->id_outlet,
                    'nama_outlet' => $bank->outlet->nama_outlet,
                ] : null,
                'outlet_name' => $bank->outlet ? $bank->outlet->nama_outlet : 'Head Office',
            ];
        });
        
        return Inertia::render('NonFoodPayment/Create', [
            'suppliers' => $suppliers,
            'availablePOs' => $availablePOs,
            'availablePRs' => $availablePRs,
            'banks' => $banks,
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
            
            // Log PO data for debugging
            \Log::info('PO Data for getPOItems', [
                'po_id' => $poId,
                'subtotal' => $po->subtotal ?? null,
                'discount_total_percent' => $po->discount_total_percent ?? null,
                'discount_total_amount' => $po->discount_total_amount ?? null,
                'grand_total' => $po->grand_total ?? null,
                'ppn_enabled' => $po->ppn_enabled ?? null,
                'ppn_amount' => $po->ppn_amount ?? null,
            ]);

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
                    // PR Ops uses outlet/category at item level. Join PR item as source of truth.
                    ->leftJoin('purchase_requisition_items as pri', 'poi.pr_ops_item_id', '=', 'pri.id')
                    // Join outlet by PO item outlet_id (fallback to PR item outlet_id for legacy data)
                    ->leftJoin('tbl_data_outlet as o', function ($join) {
                        $join->on(DB::raw('COALESCE(poi.outlet_id, pri.outlet_id)'), '=', 'o.id_outlet');
                    })
                    // Category is also at PR item level (fallback handled by left join)
                    ->leftJoin('purchase_requisition_categories as prc', 'pri.category_id', '=', 'prc.id')
                    ->where('poi.purchase_order_ops_id', $poId)
                    ->select(
                        'poi.id',
                        'poi.item_name',
                        'poi.quantity',
                        'poi.unit',
                        'poi.price',
                        'poi.discount_percent',
                        'poi.discount_amount',
                        'poi.total',
                        'pr.id as pr_id',
                        DB::raw('COALESCE(poi.outlet_id, pri.outlet_id) as outlet_id'),
                        'o.nama_outlet as outlet_name',
                        'pri.category_id as category_id',
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
                        'poi.discount_percent',
                        'poi.discount_amount',
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
                
                $outletSubtotal = $outletItems->sum('total');
                
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
                            'discount_percent' => $item->discount_percent ?? 0,
                            'discount_amount' => $item->discount_amount ?? 0,
                            'total' => $item->total,
                        ];
                    }),
                    'subtotal' => $outletSubtotal,
                    'default_amount' => $outletSubtotal // Default amount untuk payment per outlet
                ];
            });

            // Add PO discount info and recalculate grand_total to ensure accuracy
            $poDiscountInfo = null;
            if ($po) {
                // Calculate subtotal from items (after item-level discounts)
                // This is the sum of all item totals (price * qty - item discount)
                $calculatedSubtotal = $items->sum('total');
                
                // Always use calculated subtotal from items to ensure accuracy
                // Subtotal = sum of all item totals (already includes item-level discounts)
                $subtotal = $calculatedSubtotal;
                
                $discountTotalPercent = $po->discount_total_percent ?? 0;
                $discountTotalAmount = $po->discount_total_amount ?? 0;
                
                // Recalculate discount amount from percentage if percentage is provided
                if ($discountTotalPercent > 0) {
                    $discountTotalAmount = $subtotal * ($discountTotalPercent / 100);
                }
                
                // Calculate subtotal after item discounts but before total discount
                $subtotalAfterItemDiscounts = $subtotal;
                
                // Apply total discount
                $subtotalAfterTotalDiscount = $subtotalAfterItemDiscounts - $discountTotalAmount;
                
                // Check if PPN is enabled and calculate PPN
                $ppnAmount = 0;
                if ($po->ppn_enabled ?? false) {
                    // PPN is calculated on subtotal after total discount
                    $ppnAmount = $subtotalAfterTotalDiscount * 0.11; // 11% PPN
                }
                
                // Grand total = subtotal after discount + PPN (if any)
                $grandTotalAfterDiscount = $subtotalAfterTotalDiscount + $ppnAmount;
                
                \Log::info('PO Discount Calculation', [
                    'po_id' => $poId,
                    'calculated_subtotal' => $calculatedSubtotal,
                    'subtotal_from_po' => $po->subtotal ?? null,
                    'discount_total_percent' => $discountTotalPercent,
                    'discount_total_amount' => $discountTotalAmount,
                    'subtotal_after_total_discount' => $subtotalAfterTotalDiscount,
                    'ppn_enabled' => $po->ppn_enabled ?? false,
                    'ppn_amount' => $ppnAmount,
                    'grand_total_after_discount' => $grandTotalAfterDiscount,
                    'grand_total_from_po' => $po->grand_total ?? null,
                ]);
                
                $poDiscountInfo = [
                    'discount_total_percent' => $discountTotalPercent,
                    'discount_total_amount' => $discountTotalAmount,
                    'subtotal' => $subtotal,
                    'ppn_enabled' => $po->ppn_enabled ?? false,
                    'ppn_amount' => $ppnAmount,
                    'grand_total' => $grandTotalAfterDiscount, // Use recalculated value
                ];
            }

            return response()->json([
                'po' => $po,
                'items_by_outlet' => $itemsByOutlet,
                'total_amount' => $items->sum('total'),
                'po_attachments' => $poAttachments,
                'po_discount_info' => $poDiscountInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load PO items: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPaymentInfo($poId)
    {
        try {
            $po = DB::table('purchase_order_ops')
                ->where('id', $poId)
                ->first();
            
            if (!$po) {
                return response()->json(['error' => 'Purchase Order not found'], 404);
            }
            
            // Get grand_total after discount (this is the correct value)
            $grandTotal = $po->grand_total ?? 0;
            
            // Get total paid amount from all non-cancelled payments
            $totalPaid = DB::table('non_food_payments')
                ->where('purchase_order_ops_id', $poId)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->sum('amount');
            
            $remaining = $grandTotal - $totalPaid;
            
            // Get payment count
            $paymentCount = DB::table('non_food_payments')
                ->where('purchase_order_ops_id', $poId)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->count();
            
            // Get payment history
            $paymentHistory = DB::table('non_food_payments')
                ->where('purchase_order_ops_id', $poId)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->select('id', 'payment_number', 'amount', 'payment_date', 'status', 'payment_sequence')
                ->orderBy('payment_sequence', 'asc')
                ->orderBy('payment_date', 'asc')
                ->get();
            
            return response()->json([
                'total_paid' => (float) $totalPaid,
                'remaining' => max(0, (float) $remaining),
                'payment_count' => $paymentCount,
                'grand_total' => (float) $po->grand_total,
                'payment_history' => $paymentHistory
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get payment info: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRetailNonFoodItems($retailNonFoodId)
    {
        \Log::info('getRetailNonFoodItems called', [
            'retail_non_food_id' => $retailNonFoodId
        ]);
        
        try {
            // Get basic Retail Non Food info
            $retailNonFood = DB::table('retail_non_food as rnf')
                ->leftJoin('suppliers as s', 'rnf.supplier_id', '=', 's.id')
                ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
                ->leftJoin('purchase_requisition_categories as prc', 'rnf.category_budget_id', '=', 'prc.id')
                ->where('rnf.id', $retailNonFoodId)
                ->whereNull('rnf.deleted_at')
                ->select(
                    'rnf.*',
                    's.name as supplier_name',
                    'o.nama_outlet as outlet_name',
                    'prc.name as category_name',
                    'prc.division as category_division',
                    'prc.subcategory as category_subcategory',
                    'prc.budget_type as category_budget_type'
                )
                ->first();

            if (!$retailNonFood) {
                \Log::warning('Retail Non Food not found', ['retail_non_food_id' => $retailNonFoodId]);
                return response()->json(['error' => 'Retail Non Food not found'], 404);
            }

            // Get Retail Non Food items
            $items = DB::table('retail_non_food_items as rnfi')
                ->where('rnfi.retail_non_food_id', $retailNonFoodId)
                ->select(
                    'rnfi.id',
                    'rnfi.item_name',
                    'rnfi.qty as quantity',
                    'rnfi.unit',
                    'rnfi.price',
                    'rnfi.subtotal as total'
                )
                ->get();

            // Group items by outlet (all items belong to same outlet for retail non food)
            $itemsByOutlet = [
                [
                    'outlet_id' => $retailNonFood->outlet_id,
                    'outlet_name' => $retailNonFood->outlet_name ?? 'Unknown Outlet',
                    'category_id' => $retailNonFood->category_budget_id,
                    'category_name' => $retailNonFood->category_name,
                    'category_division' => $retailNonFood->category_division,
                    'category_subcategory' => $retailNonFood->category_subcategory,
                    'category_budget_type' => $retailNonFood->category_budget_type,
                    'items' => $items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'item_name' => $item->item_name,
                            'quantity' => $item->quantity,
                            'unit' => $item->unit,
                            'price' => $item->price,
                            'total' => $item->total,
                        ];
                    })->toArray(),
                    'outlet_total' => $items->sum('total')
                ]
            ];

            // Get Retail Non Food invoices/attachments
            $retailNonFoodAttachments = [];
            try {
                \Log::info('Fetching retail non food attachments', [
                    'retail_non_food_id' => $retailNonFoodId
                ]);
                
                // Try using model relationship first
                $retailNonFoodModel = \App\Models\RetailNonFood::find($retailNonFoodId);
                if ($retailNonFoodModel) {
                    $invoicesViaModel = $retailNonFoodModel->invoices;
                    \Log::info('Retail non food invoices via model', [
                        'retail_non_food_id' => $retailNonFoodId,
                        'count' => $invoicesViaModel->count(),
                        'invoices' => $invoicesViaModel->toArray()
                    ]);
                }
                
                // Also try direct query
                $invoiceCount = DB::table('retail_non_food_invoices')
                    ->where('retail_non_food_id', $retailNonFoodId)
                    ->count();
                
                \Log::info('Retail non food invoices count (direct query)', [
                    'retail_non_food_id' => $retailNonFoodId,
                    'count' => $invoiceCount
                ]);
                
                // Check all invoices in table (for debugging)
                $allInvoices = DB::table('retail_non_food_invoices')
                    ->select('id', 'retail_non_food_id', 'file_path')
                    ->limit(10)
                    ->get();
                
                \Log::info('Sample retail_non_food_invoices records', [
                    'sample_count' => $allInvoices->count(),
                    'samples' => $allInvoices->toArray()
                ]);
                
                $retailNonFoodAttachments = DB::table('retail_non_food_invoices as rnfi')
                    ->where('rnfi.retail_non_food_id', $retailNonFoodId)
                    ->select(
                        'rnfi.id',
                        'rnfi.file_path',
                        'rnfi.created_at'
                    )
                    ->get()
                    ->map(function($attachment) {
                        $filePath = $attachment->file_path;
                        $fileName = basename($filePath);
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        // Determine file type based on extension
                        $fileType = 'image/jpeg'; // Default
                        if (in_array($fileExtension, ['jpg', 'jpeg'])) {
                            $fileType = 'image/jpeg';
                        } elseif ($fileExtension === 'png') {
                            $fileType = 'image/png';
                        } elseif ($fileExtension === 'pdf') {
                            $fileType = 'application/pdf';
                        }
                        
                        return [
                            'id' => $attachment->id,
                            'file_name' => $fileName,
                            'file_path' => $filePath,
                            'file_type' => $fileType,
                            'file_size' => null,
                            'created_at' => $attachment->created_at,
                        ];
                    })
                    ->toArray();
                
                \Log::info('Retail non food attachments fetched', [
                    'retail_non_food_id' => $retailNonFoodId,
                    'attachments_count' => count($retailNonFoodAttachments),
                    'attachments' => $retailNonFoodAttachments
                ]);
            } catch (\Exception $e) {
                // Table might not exist, continue without attachments
                \Log::error('Error fetching retail non food attachments', [
                    'retail_non_food_id' => $retailNonFoodId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $responseData = [
                'retail_non_food' => $retailNonFood,
                'items_by_outlet' => $itemsByOutlet,
                'total_amount' => $retailNonFood->total_amount,
                'retail_non_food_attachments' => $retailNonFoodAttachments
            ];
            
            \Log::info('Returning getRetailNonFoodItems response', [
                'retail_non_food_id' => $retailNonFoodId,
                'attachments_count' => count($retailNonFoodAttachments),
                'has_attachments' => !empty($retailNonFoodAttachments)
            ]);

            return response()->json($responseData);

        } catch (\Exception $e) {
            \Log::error('Error in getRetailNonFoodItems', [
                'retail_non_food_id' => $retailNonFoodId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to load Retail Non Food items: ' . $e->getMessage()
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
                    // Normalize outlet/category id for frontend validation + bank filtering
                    // - outlet_id/category_id should be numeric or null (never 'global'/'no-outlet' strings)
                    $outletId = $firstItem->outlet_id ?? $pr->outlet_id; // can be null (Head Office)
                    $categoryId = $firstItem->category_id ?? $pr->category_id; // can be null
                    
                    // Get outlet name
                    $outletName = $firstItem->item_outlet_name ?? $pr->outlet_name;
                    if (empty($outletName)) {
                        $outletName = $outletId ? 'Unknown Outlet' : 'Head Office';
                    }
                    
                    // Get category info
                    $categoryName = $firstItem->item_category_name ?? $pr->category_name;
                    $categoryDivision = $firstItem->item_category_division ?? $pr->category_division;
                    $categorySubcategory = $firstItem->item_category_subcategory ?? $pr->category_subcategory;
                    $categoryBudgetType = $firstItem->item_category_budget_type ?? $pr->category_budget_type;
                    
                    // Get attachments for this outlet
                    $outletAttachments = $prAttachments->where('attachment_outlet_id', $outletId)->values();
                    
                    $outletSubtotal = $groupItems->sum('total');
                    
                    $itemsByOutlet[$key] = [
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
                        'subtotal' => $outletSubtotal,
                        'default_amount' => $outletSubtotal // Default amount untuk payment per outlet
                    ];
                }
            } else {
                // Old structure or other modes: Group by main PR outlet/category
                $outletId = $pr->outlet_id ?? 'global';
                $outletName = $pr->outlet_name ?? 'Global / All Outlets';
                
                // Get all attachments (for old structure, no outlet_id in attachments)
                $allAttachments = $prAttachments->values();
                
                $outletSubtotal = $items->sum('total');
                
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
                    'subtotal' => $outletSubtotal,
                    'default_amount' => $outletSubtotal // Default amount untuk payment per outlet
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
        \Log::info('NonFoodPaymentController@store - Input', $request->all());
        
        // Determine if supplier_id is required based on payment mode
        $supplierRequired = true;
        if ($request->purchase_requisition_id) {
            $pr = \App\Models\PurchaseRequisition::find($request->purchase_requisition_id);
            if ($pr) {
                $mode = $pr->mode;
                // pr_ops: required, purchase_payment: optional, travel_application/kasbon: not needed
                $supplierRequired = ($mode === 'pr_ops');
            }
        }
        
        $validationRules = [
            'purchase_order_ops_id' => 'nullable|exists:purchase_order_ops,id',
            'purchase_requisition_id' => 'nullable|exists:purchase_requisitions,id',
            'retail_non_food_id' => 'nullable|exists:retail_non_food,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,check',
            'payment_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:payment_date',
            'description' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'is_partial_payment' => 'nullable|boolean',
            'outlet_payments' => 'nullable|array',
            'outlet_payments.*.outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
            'outlet_payments.*.category_id' => 'nullable|exists:purchase_requisition_categories,id',
            'outlet_payments.*.amount' => 'required|numeric|min:0',
            'outlet_payments.*.bank_id' => 'nullable|required_if:payment_method,transfer,check|exists:bank_accounts,id',
        ];
        
        // Bank_id validation: required at main level only if no outlet_payments
        // If outlet_payments exist, bank_id is handled per outlet
        if (empty($request->outlet_payments) || !is_array($request->outlet_payments) || count($request->outlet_payments) === 0) {
            $validationRules['bank_id'] = 'nullable|required_if:payment_method,transfer,check|exists:bank_accounts,id';
        } else {
            $validationRules['bank_id'] = 'nullable|exists:bank_accounts,id';
        }
        
        // Add supplier_id validation based on requirement
        if ($supplierRequired) {
            $validationRules['supplier_id'] = 'required|exists:suppliers,id';
        } else {
            $validationRules['supplier_id'] = 'nullable|exists:suppliers,id';
        }
        
        try {
            $request->validate($validationRules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('NonFoodPaymentController@store - Validation failed', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return back()->withErrors($e->errors())->withInput();
        }

        // Validate that at least one transaction is selected
        if (empty($request->purchase_order_ops_id) && empty($request->purchase_requisition_id) && empty($request->retail_non_food_id)) {
            return back()->with('error', 'Pilih minimal satu transaksi (Purchase Order, Purchase Requisition, atau Retail Non Food).');
        }

        // Validate supplier_id based on payment mode
        if ($request->purchase_requisition_id) {
            $pr = \App\Models\PurchaseRequisition::find($request->purchase_requisition_id);
            if ($pr) {
                $mode = $pr->mode;
                
                // pr_ops: supplier wajib
                if ($mode === 'pr_ops' && empty($request->supplier_id)) {
                    return back()->with('error', 'Supplier harus dipilih untuk payment dengan mode PR Ops.');
                }
                
                // purchase_payment: supplier optional (bisa kosong)
                // travel_application dan kasbon: supplier tidak perlu (bisa kosong)
                // No validation needed for these modes
            }
        } else if ($request->purchase_order_ops_id) {
            // For PO, supplier is always required (from PO)
            if (empty($request->supplier_id)) {
                return back()->with('error', 'Supplier harus dipilih untuk payment.');
            }
        }

        // Check if PO already has a payment (only for lunas payment)
        if ($request->purchase_order_ops_id) {
            $po = \App\Models\PurchaseOrderOps::find($request->purchase_order_ops_id);
            
            // For termin payment, allow multiple payments
            if ($po && $po->payment_type === 'termin') {
                // Check total paid amount
                $totalPaid = NonFoodPayment::where('purchase_order_ops_id', $request->purchase_order_ops_id)
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->sum('amount');
                
                $remaining = $po->grand_total - $totalPaid;
                
                // Validate payment amount doesn't exceed remaining
                if ($request->amount > $remaining) {
                    return back()->with('error', "Jumlah pembayaran melebihi sisa yang harus dibayar. Sisa: " . number_format($remaining, 0, ',', '.'));
                }
            } else {
                // For lunas payment, only allow one payment
                $existingPayment = NonFoodPayment::where('purchase_order_ops_id', $request->purchase_order_ops_id)
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->first();
                if ($existingPayment) {
                    return back()->with('error', 'Purchase Order ini sudah memiliki payment yang aktif.');
                }
            }
        }

        // Check if PR already has a payment
        if ($request->purchase_requisition_id) {
            $existingPayment = NonFoodPayment::where('purchase_requisition_id', $request->purchase_requisition_id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
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

        // Check if Retail Non Food already has a payment
        if ($request->retail_non_food_id) {
            $existingPayment = NonFoodPayment::where('retail_non_food_id', $request->retail_non_food_id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->first();
            if ($existingPayment) {
                return back()->with('error', 'Retail Non Food ini sudah memiliki payment yang aktif.');
            }
            
            // Validate retail non food exists and has payment_method = contra_bon
            $retailNonFood = \App\Models\RetailNonFood::find($request->retail_non_food_id);
            if (!$retailNonFood) {
                return back()->with('error', 'Retail Non Food tidak ditemukan.');
            }
            if ($retailNonFood->payment_method !== 'contra_bon') {
                return back()->with('error', 'Hanya Retail Non Food dengan metode pembayaran Contra Bon yang dapat dibuat payment.');
            }
            if ($retailNonFood->status !== 'approved') {
                return back()->with('error', 'Retail Non Food harus berstatus approved untuk dibuat payment.');
            }
            
            // Set supplier_id from retail non food if not provided
            if (empty($request->supplier_id) && $retailNonFood->supplier_id) {
                $request->merge(['supplier_id' => $retailNonFood->supplier_id]);
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

            // Get payment sequence for termin payments
            $paymentSequence = null;
            $isPartialPayment = false;
            if ($request->purchase_order_ops_id) {
                $po = \App\Models\PurchaseOrderOps::find($request->purchase_order_ops_id);
                if ($po && $po->payment_type === 'termin') {
                    $isPartialPayment = true;
                    // Get next sequence number
                    $lastPayment = NonFoodPayment::where('purchase_order_ops_id', $request->purchase_order_ops_id)
                        ->where('status', '!=', 'cancelled')
                        ->orderBy('payment_sequence', 'desc')
                        ->first();
                    $paymentSequence = $lastPayment ? ($lastPayment->payment_sequence + 1) : 1;
                }
            }

            // Create payment
            $payment = NonFoodPayment::create([
                'payment_number' => $paymentNumber,
                'purchase_order_ops_id' => $request->purchase_order_ops_id,
                'purchase_requisition_id' => $request->purchase_requisition_id,
                'retail_non_food_id' => $request->retail_non_food_id,
                'supplier_id' => !empty($request->supplier_id) ? $request->supplier_id : null,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'bank_id' => in_array($request->payment_method, ['transfer', 'check']) ? ($request->bank_id ?? null) : null,
                'payment_date' => $request->payment_date,
                'due_date' => $request->due_date,
                'status' => $request->status ?? 'pending', // Allow status to be set directly
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'is_partial_payment' => $isPartialPayment,
                'payment_sequence' => $paymentSequence,
                'created_by' => Auth::id(),
            ]);

            // Save payment per outlet if provided
            if ($request->has('outlet_payments') && is_array($request->outlet_payments)) {
                foreach ($request->outlet_payments as $outletPayment) {
                    if (!empty($outletPayment['amount']) && $outletPayment['amount'] > 0) {
                        // Validate bank_id if payment method is transfer or check
                        $bankId = null;
                        if (in_array($request->payment_method, ['transfer', 'check'])) {
                            if (empty($outletPayment['bank_id'])) {
                                DB::rollback();
                                return back()->with('error', 'Bank harus dipilih untuk setiap outlet dengan metode pembayaran ' . $request->payment_method . '.');
                            }
                            $bankId = $outletPayment['bank_id'];

                            // Enforce bank account belongs to the same outlet (strict)
                            $outletId = $outletPayment['outlet_id'] ?? null;
                            $bank = \App\Models\BankAccount::find($bankId);
                            if (!$bank) {
                                DB::rollback();
                                return back()->with('error', 'Bank tidak ditemukan. Silakan pilih ulang bank untuk setiap outlet.');
                            }
                            if (!empty($outletId)) {
                                if (intval($bank->outlet_id) !== intval($outletId)) {
                                    DB::rollback();
                                    return back()->with('error', 'Rekening bank harus sesuai outlet masing-masing. Silakan pilih rekening bank untuk outlet yang tepat.');
                                }
                            } else {
                                // For global/HO outlet, only allow HO bank account (outlet_id null)
                                if (!empty($bank->outlet_id)) {
                                    DB::rollback();
                                    return back()->with('error', 'Untuk outlet Global/Head Office, pilih rekening bank Head Office.');
                                }
                            }
                        }
                        
                        \App\Models\NonFoodPaymentOutlet::create([
                            'non_food_payment_id' => $payment->id,
                            'outlet_id' => $outletPayment['outlet_id'] ?? null,
                            'category_id' => $outletPayment['category_id'] ?? null,
                            'amount' => $outletPayment['amount'],
                            'bank_id' => $bankId,
                        ]);
                    }
                }
            }

            // If payment is created with approved/paid status, update PR status
            if (in_array($payment->status, ['approved', 'paid'])) {
                $this->updatePRStatusIfAllPaid($payment);
            }

            DB::commit();
            \Log::info('NonFoodPaymentController@store - Success', ['payment_id' => $payment->id]);

            return redirect()->route('non-food-payments.index')
                ->with('success', 'Non Food Payment berhasil dibuat.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollback();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating Non Food Payment: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return back()->with('error', 'Gagal membuat Non Food Payment: ' . $e->getMessage())->withInput();
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
            'retailNonFood.outlet',
            'retailNonFood.supplier',
            'retailNonFood.items',
            'supplier',
            'creator',
            'approver',
            'attachments.uploader',
            'paymentOutlets.outlet',
            'paymentOutlets.category'
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

        // Get Retail Non Food attachments if payment is for Retail Non Food
        $retailNonFoodAttachments = [];
        
        \Log::info('Checking for Retail Non Food attachments', [
            'payment_id' => $nonFoodPayment->id,
            'retail_non_food_id' => $nonFoodPayment->retail_non_food_id,
            'retail_non_food_id_raw' => $nonFoodPayment->getRawOriginal('retail_non_food_id') ?? 'null'
        ]);
        
        if ($nonFoodPayment->retail_non_food_id) {
            try {
                // Try using relationship first
                if ($nonFoodPayment->retailNonFood) {
                    $invoices = $nonFoodPayment->retailNonFood->invoices;
                    \Log::info('Retail Non Food invoices via relationship', [
                        'retail_non_food_id' => $nonFoodPayment->retail_non_food_id,
                        'invoices_count' => $invoices->count()
                    ]);
                }
                
                // Also try direct query
                $retailNonFoodAttachments = DB::table('retail_non_food_invoices as rnfi')
                    ->where('rnfi.retail_non_food_id', $nonFoodPayment->retail_non_food_id)
                    ->select(
                        'rnfi.id',
                        DB::raw('SUBSTRING_INDEX(rnfi.file_path, "/", -1) as file_name'),
                        'rnfi.file_path',
                        DB::raw("CASE 
                            WHEN rnfi.file_path LIKE '%.jpg' OR rnfi.file_path LIKE '%.jpeg' THEN 'image/jpeg'
                            WHEN rnfi.file_path LIKE '%.png' THEN 'image/png'
                            WHEN rnfi.file_path LIKE '%.pdf' THEN 'application/pdf'
                            ELSE 'image/jpeg'
                        END as file_type"),
                        DB::raw('NULL as file_size'),
                        'rnfi.created_at'
                    )
                    ->get()
                    ->map(function($attachment) {
                        return [
                            'id' => $attachment->id,
                            'file_name' => $attachment->file_name,
                            'file_path' => $attachment->file_path,
                            'file_type' => $attachment->file_type,
                            'file_size' => $attachment->file_size,
                            'created_at' => $attachment->created_at,
                        ];
                    })
                    ->toArray();
                
                \Log::info('Retail Non Food Attachments fetched', [
                    'retail_non_food_id' => $nonFoodPayment->retail_non_food_id,
                    'count' => count($retailNonFoodAttachments),
                    'attachments' => $retailNonFoodAttachments
                ]);
            } catch (\Exception $e) {
                // Table might not exist, continue without attachments
                \Log::error('Error fetching retail non food attachments', [
                    'error' => $e->getMessage(),
                    'retail_non_food_id' => $nonFoodPayment->retail_non_food_id,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            \Log::info('No retail_non_food_id found in payment', [
                'payment_id' => $nonFoodPayment->id,
                'retail_non_food_id' => $nonFoodPayment->retail_non_food_id,
                'payment_data' => $nonFoodPayment->toArray()
            ]);
        }

        \Log::info('Returning NonFoodPayment Show data', [
            'payment_id' => $nonFoodPayment->id,
            'retail_non_food_id' => $nonFoodPayment->retail_non_food_id,
            'retail_non_food_attachments_count' => count($retailNonFoodAttachments),
            'retail_non_food_attachments' => $retailNonFoodAttachments
        ]);
        
        return Inertia::render('NonFoodPayment/Show', [
            'payment' => $nonFoodPayment,
            'po_attachments' => $poAttachments,
            'pr_attachments' => $prAttachments,
            'retail_non_food_attachments' => $retailNonFoodAttachments
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
            'supplier',
            'paymentOutlets.outlet',
            'paymentOutlets.category'
        ]);

        // Get suppliers
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Get banks for dropdown
        $banks = \App\Models\BankAccount::where('is_active', 1)
            ->with('outlet')
            ->orderBy('bank_name')
            ->get();
        
        // Transform untuk include outlet name (sama seperti di BankAccount/Index)
        $banks = $banks->map(function($bank) {
            return [
                'id' => $bank->id,
                'bank_name' => $bank->bank_name,
                'account_number' => $bank->account_number,
                'account_name' => $bank->account_name,
                'outlet_id' => $bank->outlet_id,
                'outlet' => $bank->outlet ? [
                    'id_outlet' => $bank->outlet->id_outlet,
                    'nama_outlet' => $bank->outlet->nama_outlet,
                ] : null,
                'outlet_name' => $bank->outlet ? $bank->outlet->nama_outlet : 'Head Office',
            ];
        });

        return Inertia::render('NonFoodPayment/Edit', [
            'payment' => $nonFoodPayment,
            'suppliers' => $suppliers,
            'banks' => $banks
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
            'bank_id' => 'nullable|required_if:payment_method,transfer,check|exists:bank_accounts,id',
            'payment_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:payment_date',
            'description' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'outlet_payments' => 'nullable|array',
            'outlet_payments.*.outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
            'outlet_payments.*.category_id' => 'nullable|exists:purchase_requisition_categories,id',
            'outlet_payments.*.amount' => 'required|numeric|min:0',
            'outlet_payments.*.bank_id' => 'nullable|required_if:payment_method,transfer,check|exists:bank_accounts,id',
        ]);

        try {
            DB::beginTransaction();

            $updateData = [
                'supplier_id' => $request->supplier_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'bank_id' => in_array($request->payment_method, ['transfer', 'check']) ? ($request->bank_id ?? null) : null,
                'payment_date' => $request->payment_date,
                'due_date' => $request->due_date,
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
            ];

            $nonFoodPayment->update($updateData);

            // Update payment per outlet if provided
            if ($request->has('outlet_payments') && is_array($request->outlet_payments)) {
                // Delete existing outlet payments
                \App\Models\NonFoodPaymentOutlet::where('non_food_payment_id', $nonFoodPayment->id)->delete();
                
                // Create new outlet payments
                foreach ($request->outlet_payments as $outletPayment) {
                    if (!empty($outletPayment['amount']) && $outletPayment['amount'] > 0) {
                        // Validate bank_id if payment method is transfer or check
                        $bankId = null;
                        if (in_array($request->payment_method, ['transfer', 'check'])) {
                            if (empty($outletPayment['bank_id'])) {
                                DB::rollback();
                                return back()->with('error', 'Bank harus dipilih untuk setiap outlet dengan metode pembayaran ' . $request->payment_method . '.');
                            }
                            $bankId = $outletPayment['bank_id'];
                        }
                        
                        \App\Models\NonFoodPaymentOutlet::create([
                            'non_food_payment_id' => $nonFoodPayment->id,
                            'outlet_id' => $outletPayment['outlet_id'] ?? null,
                            'category_id' => $outletPayment['category_id'] ?? null,
                            'amount' => $outletPayment['amount'],
                            'bank_id' => $bankId,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('non-food-payments.show', $nonFoodPayment->id)
                ->with('success', 'Non Food Payment berhasil diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memperbarui Non Food Payment: ' . $e->getMessage());
        }
    }

    public function destroy(NonFoodPayment $nonFoodPayment, \App\Services\BankBookService $bankBookService)
    {
        if (!$nonFoodPayment->canBeDeleted()) {
            return back()->with('error', 'Payment ini tidak dapat dihapus.');
        }

        try {
            DB::beginTransaction();

            // Delete bank book entries if exists
            $bankBookService->deleteByReference('non_food_payment', $nonFoodPayment->id);

            $nonFoodPayment->delete();

            DB::commit();

            return redirect()->route('non-food-payments.index')
                ->with('success', 'Non Food Payment berhasil dihapus.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus Non Food Payment: ' . $e->getMessage());
        }
    }

    public function approve($nonFoodPayment, Request $request = null)
    {
        // Ensure we have a request object
        if ($request === null) {
            $request = request();
        }
        
        // Handle both route model binding (web) and ID parameter (API)
        if (!($nonFoodPayment instanceof NonFoodPayment)) {
            $nonFoodPayment = NonFoodPayment::findOrFail($nonFoodPayment);
        }
        
        // Log approval attempt
        \Log::info('Non Food Payment Approval Attempt', [
            'payment_id' => $nonFoodPayment->id,
            'payment_number' => $nonFoodPayment->payment_number,
            'current_status' => $nonFoodPayment->status,
            'user_id' => Auth::id(),
            'user_jabatan' => Auth::user()->id_jabatan ?? null,
            'can_be_approved' => $nonFoodPayment->canBeApproved()
        ]);
        
        if (!$nonFoodPayment->canBeApproved()) {
            \Log::warning('Non Food Payment cannot be approved', [
                'payment_id' => $nonFoodPayment->id,
                'status' => $nonFoodPayment->status,
                'user_id' => Auth::id()
            ]);
            
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Payment ini tidak dapat disetujui.'], 400);
            }
            return back()->with('error', 'Payment ini tidak dapat disetujui.');
        }

        try {
            DB::beginTransaction();
            
            $user = Auth::user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            $note = $request->input('note', '');
            
            $updateData = [];
            $approvalLevel = '';
            $notificationMessage = '';
            
            // Determine approval level
            if ($isSuperadmin) {
                // Superadmin can approve all levels at once
                if ($nonFoodPayment->status === 'pending' || $nonFoodPayment->approved_finance_manager_by === null) {
                    // Approve Finance Manager level
                    $updateData['approved_finance_manager_by'] = Auth::id();
                    $updateData['approved_finance_manager_at'] = now();
                    $approvalLevel = 'Finance Manager';
                }
                // Also approve GM Finance level
                $updateData['approved_gm_finance_by'] = Auth::id();
                $updateData['approved_gm_finance_at'] = now();
                $updateData['status'] = 'approved';
                $updateData['approved_by'] = Auth::id();
                $updateData['approved_at'] = now();
                $approvalLevel = 'GM Finance (Superadmin)';
                $notificationMessage = "Non Food Payment {$nonFoodPayment->payment_number} telah disetujui oleh Superadmin.";
            } elseif ($user->id_jabatan == 160 && $user->status == 'A') {
                // Finance Manager approval (Level 1)
                // Don't update status, keep it as 'pending' until GM Finance approves
                $updateData['approved_finance_manager_by'] = Auth::id();
                $updateData['approved_finance_manager_at'] = now();
                // Status tetap 'pending', tidak diupdate
                $approvalLevel = 'Finance Manager';
                $notificationMessage = "Non Food Payment {$nonFoodPayment->payment_number} telah disetujui oleh Finance Manager, menunggu persetujuan GM Finance.";
            } elseif ($user->id_jabatan == 316 && $user->status == 'A') {
                // GM Finance approval (Level 2) - only if Finance Manager already approved
                if ($nonFoodPayment->approved_finance_manager_by === null) {
                    DB::rollBack();
                    if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                        return response()->json(['success' => false, 'message' => 'Payment ini harus disetujui Finance Manager terlebih dahulu.'], 400);
                    }
                    return back()->with('error', 'Payment ini harus disetujui Finance Manager terlebih dahulu.');
                }
                $updateData['approved_gm_finance_by'] = Auth::id();
                $updateData['approved_gm_finance_at'] = now();
                $updateData['status'] = 'approved'; // Final approval
                $updateData['approved_by'] = Auth::id();
                $updateData['approved_at'] = now();
                $approvalLevel = 'GM Finance';
                $notificationMessage = "Non Food Payment {$nonFoodPayment->payment_number} telah disetujui oleh GM Finance.";
            } else {
                DB::rollBack();
                if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menyetujui payment ini.'], 403);
                }
                return back()->with('error', 'Anda tidak memiliki izin untuk menyetujui payment ini.');
            }
            
            // Update payment
            $nonFoodPayment->update($updateData);
            $nonFoodPayment->refresh();
            
            \Log::info('Non Food Payment updated successfully', [
                'payment_id' => $nonFoodPayment->id,
                'payment_number' => $nonFoodPayment->payment_number,
                'new_status' => $nonFoodPayment->status,
                'update_data' => $updateData,
                'approval_level' => $approvalLevel
            ]);

            // Log activity
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'approve',
                'module' => 'non_food_payment',
                'description' => "Approve Non Food Payment ({$approvalLevel}): " . $nonFoodPayment->payment_number,
                'ip_address' => $request->ip(),
            ]);

            // Send notification to creator
            if ($nonFoodPayment->created_by) {
                \App\Models\Notification::create([
                    'user_id' => $nonFoodPayment->created_by,
                    'type' => 'non_food_payment_approval',
                    'title' => 'Non Food Payment Disetujui',
                    'message' => $notificationMessage,
                ]);
            }

            // Update PR status to PAID if all payments are completed (only if fully approved)
            if ($nonFoodPayment->status === 'approved') {
                $this->updatePRStatusIfAllPaid($nonFoodPayment);
            }

            DB::commit();

            $successMessage = $isSuperadmin 
                ? 'Non Food Payment berhasil disetujui (semua level).'
                : ($user->id_jabatan == 160 
                    ? 'Non Food Payment berhasil disetujui, menunggu persetujuan GM Finance.'
                    : 'Non Food Payment berhasil disetujui.');

            // Always return JSON for API requests (axios sends Accept: application/json by default)
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson() || $request->header('Accept') === 'application/json') {
                \Log::info('Returning JSON response for approval', [
                    'payment_id' => $nonFoodPayment->id,
                    'success' => true,
                    'message' => $successMessage
                ]);
                return response()->json(['success' => true, 'message' => $successMessage]);
            }

            return back()->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error approving Non Food Payment', [
                'payment_id' => $nonFoodPayment->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Gagal menyetujui Non Food Payment: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal menyetujui Non Food Payment: ' . $e->getMessage());
        }
    }

    public function reject($nonFoodPayment, Request $request = null)
    {
        // Ensure we have a request object
        if ($request === null) {
            $request = request();
        }
        
        // Handle both route model binding (web) and ID parameter (API)
        if (!($nonFoodPayment instanceof NonFoodPayment)) {
            $nonFoodPayment = NonFoodPayment::findOrFail($nonFoodPayment);
        }
        
        if (!$nonFoodPayment->canBeRejected()) {
            if ($request && ($request->wantsJson() || $request->ajax() || $request->expectsJson())) {
                return response()->json(['success' => false, 'message' => 'Payment ini tidak dapat ditolak.'], 400);
            }
            return back()->with('error', 'Payment ini tidak dapat ditolak.');
        }

        try {
            $user = Auth::user();
            $note = $request ? $request->input('note', '') : '';
            
            $updateData = [
                'status' => 'rejected',
                // Store who rejected and when in approved_by/approved_at fields
                // (will be displayed as "Rejected By/At" in view if status is rejected)
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ];
            
            // If Finance Manager rejects, clear their approval
            if ($user->id_jabatan == 160 && $user->status == 'A') {
                $updateData['approved_finance_manager_by'] = null;
                $updateData['approved_finance_manager_at'] = null;
            }
            
            // If GM Finance rejects, clear their approval but keep Finance Manager approval
            if ($user->id_jabatan == 316 && $user->status == 'A') {
                $updateData['approved_gm_finance_by'] = null;
                $updateData['approved_gm_finance_at'] = null;
            }
            
            $nonFoodPayment->update($updateData);

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

    public function markAsPaid(NonFoodPayment $nonFoodPayment, \App\Services\BankBookService $bankBookService)
    {
        if (!$nonFoodPayment->canBePaid()) {
            return back()->with('error', 'Payment ini tidak dapat ditandai sebagai dibayar.');
        }

        try {
            DB::beginTransaction();
            
            // Load payment outlets relationship before updating
            $nonFoodPayment->load('paymentOutlets.outlet');
            
            $nonFoodPayment->update([
                'status' => 'paid',
            ]);

            // Create bank book entry if payment method is transfer or check
            $bankBookService->createFromNonFoodPayment($nonFoodPayment);

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
                'purchaseOrderOps.creator',
                'purchaseOrderOps.source_pr.outlet',
                'purchaseOrderOps.source_pr.creator',
                'purchaseOrderOps.source_pr.division',
                'purchaseOrderOps.source_pr.category',
                'purchaseOrderOps.source_pr.items',
                'purchaseRequisition.items',
                'purchaseRequisition.outlet',
                'purchaseRequisition.division',
                'purchaseRequisition.category',
                'purchaseRequisition.creator'
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
                                $pr = \App\Models\PurchaseRequisition::with(['outlet', 'items', 'division', 'category', 'creator'])
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
            
            // Get budget information for each payment - separate loop for clarity
            $budgetInfos = [];
            foreach ($payments as $payment) {
                $budgetInfo = null;
                $pr = null;
                
                // Strategy 1: Get PR from direct relation
                if ($payment->purchase_requisition_id) {
                    $pr = $payment->purchaseRequisition ?? $payment->purchase_requisition ?? null;
                }
                
                // Strategy 2: Get PR from PO source_pr (already loaded in with())
                if (!$pr && $payment->purchase_order_ops_id && $payment->purchaseOrderOps) {
                    if ($payment->purchaseOrderOps->source_pr) {
                        $pr = $payment->purchaseOrderOps->source_pr;
                    } elseif ($payment->purchaseOrderOps->source_type === 'purchase_requisition_ops' && $payment->purchaseOrderOps->source_id) {
                        // Load PR if not already loaded
                        $pr = \App\Models\PurchaseRequisition::with(['category', 'outlet', 'division', 'items'])
                            ->find($payment->purchaseOrderOps->source_id);
                    }
                }
                
                // Strategy 3: Get PR from pr_from_po attribute (set earlier in the loop)
                if (!$pr && isset($payment->pr_from_po)) {
                    $pr = $payment->pr_from_po;
                }
                
                // If PR found, try to get category_id
                if ($pr) {
                    $categoryId = null;
                    
                    // Try to get category_id from PR directly
                    if ($pr->category_id) {
                        $categoryId = $pr->category_id;
                    } 
                    // Try to get from category relation
                    elseif ($pr->category && $pr->category->id) {
                        $categoryId = $pr->category->id;
                    }
                    // Try to get from PR items (new structure)
                    elseif ($pr->items && $pr->items->count() > 0) {
                        foreach ($pr->items as $item) {
                            if (isset($item->category_id) && $item->category_id) {
                                $categoryId = $item->category_id;
                                break;
                            }
                        }
                    }
                    
                    // If still no category_id, try to load from database
                    if (!$categoryId) {
                        $prItems = DB::table('purchase_requisition_items')
                            ->where('purchase_requisition_id', $pr->id)
                            ->whereNotNull('category_id')
                            ->first();
                        if ($prItems && $prItems->category_id) {
                            $categoryId = $prItems->category_id;
                        }
                    }
                    
                    // If we have category_id, calculate budget info
                    if ($categoryId) {
                        try {
                            // Ensure PR has category_id set for getBudgetInfo method
                            if (!$pr->category_id) {
                                $pr->category_id = $categoryId;
                            }
                            
                            $budgetInfo = $this->getBudgetInfo($pr);
                            
                            if ($budgetInfo) {
                                \Log::info('Budget info calculated successfully for payment ' . $payment->id, [
                                    'pr_id' => $pr->id,
                                    'category_id' => $categoryId,
                                    'budget_type' => $budgetInfo['budget_type'] ?? null
                                ]);
                            }
                        } catch (\Exception $e) {
                            \Log::error('Failed to get budget info for PR ' . $pr->id . ' in Non Food Payment print preview', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            $budgetInfo = null;
                        }
                    } else {
                        \Log::warning('PR has no category_id for payment ' . $payment->id, [
                            'pr_id' => $pr->id ?? null,
                            'pr_category_id' => $pr->category_id ?? null,
                            'has_category_relation' => $pr->category ? true : false,
                            'has_items' => $pr->items ? $pr->items->count() : 0
                        ]);
                    }
                } else {
                    \Log::warning('No PR found for payment ' . $payment->id, [
                        'payment_id' => $payment->id,
                        'purchase_requisition_id' => $payment->purchase_requisition_id,
                        'purchase_order_ops_id' => $payment->purchase_order_ops_id,
                        'has_po' => $payment->purchaseOrderOps ? true : false
                    ]);
                }
                
                $budgetInfos[$payment->id] = $budgetInfo;
            }

            return view('non-food-payments.print-preview', [
                'payments' => $payments,
                'paymentItems' => $paymentItems,
                'budgetInfos' => $budgetInfos,
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
            
            $pendingApprovals = [];
            
            // Finance Manager approvals (id_jabatan == 160) - Level 1
            // Only show payments that haven't been approved by Finance Manager yet
            if (($user->id_jabatan == 160 && $user->status == 'A') || $isSuperadmin) {
                $query = NonFoodPayment::with(['supplier', 'creator', 'purchaseOrderOps', 'purchaseRequisition'])
                    ->where('status', 'pending')
                    ->whereNull('approved_finance_manager_by') // Only show payments not yet approved by Finance Manager
                    ->orderByDesc('created_at');
                
                // Get approver name for this level
                $approver = DB::table('users')
                    ->where('id_jabatan', 160)
                    ->where('status', 'A')
                    ->select('nama_lengkap')
                    ->first();
                
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
                        'approver_name' => $approver ? $approver->nama_lengkap : 'Finance Manager',
                        'approval_level' => 'Finance Manager',
                        'description' => $nfp->description,
                        'notes' => $nfp->notes,
                        'created_at' => $nfp->created_at
                    ];
                }
            }
            
            // GM Finance approvals (id_jabatan == 316) - Level 2
            // Status should still be 'pending' (not updated by Finance Manager)
            if (($user->id_jabatan == 316 && $user->status == 'A') || $isSuperadmin) {
                $query = NonFoodPayment::with(['supplier', 'creator', 'purchaseOrderOps', 'purchaseRequisition'])
                    ->where('status', 'pending')
                    ->whereNotNull('approved_finance_manager_by')
                    ->orderByDesc('approved_finance_manager_at');
                
                // Get approver name for this level
                $approver = DB::table('users')
                    ->where('id_jabatan', 316)
                    ->where('status', 'A')
                    ->select('nama_lengkap')
                    ->first();
                
                $gmFinanceApprovals = $query->get();
                
                foreach ($gmFinanceApprovals as $nfp) {
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
                        'approver_name' => $approver ? $approver->nama_lengkap : 'GM Finance',
                        'approval_level' => 'GM Finance',
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
                        ->leftJoin('purchase_requisition_items as pri', 'poi.pr_ops_item_id', '=', 'pri.id')
                        ->leftJoin('tbl_data_outlet as o', function ($join) {
                            $join->on(DB::raw('COALESCE(poi.outlet_id, pri.outlet_id)'), '=', 'o.id_outlet');
                        })
                        ->leftJoin('purchase_requisition_categories as prc', 'pri.category_id', '=', 'prc.id')
                        ->where('poi.purchase_order_ops_id', $po->id)
                        ->select(
                            'poi.id',
                            'poi.item_name',
                            'poi.quantity',
                            'poi.unit',
                            'poi.price',
                            'poi.discount_percent',
                            'poi.discount_amount',
                            'poi.total',
                            'pr.id as pr_id',
                            DB::raw('COALESCE(poi.outlet_id, pri.outlet_id) as outlet_id'),
                            'o.nama_outlet as outlet_name',
                            'pri.category_id as category_id',
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
                                'discount_percent' => $item->discount_percent ?? 0,
                                'discount_amount' => $item->discount_amount ?? 0,
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
                    'subtotal' => $po->subtotal ?? 0,
                    'discount_total_percent' => $po->discount_total_percent ?? 0,
                    'discount_total_amount' => $po->discount_total_amount ?? 0,
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
                $prAttachments = [];
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
                    $prAttachments = [];
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
                // If items don't have outlet_id but PR has outlet, use PR outlet
                $groupedItems = $items->groupBy(function($item) use ($pr) {
                    // Use item outlet_id if available, otherwise use PR outlet_id
                    return $item->outlet_id ?? $pr->outlet_id ?? null;
                });
                
                $itemsByOutlet = $groupedItems->map(function ($outletItems, $outletId) use ($pr, $prAttachments) {
                    $firstItem = $outletItems->first();
                    
                    // Get outlet name - prefer from item, fallback to PR outlet, then query by outlet_id
                    $outletName = $firstItem->item_outlet_name ?? null;
                    if (!$outletName && $pr->outlet) {
                        $outletName = $pr->outlet->nama_outlet;
                    }
                    if (!$outletName && $outletId) {
                        // Try to get outlet name directly from database
                        try {
                            $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
                            $outletName = $outlet ? $outlet->nama_outlet : null;
                        } catch (\Exception $e) {
                            // Ignore
                        }
                    }
                    if (!$outletName) {
                        $outletName = 'Unknown Outlet';
                    }
                    
                    return [
                        'outlet_id' => $outletId,
                        'outlet_name' => $outletName,
                        'category_id' => $firstItem->category_id ?? null,
                        'category_name' => $firstItem->item_category_name ?? null,
                        'category_division' => $firstItem->item_category_division ?? null,
                        'category_subcategory' => $firstItem->item_category_subcategory ?? null,
                        'category_budget_type' => $firstItem->item_category_budget_type ?? null,
                        'pr_number' => $pr->pr_number,
                        'pr_title' => $pr->title,
                        'pr_description' => $pr->description,
                        'pr_attachments' => $prAttachments ?? [],
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
                    'creator' => $pr->creator ? [
                        'nama_lengkap' => $pr->creator->nama_lengkap
                    ] : null,
                    'created_by_name' => $pr->creator ? $pr->creator->nama_lengkap : null,
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

    /**
     * Get budget information for a Purchase Requisition
     * Same logic as PurchaseOrderOpsController::getBudgetInfo
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
            $categoryBudget = $category->budget_limit;
            
            // Get PR IDs in this category for the month
            $prIds = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($categoryId) {
                    $q->where('pr.category_id', $categoryId)
                      ->orWhere('pri.category_id', $categoryId);
                })
                ->whereYear('pr.created_at', $year)
                ->whereMonth('pr.created_at', $month)
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('pr.is_held', false)
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
            
            $allPrs = PurchaseRequisition::whereIn('id', $prIdsForUnpaid)->get();
            
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
                'division' => $category->division ?? null,
                'category_name' => $category->name ?? null,
            ];

        } else if ($category->isPerOutletBudget()) {
            // PER_OUTLET BUDGET: Calculate per specific outlet
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
            $prIds = DB::table('purchase_requisitions as pr')
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
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('pr.is_held', false)
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
            
            $allPrs = PurchaseRequisition::whereIn('id', $prIdsForUnpaid)->get();
            
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
                'division' => $category->division ?? null,
                'category_name' => $category->name ?? null,
                'outlet_info' => [
                    'id' => $outletBudget->outlet_id,
                    'name' => $outletBudget->outlet->nama_outlet ?? 'Unknown Outlet',
                ],
            ];
        }

        return $budgetInfo;
    }
}
