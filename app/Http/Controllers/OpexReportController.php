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
                    
                    // Get PR IDs for this outlet (same logic as getBudgetInfo)
                    $prIds = DB::table('purchase_requisitions as pr')
                        ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                        ->where(function($q) use ($category, $outletId) {
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
                        ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                        ->where('pr.is_held', false)
                        ->distinct()
                        ->pluck('pr.id')
                        ->toArray();
                    
                    // Get PO IDs linked to PRs in this outlet
                    // IMPORTANT: Untuk PR Ops, filter PO items berdasarkan outlet_id di purchase_requisition_items
                    // IMPORTANT: Filter by PR created_at month (BUDGET IS MONTHLY)
                    $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
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
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
                        ->where('pr.is_held', false)
                        ->where(function($q) use ($category, $outletId) {
                            // Old structure: category and outlet at PR level
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            // New structure: category and outlet at items level (PENTING: filter by pri.outlet_id)
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->distinct()
                        ->pluck('poi.purchase_order_ops_id')
                        ->toArray();
                    
                    // Get paid amount from non_food_payments for this outlet (same logic as getBudgetInfo)
                    // 
                    // SKENARIO KOMPLEKS:
                    // 1. 1 PR bisa jadi beberapa PO → Loop semua PO yang terkait dengan PR untuk outlet/category ini ✅
                    // 2. 1 PO bisa gabungan dari beberapa PR dan outlet → Hitung proporsi PO items untuk outlet/category ini ✅
                    // 3. 1 NFP biasanya membayar 1 PO (via purchase_order_ops_id) → Loop per PO, hitung proporsi per payment ✅
                    //
                    // LOGIKA PROPORSIONAL:
                    // - Untuk setiap PO yang terkait dengan outlet/category ini:
                    //   1. Hitung total PO items untuk outlet/category ini
                    //   2. Hitung total semua PO items di PO tersebut
                    //   3. Hitung proporsi: (outlet items) / (total items)
                    //   4. Alokasikan payment: payment_amount * proportion
                    // - Ini memastikan jika 1 PO gabungan dari beberapa outlet, hanya proporsi yang sesuai yang dihitung
                    //
                    $paidAmountFromPo = 0;
                    if (!empty($poIdsInCategory)) {
                        // Loop semua PO yang terkait dengan outlet/category ini
                        foreach ($poIdsInCategory as $poId) {
                            // STEP 1: Get PO items yang berasal dari PR items di outlet/category ini
                            // Ini menghitung berapa banyak PO items yang benar-benar untuk outlet/category ini
                            // (untuk handle skenario: 1 PO gabungan dari beberapa PR dan outlet)
                            $outletPoItemIds = DB::table('purchase_order_ops_items as poi')
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
                                ->where('poi.purchase_order_ops_id', $poId)
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->where(function($q) use ($category, $outletId) {
                                    // Old structure: category and outlet at PR level
                                    $q->where(function($q2) use ($category, $outletId) {
                                        $q2->where('pr.category_id', $category->id)
                                           ->where('pr.outlet_id', $outletId);
                                    })
                                    // New structure: category and outlet at items level
                                    ->orWhere(function($q2) use ($category, $outletId) {
                                        $q2->where('pri.category_id', $category->id)
                                           ->where('pri.outlet_id', $outletId);
                                    });
                                })
                                ->pluck('poi.total')
                                ->toArray();
                            
                            // STEP 2: Get payment untuk PO ini
                            // Catatan: 1 NFP biasanya membayar 1 PO (via purchase_order_ops_id)
                            // Jika ada skenario 1 NFP membayar beberapa PO, perlu struktur tambahan
                            $poPayment = DB::table('non_food_payments')
                                ->where('purchase_order_ops_id', $poId)
                                ->where('status', 'paid') // Only 'paid' status, not 'approved'
                                ->where('status', '!=', 'cancelled')
                                ->whereBetween('payment_date', [$dateFrom, $dateTo])
                                ->first();
                            
                            if ($poPayment) {
                                // STEP 3: Verify PO is still approved (not deleted)
                                $poStatus = DB::table('purchase_order_ops')
                                    ->where('id', $poId)
                                    ->value('status');
                                
                                if ($poStatus === 'approved') {
                                    // STEP 4: Hitung proporsi untuk alokasi payment
                                    // Proporsi = (PO items untuk outlet/category ini) / (total semua PO items)
                                    // Ini memastikan jika 1 PO gabungan dari beberapa outlet, hanya proporsi yang sesuai yang dihitung
                                    $poTotalItems = DB::table('purchase_order_ops_items')
                                        ->where('purchase_order_ops_id', $poId)
                                        ->sum('total');
                                    
                                    if ($poTotalItems > 0) {
                                        $outletPoItemsTotal = array_sum($outletPoItemIds);
                                        $proportion = $outletPoItemsTotal / $poTotalItems;
                                        
                                        // STEP 5: Alokasikan payment berdasarkan proporsi
                                        // allocated_amount = payment_amount * proportion
                                        $paidAmountFromPo += $poPayment->amount * $proportion;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Get Retail Non Food for this outlet
                    $outletRetailNonFoodApproved = DB::table('retail_non_food')
                        ->where('category_budget_id', $category->id)
                        ->where('outlet_id', $outletId)
                        ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                        ->where('status', 'approved')
                        ->sum('total_amount');
                    
                    // Get unpaid PR data for this outlet (same logic as getBudgetInfo)
                    $prIdsForUnpaid = DB::table('purchase_requisitions as pr')
                        ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                        ->leftJoin('purchase_order_ops_items as poi', function($join) {
                            $join->on('pr.id', '=', 'poi.source_id')
                                 ->where('poi.source_type', '=', 'purchase_requisition_ops');
                        })
                        ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                        ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
                        ->where(function($q) use ($category, $outletId) {
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
                        ->whereIn('pr.status', ['SUBMITTED', 'APPROVED'])
                        ->where('pr.is_held', false)
                        ->whereNull('poo.id')
                        ->whereNull('nfp.id')
                        ->distinct()
                        ->pluck('pr.id')
                        ->toArray();
                    
                    $allPrs = DB::table('purchase_requisitions')->whereIn('id', $prIdsForUnpaid)->get();
                    
                    // Get PO totals per PR for this outlet
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
                        ->where(function($q) use ($category, $outletId) {
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
                        ->where('pr.is_held', false)
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->whereIn('poo.status', ['submitted', 'approved'])
                        ->groupBy('pr.id')
                        ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                        ->pluck('po_total', 'pr_id')
                        ->toArray();
                    
                    // Get total paid per PR for this outlet
                    $paidTotalsByPr = DB::table('non_food_payments as nfp')
                        ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
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
                        ->where(function($q) use ($category, $outletId) {
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
                        ->where('pr.is_held', false)
                        ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                        ->where('nfp.status', 'paid')
                        ->where('nfp.status', '!=', 'cancelled')
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->where('poo.status', 'approved')
                        ->groupBy('pr.id')
                        ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                        ->pluck('total_paid', 'pr_id')
                        ->toArray();
                    
                    // Get unpaid PO data for this outlet
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
                        ->where(function($q) use ($category, $outletId) {
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
                        ->where('pr.is_held', false)
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->whereIn('poo.status', ['submitted', 'approved'])
                        ->whereNull('nfp.id')
                        ->groupBy('poo.id')
                        ->select('poo.id as po_id', DB::raw('SUM(poi.total) as po_total'))
                        ->get();
                    
                    // Calculate unpaid for each PR
                    // IMPORTANT: Untuk PR Ops (mode pr_ops/purchase_payment), hitung berdasarkan items di outlet tersebut
                    // Untuk mode lain, gunakan PR amount
                    $prUnpaidAmount = 0;
                    foreach ($allPrs as $pr) {
                        // Untuk PR Ops: hitung berdasarkan items di outlet tersebut
                        if (in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
                            // Hitung subtotal items di outlet ini
                            $outletItemsSubtotal = DB::table('purchase_requisition_items')
                                ->where('purchase_requisition_id', $pr->id)
                                ->where('outlet_id', $outletId)
                                ->where('category_id', $category->id)
                                ->sum('subtotal');
                            $prUnpaidAmount += $outletItemsSubtotal ?? 0;
                        } else {
                            // Untuk mode lain: gunakan PR amount
                            $prUnpaidAmount += $pr->amount;
                        }
                    }
                    
                    // Calculate unpaid for each PO
                    $poUnpaidAmount = 0;
                    foreach ($allPOs as $po) {
                        $poUnpaidAmount += $po->po_total ?? 0;
                    }
                    
                    // Calculate unpaid NFP for this outlet
                    $nfpUnpaidFromPr = DB::table('non_food_payments as nfp')
                        ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                        ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                        ->where(function($q) use ($category, $outletId) {
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
                        ->where('pr.is_held', false)
                        ->whereNull('nfp.purchase_order_ops_id')
                        ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                        ->whereIn('nfp.status', ['pending', 'approved'])
                        ->where('nfp.status', '!=', 'cancelled')
                        ->sum('nfp.amount');
                    
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
                        ->where(function($q) use ($category, $outletId) {
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
                        ->where('pr.is_held', false)
                        ->whereNotNull('nfp.purchase_order_ops_id')
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
                        ->where(function($q) use ($category, $outletId) {
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
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
                        ->where(function($q) use ($category, $outletId) {
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
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
                        ->where(function($q) use ($category, $outletId) {
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
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
                        ->where(function($q) use ($category, $outletId) {
                            $q->where(function($q2) use ($category, $outletId) {
                                $q2->where('pr.category_id', $category->id)
                                   ->where('pr.outlet_id', $outletId);
                            })
                            ->orWhere(function($q2) use ($category, $outletId) {
                                $q2->where('pri.category_id', $category->id)
                                   ->where('pri.outlet_id', $outletId);
                            });
                        })
                        ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                        ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
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
                    
                    $outletMap[] = [
                        'outlet_id' => $outletId,
                        'outlet_name' => $outletBudget->outlet_name,
                        'budget_limit' => $outletBudget->allocated_budget,
                        'paid_amount' => $paidAmount,
                        'unpaid_amount' => $unpaidAmount,
                        'used_amount' => $outletUsedAmount, // Total used (paid + unpaid)
                        'remaining_budget' => $outletBudget->allocated_budget - $outletUsedAmount,
                        'breakdown' => [
                            'pr_unpaid' => $prUnpaidAmount, // PR Submitted & Approved yang belum dibuat PO
                            'po_unpaid' => $poUnpaidAmount, // PO Submitted & Approved yang belum dibuat NFP
                            'nfp_submitted' => $nfpSubmittedAmount, // NFP Submitted
                            'nfp_approved' => $nfpApprovedAmount, // NFP Approved (unpaid)
                            'nfp_paid' => $nfpPaidAmount, // NFP Paid
                            'retail_non_food' => $outletRetailNonFoodApproved, // Retail Non Food Approved
                        ],
                    ];
                    
                    // Add to totals
                    $categoryGroup['total_paid_amount'] += $paidAmount;
                    $categoryGroup['total_unpaid_amount'] += $unpaidAmount;
                }

                $categoryGroup['outlets'] = $outletMap;
                $categoryGroup['total_remaining_budget'] = $category->budget_limit - ($categoryGroup['total_paid_amount'] + $categoryGroup['total_unpaid_amount']);
                
                // Calculate breakdown for PER_OUTLET budget type (sum from all outlets)
                // Breakdown sudah dihitung per outlet di loop di atas, sekarang kita sum untuk total
                $totalNfpAmount = 0;
                $totalPrUnpaidAmount = 0;
                $totalPoUnpaidAmount = 0;
                $totalNfpUnpaidAmount = 0;
                $totalRnfAmount = 0;
                
                // Sum breakdown dari semua outlet
                foreach ($outletMap as $outlet) {
                    // NFP paid sudah termasuk di paid_amount (paidAmountFromPo)
                    // RNF sudah termasuk di paid_amount (outletRetailNonFoodApproved)
                    // Unpaid sudah dihitung di unpaid_amount
                    // Untuk breakdown detail, kita perlu hitung ulang secara global atau dari outlet data
                }
                
                // Get global breakdown untuk kategori ini (tanpa filter outlet)
                $prIdsInCategory = DB::table('purchase_requisitions as pr')
                    ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                    ->where(function($q) use ($category) {
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereYear('pr.created_at', date('Y', strtotime($dateFrom)))
                    ->whereMonth('pr.created_at', date('m', strtotime($dateFrom)))
                    ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                    ->where('pr.is_held', false)
                    ->distinct()
                    ->pluck('pr.id')
                    ->toArray();
                
                $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->whereIn('poi.source_id', $prIdsInCategory)
                    ->distinct()
                    ->pluck('poi.purchase_order_ops_id')
                    ->toArray();
                
                $totalNfpAmount = DB::table('non_food_payments')
                    ->whereIn('purchase_order_ops_id', $poIdsInCategory)
                    ->whereBetween('payment_date', [$dateFrom, $dateTo])
                    ->where('status', 'paid')
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
                
                $totalRnfAmount = DB::table('retail_non_food')
                    ->where('category_budget_id', $category->id)
                    ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                    ->where('status', 'approved')
                    ->sum('total_amount');
                
                // Total unpaid = total_unpaid_amount yang sudah dihitung
                $totalPrUnpaidAmount = $categoryGroup['total_unpaid_amount'];
                
                // Calculate detailed breakdown for PER_OUTLET (sum from all outlets)
                $totalPrUnpaid = 0;
                $totalPoUnpaid = 0;
                $totalNfpSubmitted = 0;
                $totalNfpApproved = 0;
                $totalNfpPaid = 0;
                $totalRnf = 0;
                
                foreach ($outletMap as $outlet) {
                    if (isset($outlet['breakdown'])) {
                        $totalPrUnpaid += $outlet['breakdown']['pr_unpaid'] ?? 0;
                        $totalPoUnpaid += $outlet['breakdown']['po_unpaid'] ?? 0;
                        $totalNfpSubmitted += $outlet['breakdown']['nfp_submitted'] ?? 0;
                        $totalNfpApproved += $outlet['breakdown']['nfp_approved'] ?? 0;
                        $totalNfpPaid += $outlet['breakdown']['nfp_paid'] ?? 0;
                        $totalRnf += $outlet['breakdown']['retail_non_food'] ?? 0;
                    }
                }
                
                $categoryGroup['budget_breakdown'] = [
                    'pr_unpaid' => $totalPrUnpaid, // PR Submitted & Approved yang belum dibuat PO
                    'po_unpaid' => $totalPoUnpaid, // PO Submitted & Approved yang belum dibuat NFP
                    'nfp_submitted' => $totalNfpSubmitted, // NFP Submitted
                    'nfp_approved' => $totalNfpApproved, // NFP Approved (unpaid)
                    'nfp_paid' => $totalNfpPaid, // NFP Paid
                    'retail_non_food' => $totalRnf, // Retail Non Food Approved
                    // Keep old fields for backward compatibility
                    'nfp_amount' => $totalNfpPaid,
                    'pr_unpaid_amount' => $totalPrUnpaid + $totalPoUnpaid + $totalNfpApproved,
                    'rnf_amount' => $totalRnf,
                ];
            } else {
                // For GLOBAL budget type, get paid amount from non_food_payments (actual payment amount)
                // Ambil langsung dari non_food_payments tanpa join ke PO items untuk menghindari double counting
                // Filter category melalui relasi PO -> PO Items -> PR
                // 1 payment = 1 transaksi pembayaran yang unik, tidak boleh dihitung berkali-kali
                
                // Get PR IDs in this category for the selected date range (follow date filter from above)
                // Support both old structure (category at PR level) and new structure (category at items level)
                // Use whereBetween to follow the date filter selected by user
                $prIdsInCategory = DB::table('purchase_requisitions as pr')
                    ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                    ->where(function($q) use ($category) {
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                    ->where('pr.is_held', false) // Exclude held PRs
                    ->distinct()
                    ->pluck('pr.id')
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
                // Support both old structure (outlet at PR level) and new structure (outlet at items level)
                // Ambil outlet dari items (new structure) atau fallback ke PR level (old structure)
                $paidPaymentsData = DB::table('non_food_payments as nfp')
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
                    ->leftJoin('tbl_data_outlet as o', function($join) {
                        $join->on(DB::raw('COALESCE(pri.outlet_id, pr.outlet_id)'), '=', 'o.id_outlet');
                    })
                    ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                    ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                    ->where('nfp.status', 'paid') // Only 'paid' status, not 'approved'
                    ->where('nfp.status', '!=', 'cancelled')
                    ->groupBy('nfp.id') // Group by payment ID saja untuk menghindari duplikasi
                    ->select(
                        DB::raw('MIN(COALESCE(pri.outlet_id, pr.outlet_id)) as outlet_id'), // Ambil outlet dari items atau PR
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
                // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO dan belum jadi NFP
                // Support both old structure (category/outlet at PR level) and new structure (category/outlet at items level)
                // Exclude held PRs and rejected PRs
                $allPrs = DB::table('purchase_requisitions as pr')
                    ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                    ->leftJoin('tbl_data_outlet as o', function($join) {
                        // Support both old structure (outlet at PR level) and new structure (outlet at items level)
                        $join->on(DB::raw('COALESCE(pri.outlet_id, pr.outlet_id)'), '=', 'o.id_outlet');
                    })
                    ->leftJoin('purchase_order_ops_items as poi', function($join) {
                        $join->on('pr.id', '=', 'poi.source_id')
                             ->where('poi.source_type', '=', 'purchase_requisition_ops');
                    })
                    ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                    ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
                    ->where(function($q) use ($category) {
                        // Support both old structure (category at PR level) and new structure (category at items level)
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->whereIn('pr.status', ['SUBMITTED', 'APPROVED']) // Only SUBMITTED and APPROVED
                    ->where('pr.is_held', false) // Exclude held PRs
                    ->whereNull('poo.id') // PR yang belum jadi PO (belum ada PO)
                    ->whereNull('nfp.id') // PR yang belum jadi NFP (baik langsung maupun melalui PO)
                    ->groupBy(
                        'pr.id', 
                        'o.id_outlet', 
                        'o.nama_outlet', 
                        'pr.amount', 
                        'pr.pr_number', 
                        'pr.created_at', 
                        'pr.status',
                        DB::raw('COALESCE(pri.outlet_id, pr.outlet_id)') // Add COALESCE to GROUP BY
                    )
                    ->select(
                        'pr.id as pr_id',
                        DB::raw('COALESCE(pri.outlet_id, pr.outlet_id) as outlet_id'), // Support both structures
                        'o.nama_outlet as outlet_name',
                        'pr.amount as pr_amount',
                        'pr.pr_number',
                        'pr.created_at as transaction_date',
                        'pr.status'
                    )
                    ->get();

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
                    ->where(function($q) use ($category) {
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->where('pr.is_held', false)
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                    ->groupBy('pr.id')
                    ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                    ->pluck('po_total', 'pr_id')
                    ->toArray();

                // Get unpaid PO data
                // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
                // Get all POs in this category
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
                    ->leftJoin('tbl_data_outlet as o', function($join) {
                        $join->on(DB::raw('COALESCE(pri.outlet_id, pr.outlet_id)'), '=', 'o.id_outlet');
                    })
                    ->leftJoin('non_food_payments as nfp', 'poo.id', '=', 'nfp.purchase_order_ops_id')
                    ->where(function($q) use ($category) {
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->where('pr.is_held', false)
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->whereIn('poo.status', ['submitted', 'approved']) // PO dengan status SUBMITTED dan APPROVED
                    ->whereNull('nfp.id') // PO yang belum jadi NFP (belum ada NFP)
                    ->groupBy('poo.id', 'poo.number', 'poo.date', 'o.id_outlet', 'o.nama_outlet')
                    ->select(
                        'poo.id as po_id',
                        'poo.number as po_number',
                        'poo.date as po_date',
                        'o.id_outlet as outlet_id',
                        'o.nama_outlet as outlet_name',
                        DB::raw('SUM(poi.total) as po_total')
                    )
                    ->get();

                // Get unpaid NFP data
                // NEW LOGIC: NFP unpaid = NFP dengan status pending dan approved
                // Mencakup NFP yang langsung dari PR (tanpa PO) dan NFP yang melalui PO
                // Case 1: NFP langsung dari PR
                $unpaidNfpFromPr = DB::table('non_food_payments as nfp')
                    ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                    ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                    ->leftJoin('tbl_data_outlet as o', function($join) {
                        $join->on(DB::raw('COALESCE(pri.outlet_id, pr.outlet_id)'), '=', 'o.id_outlet');
                    })
                    ->where(function($q) use ($category) {
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->where('pr.is_held', false)
                    ->whereNull('nfp.purchase_order_ops_id') // NFP langsung dari PR (tanpa PO)
                    ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                    ->whereIn('nfp.status', ['pending', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->groupBy('nfp.id', 'nfp.amount', 'nfp.payment_date', 'nfp.payment_number', 'nfp.status', 'o.id_outlet', 'o.nama_outlet')
                    ->select(
                        'o.id_outlet as outlet_id',
                        'o.nama_outlet as outlet_name',
                        'nfp.amount as unpaid_amount',
                        'nfp.payment_number',
                        'nfp.payment_date as transaction_date',
                        'nfp.status',
                        DB::raw('NULL as po_id')
                    )
                    ->get();
                
                // Case 2: NFP melalui PO
                $unpaidNfpFromPo = DB::table('non_food_payments as nfp')
                    ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
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
                    ->leftJoin('tbl_data_outlet as o', function($join) {
                        $join->on(DB::raw('COALESCE(pri.outlet_id, pr.outlet_id)'), '=', 'o.id_outlet');
                    })
                    ->where(function($q) use ($category) {
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->where('pr.is_held', false)
                    ->whereNotNull('nfp.purchase_order_ops_id') // NFP melalui PO
                    ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                    ->whereIn('nfp.status', ['pending', 'approved'])
                    ->where('nfp.status', '!=', 'cancelled')
                    ->where('poi.source_type', 'purchase_requisition_ops')
                    ->groupBy('nfp.id', 'nfp.purchase_order_ops_id', 'nfp.amount', 'nfp.payment_date', 'nfp.payment_number', 'nfp.status', 'o.id_outlet', 'o.nama_outlet')
                    ->select(
                        'o.id_outlet as outlet_id',
                        'o.nama_outlet as outlet_name',
                        'nfp.amount as unpaid_amount',
                        'nfp.payment_number',
                        'nfp.payment_date as transaction_date',
                        'nfp.status',
                        'nfp.purchase_order_ops_id as po_id'
                    )
                    ->get();
                
                // Combine both results
                $unpaidNfpData = $unpaidNfpFromPr->merge($unpaidNfpFromPo);

                // Calculate unpaid for each PR
                // NEW LOGIC: PR unpaid = PR dengan status SUBMITTED dan APPROVED yang belum jadi PO
                // PR yang sudah difilter di query (belum jadi PO)
                // IMPORTANT: Untuk GLOBAL budget, sum semua items dari semua outlets (tidak filter outlet)
                // Untuk PR Ops, hitung berdasarkan items.subtotal di category ini (sum semua outlets)
                $prData = collect();
                foreach ($allPrs as $pr) {
                    // Get PR model untuk mendapatkan mode
                    $prModel = \App\Models\PurchaseRequisition::find($pr->pr_id);
                    
                    // Untuk PR Ops: hitung berdasarkan items di category ini (sum semua outlets)
                    if ($prModel && in_array($prModel->mode, ['pr_ops', 'purchase_payment'])) {
                        // Hitung subtotal items di category ini (semua outlets) - TIDAK filter outlet untuk global budget
                        $categoryItemsSubtotal = DB::table('purchase_requisition_items')
                            ->where('purchase_requisition_id', $pr->pr_id)
                            ->where('category_id', $category->id)
                            ->sum('subtotal');
                        $prAmount = $categoryItemsSubtotal ?? 0;
                    } else {
                        // Untuk mode lain: gunakan PR amount
                        $prAmount = $pr->pr_amount;
                    }
                    
                    // PR yang sudah difilter di query (belum jadi PO, status SUBMITTED/APPROVED)
                    $prData->push((object)[
                        'outlet_id' => $pr->outlet_id,
                        'outlet_name' => $pr->outlet_name,
                        'po_item_total' => $prAmount,
                        'pr_number' => $pr->pr_number,
                        'po_numbers' => null, // PR hasn't been converted to PO
                        'transaction_date' => $pr->transaction_date,
                        'status' => $pr->status,
                        'payment_id' => null,
                        'payment_amount' => null,
                        'source_type' => 'purchase_requisition'
                    ]);
                }

                // Calculate unpaid for each PO
                // NEW LOGIC: PO unpaid = PO dengan status SUBMITTED dan APPROVED yang belum jadi NFP
                // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                $poData = collect();
                foreach ($allPOs as $po) {
                    // PO yang sudah difilter di query (belum jadi NFP, status SUBMITTED/APPROVED)
                    $poData->push((object)[
                        'outlet_id' => $po->outlet_id,
                        'outlet_name' => $po->outlet_name,
                        'po_item_total' => $po->po_total,
                        'po_number' => $po->po_number,
                        'transaction_date' => $po->po_date,
                        'status' => 'approved',
                        'payment_id' => null,
                        'payment_amount' => null,
                        'source_type' => 'purchase_order'
                    ]);
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

                // Process unpaid PO data (PO that haven't been paid or partially paid)
                foreach ($poData as $row) {
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

                // Process unpaid NFP data (NFP with status 'approved' but not 'paid')
                foreach ($unpaidNfpData as $row) {
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

                    $itemTotal = $row->unpaid_amount ?? 0;
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
                        'po_numbers' => $row->po_numbers ?? null, // PO ops numbers if PR has been converted to PO
                        'date' => $row->transaction_date,
                        'outlet' => $row->outlet_name,
                        'amount' => $row->po_item_total,
                        'status' => $row->status
                    ];
                }
                
                // Add unpaid PO
                foreach ($poData as $row) {
                    $unpaidTransactions[] = [
                        'type' => 'purchase_order',
                        'number' => $row->po_number ?? '-',
                        'date' => $row->transaction_date,
                        'outlet' => $row->outlet_name,
                        'amount' => $row->po_item_total,
                        'status' => $row->status
                    ];
                }
                
                // Add unpaid NFP
                foreach ($unpaidNfpData as $row) {
                    $unpaidTransactions[] = [
                        'type' => 'non_food_payment',
                        'number' => $row->payment_number ?? '-',
                        'date' => $row->transaction_date,
                        'outlet' => $row->outlet_name,
                        'amount' => $row->unpaid_amount,
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
                
                // Calculate breakdown by source type with detail
                // Get PR unpaid amount (from prData)
                $prUnpaidAmount = $prData->sum('po_item_total');
                
                // Get PO unpaid amount (from poData)
                $poUnpaidAmount = $poData->sum('po_item_total');
                
                // Get NFP breakdown by status
                // NFP Submitted
                $nfpSubmittedAmount = DB::table('non_food_payments as nfp')
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
                    ->where(function($q) use ($category) {
                        $q->where('pr.category_id', $category->id)
                          ->orWhere('pri.category_id', $category->id);
                    })
                    ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
                    ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                    ->where('nfp.status', 'submitted')
                    ->where('nfp.status', '!=', 'cancelled')
                    ->sum('nfp.amount');
                
                // NFP Approved (unpaid)
                $nfpApprovedAmount = $unpaidNfpData->sum('unpaid_amount');
                
                // NFP Paid
                $nfpPaidAmount = $paidPaymentsData->sum('total_paid_amount');
                
                // RNF Approved
                $rnfAmount = $retailNonFoodData->sum('po_item_total');
                
                $categoryGroup['budget_breakdown'] = [
                    'pr_unpaid' => $prUnpaidAmount, // PR Submitted & Approved yang belum dibuat PO
                    'po_unpaid' => $poUnpaidAmount, // PO Submitted & Approved yang belum dibuat NFP
                    'nfp_submitted' => $nfpSubmittedAmount, // NFP Submitted
                    'nfp_approved' => $nfpApprovedAmount, // NFP Approved (unpaid)
                    'nfp_paid' => $nfpPaidAmount, // NFP Paid
                    'retail_non_food' => $rnfAmount, // Retail Non Food Approved
                    // Keep old fields for backward compatibility
                    'nfp_amount' => $nfpPaidAmount,
                    'pr_unpaid_amount' => $prUnpaidAmount + $poUnpaidAmount + $nfpApprovedAmount,
                    'rnf_amount' => $rnfAmount,
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
