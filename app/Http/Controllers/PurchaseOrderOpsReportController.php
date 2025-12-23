<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrderOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PurchaseOrderOpsReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'date_from' => $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'status' => $request->input('status', 'all'),
            'supplier_id' => $request->input('supplier_id', ''),
            'search' => $request->input('search', ''),
        ];

        // Get summary metrics
        $summary = $this->getSummaryMetrics($filters);
        
        // Get status distribution
        $statusDistribution = $this->getStatusDistribution($filters);
        
        // Get trend data (daily)
        $trendData = $this->getTrendData($filters);
        
        // Get supplier analysis with pagination
        $supplierAnalysis = $this->getSupplierAnalysis($filters, $request->input('supplier_per_page', 10), $request->input('supplier_search', ''), $request->input('supplier_page', 1));
        
        // Get payment analysis
        $paymentAnalysis = $this->getPaymentAnalysis($filters);
        
        // Get detailed PO data
        $purchaseOrders = $this->getPurchaseOrders($filters, $request->input('per_page', 15));
        
        // Get item analysis with pagination
        $itemAnalysis = $this->getItemAnalysis($filters, $request->input('item_per_page', 15), $request->input('item_search', ''), $request->input('item_page', 1));
        
        // Ensure it's a paginated result, not a query builder
        // Force execution by converting to array and back to ensure it's serialized correctly
        if ($itemAnalysis instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            // Access items to force query execution
            $items = $itemAnalysis->items();
            // Recreate paginator with executed data to ensure proper serialization
            $itemAnalysis = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $itemAnalysis->total(),
                $itemAnalysis->perPage(),
                $itemAnalysis->currentPage(),
                [
                    'path' => $itemAnalysis->path(),
                    'pageName' => 'item_page',
                ]
            );
            $itemAnalysis->appends($request->except('item_page'));
        }

        // Get PO per outlet data
        $poPerOutlet = $this->getPOPerOutlet($filters);

        // Get PO per category data
        $poPerCategory = $this->getPOPerCategory($filters);

        // Get suppliers for filter
        $suppliers = DB::table('suppliers')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('PurchaseOrderOps/Report', [
            'summary' => $summary,
            'statusDistribution' => $statusDistribution,
            'trendData' => $trendData,
            'supplierAnalysis' => $supplierAnalysis,
            'paymentAnalysis' => $paymentAnalysis,
            'itemAnalysis' => $itemAnalysis,
            'purchaseOrders' => $purchaseOrders,
            'poPerOutlet' => $poPerOutlet,
            'poPerCategory' => $poPerCategory,
            'suppliers' => $suppliers,
            'filters' => array_merge($filters, [
                'supplier_search' => $request->input('supplier_search', ''),
                'supplier_per_page' => $request->input('supplier_per_page', 10),
                'item_search' => $request->input('item_search', ''),
                'item_per_page' => $request->input('item_per_page', 15),
                'per_page' => $request->input('per_page', 15),
            ]),
        ]);
    }

    private function getSummaryMetrics($filters)
    {
        $query = DB::table('purchase_order_ops as po')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']]);

        // Apply status filter
        if ($filters['status'] !== 'all') {
            $query->where('po.status', $filters['status']);
        }

        // Apply supplier filter
        if ($filters['supplier_id']) {
            $query->where('po.supplier_id', $filters['supplier_id']);
        }

        $baseQuery = clone $query;
        
        // Apply search filter for summary (if needed, join suppliers)
        if ($filters['search']) {
            $query->leftJoin('suppliers as s_search', 'po.supplier_id', '=', 's_search.id')
                  ->leftJoin('purchase_requisitions as pr_search', 'po.source_id', '=', 'pr_search.id')
                  ->leftJoin('tbl_data_outlet as o_search', 'pr_search.outlet_id', '=', 'o_search.id_outlet')
                  ->where(function($q) use ($filters) {
                      $q->where('po.number', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('s_search.name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('pr_search.pr_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('o_search.nama_outlet', 'like', '%' . $filters['search'] . '%');
                  });
        }

        return [
            'total_po' => $baseQuery->count(),
            'total_value' => $baseQuery->sum('po.grand_total'),
            'total_subtotal' => $baseQuery->sum('po.subtotal'),
            'total_discount' => $baseQuery->sum('po.discount_total_amount'),
            'total_ppn' => $baseQuery->sum('po.ppn_amount'),
            'avg_po_value' => $baseQuery->avg('po.grand_total'),
            'approved_count' => (clone $query)->where('po.status', 'approved')->count(),
            'draft_count' => (clone $query)->where('po.status', 'draft')->count(),
            'received_count' => (clone $query)->where('po.status', 'received')->count(),
            'rejected_count' => (clone $query)->where('po.status', 'rejected')->count(),
        ];
    }

    private function getStatusDistribution($filters)
    {
        $query = DB::table('purchase_order_ops as po')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']]);

        if ($filters['supplier_id']) {
            $query->where('po.supplier_id', $filters['supplier_id']);
        }

        return $query->select('po.status', DB::raw('COUNT(*) as count'), DB::raw('SUM(po.grand_total) as total_value'))
            ->groupBy('po.status')
            ->get()
            ->map(function($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->count,
                    'total_value' => (float)$item->total_value,
                ];
            });
    }

    private function getTrendData($filters)
    {
        $query = DB::table('purchase_order_ops as po')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']]);

        if ($filters['status'] !== 'all') {
            $query->where('po.status', $filters['status']);
        }

        if ($filters['supplier_id']) {
            $query->where('po.supplier_id', $filters['supplier_id']);
        }

        return $query->select(
                DB::raw('DATE(po.date) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(po.grand_total) as total_value')
            )
            ->groupBy(DB::raw('DATE(po.date)'))
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                    'total_value' => (float)$item->total_value,
                ];
            });
    }

    private function getSupplierAnalysis($filters, $perPage = 10, $search = '', $page = 1)
    {
        // First, get supplier summary with PO counts and totals
        // For Total Value, we'll use approved PO only to be consistent with payment calculation
        $supplierQuery = DB::table('purchase_order_ops as po')
            ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']]);

        if ($filters['status'] !== 'all') {
            $supplierQuery->where('po.status', $filters['status']);
        }

        if ($filters['supplier_id']) {
            $supplierQuery->where('po.supplier_id', $filters['supplier_id']);
        }

        // Apply search filter
        if ($search) {
            $supplierQuery->where('s.name', 'like', '%' . $search . '%');
        }

        // Get all PO counts (for display)
        $suppliersAll = $supplierQuery->select(
                's.id as supplier_id',
                's.name as supplier_name',
                DB::raw('COUNT(*) as po_count'),
                DB::raw('SUM(po.grand_total) as total_value_all'),
                DB::raw('AVG(po.grand_total) as avg_value')
            )
            ->groupBy('s.id', 's.name')
            ->get();

        // Get approved PO totals only (for payment consistency)
        $suppliersApproved = (clone $supplierQuery)
            ->where('po.status', 'approved')
            ->select(
                's.id as supplier_id',
                DB::raw('COALESCE(SUM(po.grand_total), 0) as approved_total_value')
            )
            ->groupBy('s.id')
            ->get()
            ->keyBy('supplier_id');

        // Get supplier IDs for payment calculation
        $supplierIds = $suppliersAll->pluck('supplier_id')->toArray();

        if (empty($supplierIds)) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $perPage,
                $page,
                ['path' => request()->url(), 'pageName' => 'supplier_page']
            );
        }

        // Get payment information per supplier (only for approved POs)
        $paymentQuery = DB::table('purchase_order_ops as po')
            ->leftJoin('non_food_payments as nfp', function($join) {
                $join->on('po.id', '=', 'nfp.purchase_order_ops_id')
                     ->where('nfp.status', '!=', 'cancelled');
            })
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']])
            ->whereIn('po.supplier_id', $supplierIds)
            ->where('po.status', 'approved')
            ->select(
                'po.supplier_id',
                DB::raw('COALESCE(SUM(CASE WHEN nfp.status = "paid" THEN nfp.amount ELSE 0 END), 0) as total_paid'),
                DB::raw('COALESCE(SUM(CASE WHEN nfp.status IN ("pending", "approved") THEN nfp.amount ELSE 0 END), 0) as total_pending')
            )
            ->groupBy('po.supplier_id')
            ->get()
            ->keyBy('supplier_id');

        // Merge payment data with supplier data
        $suppliersWithPayment = $suppliersAll->map(function($supplier) use ($paymentQuery, $suppliersApproved) {
            $payment = $paymentQuery->get($supplier->supplier_id);
            $totalPaid = $payment ? (float)$payment->total_paid : 0;
            $totalPending = $payment ? (float)$payment->total_pending : 0;
            
            // Get approved PO total for this supplier (use this as Total Value for consistency)
            $approvedTotal = $suppliersApproved->get($supplier->supplier_id) 
                ? (float)$suppliersApproved->get($supplier->supplier_id)->approved_total_value 
                : 0;
            
            // Unpaid = approved total - paid - pending (ensuring consistency)
            $totalUnpaid = max(0, $approvedTotal - $totalPaid - $totalPending);

            return [
                'supplier_id' => $supplier->supplier_id,
                'supplier_name' => $supplier->supplier_name,
                'po_count' => $supplier->po_count,
                'total_value' => $approvedTotal, // Use approved total for consistency with payment
                'total_value_all' => (float)$supplier->total_value_all, // Keep all status for reference
                'avg_value' => (float)$supplier->avg_value,
                'total_paid' => $totalPaid,
                'total_pending' => $totalPending,
                'total_unpaid' => $totalUnpaid,
            ];
        })->filter(function($supplier) {
            // Only show suppliers with approved PO (total_value > 0)
            return $supplier['total_value'] > 0;
        })->sortByDesc('total_value')
        ->values();

        if ($perPage > 10000) {
            // For export, return all data without pagination
            return $suppliersWithPayment;
        }

        // Create paginator manually
        $currentPage = $page;
        $items = $suppliersWithPayment->forPage($currentPage, $perPage);
        $total = $suppliersWithPayment->count();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'supplier_page',
                'query' => [
                    'supplier_search' => $search,
                    'supplier_per_page' => $perPage,
                    'date_from' => $filters['date_from'],
                    'date_to' => $filters['date_to'],
                    'status' => $filters['status'],
                    'supplier_id' => $filters['supplier_id'],
                ]
            ]
        );
    }

    private function getPaymentAnalysis($filters)
    {
        // Get all approved POs first
        $poQuery = DB::table('purchase_order_ops as po')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']])
            ->where('po.status', 'approved');

        if ($filters['supplier_id']) {
            $poQuery->where('po.supplier_id', $filters['supplier_id']);
        }

        $approvedPOs = $poQuery->select('po.id', 'po.number', 'po.grand_total')
            ->get();

        $poIds = $approvedPOs->pluck('id')->toArray();
        $totalValue = $approvedPOs->sum('grand_total');
        $totalPO = $approvedPOs->count();

        // Get payment information for all approved POs
        $totalPaid = 0;
        $totalPending = 0;
        $fullyPaid = 0;
        $partiallyPaid = 0;
        $unpaid = 0;

        if (!empty($poIds)) {
            $payments = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $poIds)
                ->where('nfp.status', '!=', 'cancelled')
                ->select(
                    'nfp.purchase_order_ops_id',
                    'nfp.status',
                    'nfp.amount'
                )
                ->get()
                ->groupBy('purchase_order_ops_id');

            // Calculate payment status for each PO
            foreach ($approvedPOs as $po) {
                $poPayments = $payments->get($po->id, collect());
                $poPaid = $poPayments->where('status', 'paid')->sum('amount');
                $poPending = $poPayments->whereIn('status', ['pending', 'approved'])->sum('amount');
                
                $totalPaid += $poPaid;
                $totalPending += $poPending;

                // Count payment status
                if ($poPaid >= (float)$po->grand_total) {
                    $fullyPaid++;
                } elseif ($poPaid > 0) {
                    $partiallyPaid++;
                } else {
                    $unpaid++;
                }
            }
        }

        // Unpaid = Total Value - Paid - Pending (ensuring consistency)
        $totalUnpaid = max(0, $totalValue - $totalPaid - $totalPending);

        return [
            'total_po' => $totalPO,
            'total_value' => $totalValue,
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
            'total_unpaid' => $totalUnpaid,
            'fully_paid_count' => $fullyPaid,
            'partially_paid_count' => $partiallyPaid,
            'unpaid_count' => $unpaid,
            'payment_rate' => $totalValue > 0 ? ($totalPaid / $totalValue) * 100 : 0,
        ];
    }

    private function getPurchaseOrders($filters, $perPage = 15)
    {
        $query = DB::table('purchase_order_ops as po')
            ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('users as creator', 'po.created_by', '=', 'creator.id')
            ->leftJoin('users as pm', 'po.purchasing_manager_approved_by', '=', 'pm.id')
            ->leftJoin('users as gm', 'po.gm_finance_approved_by', '=', 'gm.id')
            ->leftJoin('purchase_requisitions as pr', 'po.source_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin(DB::raw('(SELECT purchase_order_ops_id, COUNT(*) as item_count, SUM(total) as items_total FROM purchase_order_ops_items GROUP BY purchase_order_ops_id) as items'), 'po.id', '=', 'items.purchase_order_ops_id')
            ->leftJoin(DB::raw('(SELECT purchase_order_ops_id, COUNT(*) as payment_count, SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END) as total_paid, SUM(CASE WHEN status IN ("pending", "approved") THEN amount ELSE 0 END) as total_pending FROM non_food_payments WHERE status != "cancelled" GROUP BY purchase_order_ops_id) as payments'), 'po.id', '=', 'payments.purchase_order_ops_id')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']])
            ->select(
                'po.id',
                'po.number',
                'po.date',
                'po.status',
                'po.subtotal',
                'po.discount_total_amount',
                'po.ppn_amount',
                'po.grand_total',
                'po.payment_type',
                'po.payment_terms',
                'po.created_at',
                's.id as supplier_id',
                's.name as supplier_name',
                'creator.nama_lengkap as creator_name',
                'pm.nama_lengkap as purchasing_manager_name',
                'gm.nama_lengkap as gm_finance_name',
                'pr.pr_number as source_pr_number',
                'o.nama_outlet as outlet_name',
                'items.item_count',
                'items.items_total',
                'payments.payment_count',
                'payments.total_paid',
                'payments.total_pending',
                DB::raw('(po.grand_total - COALESCE(payments.total_paid, 0) - COALESCE(payments.total_pending, 0)) as remaining_amount')
            );

        // Apply status filter
        if ($filters['status'] !== 'all') {
            $query->where('po.status', $filters['status']);
        }

        // Apply supplier filter
        if ($filters['supplier_id']) {
            $query->where('po.supplier_id', $filters['supplier_id']);
        }

        // Apply search filter
        if ($filters['search']) {
            $query->where(function($q) use ($filters) {
                $q->where('po.number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('s.name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('pr.pr_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('o.nama_outlet', 'like', '%' . $filters['search'] . '%');
            });
        }

        if ($perPage > 10000) {
            // For export, return all data without pagination
            return $query->orderBy('po.date', 'desc')
                ->orderBy('po.created_at', 'desc')
                ->get();
        }
        
        $query = $query->orderBy('po.date', 'desc')
            ->orderBy('po.created_at', 'desc');
            
        if ($perPage > 10000) {
            // For export, return all data without pagination
            return $query->get();
        }
        
        return $query->paginate($perPage)
            ->withQueryString();
    }

    private function getItemAnalysis($filters, $perPage = 15, $search = '', $page = 1)
    {
        $query = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']]);

        if ($filters['status'] !== 'all') {
            $query->where('po.status', $filters['status']);
        }

        if ($filters['supplier_id']) {
            $query->where('po.supplier_id', $filters['supplier_id']);
        }

        // Apply search filter
        if ($search) {
            $query->where('poi.item_name', 'like', '%' . $search . '%');
        }

        $query = $query->select(
                'poi.item_name',
                'poi.unit',
                DB::raw('SUM(poi.quantity) as total_quantity'),
                DB::raw('AVG(poi.price) as avg_price'),
                DB::raw('MIN(poi.price) as min_price'),
                DB::raw('MAX(poi.price) as max_price'),
                DB::raw('SUM(poi.discount_amount) as total_discount'),
                DB::raw('SUM(poi.total) as total_value'),
                DB::raw('COUNT(DISTINCT poi.purchase_order_ops_id) as po_count'),
                DB::raw('COUNT(*) as item_count')
            )
            ->groupBy('poi.item_name', 'poi.unit')
            ->orderByDesc('total_value');
            
        if ($perPage > 10000) {
            // For export, return all data without pagination
            return $query->get();
        }
        
        return $query->paginate($perPage, ['*'], 'item_page', $page)
            ->appends([
                'item_search' => $search,
                'item_per_page' => $perPage,
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'status' => $filters['status'],
                'supplier_id' => $filters['supplier_id'],
            ]);
    }

    public function getItemDetail(Request $request)
    {
        $filters = [
            'date_from' => $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'status' => $request->input('status', 'all'),
            'item_name' => $request->input('item_name'),
            'unit' => $request->input('unit'),
        ];

        if (!$filters['item_name']) {
            return response()->json([
                'success' => false,
                'message' => 'Item name is required'
            ], 400);
        }

        // Get all PO items matching the item name and unit
        $query = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('purchase_requisitions as pr', 'po.source_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as creator', 'po.created_by', '=', 'creator.id')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']])
            ->where('poi.item_name', $filters['item_name']);

        if ($filters['unit']) {
            $query->where('poi.unit', $filters['unit']);
        }

        if ($filters['status'] !== 'all') {
            $query->where('po.status', $filters['status']);
        }

        $itemDetails = $query->select(
                'poi.id',
                'poi.purchase_order_ops_id',
                'poi.item_name',
                'poi.quantity',
                'poi.unit',
                'poi.price',
                'poi.discount_percent',
                'poi.discount_amount',
                'poi.total',
                'po.id as po_id',
                'po.number as po_number',
                'po.date as po_date',
                'po.status as po_status',
                'po.grand_total as po_grand_total',
                's.id as supplier_id',
                's.name as supplier_name',
                'pr.pr_number as source_pr_number',
                'o.nama_outlet as outlet_name',
                'creator.nama_lengkap as creator_name'
            )
            ->orderBy('po.date', 'desc')
            ->orderBy('po.number', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'po_id' => $item->po_id,
                    'po_number' => $item->po_number,
                    'po_date' => $item->po_date,
                    'po_status' => $item->po_status,
                    'po_grand_total' => (float)$item->po_grand_total,
                    'item_name' => $item->item_name,
                    'quantity' => (float)$item->quantity,
                    'unit' => $item->unit,
                    'price' => (float)$item->price,
                    'discount_percent' => (float)$item->discount_percent,
                    'discount_amount' => (float)$item->discount_amount,
                    'total' => (float)$item->total,
                    'supplier_id' => $item->supplier_id,
                    'supplier_name' => $item->supplier_name,
                    'source_pr_number' => $item->source_pr_number,
                    'outlet_name' => $item->outlet_name,
                    'creator_name' => $item->creator_name,
                ];
            });

        // Calculate summary
        $summary = [
            'total_quantity' => $itemDetails->sum('quantity'),
            'total_value' => $itemDetails->sum('total'),
            'total_discount' => $itemDetails->sum('discount_amount'),
            'avg_price' => $itemDetails->avg('price'),
            'min_price' => $itemDetails->min('price'),
            'max_price' => $itemDetails->max('price'),
            'po_count' => $itemDetails->pluck('po_id')->unique()->count(),
            'supplier_count' => $itemDetails->pluck('supplier_id')->filter()->unique()->count(),
            'item_count' => $itemDetails->count(),
        ];

        // Get unique suppliers
        $suppliers = $itemDetails->pluck('supplier_name', 'supplier_id')
            ->filter()
            ->unique()
            ->values();

        return response()->json([
            'success' => true,
            'item_name' => $filters['item_name'],
            'unit' => $filters['unit'],
            'summary' => $summary,
            'suppliers' => $suppliers,
            'details' => $itemDetails,
        ]);
    }

    private function getPOPerOutlet($filters)
    {
        // Query melalui PO items -> PR items -> outlet
        // Karena 1 PO bisa punya beberapa PR items, dan 1 PR item bisa punya outlet berbeda
        $query = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
            ->leftJoin('purchase_requisition_items as pri', function($join) {
                // Join melalui pr_ops_item_id (primary) atau source_id jika source_type = purchase_requisition_ops
                $join->where(function($q) {
                    $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                      ->orWhere(function($q2) {
                          $q2->where('poi.source_type', '=', 'purchase_requisition_ops')
                             ->whereColumn('poi.source_id', 'pri.id');
                      });
                });
            })
            ->leftJoin('tbl_data_outlet as o', 'pri.outlet_id', '=', 'o.id_outlet')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']]);

        if ($filters['status'] !== 'all') {
            $query->where('po.status', $filters['status']);
        }

        if ($filters['supplier_id']) {
            $query->where('po.supplier_id', $filters['supplier_id']);
        }

        // First, get outlet summary
        $outletSummary = $query->select(
                'o.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
                DB::raw('COUNT(DISTINCT po.id) as po_count'),
                DB::raw('COALESCE(SUM(poi.total), 0) as total_value')
            )
            ->whereNotNull('o.id_outlet')
            ->groupBy('o.id_outlet', 'o.nama_outlet')
            ->orderByDesc('total_value')
            ->get();

        // Get PO to outlet mapping
        $poToOutlet = [];
        $outletPoIds = [];
        foreach ($outletSummary as $outlet) {
            $outletPoIds[$outlet->outlet_id] = [];
        }

        // Get PO IDs per outlet
        $poOutletQuery = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
            ->leftJoin('purchase_requisition_items as pri', function($join) {
                $join->where(function($q) {
                    $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                      ->orWhere(function($q2) {
                          $q2->where('poi.source_type', '=', 'purchase_requisition_ops')
                             ->whereColumn('poi.source_id', 'pri.id');
                      });
                });
            })
            ->leftJoin('tbl_data_outlet as o', 'pri.outlet_id', '=', 'o.id_outlet')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']])
            ->whereNotNull('o.id_outlet')
            ->whereIn('o.id_outlet', array_keys($outletPoIds));

        if ($filters['status'] !== 'all') {
            $poOutletQuery->where('po.status', $filters['status']);
        }

        if ($filters['supplier_id']) {
            $poOutletQuery->where('po.supplier_id', $filters['supplier_id']);
        }

        $poOutletMapping = $poOutletQuery->select('po.id as po_id', 'o.id_outlet as outlet_id')
            ->distinct()
            ->get();

        foreach ($poOutletMapping as $mapping) {
            if (!isset($outletPoIds[$mapping->outlet_id])) {
                $outletPoIds[$mapping->outlet_id] = [];
            }
            $outletPoIds[$mapping->outlet_id][] = $mapping->po_id;
            $poToOutlet[$mapping->po_id] = $mapping->outlet_id;
        }

        // Get all PO IDs
        $allPoIds = [];
        foreach ($outletPoIds as $poIds) {
            $allPoIds = array_merge($allPoIds, $poIds);
        }
        $allPoIds = array_unique($allPoIds);

        // Get payment data per PO
        $payments = collect();
        if (!empty($allPoIds)) {
            $payments = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $allPoIds)
                ->where('nfp.status', '!=', 'cancelled')
                ->select(
                    'nfp.purchase_order_ops_id',
                    DB::raw('SUM(CASE WHEN nfp.status = "paid" THEN nfp.amount ELSE 0 END) as paid'),
                    DB::raw('SUM(CASE WHEN nfp.status IN ("pending", "approved") THEN nfp.amount ELSE 0 END) as pending')
                )
                ->groupBy('nfp.purchase_order_ops_id')
                ->get()
                ->keyBy('purchase_order_ops_id');
        }

        // Merge payment data with outlet data
        return $outletSummary->filter(function($item) {
                return $item->po_count > 0 && $item->total_value > 0;
            })
            ->map(function($item) use ($outletPoIds, $payments) {
                $outletPaid = 0;
                $outletPending = 0;
                
                $poIds = $outletPoIds[$item->outlet_id] ?? [];
                foreach ($poIds as $poId) {
                    $payment = $payments->get($poId);
                    if ($payment) {
                        $outletPaid += (float)$payment->paid;
                        $outletPending += (float)$payment->pending;
                    }
                }
                
                $outletTotal = (float)$item->total_value;
                $outletUnpaid = max(0, $outletTotal - $outletPaid - $outletPending);
                
                return [
                    'outlet_id' => $item->outlet_id,
                    'outlet_name' => $item->outlet_name,
                    'po_count' => (int)$item->po_count,
                    'total_value' => $outletTotal,
                    'total_paid' => $outletPaid,
                    'total_pending' => $outletPending,
                    'total_unpaid' => $outletUnpaid,
                ];
            })
            ->sortByDesc('total_value')
            ->values();
    }

    private function getPOPerCategory($filters)
    {
        // Query melalui PO items -> PR items -> category
        // Karena 1 PO bisa punya beberapa PR items, dan 1 PR item bisa punya category berbeda
        $query = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
            ->leftJoin('purchase_requisition_items as pri', function($join) {
                // Join melalui pr_ops_item_id (primary) atau source_id jika source_type = purchase_requisition_ops
                $join->where(function($q) {
                    $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                      ->orWhere(function($q2) {
                          $q2->where('poi.source_type', '=', 'purchase_requisition_ops')
                             ->whereColumn('poi.source_id', 'pri.id');
                      });
                });
            })
            ->leftJoin('purchase_requisition_categories as prc', 'pri.category_id', '=', 'prc.id')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']]);

        if ($filters['status'] !== 'all') {
            $query->where('po.status', $filters['status']);
        }

        if ($filters['supplier_id']) {
            $query->where('po.supplier_id', $filters['supplier_id']);
        }

        // First, get category summary
        $categorySummary = $query->select(
                'prc.id as category_id',
                'prc.name as category_name',
                'prc.division as category_division',
                DB::raw('CONCAT(prc.division, " - ", prc.name) as category_display_name'),
                DB::raw('COUNT(DISTINCT po.id) as po_count'),
                DB::raw('COALESCE(SUM(poi.total), 0) as total_value')
            )
            ->whereNotNull('prc.id')
            ->groupBy('prc.id', 'prc.name', 'prc.division')
            ->orderByDesc('total_value')
            ->get();

        // Get PO to category mapping
        $categoryPoIds = [];
        foreach ($categorySummary as $category) {
            $categoryPoIds[$category->category_id] = [];
        }

        // Get PO IDs per category
        $poCategoryQuery = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
            ->leftJoin('purchase_requisition_items as pri', function($join) {
                $join->where(function($q) {
                    $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                      ->orWhere(function($q2) {
                          $q2->where('poi.source_type', '=', 'purchase_requisition_ops')
                             ->whereColumn('poi.source_id', 'pri.id');
                      });
                });
            })
            ->leftJoin('purchase_requisition_categories as prc', 'pri.category_id', '=', 'prc.id')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']])
            ->whereNotNull('prc.id')
            ->whereIn('prc.id', array_keys($categoryPoIds));

        if ($filters['status'] !== 'all') {
            $poCategoryQuery->where('po.status', $filters['status']);
        }

        if ($filters['supplier_id']) {
            $poCategoryQuery->where('po.supplier_id', $filters['supplier_id']);
        }

        $poCategoryMapping = $poCategoryQuery->select('po.id as po_id', 'prc.id as category_id')
            ->distinct()
            ->get();

        foreach ($poCategoryMapping as $mapping) {
            if (!isset($categoryPoIds[$mapping->category_id])) {
                $categoryPoIds[$mapping->category_id] = [];
            }
            $categoryPoIds[$mapping->category_id][] = $mapping->po_id;
        }

        // Get all PO IDs
        $allPoIds = [];
        foreach ($categoryPoIds as $poIds) {
            $allPoIds = array_merge($allPoIds, $poIds);
        }
        $allPoIds = array_unique($allPoIds);

        // Get payment data per PO
        $payments = collect();
        if (!empty($allPoIds)) {
            $payments = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $allPoIds)
                ->where('nfp.status', '!=', 'cancelled')
                ->select(
                    'nfp.purchase_order_ops_id',
                    DB::raw('SUM(CASE WHEN nfp.status = "paid" THEN nfp.amount ELSE 0 END) as paid'),
                    DB::raw('SUM(CASE WHEN nfp.status IN ("pending", "approved") THEN nfp.amount ELSE 0 END) as pending')
                )
                ->groupBy('nfp.purchase_order_ops_id')
                ->get()
                ->keyBy('purchase_order_ops_id');
        }

        // Merge payment data with category data
        return $categorySummary->filter(function($item) {
                return $item->po_count > 0 && $item->total_value > 0;
            })
            ->map(function($item) use ($categoryPoIds, $payments) {
                $categoryPaid = 0;
                $categoryPending = 0;
                
                $poIds = $categoryPoIds[$item->category_id] ?? [];
                foreach ($poIds as $poId) {
                    $payment = $payments->get($poId);
                    if ($payment) {
                        $categoryPaid += (float)$payment->paid;
                        $categoryPending += (float)$payment->pending;
                    }
                }
                
                $categoryTotal = (float)$item->total_value;
                $categoryUnpaid = max(0, $categoryTotal - $categoryPaid - $categoryPending);
                
                return [
                    'category_id' => $item->category_id,
                    'category_name' => $item->category_display_name ?: ($item->category_division . ' - ' . $item->category_name),
                    'category_division' => $item->category_division,
                    'po_count' => (int)$item->po_count,
                    'total_value' => $categoryTotal,
                    'total_paid' => $categoryPaid,
                    'total_pending' => $categoryPending,
                    'total_unpaid' => $categoryUnpaid,
                ];
            })
            ->sortByDesc('total_value')
            ->values();
    }

    public function getOutletDetail(Request $request, $outletId)
    {
        $filters = [
            'date_from' => $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'status' => $request->input('status', 'all'),
        ];

        // Get outlet info
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->select('id_outlet', 'nama_outlet')
            ->first();

        if (!$outlet) {
            return response()->json([
                'success' => false,
                'message' => 'Outlet not found'
            ], 404);
        }

        // Get all POs that have items linked to this outlet
        // Query melalui PO items -> PR items -> outlet
        $poIdsQuery = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
            ->leftJoin('purchase_requisition_items as pri', function($join) {
                // Join melalui pr_ops_item_id (primary) atau source_id jika source_type = purchase_requisition_ops
                $join->where(function($q) {
                    $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                      ->orWhere(function($q2) {
                          $q2->where('poi.source_type', '=', 'purchase_requisition_ops')
                             ->whereColumn('poi.source_id', 'pri.id');
                      });
                });
            })
            ->where('pri.outlet_id', $outletId)
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']])
            ->distinct()
            ->pluck('po.id');

        if ($poIdsQuery->isEmpty()) {
            return response()->json([
                'success' => true,
                'outlet' => [
                    'id' => $outlet->id_outlet,
                    'name' => $outlet->nama_outlet,
                ],
                'summary' => [
                    'total_po' => 0,
                    'total_value' => 0,
                    'total_subtotal' => 0,
                    'total_discount' => 0,
                    'total_ppn' => 0,
                    'total_paid' => 0,
                    'total_pending' => 0,
                    'total_unpaid' => 0,
                ],
                'purchase_orders' => [],
            ]);
        }

        // Get PO details
        $query = DB::table('purchase_order_ops as po')
            ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('users as creator', 'po.created_by', '=', 'creator.id')
            ->leftJoin('purchase_requisitions as pr', function($join) {
                $join->on('po.source_id', '=', 'pr.id')
                     ->where(function($q) {
                         $q->where('po.source_type', '=', 'purchase_requisition')
                           ->orWhere('po.source_type', '=', 'purchase_requisition_ops')
                           ->orWhereNull('po.source_type');
                     });
            })
            ->whereIn('po.id', $poIdsQuery);

        if ($filters['status'] !== 'all') {
            $query->where('po.status', $filters['status']);
        }

        $purchaseOrders = $query->select(
                'po.id',
                'po.number',
                'po.date',
                'po.status',
                'po.grand_total',
                'po.subtotal',
                'po.discount_total_amount',
                'po.ppn_amount',
                's.name as supplier_name',
                'creator.nama_lengkap as creator_name',
                'pr.pr_number as source_pr_number'
            )
            ->orderBy('po.date', 'desc')
            ->orderBy('po.number', 'desc')
            ->get()
            ->map(function($po) {
                return [
                    'id' => $po->id,
                    'number' => $po->number,
                    'date' => $po->date,
                    'status' => $po->status,
                    'grand_total' => (float)$po->grand_total,
                    'subtotal' => (float)$po->subtotal,
                    'discount_total_amount' => (float)$po->discount_total_amount,
                    'ppn_amount' => (float)$po->ppn_amount,
                    'supplier_name' => $po->supplier_name,
                    'creator_name' => $po->creator_name,
                    'source_pr_number' => $po->source_pr_number,
                ];
            });

        // Get items for each PO that belong to this outlet (filtered by outlet_id)
        $poIds = $purchaseOrders->pluck('id')->toArray();
        $items = [];
        if (!empty($poIds)) {
            $itemsQuery = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    // Join melalui pr_ops_item_id (primary) atau source_id jika source_type = purchase_requisition_ops
                    $join->where(function($q) {
                        $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                          ->orWhere(function($q2) {
                              $q2->where('poi.source_type', '=', 'purchase_requisition_ops')
                                 ->whereColumn('poi.source_id', 'pri.id');
                          });
                    });
                })
                ->whereIn('poi.purchase_order_ops_id', $poIds)
                ->where('pri.outlet_id', $outletId) // Only items for this outlet
                ->select(
                    'poi.purchase_order_ops_id',
                    'poi.item_name',
                    'poi.quantity',
                    'poi.unit',
                    'poi.price',
                    'poi.discount_percent',
                    'poi.discount_amount',
                    'poi.total'
                )
                ->orderBy('poi.purchase_order_ops_id')
                ->orderBy('poi.item_name')
                ->get();

            foreach ($itemsQuery as $item) {
                if (!isset($items[$item->purchase_order_ops_id])) {
                    $items[$item->purchase_order_ops_id] = [];
                }
                $items[$item->purchase_order_ops_id][] = [
                    'item_name' => $item->item_name,
                    'quantity' => (float)$item->quantity,
                    'unit' => $item->unit,
                    'price' => (float)$item->price,
                    'discount_percent' => (float)$item->discount_percent,
                    'discount_amount' => (float)$item->discount_amount,
                    'total' => (float)$item->total,
                ];
            }
        }

        // Attach items to each PO
        $purchaseOrdersWithItems = $purchaseOrders->map(function($po) use ($items) {
            $po['items'] = $items[$po['id']] ?? [];
            return $po;
        });

        // Calculate summary based on items for this outlet only
        $totalValue = 0;
        $totalSubtotal = 0;
        $totalDiscount = 0;
        foreach ($items as $poId => $poItems) {
            foreach ($poItems as $item) {
                $totalValue += $item['total'];
                $totalSubtotal += ($item['price'] * $item['quantity']);
                $totalDiscount += $item['discount_amount'];
            }
        }

        // Get payment data for POs in this outlet
        $poIds = $purchaseOrders->pluck('id')->toArray();
        $totalPaid = 0;
        $totalPending = 0;
        
        if (!empty($poIds)) {
            $payments = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $poIds)
                ->where('nfp.status', '!=', 'cancelled')
                ->select(
                    'nfp.purchase_order_ops_id',
                    DB::raw('SUM(CASE WHEN nfp.status = "paid" THEN nfp.amount ELSE 0 END) as paid'),
                    DB::raw('SUM(CASE WHEN nfp.status IN ("pending", "approved") THEN nfp.amount ELSE 0 END) as pending')
                )
                ->groupBy('nfp.purchase_order_ops_id')
                ->get()
                ->keyBy('purchase_order_ops_id');

            foreach ($poIds as $poId) {
                $payment = $payments->get($poId);
                if ($payment) {
                    $totalPaid += (float)$payment->paid;
                    $totalPending += (float)$payment->pending;
                }
            }
        }

        $totalUnpaid = max(0, $totalValue - $totalPaid - $totalPending);

        $summary = [
            'total_po' => $purchaseOrders->count(),
            'total_value' => $totalValue, // Sum dari items untuk outlet ini
            'total_subtotal' => $totalSubtotal,
            'total_discount' => $totalDiscount,
            'total_ppn' => 0, // PPN dihitung per PO, tidak per item
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
            'total_unpaid' => $totalUnpaid,
        ];

        return response()->json([
            'success' => true,
            'outlet' => [
                'id' => $outlet->id_outlet,
                'name' => $outlet->nama_outlet,
            ],
            'summary' => $summary,
            'purchase_orders' => $purchaseOrdersWithItems,
        ]);
    }

    public function getSupplierDetail(Request $request, $supplierId)
    {
        $filters = [
            'date_from' => $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'status' => $request->input('status', 'all'),
        ];

        // Get all POs for this supplier
        $query = DB::table('purchase_order_ops as po')
            ->leftJoin('purchase_requisitions as pr', 'po.source_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as creator', 'po.created_by', '=', 'creator.id')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']])
            ->where('po.supplier_id', $supplierId);

        if ($filters['status'] !== 'all') {
            $query->where('po.status', $filters['status']);
        }

        $purchaseOrders = $query->select(
                'po.id',
                'po.number',
                'po.date',
                'po.status',
                'po.subtotal',
                'po.discount_total_amount',
                'po.ppn_amount',
                'po.grand_total',
                'po.payment_type',
                'pr.pr_number as source_pr_number',
                'o.nama_outlet as outlet_name',
                'creator.nama_lengkap as creator_name'
            )
            ->orderBy('po.date', 'desc')
            ->get();

        // Get items for each PO
        $poIds = $purchaseOrders->pluck('id')->toArray();
        
        $items = [];
        if (!empty($poIds)) {
            $items = DB::table('purchase_order_ops_items as poi')
                ->whereIn('poi.purchase_order_ops_id', $poIds)
                ->select(
                    'poi.purchase_order_ops_id',
                    'poi.item_name',
                    'poi.quantity',
                    'poi.unit',
                    'poi.price',
                    'poi.discount_amount',
                    'poi.total'
                )
                ->get()
                ->groupBy('purchase_order_ops_id');
        }

        // Get payment info for each PO
        $payments = [];
        if (!empty($poIds)) {
            $payments = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $poIds)
                ->where('nfp.status', '!=', 'cancelled')
                ->select(
                    'nfp.purchase_order_ops_id',
                    'nfp.status',
                    'nfp.amount'
                )
                ->get()
                ->groupBy('purchase_order_ops_id')
                ->map(function($group) {
                    return [
                        'total_paid' => $group->where('status', 'paid')->sum('amount'),
                        'total_pending' => $group->whereIn('status', ['pending', 'approved'])->sum('amount'),
                    ];
                });
        }

        // Combine data
        $poDetails = $purchaseOrders->map(function($po) use ($items, $payments) {
            $poItems = $items->get($po->id, collect());
            $payment = $payments->get($po->id, ['total_paid' => 0, 'total_pending' => 0]);
            
            return [
                'id' => $po->id,
                'number' => $po->number,
                'date' => $po->date,
                'status' => $po->status,
                'subtotal' => (float)$po->subtotal,
                'discount_total_amount' => (float)$po->discount_total_amount,
                'ppn_amount' => (float)$po->ppn_amount,
                'grand_total' => (float)$po->grand_total,
                'payment_type' => $po->payment_type,
                'source_pr_number' => $po->source_pr_number,
                'outlet_name' => $po->outlet_name,
                'creator_name' => $po->creator_name,
                'total_paid' => (float)$payment['total_paid'],
                'total_pending' => (float)$payment['total_pending'],
                'total_unpaid' => max(0, (float)$po->grand_total - (float)$payment['total_paid'] - (float)$payment['total_pending']),
                'items' => $poItems->map(function($item) {
                    return [
                        'item_name' => $item->item_name,
                        'quantity' => (float)$item->quantity,
                        'unit' => $item->unit,
                        'price' => (float)$item->price,
                        'discount_amount' => (float)$item->discount_amount,
                        'total' => (float)$item->total,
                    ];
                })->values(),
            ];
        });

        // Get supplier info
        $supplier = DB::table('suppliers')
            ->where('id', $supplierId)
            ->select('id', 'name')
            ->first();

        return response()->json([
            'success' => true,
            'supplier' => $supplier,
            'purchase_orders' => $poDetails,
            'summary' => [
                'total_po' => $poDetails->count(),
                'total_value' => $poDetails->sum('grand_total'),
                'total_paid' => $poDetails->sum('total_paid'),
                'total_pending' => $poDetails->sum('total_pending'),
                'total_unpaid' => $poDetails->sum('total_unpaid'),
            ]
        ]);
    }

    public function getCategoryDetail(Request $request, $categoryId)
    {
        $filters = [
            'date_from' => $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'status' => $request->input('status', 'all'),
        ];

        // Get category info
        $category = DB::table('purchase_requisition_categories')
            ->where('id', $categoryId)
            ->select('id', 'name', 'division', DB::raw('CONCAT(division, " - ", name) as display_name'))
            ->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        // Get all POs that have items linked to this category
        $poIdsQuery = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
            ->leftJoin('purchase_requisition_items as pri', function($join) {
                $join->where(function($q) {
                    $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                      ->orWhere(function($q2) {
                          $q2->where('poi.source_type', '=', 'purchase_requisition_ops')
                             ->whereColumn('poi.source_id', 'pri.id');
                      });
                });
            })
            ->where('pri.category_id', $categoryId)
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']])
            ->distinct()
            ->pluck('po.id');

        if ($poIdsQuery->isEmpty()) {
            return response()->json([
                'success' => true,
                'category' => [
                    'id' => $category->id,
                    'name' => $category->display_name ?: ($category->division . ' - ' . $category->name),
                    'division' => $category->division,
                ],
                'summary' => [
                    'total_po' => 0,
                    'total_value' => 0,
                    'total_subtotal' => 0,
                    'total_discount' => 0,
                    'total_ppn' => 0,
                    'total_paid' => 0,
                    'total_pending' => 0,
                    'total_unpaid' => 0,
                ],
                'purchase_orders' => [],
            ]);
        }

        // Get PO details
        $query = DB::table('purchase_order_ops as po')
            ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('users as creator', 'po.created_by', '=', 'creator.id')
            ->leftJoin('purchase_requisitions as pr', function($join) {
                $join->on('po.source_id', '=', 'pr.id')
                     ->where(function($q) {
                         $q->where('po.source_type', '=', 'purchase_requisition')
                           ->orWhere('po.source_type', '=', 'purchase_requisition_ops')
                           ->orWhereNull('po.source_type');
                     });
            })
            ->whereIn('po.id', $poIdsQuery);

        if ($filters['status'] !== 'all') {
            $query->where('po.status', $filters['status']);
        }

        $purchaseOrders = $query->select(
                'po.id',
                'po.number',
                'po.date',
                'po.status',
                'po.grand_total',
                'po.subtotal',
                'po.discount_total_amount',
                'po.ppn_amount',
                's.name as supplier_name',
                'creator.nama_lengkap as creator_name',
                'pr.pr_number as source_pr_number'
            )
            ->orderBy('po.date', 'desc')
            ->orderBy('po.number', 'desc')
            ->get()
            ->map(function($po) {
                return [
                    'id' => $po->id,
                    'number' => $po->number,
                    'date' => $po->date,
                    'status' => $po->status,
                    'grand_total' => (float)$po->grand_total,
                    'subtotal' => (float)$po->subtotal,
                    'discount_total_amount' => (float)$po->discount_total_amount,
                    'ppn_amount' => (float)$po->ppn_amount,
                    'supplier_name' => $po->supplier_name,
                    'creator_name' => $po->creator_name,
                    'source_pr_number' => $po->source_pr_number,
                ];
            });

        // Get items for each PO that belong to this category (filtered by category_id)
        $poIds = $purchaseOrders->pluck('id')->toArray();
        $items = [];
        if (!empty($poIds)) {
            $itemsQuery = DB::table('purchase_order_ops_items as poi')
                ->leftJoin('purchase_requisition_items as pri', function($join) {
                    $join->where(function($q) {
                        $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                          ->orWhere(function($q2) {
                              $q2->where('poi.source_type', '=', 'purchase_requisition_ops')
                                 ->whereColumn('poi.source_id', 'pri.id');
                          });
                    });
                })
                ->whereIn('poi.purchase_order_ops_id', $poIds)
                ->where('pri.category_id', $categoryId) // Only items for this category
                ->select(
                    'poi.purchase_order_ops_id',
                    'poi.item_name',
                    'poi.quantity',
                    'poi.unit',
                    'poi.price',
                    'poi.discount_percent',
                    'poi.discount_amount',
                    'poi.total'
                )
                ->orderBy('poi.purchase_order_ops_id')
                ->orderBy('poi.item_name')
                ->get();

            foreach ($itemsQuery as $item) {
                if (!isset($items[$item->purchase_order_ops_id])) {
                    $items[$item->purchase_order_ops_id] = [];
                }
                $items[$item->purchase_order_ops_id][] = [
                    'item_name' => $item->item_name,
                    'quantity' => (float)$item->quantity,
                    'unit' => $item->unit,
                    'price' => (float)$item->price,
                    'discount_percent' => (float)$item->discount_percent,
                    'discount_amount' => (float)$item->discount_amount,
                    'total' => (float)$item->total,
                ];
            }
        }

        // Attach items to each PO
        $purchaseOrdersWithItems = $purchaseOrders->map(function($po) use ($items) {
            $po['items'] = $items[$po['id']] ?? [];
            return $po;
        });

        // Calculate summary based on items for this category only
        $totalValue = 0;
        $totalSubtotal = 0;
        $totalDiscount = 0;
        foreach ($items as $poId => $poItems) {
            foreach ($poItems as $item) {
                $totalValue += $item['total'];
                $totalSubtotal += ($item['price'] * $item['quantity']);
                $totalDiscount += $item['discount_amount'];
            }
        }

        // Get payment data for POs in this category
        $totalPaid = 0;
        $totalPending = 0;
        
        if (!empty($poIds)) {
            $payments = DB::table('non_food_payments as nfp')
                ->whereIn('nfp.purchase_order_ops_id', $poIds)
                ->where('nfp.status', '!=', 'cancelled')
                ->select(
                    'nfp.purchase_order_ops_id',
                    DB::raw('SUM(CASE WHEN nfp.status = "paid" THEN nfp.amount ELSE 0 END) as paid'),
                    DB::raw('SUM(CASE WHEN nfp.status IN ("pending", "approved") THEN nfp.amount ELSE 0 END) as pending')
                )
                ->groupBy('nfp.purchase_order_ops_id')
                ->get()
                ->keyBy('purchase_order_ops_id');

            foreach ($poIds as $poId) {
                $payment = $payments->get($poId);
                if ($payment) {
                    $totalPaid += (float)$payment->paid;
                    $totalPending += (float)$payment->pending;
                }
            }
        }

        $totalUnpaid = max(0, $totalValue - $totalPaid - $totalPending);

        $summary = [
            'total_po' => $purchaseOrders->count(),
            'total_value' => $totalValue,
            'total_subtotal' => $totalSubtotal,
            'total_discount' => $totalDiscount,
            'total_ppn' => 0,
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
            'total_unpaid' => $totalUnpaid,
        ];

        return response()->json([
            'success' => true,
            'category' => [
                'id' => $category->id,
                'name' => $category->display_name ?: ($category->division . ' - ' . $category->name),
                'division' => $category->division,
            ],
            'summary' => $summary,
            'purchase_orders' => $purchaseOrdersWithItems,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $filters = [
            'date_from' => $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'status' => $request->input('status', 'all'),
            'supplier_id' => $request->input('supplier_id', ''),
            'search' => $request->input('search', ''),
        ];

        // Create new Spreadsheet
        $spreadsheet = new Spreadsheet();
        
        // Remove default sheet
        $spreadsheet->removeSheetByIndex(0);

        // Sheet 1: Summary Metrics
        $this->createSummarySheet($spreadsheet, $filters);
        
        // Sheet 2: Top Suppliers
        $this->createSuppliersSheet($spreadsheet, $filters);
        
        // Sheet 3: Item Analysis (with categories and full details)
        $this->createItemAnalysisSheet($spreadsheet, $filters);
        
        // Sheet 4: Purchase Orders Detail
        $this->createPurchaseOrdersSheet($spreadsheet, $filters);
        
        // Sheet 5: Payment Analysis
        $this->createPaymentAnalysisSheet($spreadsheet, $filters);
        
        // Sheet 6: PO per Outlet
        $this->createPOPerOutletSheet($spreadsheet, $filters);
        
        // Sheet 7: PO per Category
        $this->createPOPerCategorySheet($spreadsheet, $filters);

        // Set first sheet as active
        $spreadsheet->setActiveSheetIndex(0);

        // Create writer and output
        $writer = new Xlsx($spreadsheet);
        $filename = 'PO_Ops_Report_' . date('Ymd_His') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }

    private function createSummarySheet($spreadsheet, $filters)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Summary');
        
        $summary = $this->getSummaryMetrics($filters);
        $statusDistribution = $this->getStatusDistribution($filters);
        $paymentAnalysis = $this->getPaymentAnalysis($filters);
        
        $row = 1;
        
        // Title
        $sheet->setCellValue('A' . $row, 'Purchase Order Ops Report - Summary');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16);
        $row += 2;
        
        // Date Range
        $sheet->setCellValue('A' . $row, 'Period:');
        $sheet->setCellValue('B' . $row, $filters['date_from'] . ' to ' . $filters['date_to']);
        $row += 2;
        
        // Summary Metrics
        $sheet->setCellValue('A' . $row, 'Summary Metrics');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Total PO');
        $sheet->setCellValue('B' . $row, $summary['total_po'] ?? 0);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Total Value');
        $sheet->setCellValue('B' . $row, number_format($summary['total_value'] ?? 0, 2));
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Approved PO');
        $sheet->setCellValue('B' . $row, $summary['approved_count'] ?? 0);
        $row++;
        
        // Calculate approved value
        $approvedValueQuery = DB::table('purchase_order_ops as po')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']])
            ->where('po.status', 'approved');
        
        if ($filters['supplier_id']) {
            $approvedValueQuery->where('po.supplier_id', $filters['supplier_id']);
        }
        
        if ($filters['search']) {
            $approvedValueQuery->leftJoin('suppliers as s_search', 'po.supplier_id', '=', 's_search.id')
                  ->leftJoin('purchase_requisitions as pr_search', 'po.source_id', '=', 'pr_search.id')
                  ->leftJoin('tbl_data_outlet as o_search', 'pr_search.outlet_id', '=', 'o_search.id_outlet')
                  ->where(function($q) use ($filters) {
                      $q->where('po.number', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('s_search.name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('pr_search.pr_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('o_search.nama_outlet', 'like', '%' . $filters['search'] . '%');
                  });
        }
        
        $approvedValueTotal = $approvedValueQuery->sum('po.grand_total') ?? 0;
        
        $sheet->setCellValue('A' . $row, 'Approved Value');
        $sheet->setCellValue('B' . $row, number_format($approvedValueTotal, 2));
        $row += 2;
        
        // Status Distribution
        $sheet->setCellValue('A' . $row, 'Status Distribution');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Status');
        $sheet->setCellValue('B' . $row, 'Count');
        $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $row++;
        
        foreach ($statusDistribution as $status) {
            $sheet->setCellValue('A' . $row, ucfirst($status['status']));
            $sheet->setCellValue('B' . $row, $status['count']);
            $row++;
        }
        
        $row += 2;
        
        // Payment Analysis
        $sheet->setCellValue('A' . $row, 'Payment Analysis');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Total Value (Approved PO)');
        $sheet->setCellValue('B' . $row, number_format($paymentAnalysis['total_value'], 2));
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Total Paid');
        $sheet->setCellValue('B' . $row, number_format($paymentAnalysis['total_paid'], 2));
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Total Pending');
        $sheet->setCellValue('B' . $row, number_format($paymentAnalysis['total_pending'], 2));
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Total Unpaid');
        $sheet->setCellValue('B' . $row, number_format($paymentAnalysis['total_unpaid'], 2));
        
        // Auto-size columns
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Apply header styles
        $this->applyHeaderStyle($sheet, 'A1:D1');
    }

    private function createSuppliersSheet($spreadsheet, $filters)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Top Suppliers');
        
        $suppliers = $this->getSupplierAnalysis($filters, 10000, '', 1); // Get all suppliers
        
        $row = 1;
        
        // Headers
        $headers = ['Supplier Name', 'PO Count', 'Total Value', 'Avg Value', 'Total Paid', 'Total Pending', 'Total Unpaid', 'Paid %', 'Pending %', 'Unpaid %'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $col++;
        }
        $this->applyHeaderStyle($sheet, 'A' . $row . ':J' . $row);
        $row++;
        
        // Data
        foreach ($suppliers as $supplier) {
            $totalValue = (float)$supplier['total_value'];
            $totalPaid = (float)$supplier['total_paid'];
            $totalPending = (float)$supplier['total_pending'];
            $totalUnpaid = (float)$supplier['total_unpaid'];
            
            $paidPercent = $totalValue > 0 ? ($totalPaid / $totalValue * 100) : 0;
            $pendingPercent = $totalValue > 0 ? ($totalPending / $totalValue * 100) : 0;
            $unpaidPercent = $totalValue > 0 ? ($totalUnpaid / $totalValue * 100) : 0;
            
            $sheet->setCellValue('A' . $row, $supplier['supplier_name']);
            $sheet->setCellValue('B' . $row, $supplier['po_count']);
            $sheet->setCellValue('C' . $row, number_format($totalValue, 2));
            $sheet->setCellValue('D' . $row, number_format($supplier['avg_value'], 2));
            $sheet->setCellValue('E' . $row, number_format($totalPaid, 2));
            $sheet->setCellValue('F' . $row, number_format($totalPending, 2));
            $sheet->setCellValue('G' . $row, number_format($totalUnpaid, 2));
            $sheet->setCellValue('H' . $row, number_format($paidPercent, 2) . '%');
            $sheet->setCellValue('I' . $row, number_format($pendingPercent, 2) . '%');
            $sheet->setCellValue('J' . $row, number_format($unpaidPercent, 2) . '%');
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function createItemAnalysisSheet($spreadsheet, $filters)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Item Analysis');
        
        // Get all item analysis data with full details including categories
        // Note: We need to get outlet from PR, so we join through PR items -> PR -> outlet
        $query = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
            ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
            ->leftJoin('users as creator', 'po.created_by', '=', 'creator.id')
            ->leftJoin('purchase_requisition_items as pri', function($join) {
                $join->where(function($q) {
                    $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                      ->orWhere(function($q2) {
                          $q2->where('poi.source_type', '=', 'purchase_requisition_ops')
                             ->whereColumn('poi.source_id', 'pri.id');
                      });
                });
            })
            ->leftJoin('purchase_requisition_categories as prc', 'pri.category_id', '=', 'prc.id')
            ->leftJoin('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
            // Join outlet from PR
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->whereBetween('po.date', [$filters['date_from'], $filters['date_to']]);

        if ($filters['status'] !== 'all') {
            $query->where('po.status', $filters['status']);
        }

        if ($filters['supplier_id']) {
            $query->where('po.supplier_id', $filters['supplier_id']);
        }

        $items = $query->select(
                'poi.item_name',
                'poi.unit',
                'poi.quantity',
                'poi.price',
                'poi.discount_amount',
                'poi.total',
                'po.number as po_number',
                'po.date as po_date',
                'po.status as po_status',
                's.name as supplier_name',
                'creator.nama_lengkap as creator_name',
                'pr.pr_number as pr_number',
                'o.nama_outlet as outlet_name',
                DB::raw('CONCAT(COALESCE(prc.division, ""), IF(prc.division IS NOT NULL AND prc.name IS NOT NULL, " - ", ""), COALESCE(prc.name, "")) as category_name')
            )
            ->orderBy('poi.item_name')
            ->orderBy('poi.unit')
            ->orderBy('po.date', 'desc')
            ->get();

        $row = 1;
        
        // Headers
        $headers = ['Item Name', 'Unit', 'Category', 'PO Number', 'PO Date', 'PO Status', 'Supplier', 'PR Number', 'Outlet', 'Creator', 'Quantity', 'Price', 'Discount', 'Total'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $col++;
        }
        $this->applyHeaderStyle($sheet, 'A' . $row . ':N' . $row);
        $row++;
        
        // Data - Group items by PR to batch fetch outlets if needed
        $prNumbers = [];
        foreach ($items as $item) {
            if ($item->pr_number && !$item->outlet_name) {
                $prNumbers[] = $item->pr_number;
            }
        }
        
        // Batch fetch outlets for PRs that don't have outlet from join
        $outletMap = [];
        if (!empty($prNumbers)) {
            $outlets = DB::table('purchase_requisitions as pr')
                ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
                ->whereIn('pr.pr_number', array_unique($prNumbers))
                ->select('pr.pr_number', 'o.nama_outlet')
                ->get()
                ->keyBy('pr_number');
            
            foreach ($outlets as $prNumber => $outlet) {
                $outletMap[$prNumber] = $outlet->nama_outlet;
            }
        }
        
        // Data
        foreach ($items as $item) {
            // Get outlet from PR detail - use from join first, then from batch map
            $outletName = $item->outlet_name;
            
            // If outlet_name is not available, try to get from batch map
            if (!$outletName && $item->pr_number && isset($outletMap[$item->pr_number])) {
                $outletName = $outletMap[$item->pr_number];
            }
            
            $sheet->setCellValue('A' . $row, $item->item_name);
            $sheet->setCellValue('B' . $row, $item->unit);
            $sheet->setCellValue('C' . $row, $item->category_name ?: '-');
            $sheet->setCellValue('D' . $row, $item->po_number);
            $sheet->setCellValue('E' . $row, $item->po_date);
            $sheet->setCellValue('F' . $row, ucfirst($item->po_status));
            $sheet->setCellValue('G' . $row, $item->supplier_name ?: '-');
            $sheet->setCellValue('H' . $row, $item->pr_number ?: '-');
            $sheet->setCellValue('I' . $row, $outletName ?: '-');
            $sheet->setCellValue('J' . $row, $item->creator_name ?: '-');
            $sheet->setCellValue('K' . $row, number_format((float)$item->quantity, 2));
            $sheet->setCellValue('L' . $row, number_format((float)$item->price, 2));
            $sheet->setCellValue('M' . $row, number_format((float)$item->discount_amount, 2));
            $sheet->setCellValue('N' . $row, number_format((float)$item->total, 2));
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function createPurchaseOrdersSheet($spreadsheet, $filters)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Purchase Orders');
        
        $purchaseOrders = $this->getPurchaseOrders($filters, 10000); // Get all POs
        
        $row = 1;
        
        // Headers
        $headers = ['PO Number', 'Date', 'Supplier', 'Status', 'Subtotal', 'Discount', 'PPN', 'Grand Total', 'Creator', 'PM Approved', 'GM Finance Approved', 'PR Number', 'Outlet', 'Item Count', 'Payment Count', 'Total Paid', 'Total Pending', 'Remaining'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $col++;
        }
        $this->applyHeaderStyle($sheet, 'A' . $row . ':R' . $row);
        $row++;
        
        // Data
        foreach ($purchaseOrders as $po) {
            $sheet->setCellValue('A' . $row, $po->number);
            $sheet->setCellValue('B' . $row, $po->date);
            $sheet->setCellValue('C' . $row, $po->supplier_name ?: '-');
            $sheet->setCellValue('D' . $row, ucfirst($po->status));
            $sheet->setCellValue('E' . $row, number_format((float)$po->subtotal, 2));
            $sheet->setCellValue('F' . $row, number_format((float)$po->discount_total_amount, 2));
            $sheet->setCellValue('G' . $row, number_format((float)$po->ppn_amount, 2));
            $sheet->setCellValue('H' . $row, number_format((float)$po->grand_total, 2));
            $sheet->setCellValue('I' . $row, $po->creator_name ?: '-');
            $sheet->setCellValue('J' . $row, $po->purchasing_manager_name ?: '-');
            $sheet->setCellValue('K' . $row, $po->gm_finance_name ?: '-');
            $sheet->setCellValue('L' . $row, $po->source_pr_number ?: '-');
            $sheet->setCellValue('M' . $row, $po->outlet_name ?: '-');
            $sheet->setCellValue('N' . $row, $po->item_count ?: 0);
            $sheet->setCellValue('O' . $row, $po->payment_count ?: 0);
            $sheet->setCellValue('P' . $row, number_format((float)($po->total_paid ?? 0), 2));
            $sheet->setCellValue('Q' . $row, number_format((float)($po->total_pending ?? 0), 2));
            $sheet->setCellValue('R' . $row, number_format((float)($po->remaining_amount ?? 0), 2));
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'R') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function createPaymentAnalysisSheet($spreadsheet, $filters)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Payment Analysis');
        
        $paymentAnalysis = $this->getPaymentAnalysis($filters);
        
        $row = 1;
        
        // Title
        $sheet->setCellValue('A' . $row, 'Payment Analysis');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $row += 2;
        
        // Summary
        $sheet->setCellValue('A' . $row, 'Total Value (Approved PO)');
        $sheet->setCellValue('B' . $row, number_format($paymentAnalysis['total_value'], 2));
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Total Paid');
        $sheet->setCellValue('B' . $row, number_format($paymentAnalysis['total_paid'], 2));
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Total Pending');
        $sheet->setCellValue('B' . $row, number_format($paymentAnalysis['total_pending'], 2));
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Total Unpaid');
        $sheet->setCellValue('B' . $row, number_format($paymentAnalysis['total_unpaid'], 2));
        $row += 2;
        
        // Payment Status Count
        $sheet->setCellValue('A' . $row, 'Payment Status');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Status');
        $sheet->setCellValue('B' . $row, 'Count');
        $this->applyHeaderStyle($sheet, 'A' . $row . ':B' . $row);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Fully Paid');
        $sheet->setCellValue('B' . $row, $paymentAnalysis['fully_paid_count'] ?? 0);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Partially Paid');
        $sheet->setCellValue('B' . $row, $paymentAnalysis['partially_paid_count'] ?? 0);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Unpaid');
        $sheet->setCellValue('B' . $row, $paymentAnalysis['unpaid_count'] ?? 0);
        
        // Auto-size columns
        foreach (range('A', 'B') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function createPOPerOutletSheet($spreadsheet, $filters)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('PO per Outlet');
        
        $poPerOutlet = $this->getPOPerOutlet($filters);
        
        $row = 1;
        
        // Headers
        $headers = ['Outlet Name', 'PO Count', 'Total Value', 'Total Paid', 'Total Pending', 'Total Unpaid'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $col++;
        }
        $this->applyHeaderStyle($sheet, 'A' . $row . ':F' . $row);
        $row++;
        
        // Data
        foreach ($poPerOutlet as $outlet) {
            $sheet->setCellValue('A' . $row, $outlet['outlet_name']);
            $sheet->setCellValue('B' . $row, $outlet['po_count']);
            $sheet->setCellValue('C' . $row, number_format($outlet['total_value'], 2));
            $sheet->setCellValue('D' . $row, number_format($outlet['total_paid'], 2));
            $sheet->setCellValue('E' . $row, number_format($outlet['total_pending'], 2));
            $sheet->setCellValue('F' . $row, number_format($outlet['total_unpaid'], 2));
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function createPOPerCategorySheet($spreadsheet, $filters)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('PO per Category');
        
        $poPerCategory = $this->getPOPerCategory($filters);
        
        $row = 1;
        
        // Headers
        $headers = ['Category', 'PO Count', 'Total Value', 'Total Paid', 'Total Pending', 'Total Unpaid'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $col++;
        }
        $this->applyHeaderStyle($sheet, 'A' . $row . ':F' . $row);
        $row++;
        
        // Data
        foreach ($poPerCategory as $category) {
            // Use category_name (which already contains the display format from getPOPerCategory)
            $categoryName = $category['category_name'] ?? 'Unknown';
            $sheet->setCellValue('A' . $row, $categoryName);
            $sheet->setCellValue('B' . $row, $category['po_count'] ?? 0);
            $sheet->setCellValue('C' . $row, number_format($category['total_value'] ?? 0, 2));
            $sheet->setCellValue('D' . $row, number_format($category['total_paid'] ?? 0, 2));
            $sheet->setCellValue('E' . $row, number_format($category['total_pending'] ?? 0, 2));
            $sheet->setCellValue('F' . $row, number_format($category['total_unpaid'] ?? 0, 2));
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function applyHeaderStyle($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);
    }
}

