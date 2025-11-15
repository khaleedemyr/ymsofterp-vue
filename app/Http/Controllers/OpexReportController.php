<?php

namespace App\Http\Controllers;

use App\Models\RetailNonFood;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OpexReportController extends Controller
{
    public function index(Request $request)
    {
        // Get filter parameters
        $dateFrom = $request->input('date_from', date('Y-m-01')); // Default to first day of current month
        $dateTo = $request->input('date_to', date('Y-m-t')); // Default to last day of current month
        $outletId = $request->input('outlet_id');
        $categoryId = $request->input('category_id');
        $status = $request->input('status'); // all, paid, unpaid

        // Build the hierarchical query
        $query = DB::table('purchase_order_ops as poo')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->leftJoin('suppliers as s', 'poo.supplier_id', '=', 's.id')
            ->leftJoin('non_food_payments as nfp', function($join) {
                $join->on('poo.id', '=', 'nfp.purchase_order_ops_id')
                     ->where('nfp.status', '!=', 'cancelled');
            })
            ->where('poo.status', 'approved') // Only approved POs
            ->whereBetween('poo.date', [$dateFrom, $dateTo])
            ->select(
                'o.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
                'prc.id as category_id',
                'prc.name as category_name',
                'prc.division as category_division',
                'prc.subcategory as category_subcategory',
                'prc.budget_type as category_budget_type',
                'poo.id as po_id',
                'poo.number as po_number',
                'poo.date as po_date',
                's.name as supplier_name',
                'poi.id as po_item_id',
                'poi.item_name',
                'poi.quantity',
                'poi.unit',
                'poi.price',
                'poi.total as po_item_total',
                'nfp.id as payment_id',
                'nfp.payment_number',
                'nfp.status as payment_status',
                'nfp.payment_date',
                'nfp.amount as payment_amount'
            );

        // Apply filters
        if ($outletId) {
            $query->where('o.id_outlet', $outletId);
        }

        if ($categoryId) {
            $query->where('prc.id', $categoryId);
        }

        if ($status === 'paid') {
            $query->whereNotNull('nfp.id');
        } elseif ($status === 'unpaid') {
            $query->whereNull('nfp.id');
        }

        $results = $query->get();

        // Get Retail Non Food data with items
        $retailNonFoodQuery = DB::table('retail_non_food as rnf')
            ->leftJoin('retail_non_food_items as rnfi', 'rnf.id', '=', 'rnfi.retail_non_food_id')
            ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('purchase_requisition_categories as prc', 'rnf.category_budget_id', '=', 'prc.id')
            ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
            ->whereIn('rnf.status', ['approved', 'pending'])
            ->select(
                'o.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
                'prc.id as category_id',
                'prc.name as category_name',
                'prc.division as category_division',
                'prc.subcategory as category_subcategory',
                'prc.budget_type as category_budget_type',
                DB::raw('NULL as po_id'),
                DB::raw('rnf.retail_number as po_number'),
                'rnf.transaction_date as po_date',
                DB::raw('NULL as supplier_name'),
                DB::raw('rnfi.id as po_item_id'), // Use item ID
                'rnfi.item_name', // Use actual item name from retail_non_food_items
                'rnfi.qty as quantity', // Use actual quantity
                'rnfi.unit', // Use actual unit
                'rnfi.price', // Use actual price
                'rnfi.subtotal as po_item_total', // Use subtotal from items
                DB::raw('rnf.id as retail_non_food_id'), // Store retail_non_food_id for grouping
                // For Retail Non Food: approved = paid (has payment_id), pending = unpaid (no payment_id)
                // Payment info should be the same for all items in the same transaction
                DB::raw('CASE WHEN rnf.status = "approved" THEN rnf.id ELSE NULL END as payment_id'),
                DB::raw('CASE WHEN rnf.status = "approved" THEN rnf.retail_number ELSE NULL END as payment_number'),
                DB::raw('CASE WHEN rnf.status = "approved" THEN "paid" ELSE NULL END as payment_status'),
                DB::raw('CASE WHEN rnf.status = "approved" THEN rnf.transaction_date ELSE NULL END as payment_date'),
                DB::raw('CASE WHEN rnf.status = "approved" THEN rnf.total_amount ELSE NULL END as payment_amount')
            );

        // Apply filters for Retail Non Food
        if ($outletId) {
            $retailNonFoodQuery->where('o.id_outlet', $outletId);
        }

        if ($categoryId) {
            $retailNonFoodQuery->where('prc.id', $categoryId);
        }

        // Apply status filter for Retail Non Food
        if ($status === 'paid') {
            $retailNonFoodQuery->where('rnf.status', 'approved');
        } elseif ($status === 'unpaid') {
            $retailNonFoodQuery->where('rnf.status', 'pending');
        }

        $retailNonFoodResults = $retailNonFoodQuery->get();

        // Get paid payments directly from non_food_payments (to avoid double counting)
        // Group by payment ID to get unique payments
        $paidPayments = DB::table('non_food_payments as nfp')
            ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
            ->whereIn('nfp.status', ['paid', 'approved'])
            ->where('nfp.status', '!=', 'cancelled')
            ->groupBy('nfp.id', 'nfp.purchase_order_ops_id', 'nfp.amount', 'nfp.payment_date', 'nfp.payment_number', 'nfp.status')
            ->select(
                'nfp.id as payment_id',
                'nfp.purchase_order_ops_id as po_id',
                'nfp.amount as payment_amount',
                'nfp.payment_date',
                'nfp.payment_number',
                'nfp.status as payment_status'
            )
            ->get()
            ->keyBy('po_id'); // Key by PO ID for quick lookup

        // Merge PO results with Retail Non Food results
        $allResults = $results->merge($retailNonFoodResults);

        // Build hierarchical structure with paid payments data
        $outletData = $this->buildHierarchicalStructure($allResults, $paidPayments);

        // Get filter options
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        $categories = DB::table('purchase_requisition_categories')
            ->select('id', 'name', 'division', 'subcategory')
            ->orderBy('name')
            ->get();

        // Get all categories with budget information (with date filter)
        $allCategories = $this->getAllCategoriesWithBudget($dateFrom, $dateTo);

        return Inertia::render('OpexReport/Index', [
            'outletData' => $outletData,
            'outlets' => $outlets,
            'categories' => $categories,
            'allCategories' => $allCategories,
            'filters' => $request->only(['date_from', 'date_to', 'outlet_id', 'category_id', 'status']),
            'summary' => $this->getSummaryData($outletData)
        ]);
    }

    private function buildHierarchicalStructure($results, $paidPayments = null)
    {
        $outlets = [];
        $paidPaymentsMap = $paidPayments ? $paidPayments->keyBy('po_id')->toArray() : [];

        foreach ($results as $row) {
            $outletId = $row->outlet_id;
            $categoryId = $row->category_id;
            $poId = $row->po_id;
            
            // For Retail Non Food, use retail_non_food_id as unique identifier (since po_id is NULL)
            // All items from the same Retail Non Food transaction should be grouped together
            $poKey = $poId ? $poId : (isset($row->retail_non_food_id) ? 'rnf_' . $row->retail_non_food_id : null);

            // Initialize outlet if not exists
            if (!isset($outlets[$outletId])) {
                $outlets[$outletId] = [
                    'outlet_id' => $outletId,
                    'outlet_name' => $row->outlet_name,
                    'categories' => [],
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'unpaid_amount' => 0
                ];
            }

            // Initialize category if not exists
            if ($categoryId && !isset($outlets[$outletId]['categories'][$categoryId])) {
                $outlets[$outletId]['categories'][$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => $row->category_name,
                    'category_division' => $row->category_division,
                    'category_subcategory' => $row->category_subcategory,
                    'category_budget_type' => $row->category_budget_type,
                    'budget_limit' => 0, // Will be populated later
                    'purchase_orders' => [],
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'unpaid_amount' => 0,
                    'remaining_budget' => 0 // Will be calculated later
                ];
            }

            // Check if PO has paid payment (from non_food_payments, not from join)
            $paymentInfo = null;
            if ($poId && isset($paidPaymentsMap[$poId])) {
                $paymentInfo = $paidPaymentsMap[$poId];
            } elseif ($row->payment_id && !$poId) {
                // For Retail Non Food (no PO ID), use payment info from row
                $paymentInfo = (object)[
                    'payment_id' => $row->payment_id,
                    'payment_amount' => $row->payment_amount,
                    'payment_date' => $row->payment_date,
                    'payment_number' => $row->payment_number,
                    'payment_status' => $row->payment_status
                ];
            }

            // Initialize PO if not exists (only if category exists and poKey is valid)
            if ($poKey && $categoryId && isset($outlets[$outletId]['categories'][$categoryId]) && !isset($outlets[$outletId]['categories'][$categoryId]['purchase_orders'][$poKey])) {
                $outlets[$outletId]['categories'][$categoryId]['purchase_orders'][$poKey] = [
                    'po_id' => $poId,
                    'po_number' => $row->po_number,
                    'po_date' => $row->po_date,
                    'supplier_name' => $row->supplier_name,
                    'payment_id' => $paymentInfo ? $paymentInfo->payment_id : null,
                    'payment_number' => $paymentInfo ? $paymentInfo->payment_number : null,
                    'payment_status' => $paymentInfo ? $paymentInfo->payment_status : null,
                    'payment_date' => $paymentInfo ? $paymentInfo->payment_date : null,
                    'payment_amount' => $paymentInfo ? $paymentInfo->payment_amount : null,
                    'items' => [],
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'unpaid_amount' => 0,
                    'is_paid' => !is_null($paymentInfo)
                ];
            }

            // Add item to PO (only if category exists and poKey is valid)
            if ($poKey && $categoryId && isset($outlets[$outletId]['categories'][$categoryId]) && $row->po_item_id) {
                $outlets[$outletId]['categories'][$categoryId]['purchase_orders'][$poKey]['items'][] = [
                    'po_item_id' => $row->po_item_id,
                    'item_name' => $row->item_name,
                    'quantity' => $row->quantity,
                    'unit' => $row->unit,
                    'price' => $row->price,
                    'total' => $row->po_item_total
                ];

                // Update totals
                $itemTotal = $row->po_item_total;
                $outlets[$outletId]['categories'][$categoryId]['purchase_orders'][$poKey]['total_amount'] += $itemTotal;
                // Ensure category total_amount is initialized
                if (!isset($outlets[$outletId]['categories'][$categoryId]['total_amount'])) {
                    $outlets[$outletId]['categories'][$categoryId]['total_amount'] = 0;
                }
                $outlets[$outletId]['categories'][$categoryId]['total_amount'] += $itemTotal;
                // Ensure outlet total_amount is initialized
                if (!isset($outlets[$outletId]['total_amount'])) {
                    $outlets[$outletId]['total_amount'] = 0;
                }
                $outlets[$outletId]['total_amount'] += $itemTotal;
            }
        }

        // After processing all items, calculate paid/unpaid amounts per PO
        foreach ($outlets as &$outlet) {
            foreach ($outlet['categories'] as &$category) {
                foreach ($category['purchase_orders'] as &$po) {
                    $poTotal = $po['total_amount'];
                    $hasPayment = !is_null($po['payment_id']);
                    
                    if ($po['po_id']) {
                        // For Purchase Orders: use payment from non_food_payments
                        if ($hasPayment && isset($paidPaymentsMap[$po['po_id']])) {
                            // Use actual payment amount from non_food_payments
                            $paymentAmount = $paidPaymentsMap[$po['po_id']]->payment_amount;
                            $po['paid_amount'] = $paymentAmount;
                            $po['unpaid_amount'] = max(0, $poTotal - $paymentAmount);
                        } else {
                            // No payment, all is unpaid
                            $po['paid_amount'] = 0;
                            $po['unpaid_amount'] = $poTotal;
                        }
                    } else {
                        // For Retail Non Food: approved = paid (total_amount), pending = unpaid
                        if ($hasPayment) {
                            // Retail Non Food approved = paid
                            $po['paid_amount'] = $poTotal;
                            $po['unpaid_amount'] = 0;
                        } else {
                            // Retail Non Food pending = unpaid
                            $po['paid_amount'] = 0;
                            $po['unpaid_amount'] = $poTotal;
                        }
                    }
                    
                    // Update category totals
                    $category['paid_amount'] += $po['paid_amount'];
                    $category['unpaid_amount'] += $po['unpaid_amount'];
                    
                    // Update outlet totals
                    $outlet['paid_amount'] += $po['paid_amount'];
                    $outlet['unpaid_amount'] += $po['unpaid_amount'];
                }
            }
        }

        // Convert to array and sort
        $outletArray = array_values($outlets);
        foreach ($outletArray as &$outlet) {
            $outlet['categories'] = array_values($outlet['categories']);
            foreach ($outlet['categories'] as &$category) {
                $category['purchase_orders'] = array_values($category['purchase_orders']);
            }
        }

        // Populate budget information
        $this->populateBudgetInformation($outletArray);

        return $outletArray;
    }

    private function getSummaryData($outletData)
    {
        $summary = [
            'total_outlets' => count($outletData),
            'total_categories' => 0,
            'total_purchase_orders' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'unpaid_amount' => 0
        ];

        foreach ($outletData as $outlet) {
            $summary['total_categories'] += count($outlet['categories']);
            $summary['total_amount'] += $outlet['total_amount'];
            $summary['paid_amount'] += $outlet['paid_amount'];
            $summary['unpaid_amount'] += $outlet['unpaid_amount'];

            foreach ($outlet['categories'] as $category) {
                $summary['total_purchase_orders'] += count($category['purchase_orders']);
            }
        }

        return $summary;
    }

    private function getAllCategoriesWithBudget($dateFrom = null, $dateTo = null)
    {
        // Default to current month if not provided
        if (!$dateFrom) {
            $dateFrom = date('Y-m-01');
        }
        if (!$dateTo) {
            $dateTo = date('Y-m-t');
        }
        
        // Get all categories from database
        $categories = DB::table('purchase_requisition_categories')
            ->select('id', 'name', 'division', 'subcategory', 'budget_limit', 'budget_type')
            ->orderBy('name')
            ->get();

        $categoryGroups = [];

        foreach ($categories as $category) {
            $categoryGroup = [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'category_division' => $category->division,
                'category_subcategory' => $category->subcategory,
                'budget_type' => $category->budget_type,
                'budget_limit' => $category->budget_limit ?? 0,
                'total_budget_limit' => $category->budget_limit ?? 0, // Use actual budget limit from category
                'total_paid_amount' => 0,
                'total_unpaid_amount' => 0,
                'total_remaining_budget' => 0,
                'outlets' => [],
                'budget_breakdown' => [
                    'nfp_amount' => 0,
                    'pr_unpaid_amount' => 0,
                    'rnf_amount' => 0
                ]
            ];

            // Get per outlet budget data if budget_type is PER_OUTLET
            if ($category->budget_type === 'PER_OUTLET') {
                $outletBudgets = DB::table('purchase_requisition_outlet_budgets as prob')
                    ->leftJoin('tbl_data_outlet as o', 'prob.outlet_id', '=', 'o.id_outlet')
                    ->where('prob.category_id', $category->id)
                    ->select(
                        'prob.outlet_id',
                        'o.nama_outlet as outlet_name',
                        'prob.allocated_budget',
                        'prob.used_budget'
                    )
                    ->get();

                // Get Retail Non Food data for this category (PER_OUTLET) with date filter
                $retailNonFoodData = DB::table('retail_non_food as rnf')
                    ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
                    ->where('rnf.category_budget_id', $category->id)
                    ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
                    ->where('rnf.status', 'approved') // Only approved, matching Retail Non Food form
                    ->select(
                        'o.id_outlet as outlet_id',
                        'o.nama_outlet as outlet_name',
                        DB::raw('SUM(rnf.total_amount) as retail_non_food_total')
                    )
                    ->groupBy('o.id_outlet', 'o.nama_outlet')
                    ->get();

                // Create a map for Retail Non Food totals by outlet
                $retailNonFoodMap = [];
                foreach ($retailNonFoodData as $row) {
                    $retailNonFoodMap[$row->outlet_id] = $row->retail_non_food_total ?? 0;
                }

                $outletMap = [];
                foreach ($outletBudgets as $row) {
                    $retailNonFoodAmount = $retailNonFoodMap[$row->outlet_id] ?? 0;
                    $totalPaidAmount = $row->used_budget + $retailNonFoodAmount;
                    
                    $outletMap[] = [
                        'outlet_id' => $row->outlet_id,
                        'outlet_name' => $row->outlet_name,
                        'budget_limit' => $row->allocated_budget,
                        'paid_amount' => $totalPaidAmount,
                        'unpaid_amount' => 0, // Will be calculated from transactions
                        'remaining_budget' => $row->allocated_budget - $totalPaidAmount
                    ];
                    
                    // Add to totals
                    $categoryGroup['total_paid_amount'] += $totalPaidAmount;
                }

                $categoryGroup['outlets'] = $outletMap;
                $categoryGroup['total_remaining_budget'] = $category->budget_limit - $categoryGroup['total_paid_amount'];
                
                // Calculate breakdown for PER_OUTLET budget type
                $nfpAmount = 0;
                $prUnpaidAmount = 0;
                $rnfAmount = 0;
                
                // Get NFP amount for this category
                $prIdsInCategory = DB::table('purchase_requisitions')
                    ->where('category_id', $category->id)
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                    ->pluck('id')
                    ->toArray();
                
                $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->whereIn('poi.source_id', $prIdsInCategory)
                    ->distinct()
                    ->pluck('poi.purchase_order_ops_id')
                    ->toArray();
                
                $nfpAmount = DB::table('non_food_payments')
                    ->whereIn('purchase_order_ops_id', $poIdsInCategory)
                    ->whereBetween('payment_date', [$dateFrom, $dateTo])
                    ->whereIn('status', ['paid', 'approved'])
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
                
                // Get PR unpaid amount
                $allPrs = DB::table('purchase_requisitions as pr')
                    ->where('pr.category_id', $category->id)
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                    ->pluck('pr.id')
                    ->toArray();
                
                $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->where('pr.category_id', $category->id)
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->where('poo.status', 'approved')
                    ->whereBetween('poo.date', [$dateFrom, $dateTo])
                    ->groupBy('pr.id')
                    ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                    ->pluck('po_total', 'pr_id')
                    ->toArray();
                
                $paidTotalsByPr = DB::table('non_food_payments as nfp')
                    ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->where('pr.category_id', $category->id)
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->groupBy('pr.id')
                    ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                    ->pluck('total_paid', 'pr_id')
                    ->toArray();
                
                $prUnpaidAmount = 0;
                foreach ($allPrs as $prId) {
                    $poTotal = $poTotalsByPr[$prId] ?? 0;
                    $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                    $prAmount = DB::table('purchase_requisitions')->where('id', $prId)->value('amount') ?? 0;
                    $totalAmount = $poTotal > 0 ? $poTotal : $prAmount;
                    $unpaidAmount = $totalAmount - $totalPaid;
                    if ($unpaidAmount > 0) {
                        $prUnpaidAmount += $unpaidAmount;
                    }
                }
                
                // Get RNF amount
                $rnfAmount = DB::table('retail_non_food')
                    ->where('category_budget_id', $category->id)
                    ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                    ->where('status', 'approved')
                    ->sum('total_amount');
                
                $categoryGroup['budget_breakdown'] = [
                    'nfp_amount' => $nfpAmount,
                    'pr_unpaid_amount' => $prUnpaidAmount,
                    'rnf_amount' => $rnfAmount
                ];
            } else {
                // For GLOBAL budget type, get paid amount from non_food_payments (actual payment amount)
                // Ambil langsung dari non_food_payments tanpa join ke PO items untuk menghindari double counting
                // Filter category melalui relasi PO -> PO Items -> PR
                // 1 payment = 1 transaksi pembayaran yang unik, tidak boleh dihitung berkali-kali
                
                // Get PR IDs in this category for the selected date range (follow date filter from above)
                // Use whereBetween to follow the date filter selected by user
                $prIdsInCategory = DB::table('purchase_requisitions')
                    ->where('category_id', $category->id)
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                    ->pluck('id')
                    ->toArray();
                
                // Get PO IDs that are linked to PRs in this category (from selected date range)
                $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->whereIn('poi.source_id', $prIdsInCategory)
                    ->distinct()
                    ->pluck('poi.purchase_order_ops_id')
                    ->toArray();
                
                // Get paid payments directly from non_food_payments (grouped by payment ID to avoid duplicates)
                // 1 payment = 1 transaksi pembayaran unik, tidak dihitung berkali-kali
                // Ambil outlet dari PR pertama yang terkait dengan payment ini
                $paidPaymentsData = DB::table('non_food_payments as nfp')
                    ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_order_ops_items as poi', function($join) {
                        $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                             ->where('poi.source_type', '=', 'purchase_requisition_ops');
                    })
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
                    ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                    ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->groupBy('nfp.id') // Group by payment ID saja untuk menghindari duplikasi
                    ->select(
                        DB::raw('MIN(o.id_outlet) as outlet_id'), // Ambil outlet pertama
                        DB::raw('MIN(o.nama_outlet) as outlet_name'),
                        'nfp.amount as total_paid_amount', // Payment amount (unique per payment ID)
                        DB::raw('GROUP_CONCAT(DISTINCT pr.pr_number ORDER BY pr.pr_number SEPARATOR ", ") as pr_numbers'),
                        'nfp.payment_date as transaction_date',
                        'nfp.payment_number',
                        'nfp.status',
                        DB::raw('nfp.id as payment_id'),
                        DB::raw('"purchase_requisition" as source_type')
                    )
                    ->get();

                // Get unpaid PR data
                // Logika: Unpaid = PO Total per PR - Total Paid per PR
                // Jika PR belum jadi PO, Unpaid = PR Amount
                
                // Get all PRs in this category for the selected date range (follow date filter from above)
                $allPrs = DB::table('purchase_requisitions as pr')
                    ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
                    ->where('pr.category_id', $category->id)
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                    ->select(
                        'pr.id as pr_id',
                        'o.id_outlet as outlet_id',
                        'o.nama_outlet as outlet_name',
                        'pr.amount as pr_amount',
                        'pr.pr_number',
                        'pr.created_at as transaction_date',
                        'pr.status'
                    )
                    ->get();

                // Get PO totals per PR (sum of all PO items for each PR)
                // Follow date filter from above (use whereBetween for PR created_at)
                $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->where('pr.category_id', $category->id)
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->where('poo.status', 'approved')
                    ->whereBetween('poo.date', [$dateFrom, $dateTo])
                    ->groupBy('pr.id')
                    ->select(
                        'pr.id as pr_id',
                        DB::raw('SUM(poi.total) as po_total')
                    )
                    ->pluck('po_total', 'pr_id')
                    ->toArray();

                // Get total paid per PR (sum of all payments for each PR)
                // Follow date filter from above (use whereBetween for PR created_at)
                $paidTotalsByPr = DB::table('non_food_payments as nfp')
                    ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->where('pr.category_id', $category->id)
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                    ->whereIn('nfp.status', ['paid', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->groupBy('pr.id')
                    ->select(
                        'pr.id as pr_id',
                        DB::raw('SUM(nfp.amount) as total_paid')
                    )
                    ->pluck('total_paid', 'pr_id')
                    ->toArray();

                // Calculate unpaid for each PR
                $prData = collect();
                foreach ($allPrs as $pr) {
                    $prId = $pr->pr_id;
                    $poTotal = $poTotalsByPr[$prId] ?? 0;
                    $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                    
                    // If PR hasn't been converted to PO, use PR amount
                    // If PR has been converted to PO, use PO total
                    $totalAmount = $poTotal > 0 ? $poTotal : $pr->pr_amount;
                    $unpaidAmount = $totalAmount - $totalPaid;
                    
                    // Only include if there's unpaid amount
                    if ($unpaidAmount > 0) {
                        $prData->push((object)[
                            'outlet_id' => $pr->outlet_id,
                            'outlet_name' => $pr->outlet_name,
                            'po_item_total' => $unpaidAmount,
                            'pr_number' => $pr->pr_number,
                            'transaction_date' => $pr->transaction_date,
                            'status' => $pr->status,
                            'payment_id' => null,
                            'payment_amount' => null,
                            'source_type' => 'purchase_requisition'
                        ]);
                    }
                }

                // Get Retail Non Food data for this category (with date filter, matching Retail Non Food form)
                // Retail Non Food is directly paid, so use total_amount
                $retailNonFoodData = DB::table('retail_non_food as rnf')
                    ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
                    ->where('rnf.category_budget_id', $category->id)
                    ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
                    ->where('rnf.status', 'approved') // Only approved, matching Retail Non Food form
                    ->select(
                        'o.id_outlet as outlet_id',
                        'o.nama_outlet as outlet_name',
                        'rnf.total_amount as po_item_total',
                        'rnf.retail_number',
                        'rnf.transaction_date',
                        'rnf.status',
                        DB::raw('rnf.id as payment_id'), // Retail Non Food approved is considered paid
                        DB::raw('rnf.total_amount as payment_amount'),
                        DB::raw('"retail_non_food" as source_type')
                    )
                    ->get();

                $outletMap = [];
                
                // Process paid payments data (from non_food_payments - actual payment amount)
                // 1 payment = 1 transaksi, tidak dihitung berkali-kali meskipun PO punya beberapa items
                foreach ($paidPaymentsData as $row) {
                    $outletId = $row->outlet_id;
                    
                    if (!isset($outletMap[$outletId])) {
                        $outletMap[$outletId] = [
                            'outlet_id' => $outletId,
                            'outlet_name' => $row->outlet_name,
                            'budget_limit' => $category->budget_limit ?? 0,
                            'paid_amount' => 0,
                            'unpaid_amount' => 0,
                            'remaining_budget' => 0
                        ];
                    }

                    $itemTotal = $row->total_paid_amount ?? 0; // Payment amount (unique per payment ID)
                    $outletMap[$outletId]['paid_amount'] += $itemTotal;
                    $categoryGroup['total_paid_amount'] += $itemTotal;
                }

                // Process unpaid PR data (PR that don't have paid payments)
                foreach ($prData as $row) {
                    $outletId = $row->outlet_id;
                    
                    if (!isset($outletMap[$outletId])) {
                        $outletMap[$outletId] = [
                            'outlet_id' => $outletId,
                            'outlet_name' => $row->outlet_name,
                            'budget_limit' => $category->budget_limit ?? 0,
                            'paid_amount' => 0,
                            'unpaid_amount' => 0,
                            'remaining_budget' => 0
                        ];
                    }

                    $itemTotal = $row->po_item_total ?? 0;
                    $outletMap[$outletId]['unpaid_amount'] += $itemTotal;
                    $categoryGroup['total_unpaid_amount'] += $itemTotal;
                }

                // Process Retail Non Food data (always considered as paid - direct payment)
                foreach ($retailNonFoodData as $row) {
                    $outletId = $row->outlet_id;
                    
                    if (!isset($outletMap[$outletId])) {
                        $outletMap[$outletId] = [
                            'outlet_id' => $outletId,
                            'outlet_name' => $row->outlet_name,
                            'budget_limit' => $category->budget_limit ?? 0,
                            'paid_amount' => 0,
                            'unpaid_amount' => 0,
                            'remaining_budget' => 0
                        ];
                    }

                    $itemTotal = $row->po_item_total ?? 0;
                    $outletMap[$outletId]['paid_amount'] += $itemTotal;
                    $categoryGroup['total_paid_amount'] += $itemTotal;
                }

                // Calculate remaining budget for each outlet
                foreach ($outletMap as &$outlet) {
                    $outlet['remaining_budget'] = $outlet['budget_limit'] - $outlet['paid_amount'] - $outlet['unpaid_amount'];
                }

                $categoryGroup['outlets'] = array_values($outletMap);
                $categoryGroup['total_remaining_budget'] = $category->budget_limit - $categoryGroup['total_paid_amount'] - $categoryGroup['total_unpaid_amount'];
                
                // Add transaction details for expandable view
                $paidTransactions = [];
                $unpaidTransactions = [];
                
                // Add paid payments from non_food_payments (1 payment = 1 entry)
                foreach ($paidPaymentsData as $row) {
                    $paidTransactions[] = [
                        'type' => 'purchase_requisition',
                        'number' => $row->pr_numbers ?? '-', // PR numbers terkait dengan payment ini
                        'payment_number' => $row->payment_number ?? '-',
                        'date' => $row->transaction_date,
                        'outlet' => $row->outlet_name,
                        'amount' => $row->total_paid_amount, // Payment amount (unique per payment)
                        'status' => $row->status ?? 'paid'
                    ];
                }
                
                // Add unpaid PR
                foreach ($prData as $row) {
                    $unpaidTransactions[] = [
                        'type' => 'purchase_requisition',
                        'number' => $row->pr_number ?? '-',
                        'date' => $row->transaction_date,
                        'outlet' => $row->outlet_name,
                        'amount' => $row->po_item_total,
                        'status' => $row->status
                    ];
                }
                
                // Add Retail Non Food (direct payment)
                foreach ($retailNonFoodData as $row) {
                    $paidTransactions[] = [
                        'type' => 'retail_non_food',
                        'number' => $row->retail_number ?? '-',
                        'date' => $row->transaction_date,
                        'outlet' => $row->outlet_name,
                        'amount' => $row->po_item_total,
                        'status' => $row->status
                    ];
                }
                
                $categoryGroup['transactions'] = [
                    'paid' => $paidTransactions,
                    'unpaid' => $unpaidTransactions
                ];
                
                // Calculate breakdown by source type
                $categoryGroup['budget_breakdown'] = [
                    'nfp_amount' => $paidPaymentsData->sum('total_paid_amount'), // NFP (Non-Food Payment)
                    'pr_unpaid_amount' => $prData->sum('po_item_total'), // PR Unpaid
                    'rnf_amount' => $retailNonFoodData->sum('po_item_total') // RNF (Retail Non-Food)
                ];
            }

            $categoryGroups[] = $categoryGroup;
        }

        return $categoryGroups;
    }

    private function populateBudgetInformation(&$outletArray)
    {
        foreach ($outletArray as &$outlet) {
            foreach ($outlet['categories'] as &$category) {
                // Skip if category_id is not set
                if (!isset($category['category_id']) || empty($category['category_id'])) {
                    continue;
                }
                
                // Get budget limit for this category
                $budgetData = DB::table('purchase_requisition_categories')
                    ->where('id', $category['category_id'])
                    ->select('budget_limit', 'budget_type')
                    ->first();

                if ($budgetData) {
                    $category['budget_limit'] = $budgetData->budget_limit ?? 0;
                    $category['budget_type'] = $budgetData->budget_type;
                    
                    // Calculate remaining budget (considering both paid and unpaid amounts, including Retail Non Food)
                    $category['remaining_budget'] = $category['budget_limit'] - $category['paid_amount'] - ($category['unpaid_amount'] ?? 0);
                    
                    // Ensure remaining budget doesn't go below 0
                    if ($category['remaining_budget'] < 0) {
                        $category['remaining_budget'] = 0;
                    }
                } else {
                    $category['budget_limit'] = 0;
                    $category['budget_type'] = null;
                    $category['remaining_budget'] = 0;
                }
            }
        }
    }
}
