<?php

namespace App\Http\Controllers;

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

        // Build hierarchical structure
        $outletData = $this->buildHierarchicalStructure($results);

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

        // Get all categories with budget information
        $allCategories = $this->getAllCategoriesWithBudget();

        return Inertia::render('OpexReport/Index', [
            'outletData' => $outletData,
            'outlets' => $outlets,
            'categories' => $categories,
            'allCategories' => $allCategories,
            'filters' => $request->only(['date_from', 'date_to', 'outlet_id', 'category_id', 'status']),
            'summary' => $this->getSummaryData($outletData)
        ]);
    }

    private function buildHierarchicalStructure($results)
    {
        $outlets = [];

        foreach ($results as $row) {
            $outletId = $row->outlet_id;
            $categoryId = $row->category_id;
            $poId = $row->po_id;

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

            // Initialize PO if not exists (only if category exists)
            if ($poId && $categoryId && isset($outlets[$outletId]['categories'][$categoryId]) && !isset($outlets[$outletId]['categories'][$categoryId]['purchase_orders'][$poId])) {
                $outlets[$outletId]['categories'][$categoryId]['purchase_orders'][$poId] = [
                    'po_id' => $poId,
                    'po_number' => $row->po_number,
                    'po_date' => $row->po_date,
                    'supplier_name' => $row->supplier_name,
                    'payment_id' => $row->payment_id,
                    'payment_number' => $row->payment_number,
                    'payment_status' => $row->payment_status,
                    'payment_date' => $row->payment_date,
                    'payment_amount' => $row->payment_amount,
                    'items' => [],
                    'total_amount' => 0,
                    'is_paid' => !is_null($row->payment_id)
                ];
            }

            // Add item to PO (only if category exists)
            if ($poId && $categoryId && isset($outlets[$outletId]['categories'][$categoryId]) && $row->po_item_id) {
                $outlets[$outletId]['categories'][$categoryId]['purchase_orders'][$poId]['items'][] = [
                    'po_item_id' => $row->po_item_id,
                    'item_name' => $row->item_name,
                    'quantity' => $row->quantity,
                    'unit' => $row->unit,
                    'price' => $row->price,
                    'total' => $row->po_item_total
                ];

                // Update totals
                $itemTotal = $row->po_item_total;
                $outlets[$outletId]['categories'][$categoryId]['purchase_orders'][$poId]['total_amount'] += $itemTotal;
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

                // Update paid/unpaid amounts
                if ($row->payment_id) {
                    $outlets[$outletId]['categories'][$categoryId]['purchase_orders'][$poId]['paid_amount'] = $row->payment_amount;
                    // Ensure category paid_amount is initialized
                    if (!isset($outlets[$outletId]['categories'][$categoryId]['paid_amount'])) {
                        $outlets[$outletId]['categories'][$categoryId]['paid_amount'] = 0;
                    }
                    $outlets[$outletId]['categories'][$categoryId]['paid_amount'] += $itemTotal;
                    // Ensure outlet paid_amount is initialized
                    if (!isset($outlets[$outletId]['paid_amount'])) {
                        $outlets[$outletId]['paid_amount'] = 0;
                    }
                    $outlets[$outletId]['paid_amount'] += $itemTotal;
                } else {
                    $outlets[$outletId]['categories'][$categoryId]['purchase_orders'][$poId]['unpaid_amount'] = $itemTotal;
                    // Ensure category unpaid_amount is initialized
                    if (!isset($outlets[$outletId]['categories'][$categoryId]['unpaid_amount'])) {
                        $outlets[$outletId]['categories'][$categoryId]['unpaid_amount'] = 0;
                    }
                    $outlets[$outletId]['categories'][$categoryId]['unpaid_amount'] += $itemTotal;
                    // Ensure outlet unpaid_amount is initialized
                    if (!isset($outlets[$outletId]['unpaid_amount'])) {
                        $outlets[$outletId]['unpaid_amount'] = 0;
                    }
                    $outlets[$outletId]['unpaid_amount'] += $itemTotal;
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

    private function getAllCategoriesWithBudget()
    {
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
                'outlets' => []
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

                $outletMap = [];
                foreach ($outletBudgets as $row) {
                    $outletMap[] = [
                        'outlet_id' => $row->outlet_id,
                        'outlet_name' => $row->outlet_name,
                        'budget_limit' => $row->allocated_budget,
                        'paid_amount' => $row->used_budget,
                        'unpaid_amount' => 0, // Will be calculated from transactions
                        'remaining_budget' => max(0, $row->allocated_budget - $row->used_budget)
                    ];
                    
                    // Add to totals
                    $categoryGroup['total_paid_amount'] += $row->used_budget;
                }

                $categoryGroup['outlets'] = $outletMap;
                $categoryGroup['total_remaining_budget'] = max(0, $category->budget_limit - $categoryGroup['total_paid_amount']);
            } else {
                // For GLOBAL budget type, get transaction data
                $transactionData = DB::table('purchase_order_ops as poo')
                    ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
                    ->leftJoin('non_food_payments as nfp', function($join) {
                        $join->on('poo.id', '=', 'nfp.purchase_order_ops_id')
                             ->where('nfp.status', '!=', 'cancelled');
                    })
                    ->where('poo.status', 'approved')
                    ->where('pr.category_id', $category->id)
                    ->select(
                        'o.id_outlet as outlet_id',
                        'o.nama_outlet as outlet_name',
                        'poi.total as po_item_total',
                        'nfp.id as payment_id',
                        'nfp.amount as payment_amount'
                    )
                    ->get();

                $outletMap = [];
                foreach ($transactionData as $row) {
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
                    
                    if ($row->payment_id) {
                        $outletMap[$outletId]['paid_amount'] += $itemTotal;
                        $categoryGroup['total_paid_amount'] += $itemTotal;
                    } else {
                        $outletMap[$outletId]['unpaid_amount'] += $itemTotal;
                        $categoryGroup['total_unpaid_amount'] += $itemTotal;
                    }
                }

                // Calculate remaining budget for each outlet
                foreach ($outletMap as &$outlet) {
                    $outlet['remaining_budget'] = max(0, $outlet['budget_limit'] - $outlet['paid_amount']);
                }

                $categoryGroup['outlets'] = array_values($outletMap);
                $categoryGroup['total_remaining_budget'] = max(0, $category->budget_limit - $categoryGroup['total_paid_amount']);
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
                    
                    // Calculate remaining budget
                    $category['remaining_budget'] = $category['budget_limit'] - $category['paid_amount'];
                    
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
