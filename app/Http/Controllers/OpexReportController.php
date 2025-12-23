<?php

namespace App\Http\Controllers;

use App\Models\RetailNonFood;
use App\Services\BudgetCalculationService;
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
        // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
        // FIX: Use proper join with GROUP BY to avoid double counting from multiple PR items or payments
        $query = DB::table('purchase_order_ops as poo')
            ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            // Join to PR items to get category and outlet from items (new structure)
            // FIX: Match by pr_ops_item_id first (most accurate), then by item_name + source_id to ensure unique match
            ->leftJoin('purchase_requisition_items as pri', function($join) {
                $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                     ->where(function($q) {
                         // Prefer exact match by pr_ops_item_id
                         $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                           // Fallback: match by item_name, but ensure it's the first match to avoid duplicates
                           ->orWhere(function($q2) {
                               $q2->whereNull('poi.pr_ops_item_id')
                                  ->whereColumn('poi.item_name', 'pri.item_name')
                                  ->whereRaw('pri.id = (
                                      SELECT MIN(pri2.id) 
                                      FROM purchase_requisition_items as pri2 
                                      WHERE pri2.purchase_requisition_id = pri.purchase_requisition_id 
                                      AND pri2.item_name = pri.item_name
                                  )');
                           });
                     });
            })
            // Get outlet from items (new structure) or fallback to PR level (old structure)
            ->leftJoin('tbl_data_outlet as o', function($join) {
                $join->on(DB::raw('COALESCE(pri.outlet_id, pr.outlet_id)'), '=', 'o.id_outlet');
            })
            // Get category from items (new structure) or fallback to PR level (old structure)
            ->leftJoin('purchase_requisition_categories as prc', function($join) {
                $join->on(DB::raw('COALESCE(pri.category_id, pr.category_id)'), '=', 'prc.id');
            })
            ->leftJoin('suppliers as s', 'poo.supplier_id', '=', 's.id')
            // FIX: Use subquery to get only one payment per PO (prefer paid/approved, then latest) to avoid double counting
            ->leftJoin(DB::raw('(
                SELECT 
                    nfp1.id,
                    nfp1.purchase_order_ops_id,
                    nfp1.payment_number,
                    nfp1.status as payment_status,
                    nfp1.payment_date,
                    nfp1.amount as payment_amount
                FROM non_food_payments as nfp1
                WHERE nfp1.status != "cancelled"
                AND nfp1.id = (
                    SELECT nfp2.id 
                    FROM non_food_payments as nfp2
                    WHERE nfp2.purchase_order_ops_id = nfp1.purchase_order_ops_id
                    AND nfp2.status != "cancelled"
                    ORDER BY 
                        CASE WHEN nfp2.status IN ("paid", "approved") THEN 0 ELSE 1 END,
                        nfp2.payment_date DESC,
                        nfp2.id DESC
                    LIMIT 1
                )
            ) as nfp'), 'poo.id', '=', 'nfp.purchase_order_ops_id')
            ->where('poo.status', 'approved') // Only approved POs
            ->whereBetween('poo.date', [$dateFrom, $dateTo])
            ->where('poi.source_type', 'purchase_requisition_ops') // Only PR source
            ->where('pr.is_held', false) // Exclude held PRs
            ->groupBy(
                'poi.id', // Group by PO item ID to ensure each PO item is counted only once
                'poo.id',
                'poo.number',
                'poo.date',
                's.name',
                'poi.item_name',
                'poi.quantity',
                'poi.unit',
                'poi.price',
                'poi.total',
                'pr.id',
                'pr.outlet_id',
                'pr.category_id',
                'pri.outlet_id',
                'pri.category_id',
                'o.id_outlet',
                'o.nama_outlet',
                'prc.id',
                'prc.name',
                'prc.division',
                'prc.subcategory',
                'prc.budget_type',
                'nfp.id',
                'nfp.payment_number',
                'nfp.payment_status',
                'nfp.payment_date',
                'nfp.payment_amount'
            )
            ->select(
                DB::raw('COALESCE(pri.outlet_id, pr.outlet_id) as outlet_id'),
                'o.nama_outlet as outlet_name',
                DB::raw('COALESCE(pri.category_id, pr.category_id) as category_id'),
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
                'nfp.payment_status',
                'nfp.payment_date',
                'nfp.payment_amount'
            );

        // Apply filters
        // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
        if ($outletId) {
            $query->where(function($q) use ($outletId) {
                // Support both structures: check outlet_id at item level OR PR level
                $q->where('pri.outlet_id', $outletId)
                  ->orWhere('pr.outlet_id', $outletId);
            });
        }

        if ($categoryId) {
            $query->where(function($q) use ($categoryId) {
                // Support both structures: check category_id at item level OR PR level
                $q->where('pri.category_id', $categoryId)
                  ->orWhere('pr.category_id', $categoryId);
            });
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
            ->where('pr.is_held', false) // Exclude held PRs
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
                // Get outlet budgets allocation
                $outletBudgets = DB::table('purchase_requisition_outlet_budgets as prob')
                    ->leftJoin('tbl_data_outlet as o', 'prob.outlet_id', '=', 'o.id_outlet')
                    ->where('prob.category_id', $category->id)
                    ->select(
                        'prob.outlet_id',
                        'o.nama_outlet as outlet_name',
                        'prob.allocated_budget'
                    )
                    ->get();

                // Use BudgetCalculationService for consistent calculation
                $budgetService = new BudgetCalculationService();
                $outletMap = [];
                
                foreach ($outletBudgets as $outletBudget) {
                    $outletId = $outletBudget->outlet_id;
                    
                    // Get budget info using service
                    $budgetInfo = $budgetService->getBudgetInfo(
                        categoryId: $category->id,
                        outletId: $outletId,
                        dateFrom: $dateFrom,
                        dateTo: $dateTo
                    );
                    
                    if (!$budgetInfo['success']) {
                        continue; // Skip if error
                    }
                    
                    // Extract values from BudgetCalculationService (sudah dihitung dengan benar)
                    // PENTING: Gunakan data dari BudgetCalculationService untuk konsistensi
                    $paidAmountFromPo = $budgetInfo['breakdown']['po_total'] ?? 0;
                    $prUnpaidAmount = $budgetInfo['breakdown']['pr_unpaid'] ?? 0;
                    $prPaidAmount = $budgetInfo['breakdown']['pr_paid'] ?? $budgetInfo['breakdown']['po_total'] ?? 0;
                    $poUnpaidAmount = $budgetInfo['breakdown']['po_unpaid'] ?? 0;
                    $outletRetailNonFoodApproved = $budgetInfo['breakdown']['retail_non_food'] ?? 0;
                    $prTotalAmount = $budgetInfo['breakdown']['pr_total'] ?? 0; // Semua PR items yang sudah dibuat
                    $poTotalAmount = $budgetInfo['breakdown']['po_total'] ?? 0;
                    
                    // NFP breakdown = 0 (karena kita tidak pakai NFP lagi)
                    $nfpSubmittedAmount = 0;
                    $nfpApprovedAmount = 0;
                    $nfpPaidAmount = 0;
                    
                    // LOGIKA YANG BENAR:
                    // - PR Total = semua PR items yang sudah dibuat (termasuk yang sudah jadi PO)
                    // - PR Unpaid = PR items yang belum jadi PO
                    // - PR Paid = PR items yang sudah jadi PO = PO Total
                    // - Used Budget = PR Total + RNF (karena RNF juga menggunakan budget)
                    // - Remaining Budget = Budget Limit - Used Budget
                    // - Paid Amount = PO Total + RNF (untuk tracking payment status)
                    // - Unpaid Amount = PR Unpaid (untuk tracking unpaid items)
                    
                    $unpaidAmount = $prUnpaidAmount; // PR items yang belum jadi PO
                    $paidAmount = $paidAmountFromPo + $outletRetailNonFoodApproved; // PO + RNF (untuk info payment)
                    $outletUsedAmount = $prTotalAmount + $outletRetailNonFoodApproved; // Used = PR Total + RNF
                    
                    $outletMap[] = [
                        'outlet_id' => $outletId,
                        'outlet_name' => $outletBudget->outlet_name,
                        'budget_limit' => $outletBudget->allocated_budget,
                        'paid_amount' => $paidAmount, // PO + RNF (untuk info payment status)
                        'unpaid_amount' => $unpaidAmount, // PR Unpaid (untuk info unpaid items)
                        'used_amount' => $outletUsedAmount, // PR Total (semua PR items yang sudah dibuat)
                        'remaining_budget' => $outletBudget->allocated_budget - $outletUsedAmount, // Budget - Used (PR Total + RNF)
                        'breakdown' => [
                            'pr_total' => $prTotalAmount, // Semua PR items yang sudah dibuat (termasuk yang sudah jadi PO)
                            'pr_unpaid' => $prUnpaidAmount, // PR items yang belum dibuat PO
                            'pr_paid' => $prPaidAmount, // PR items yang sudah jadi PO (alias PO Total)
                            'po_total' => $poTotalAmount, // Total PO items (sama dengan pr_paid, untuk backward compatibility)
                            'po_unpaid' => $poUnpaidAmount, // 0 (karena semua PO items sudah dihitung sebagai paid)
                            'nfp_submitted' => 0, // Tidak digunakan lagi
                            'nfp_approved' => 0, // Tidak digunakan lagi
                            'nfp_paid' => 0, // Tidak digunakan lagi
                            'retail_non_food' => $outletRetailNonFoodApproved, // Retail Non Food Approved
                        ],
                    ];
                    
                    // Add to totals
                    $categoryGroup['total_paid_amount'] += $paidAmount;
                    $categoryGroup['total_unpaid_amount'] += $unpaidAmount;
                }

                $categoryGroup['outlets'] = $outletMap;
                
                // Calculate detailed breakdown for PER_OUTLET (sum from all outlets)
                // PENTING: Semua breakdown sudah dihitung per outlet dari BudgetCalculationService
                // PENTING: Semua breakdown sudah dihitung per outlet dari BudgetCalculationService
                // Jadi kita hanya perlu sum dari outletMap
                $totalPrTotal = 0;
                $totalPrUnpaid = 0;
                $totalPoTotal = 0;
                $totalPoUnpaid = 0;
                $totalNfpSubmitted = 0;
                $totalNfpApproved = 0;
                $totalNfpPaid = 0;
                $totalRnf = 0;
                
                foreach ($outletMap as $outlet) {
                    if (isset($outlet['breakdown'])) {
                        $totalPrTotal += $outlet['breakdown']['pr_total'] ?? 0;
                        $totalPrUnpaid += $outlet['breakdown']['pr_unpaid'] ?? 0;
                        $totalPoTotal += $outlet['breakdown']['po_total'] ?? 0;
                        $totalPoUnpaid += $outlet['breakdown']['po_unpaid'] ?? 0;
                        $totalNfpSubmitted += $outlet['breakdown']['nfp_submitted'] ?? 0;
                        $totalNfpApproved += $outlet['breakdown']['nfp_approved'] ?? 0;
                        $totalNfpPaid += $outlet['breakdown']['nfp_paid'] ?? 0;
                        $totalRnf += $outlet['breakdown']['retail_non_food'] ?? 0;
                    }
                }
                
                // Calculate total used amount (PR Total + RNF)
                $totalUsedAmount = $totalPrTotal + $totalRnf;
                
                // PENTING: PR Paid = PR Total - PR Unpaid (untuk konsistensi)
                $totalPrPaid = $totalPrTotal - $totalPrUnpaid;
                
                $categoryGroup['budget_breakdown'] = [
                    'pr_total' => $totalPrTotal, // Semua PR items yang sudah dibuat (termasuk yang sudah jadi PO)
                    'pr_unpaid' => $totalPrUnpaid, // PR items yang belum dibuat PO
                    'pr_paid' => $totalPrPaid, // PR items yang sudah jadi PO = PR Total - PR Unpaid
                    'po_total' => $totalPoTotal, // Total PO items (untuk referensi, bisa berbeda dari pr_paid)
                    'po_unpaid' => $totalPoUnpaid, // PO yang belum dibuat NFP (0 karena semua PO sudah dihitung sebagai paid)
                    'nfp_submitted' => $totalNfpSubmitted, // Tidak digunakan lagi
                    'nfp_approved' => $totalNfpApproved, // Tidak digunakan lagi
                    'nfp_paid' => $totalNfpPaid, // Tidak digunakan lagi
                    'retail_non_food' => $totalRnf, // Retail Non Food Approved
                    // Keep old fields for backward compatibility
                    'nfp_amount' => $totalNfpPaid,
                    'pr_unpaid_amount' => $totalPrUnpaid, // PENTING: Hanya PR Unpaid
                    'rnf_amount' => $totalRnf,
                ];
                
                // PENTING: 
                // - Used Budget = PR Total + RNF (karena RNF juga menggunakan budget)
                // - Remaining Budget = Budget Limit - Used Budget
                // - PR Total = semua PR items yang sudah dibuat (termasuk yang sudah jadi PO)
                $categoryGroup['total_remaining_budget'] = $category->budget_limit - $totalUsedAmount;
            } else {
                // For GLOBAL budget type, use BudgetCalculationService for consistent calculation
                // GLOBAL: Sum all items for this category (no outlet filter at all)
                $budgetService = new BudgetCalculationService();
                $budgetInfo = $budgetService->getBudgetInfo(
                    categoryId: $category->id,
                    outletId: null, // GLOBAL budget: no outlet filter
                    dateFrom: $dateFrom,
                    dateTo: $dateTo,
                    currentAmount: 0 // No current amount for report
                );
                
                if (!$budgetInfo['success']) {
                    // Skip this category if error
                    continue;
                }
                
                // Extract values from BudgetCalculationService
                $paidAmountFromPo = $budgetInfo['breakdown']['po_total'] ?? 0;
                $prUnpaidAmount = $budgetInfo['breakdown']['pr_unpaid'] ?? 0;
                $prPaidAmount = $budgetInfo['breakdown']['pr_paid'] ?? $budgetInfo['breakdown']['po_total'] ?? 0;
                $poUnpaidAmount = $budgetInfo['breakdown']['po_unpaid'] ?? 0;
                $retailNonFoodApproved = $budgetInfo['breakdown']['retail_non_food'] ?? 0;
                $prTotalAmount = $budgetInfo['breakdown']['pr_total'] ?? 0; // Semua PR items yang sudah dibuat
                $poTotalAmount = $budgetInfo['breakdown']['po_total'] ?? 0;
                
                // GLOBAL: Calculate totals directly from BudgetCalculationService
                // LOGIKA YANG BENAR:
                // - PR Total = semua PR items yang sudah dibuat (termasuk yang sudah jadi PO)
                // - PR Unpaid = PR items yang belum jadi PO
                // - PR Paid = PR items yang sudah jadi PO = PO Total
                // - Used Budget = PR Total + RNF (karena RNF juga menggunakan budget)
                // - Remaining Budget = Budget Limit - Used Budget
                // - Paid Amount = PO Total + RNF (untuk tracking payment status)
                // - Unpaid Amount = PR Unpaid (untuk tracking unpaid items)
                
                $categoryGroup['total_paid_amount'] = $poTotalAmount + $retailNonFoodApproved; // PO + RNF
                $categoryGroup['total_unpaid_amount'] = $prUnpaidAmount; // PR Unpaid
                
                // PENTING: Used Budget = PR Total + RNF (karena RNF juga menggunakan budget)
                // Remaining Budget = Budget Limit - Used Budget
                $totalUsedAmount = $prTotalAmount + $retailNonFoodApproved;
                $categoryGroup['total_remaining_budget'] = $category->budget_limit - $totalUsedAmount;

                // Get Retail Non Food data for display (with outlet info for transaction details)
                $retailNonFoodData = DB::table('retail_non_food as rnf')
                    ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
                    ->where('rnf.category_budget_id', $category->id)
                    ->whereBetween('rnf.transaction_date', [$dateFrom, $dateTo])
                    ->where('rnf.status', 'approved')
                    ->select(
                        'o.id_outlet as outlet_id',
                        'o.nama_outlet as outlet_name',
                        'rnf.total_amount as po_item_total',
                        'rnf.retail_number',
                        'rnf.transaction_date',
                        'rnf.status',
                        DB::raw('rnf.id as payment_id'),
                        DB::raw('rnf.total_amount as payment_amount'),
                        DB::raw('"retail_non_food" as source_type')
                    )
                    ->get();

                // Build outlet map for display only (not for used amount calculation)
                // This is just for showing breakdown per outlet in UI, but used amount is calculated globally
                $outletMap = [];
                
                // Process Retail Non Food data for display
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
                }

                // Calculate remaining budget for each outlet (for display only)
                foreach ($outletMap as &$outlet) {
                    $outlet['remaining_budget'] = $outlet['budget_limit'] - $outlet['paid_amount'] - $outlet['unpaid_amount'];
                }

                $categoryGroup['outlets'] = array_values($outletMap);
                
                // Add transaction details for expandable view (empty for global budget - not used)
                $categoryGroup['transactions'] = [
                    'paid' => [],
                    'unpaid' => []
                ];
                
                // Use breakdown from BudgetCalculationService (already calculated correctly)
                $categoryGroup['budget_breakdown'] = $budgetInfo['breakdown'] ?? [
                    'pr_total' => 0,
                    'pr_unpaid' => 0,
                    'pr_paid' => 0,
                    'po_total' => 0,
                    'po_unpaid' => 0,
                    'nfp_submitted' => 0,
                    'nfp_approved' => 0,
                    'nfp_paid' => 0,
                    'retail_non_food' => 0,
                ];
                
                // Ensure pr_paid field exists (PR items yang sudah jadi PO = PR Total - PR Unpaid)
                if (!isset($categoryGroup['budget_breakdown']['pr_paid'])) {
                    $prTotal = $categoryGroup['budget_breakdown']['pr_total'] ?? 0;
                    $prUnpaid = $categoryGroup['budget_breakdown']['pr_unpaid'] ?? 0;
                    $categoryGroup['budget_breakdown']['pr_paid'] = $prTotal - $prUnpaid;
                }
                
                    // Keep old fields for backward compatibility
                $categoryGroup['budget_breakdown']['nfp_amount'] = $categoryGroup['budget_breakdown']['nfp_paid'] ?? 0;
                $categoryGroup['budget_breakdown']['pr_unpaid_amount'] = $categoryGroup['budget_breakdown']['pr_unpaid'] ?? 0; // PENTING: Hanya PR Unpaid
                $categoryGroup['budget_breakdown']['rnf_amount'] = $categoryGroup['budget_breakdown']['retail_non_food'] ?? 0;
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
