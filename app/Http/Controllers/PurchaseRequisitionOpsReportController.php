<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\PurchaseRequisitionCategory;
use App\Models\Outlet;
use App\Models\Divisi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PurchaseRequisitionOpsReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'date_from' => $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'status' => $request->input('status', 'all'),
            'category_id' => $request->input('category_id', ''),
            'outlet_id' => $request->input('outlet_id', ''),
            'division_id' => $request->input('division_id', ''),
            'search' => $request->input('search', ''),
        ];

        // Get summary metrics
        $summary = $this->getSummaryMetrics($filters);
        
        // Get status distribution
        $statusDistribution = $this->getStatusDistribution($filters);
        
        // Get trend data (daily)
        $trendData = $this->getTrendData($filters);
        
        // Get category analysis with pagination
        $categoryAnalysis = $this->getCategoryAnalysis($filters, $request->input('category_per_page', 10), $request->input('category_search', ''), $request->input('category_page', 1));
        
        // Get outlet analysis with pagination
        $outletAnalysis = $this->getOutletAnalysis($filters, $request->input('outlet_per_page', 10), $request->input('outlet_search', ''), $request->input('outlet_page', 1));
        
        // Get division analysis
        $divisionAnalysis = $this->getDivisionAnalysis($filters);
        
        // Get detailed PR data
        $purchaseRequisitions = $this->getPurchaseRequisitions($filters, $request->input('per_page', 15));
        
        // Get item analysis with pagination
        $itemAnalysis = $this->getItemAnalysis($filters, $request->input('item_per_page', 15), $request->input('item_search', ''), $request->input('item_page', 1));
        
        // Get PR per outlet data
        $prPerOutlet = $this->getPRPerOutlet($filters);
        
        // Get PR per category data
        $prPerCategory = $this->getPRPerCategory($filters);
        
        // Get categories for filter
        $categories = PurchaseRequisitionCategory::select('id', 'name')
            ->orderBy('name')
            ->get();
        
        // Get outlets for filter
        $outlets = Outlet::select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();
        
        // Get divisions for filter
        $divisions = Divisi::select('id', 'nama_divisi as name')
            ->orderBy('nama_divisi')
            ->get();

        return Inertia::render('PurchaseRequisition/Report', [
            'summary' => $summary,
            'statusDistribution' => $statusDistribution,
            'trendData' => $trendData,
            'categoryAnalysis' => $categoryAnalysis,
            'outletAnalysis' => $outletAnalysis,
            'divisionAnalysis' => $divisionAnalysis,
            'itemAnalysis' => $itemAnalysis,
            'purchaseRequisitions' => $purchaseRequisitions,
            'prPerOutlet' => $prPerOutlet,
            'prPerCategory' => $prPerCategory,
            'categories' => $categories,
            'outlets' => $outlets,
            'divisions' => $divisions,
            'filters' => array_merge($filters, [
                'category_search' => $request->input('category_search', ''),
                'category_per_page' => $request->input('category_per_page', 10),
                'outlet_search' => $request->input('outlet_search', ''),
                'outlet_per_page' => $request->input('outlet_per_page', 10),
                'item_search' => $request->input('item_search', ''),
                'item_per_page' => $request->input('item_per_page', 15),
                'per_page' => $request->input('per_page', 15),
            ]),
        ]);
    }

    private function getSummaryMetrics($filters)
    {
        // Base query without status filter (for counting all statuses)
        // Include pr_ops and purchase_payment, exclude kasbon and travel_application
        $baseQueryWithoutStatus = DB::table('purchase_requisitions as pr')
            ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        // Apply category filter (from items)
        if ($filters['category_id']) {
            $baseQueryWithoutStatus->whereExists(function($q) use ($filters) {
                $q->select(DB::raw(1))
                  ->from('purchase_requisition_items as pri')
                  ->whereColumn('pri.purchase_requisition_id', 'pr.id')
                  ->where('pri.category_id', $filters['category_id']);
            });
        }

        // Apply outlet filter (from items)
        if ($filters['outlet_id']) {
            $baseQueryWithoutStatus->whereExists(function($q) use ($filters) {
                $q->select(DB::raw(1))
                  ->from('purchase_requisition_items as pri')
                  ->whereColumn('pri.purchase_requisition_id', 'pr.id')
                  ->where('pri.outlet_id', $filters['outlet_id']);
            });
        }

        // Apply division filter
        if ($filters['division_id']) {
            $baseQueryWithoutStatus->where('pr.division_id', $filters['division_id']);
        }

        // Apply search filter
        if ($filters['search']) {
            $baseQueryWithoutStatus->where(function($q) use ($filters) {
                $q->where('pr.pr_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('pr.description', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Query with status filter (for filtered results)
        $query = clone $baseQueryWithoutStatus;
        
        // Apply status filter
        if ($filters['status'] !== 'all') {
            $query->where('pr.status', $filters['status']);
        }

        $baseQuery = clone $query;

        // Get total amount from items
        $totalAmount = DB::table('purchase_requisition_items as pri')
            ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
            ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $totalAmount->where('pr.status', $filters['status']);
        }

        if ($filters['category_id']) {
            $totalAmount->where('pri.category_id', $filters['category_id']);
        }

        if ($filters['outlet_id']) {
            $totalAmount->where('pri.outlet_id', $filters['outlet_id']);
        }

        if ($filters['division_id']) {
            $totalAmount->where('pr.division_id', $filters['division_id']);
        }

        if ($filters['search']) {
            $totalAmount->where(function($q) use ($filters) {
                $q->where('pr.pr_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('pr.description', 'like', '%' . $filters['search'] . '%');
            });
        }

        $totalAmountValue = $totalAmount->sum('pri.subtotal') ?? 0;

        // Get retail non food total amount (only approved)
        $retailNonFoodQuery = DB::table('retail_non_food as rnf')
            ->where('rnf.status', 'approved')
            ->whereBetween('rnf.transaction_date', [$filters['date_from'], $filters['date_to']]);

        if ($filters['category_id']) {
            $retailNonFoodQuery->where('rnf.category_budget_id', $filters['category_id']);
        }

        if ($filters['outlet_id']) {
            $retailNonFoodQuery->where('rnf.outlet_id', $filters['outlet_id']);
        }

        $retailNonFoodTotal = $retailNonFoodQuery->sum('rnf.total_amount') ?? 0;

        // Combine total value
        $combinedTotalValue = $totalAmountValue + $retailNonFoodTotal;

        return [
            'total_pr' => $baseQuery->count(),
            'total_value' => $combinedTotalValue,
            'pr_value' => $totalAmountValue,
            'retail_non_food_value' => $retailNonFoodTotal,
            'avg_pr_value' => $baseQuery->count() > 0 ? $combinedTotalValue / $baseQuery->count() : 0,
            'approved_count' => (clone $baseQueryWithoutStatus)->whereRaw('UPPER(pr.status) = ?', ['APPROVED'])->count(),
            'submitted_count' => (clone $baseQueryWithoutStatus)->whereRaw('UPPER(pr.status) = ?', ['SUBMITTED'])->count(),
            'draft_count' => (clone $baseQueryWithoutStatus)->whereRaw('UPPER(pr.status) = ?', ['DRAFT'])->count(),
            'rejected_count' => (clone $baseQueryWithoutStatus)->whereRaw('UPPER(pr.status) = ?', ['REJECTED'])->count(),
        ];
    }

    private function getStatusDistribution($filters)
    {
        $query = DB::table('purchase_requisitions as pr')
            ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['category_id']) {
            $query->whereExists(function($q) use ($filters) {
                $q->select(DB::raw(1))
                  ->from('purchase_requisition_items as pri')
                  ->whereColumn('pri.purchase_requisition_id', 'pr.id')
                  ->where('pri.category_id', $filters['category_id']);
            });
        }

        if ($filters['outlet_id']) {
            $query->whereExists(function($q) use ($filters) {
                $q->select(DB::raw(1))
                  ->from('purchase_requisition_items as pri')
                  ->whereColumn('pri.purchase_requisition_id', 'pr.id')
                  ->where('pri.outlet_id', $filters['outlet_id']);
            });
        }

        if ($filters['division_id']) {
            $query->where('pr.division_id', $filters['division_id']);
        }

        // Get status distribution with total value from items
        $statusData = $query->select('pr.status', DB::raw('COUNT(*) as count'))
            ->groupBy('pr.status')
            ->get();

        // Calculate total value per status from items
        $statusWithValue = $statusData->map(function($item) use ($filters) {
            $valueQuery = DB::table('purchase_requisition_items as pri')
                ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
                ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
                ->where('pr.status', $item->status)
                ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

            if ($filters['category_id']) {
                $valueQuery->where('pri.category_id', $filters['category_id']);
            }

            if ($filters['outlet_id']) {
                $valueQuery->where('pri.outlet_id', $filters['outlet_id']);
            }

            if ($filters['division_id']) {
                $valueQuery->where('pr.division_id', $filters['division_id']);
            }

            $totalValue = $valueQuery->sum('pri.subtotal') ?? 0;

            return [
                'status' => $item->status,
                'count' => $item->count,
                'total_value' => (float)$totalValue,
            ];
        });

        return $statusWithValue;
    }

    private function getTrendData($filters)
    {
        $query = DB::table('purchase_requisitions as pr')
            ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $query->where('pr.status', $filters['status']);
        }

        if ($filters['category_id']) {
            $query->whereExists(function($q) use ($filters) {
                $q->select(DB::raw(1))
                  ->from('purchase_requisition_items as pri')
                  ->whereColumn('pri.purchase_requisition_id', 'pr.id')
                  ->where('pri.category_id', $filters['category_id']);
            });
        }

        if ($filters['outlet_id']) {
            $query->whereExists(function($q) use ($filters) {
                $q->select(DB::raw(1))
                  ->from('purchase_requisition_items as pri')
                  ->whereColumn('pri.purchase_requisition_id', 'pr.id')
                  ->where('pri.outlet_id', $filters['outlet_id']);
            });
        }

        if ($filters['division_id']) {
            $query->where('pr.division_id', $filters['division_id']);
        }

        $trendData = $query->select(
                DB::raw('DATE(pr.created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DATE(pr.created_at)'))
            ->orderBy('date')
            ->get();

        // Calculate total value per date from items
        return $trendData->map(function($item) use ($filters) {
            // Get PR value for this date
            $prValueQuery = DB::table('purchase_requisition_items as pri')
                ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
                ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
                ->whereDate('pr.created_at', $item->date);

            if ($filters['status'] !== 'all') {
                $prValueQuery->where('pr.status', $filters['status']);
            }

            if ($filters['category_id']) {
                $prValueQuery->where('pri.category_id', $filters['category_id']);
            }

            if ($filters['outlet_id']) {
                $prValueQuery->where('pri.outlet_id', $filters['outlet_id']);
            }

            if ($filters['division_id']) {
                $prValueQuery->where('pr.division_id', $filters['division_id']);
            }

            $prValue = $prValueQuery->sum('pri.subtotal') ?? 0;

            // Get retail non food value for this date
            $rnfQuery = DB::table('retail_non_food as rnf')
                ->where('rnf.status', 'approved')
                ->whereDate('rnf.transaction_date', $item->date);

            if ($filters['category_id']) {
                $rnfQuery->where('rnf.category_budget_id', $filters['category_id']);
            }

            if ($filters['outlet_id']) {
                $rnfQuery->where('rnf.outlet_id', $filters['outlet_id']);
            }

            $rnfValue = $rnfQuery->sum('rnf.total_amount') ?? 0;

            return [
                'date' => $item->date,
                'count' => $item->count,
                'pr_value' => (float)$prValue,
                'rnf_value' => (float)$rnfValue,
                'total_value' => (float)($prValue + $rnfValue),
            ];
        });
    }

    private function getCategoryAnalysis($filters, $perPage = 10, $search = '', $page = 1)
    {
        $query = DB::table('purchase_requisition_items as pri')
            ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
            ->join('purchase_requisition_categories as cat', 'pri.category_id', '=', 'cat.id')
            ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $query->where('pr.status', $filters['status']);
        }

        if ($filters['outlet_id']) {
            $query->where('pri.outlet_id', $filters['outlet_id']);
        }

        if ($filters['division_id']) {
            $query->where('pr.division_id', $filters['division_id']);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('cat.name', 'like', '%' . $search . '%')
                  ->orWhere('cat.division', 'like', '%' . $search . '%');
            });
        }

        // Count distinct categories
        $countQuery = clone $query;
        $total = $countQuery->distinct('cat.id')->count('cat.id');

        $categories = $query
            ->select(
                'cat.id as category_id',
                'cat.name as category_name',
                'cat.division as division_name',
                'cat.budget_limit',
                DB::raw('COUNT(DISTINCT pr.id) as pr_count'),
                DB::raw('SUM(pri.subtotal) as total_value'),
                DB::raw('AVG(pri.subtotal) as avg_value')
            )
            ->groupBy('cat.id', 'cat.name', 'cat.division', 'cat.budget_limit')
            ->orderByDesc('total_value')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function($item) use ($filters) {
                // Get retail non food count and value for this category
                $rnfQuery = DB::table('retail_non_food as rnf')
                    ->where('rnf.status', 'approved')
                    ->where('rnf.category_budget_id', $item->category_id)
                    ->whereBetween('rnf.transaction_date', [$filters['date_from'], $filters['date_to']]);

                if ($filters['outlet_id']) {
                    $rnfQuery->where('rnf.outlet_id', $filters['outlet_id']);
                }

                if ($filters['division_id']) {
                    // Retail non food doesn't have division, skip
                }

                $rnfCount = $rnfQuery->count();
                $rnfValue = $rnfQuery->sum('rnf.total_amount') ?? 0;
                $totalValue = (float)($item->total_value + $rnfValue);
                $budgetLimit = $item->budget_limit ? (float)$item->budget_limit : null;
                $remainingBudget = $budgetLimit ? ($budgetLimit - $totalValue) : null;

                return [
                    'category_id' => $item->category_id,
                    'category_name' => $item->category_name,
                    'division_name' => $item->division_name ?? '',
                    'display_name' => ($item->division_name ? $item->division_name . ' - ' : '') . $item->category_name,
                    'budget_limit' => $budgetLimit,
                    'remaining_budget' => $remainingBudget,
                    'pr_count' => $item->pr_count,
                    'pr_value' => (float)$item->total_value,
                    'rnf_count' => $rnfCount,
                    'rnf_value' => (float)$rnfValue,
                    'total_value' => $totalValue,
                    'avg_value' => (float)$item->avg_value,
                ];
            });

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $categories,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'category_page']
        );
    }

    private function getOutletAnalysis($filters, $perPage = 10, $search = '', $page = 1)
    {
        $query = DB::table('purchase_requisition_items as pri')
            ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
            ->join('tbl_data_outlet as o', 'pri.outlet_id', '=', 'o.id_outlet')
            ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $query->where('pr.status', $filters['status']);
        }

        if ($filters['category_id']) {
            $query->where('pri.category_id', $filters['category_id']);
        }

        if ($filters['division_id']) {
            $query->where('pr.division_id', $filters['division_id']);
        }

        if ($search) {
            $query->where('o.nama_outlet', 'like', '%' . $search . '%');
        }

        $total = $query->distinct('o.id_outlet')->count('o.id_outlet');

        $outlets = $query->select(
                'o.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
                DB::raw('COUNT(DISTINCT pr.id) as pr_count'),
                DB::raw('SUM(pri.subtotal) as total_value'),
                DB::raw('AVG(pri.subtotal) as avg_value')
            )
            ->groupBy('o.id_outlet', 'o.nama_outlet')
            ->orderByDesc('total_value')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function($item) use ($filters) {
                // Get retail non food value for this outlet
                $rnfQuery = DB::table('retail_non_food as rnf')
                    ->where('rnf.status', 'approved')
                    ->where('rnf.outlet_id', $item->outlet_id)
                    ->whereBetween('rnf.transaction_date', [$filters['date_from'], $filters['date_to']]);

                if ($filters['category_id']) {
                    $rnfQuery->where('rnf.category_budget_id', $filters['category_id']);
                }

                $rnfCount = $rnfQuery->count();
                $rnfValue = $rnfQuery->sum('rnf.total_amount') ?? 0;

                return [
                    'outlet_id' => $item->outlet_id,
                    'outlet_name' => $item->outlet_name,
                    'pr_count' => $item->pr_count,
                    'pr_value' => (float)$item->total_value,
                    'rnf_count' => $rnfCount,
                    'rnf_value' => (float)$rnfValue,
                    'total_value' => (float)($item->total_value + $rnfValue),
                    'avg_value' => (float)$item->avg_value,
                ];
            });

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $outlets,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'outlet_page']
        );
    }

    private function getDivisionAnalysis($filters)
    {
        $query = DB::table('purchase_requisitions as pr')
            ->leftJoin('tbl_data_divisi', 'pr.division_id', '=', 'tbl_data_divisi.id')
            ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $query->where('pr.status', $filters['status']);
        }

        if ($filters['category_id']) {
            $query->whereExists(function($q) use ($filters) {
                $q->select(DB::raw(1))
                  ->from('purchase_requisition_items as pri')
                  ->whereColumn('pri.purchase_requisition_id', 'pr.id')
                  ->where('pri.category_id', $filters['category_id']);
            });
        }

        if ($filters['outlet_id']) {
            $query->whereExists(function($q) use ($filters) {
                $q->select(DB::raw(1))
                  ->from('purchase_requisition_items as pri')
                  ->whereColumn('pri.purchase_requisition_id', 'pr.id')
                  ->where('pri.outlet_id', $filters['outlet_id']);
            });
        }

        if ($filters['division_id']) {
            $query->where('pr.division_id', $filters['division_id']);
        }

        $divisions = $query->select(
                'tbl_data_divisi.id as division_id',
                'tbl_data_divisi.nama_divisi as division_name',
                DB::raw('COUNT(DISTINCT pr.id) as pr_count')
            )
            ->groupBy('tbl_data_divisi.id', 'tbl_data_divisi.nama_divisi')
            ->get();

        // Calculate total value per division from items
        return $divisions->map(function($item) use ($filters) {
            $valueQuery = DB::table('purchase_requisition_items as pri')
                ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
                ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
                ->where('pr.division_id', $item->division_id)
                ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

            if ($filters['status'] !== 'all') {
                $valueQuery->where('pr.status', $filters['status']);
            }

            if ($filters['category_id']) {
                $valueQuery->where('pri.category_id', $filters['category_id']);
            }

            if ($filters['outlet_id']) {
                $valueQuery->where('pri.outlet_id', $filters['outlet_id']);
            }

            $totalValue = $valueQuery->sum('pri.subtotal') ?? 0;

            return [
                'division_id' => $item->division_id,
                'division_name' => $item->division_name ?? 'No Division',
                'pr_count' => $item->pr_count,
                'total_value' => (float)$totalValue,
            ];
        })->sortByDesc('total_value')->values();
    }

    private function getPurchaseRequisitions($filters, $perPage = 15)
    {
        $query = PurchaseRequisition::with(['division', 'creator', 'items.category', 'items.outlet'])
            ->whereIn('mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['category_id']) {
            $query->whereHas('items', function($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        if ($filters['outlet_id']) {
            $query->whereHas('items', function($q) use ($filters) {
                $q->where('outlet_id', $filters['outlet_id']);
            });
        }

        if ($filters['division_id']) {
            $query->where('division_id', $filters['division_id']);
        }

        if ($filters['search']) {
            $query->where(function($q) use ($filters) {
                $q->where('pr_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    private function getItemAnalysis($filters, $perPage = 15, $search = '', $page = 1)
    {
        $query = DB::table('purchase_requisition_items as pri')
            ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
            ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $query->where('pr.status', $filters['status']);
        }

        if ($filters['category_id']) {
            $query->where('pri.category_id', $filters['category_id']);
        }

        if ($filters['outlet_id']) {
            $query->where('pri.outlet_id', $filters['outlet_id']);
        }

        if ($filters['division_id']) {
            $query->where('pr.division_id', $filters['division_id']);
        }

        if ($search) {
            $query->where('pri.item_name', 'like', '%' . $search . '%');
        }

        $total = $query->count();

        $items = $query->select(
                'pri.item_name',
                DB::raw('SUM(pri.qty) as total_qty'),
                DB::raw('SUM(pri.subtotal) as total_value'),
                DB::raw('AVG(pri.unit_price) as avg_price'),
                DB::raw('COUNT(DISTINCT pr.id) as pr_count')
            )
            ->groupBy('pri.item_name')
            ->orderByDesc('total_value')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function($item) {
                return [
                    'item_name' => $item->item_name,
                    'total_qty' => (float)$item->total_qty,
                    'total_value' => (float)$item->total_value,
                    'avg_price' => (float)$item->avg_price,
                    'pr_count' => $item->pr_count,
                ];
            });

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'item_page']
        );
    }

    private function getPRPerOutlet($filters)
    {
        $query = DB::table('purchase_requisition_items as pri')
            ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
            ->join('tbl_data_outlet as o', 'pri.outlet_id', '=', 'o.id_outlet')
            ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $query->where('pr.status', $filters['status']);
        }

        if ($filters['category_id']) {
            $query->where('pri.category_id', $filters['category_id']);
        }

        if ($filters['division_id']) {
            $query->where('pr.division_id', $filters['division_id']);
        }

        return $query->select(
                'o.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
                DB::raw('COUNT(DISTINCT pr.id) as pr_count'),
                DB::raw('SUM(pri.subtotal) as total_value')
            )
            ->groupBy('o.id_outlet', 'o.nama_outlet')
            ->orderByDesc('total_value')
            ->get()
            ->map(function($item) use ($filters) {
                // Get retail non food value for this outlet
                $rnfQuery = DB::table('retail_non_food as rnf')
                    ->where('rnf.status', 'approved')
                    ->where('rnf.outlet_id', $item->outlet_id)
                    ->whereBetween('rnf.transaction_date', [$filters['date_from'], $filters['date_to']]);

                if ($filters['category_id']) {
                    $rnfQuery->where('rnf.category_budget_id', $filters['category_id']);
                }

                $rnfValue = $rnfQuery->sum('rnf.total_amount') ?? 0;

                return [
                    'outlet_id' => $item->outlet_id,
                    'outlet_name' => $item->outlet_name,
                    'pr_count' => $item->pr_count,
                    'total_value' => (float)($item->total_value + $rnfValue),
                ];
            });
    }

    private function getPRPerCategory($filters)
    {
        $query = DB::table('purchase_requisition_items as pri')
            ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
            ->join('purchase_requisition_categories as cat', 'pri.category_id', '=', 'cat.id')
            ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $query->where('pr.status', $filters['status']);
        }

        if ($filters['outlet_id']) {
            $query->where('pri.outlet_id', $filters['outlet_id']);
        }

        if ($filters['division_id']) {
            $query->where('pr.division_id', $filters['division_id']);
        }

        return $query->select(
                'cat.id as category_id',
                'cat.name as category_name',
                DB::raw('COUNT(DISTINCT pr.id) as pr_count'),
                DB::raw('SUM(pri.subtotal) as total_value')
            )
            ->groupBy('cat.id', 'cat.name')
            ->orderByDesc('total_value')
            ->get()
            ->map(function($item) use ($filters) {
                // Get retail non food value for this category
                $rnfQuery = DB::table('retail_non_food as rnf')
                    ->where('rnf.status', 'approved')
                    ->where('rnf.category_budget_id', $item->category_id)
                    ->whereBetween('rnf.transaction_date', [$filters['date_from'], $filters['date_to']]);

                if ($filters['outlet_id']) {
                    $rnfQuery->where('rnf.outlet_id', $filters['outlet_id']);
                }

                $rnfValue = $rnfQuery->sum('rnf.total_amount') ?? 0;

                return [
                    'category_id' => $item->category_id,
                    'category_name' => $item->category_name,
                    'pr_count' => $item->pr_count,
                    'total_value' => (float)($item->total_value + $rnfValue),
                ];
            });
    }

    public function exportExcel(Request $request)
    {
        $filters = [
            'date_from' => $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'status' => $request->input('status', 'all'),
            'category_id' => $request->input('category_id', ''),
            'outlet_id' => $request->input('outlet_id', ''),
            'division_id' => $request->input('division_id', ''),
            'search' => $request->input('search', ''),
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PR Ops Report');

        // Header
        $sheet->setCellValue('A1', 'Purchase Requisition Ops Report');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Period: ' . $filters['date_from'] . ' to ' . $filters['date_to']);
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Column headers
        $headers = ['PR Number', 'Date', 'Status', 'Division', 'Total Items', 'Total Amount'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '4', $header);
            $sheet->getStyle($col . '4')->getFont()->setBold(true);
            $sheet->getStyle($col . '4')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . '4')->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Get data
        $query = PurchaseRequisition::with(['division', 'items'])
            ->whereIn('mode', ['pr_ops', 'purchase_payment'])
            ->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['category_id']) {
            $query->whereHas('items', function($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        if ($filters['outlet_id']) {
            $query->whereHas('items', function($q) use ($filters) {
                $q->where('outlet_id', $filters['outlet_id']);
            });
        }

        if ($filters['division_id']) {
            $query->where('division_id', $filters['division_id']);
        }

        if ($filters['search']) {
            $query->where(function($q) use ($filters) {
                $q->where('pr_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        $prs = $query->orderBy('created_at', 'desc')->get();

        $row = 5;
        foreach ($prs as $pr) {
            $totalAmount = $pr->items->sum('subtotal');
            
            $sheet->setCellValue('A' . $row, $pr->pr_number);
            $sheet->setCellValue('B' . $row, $pr->created_at->format('Y-m-d'));
            $sheet->setCellValue('C' . $row, $pr->status);
            $sheet->setCellValue('D' . $row, $pr->division->nama_divisi ?? '');
            $sheet->setCellValue('E' . $row, $pr->items->count());
            $sheet->setCellValue('F' . $row, $totalAmount);
            
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set borders
        $sheet->getStyle('A4:F' . ($row - 1))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        $filename = 'PR_Ops_Report_' . date('Y-m-d_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }

    public function getCategoryDetail(Request $request, $categoryId)
    {
        $filters = [
            'date_from' => $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'status' => $request->input('status', 'all'),
        ];

        // Get category info
        $category = PurchaseRequisitionCategory::find($categoryId);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Category not found'], 404);
        }

        // Get PR Ops for this category
        $prQuery = PurchaseRequisition::with(['division', 'creator', 'approvalFlows.approver', 'items' => function($q) use ($categoryId) {
            $q->where('category_id', $categoryId);
        }])
            ->whereIn('mode', ['pr_ops', 'purchase_payment'])
            ->whereHas('items', function($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            })
            ->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $prQuery->where('status', $filters['status']);
        }

        // Apply search filter if provided
        $search = $request->input('search', '');
        if ($search) {
            $prQuery->where(function($q) use ($search) {
                $q->where('pr_number', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // Pagination
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $prs = $prQuery->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        $prs->getCollection()->transform(function($pr) use ($categoryId) {
            $categoryItems = $pr->items->where('category_id', $categoryId);
            
            // Get latest approved approval flow
            $latestApproved = $pr->approvalFlows->where('status', 'APPROVED')->sortByDesc('approved_at')->first();
            
            return [
                'id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'date' => $pr->created_at->format('Y-m-d'),
                'status' => $pr->status,
                'division' => $pr->division->nama_divisi ?? 'No Division',
                'total_amount' => $categoryItems->sum('subtotal'),
                'creator' => $pr->creator ? [
                    'id' => $pr->creator->id,
                    'name' => $pr->creator->nama_lengkap,
                    'avatar' => $pr->creator->avatar,
                ] : null,
                'approval' => $latestApproved ? [
                    'status' => $latestApproved->status,
                    'approved_at' => $latestApproved->approved_at ? $latestApproved->approved_at->format('Y-m-d H:i:s') : null,
                    'approver' => $latestApproved->approver ? [
                        'id' => $latestApproved->approver->id,
                        'name' => $latestApproved->approver->nama_lengkap,
                        'avatar' => $latestApproved->approver->avatar,
                    ] : null,
                ] : null,
                'items' => $categoryItems->map(function($item) {
                    return [
                        'item_name' => $item->item_name,
                        'qty' => $item->qty,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $item->subtotal,
                    ];
                }),
            ];
        });

        // Get Retail Non Food for this category
        $rnfQuery = DB::table('retail_non_food as rnf')
            ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('suppliers as s', 'rnf.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'rnf.created_by', '=', 'u.id')
            ->where('rnf.status', 'approved')
            ->where('rnf.category_budget_id', $categoryId)
            ->whereBetween('rnf.transaction_date', [$filters['date_from'], $filters['date_to']])
            ->select(
                'rnf.id',
                'rnf.retail_number',
                'rnf.transaction_date',
                'rnf.total_amount',
                'o.nama_outlet as outlet_name',
                's.name as supplier_name',
                'rnf.notes',
                'u.id as creator_id',
                'u.nama_lengkap as creator_name',
                'u.avatar as creator_avatar'
            )
            ->orderBy('rnf.transaction_date', 'desc')
            ->get();

        $rnfIds = $rnfQuery->pluck('id')->toArray();
        
        // Get RNF items
        $rnfItems = [];
        if (!empty($rnfIds)) {
            $itemsQuery = DB::table('retail_non_food_items')
                ->whereIn('retail_non_food_id', $rnfIds)
                ->select('retail_non_food_id', 'item_name', 'qty', 'unit', 'price', 'subtotal')
                ->orderBy('retail_non_food_id')
                ->orderBy('item_name')
                ->get();
            
            foreach ($itemsQuery as $item) {
                if (!isset($rnfItems[$item->retail_non_food_id])) {
                    $rnfItems[$item->retail_non_food_id] = [];
                }
                $rnfItems[$item->retail_non_food_id][] = [
                    'item_name' => $item->item_name,
                    'qty' => (float)$item->qty,
                    'unit' => $item->unit,
                    'price' => (float)$item->price,
                    'subtotal' => (float)$item->subtotal,
                ];
            }
        }

        $rnfs = $rnfQuery->map(function($rnf) use ($rnfItems) {
            return [
                'id' => $rnf->id,
                'retail_number' => $rnf->retail_number,
                'date' => $rnf->transaction_date,
                'creator' => $rnf->creator_id ? [
                    'id' => $rnf->creator_id,
                    'name' => $rnf->creator_name,
                    'avatar' => $rnf->creator_avatar,
                ] : null,
                'outlet_name' => $rnf->outlet_name ?? 'N/A',
                'supplier_name' => $rnf->supplier_name ?? 'N/A',
                'total_amount' => (float)$rnf->total_amount,
                'notes' => $rnf->notes,
                'items' => $rnfItems[$rnf->id] ?? [],
            ];
        });

        // Calculate totals - need to get all PRs for total calculation, not just paginated ones
        $allPRsQuery = PurchaseRequisition::with(['items' => function($q) use ($categoryId) {
            $q->where('category_id', $categoryId);
        }])
            ->whereIn('mode', ['pr_ops', 'purchase_payment'])
            ->whereHas('items', function($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            })
            ->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $allPRsQuery->where('status', $filters['status']);
        }

        if ($search) {
            $allPRsQuery->where(function($q) use ($search) {
                $q->where('pr_number', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $allPRs = $allPRsQuery->get();
        $prTotal = $allPRs->sum(function($pr) use ($categoryId) {
            return $pr->items->where('category_id', $categoryId)->sum('subtotal');
        });
        
        $rnfTotal = $rnfs->sum('total_amount');

        return response()->json([
            'success' => true,
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
            'summary' => [
                'pr_count' => $prs->total(),
                'pr_total' => $prTotal,
                'rnf_count' => $rnfs->count(),
                'rnf_total' => $rnfTotal,
                'grand_total' => $prTotal + $rnfTotal,
            ],
            'purchase_requisitions' => [
                'data' => $prs->items(),
                'current_page' => $prs->currentPage(),
                'last_page' => $prs->lastPage(),
                'per_page' => $prs->perPage(),
                'total' => $prs->total(),
            ],
            'retail_non_food' => $rnfs,
        ]);
    }

    public function getOutletDetail(Request $request, $outletId)
    {
        $filters = [
            'date_from' => $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'status' => $request->input('status', 'all'),
        ];

        // Get outlet info
        $outlet = Outlet::find($outletId);
        if (!$outlet) {
            return response()->json(['success' => false, 'message' => 'Outlet not found'], 404);
        }

        // Get PR Ops grouped by category for this outlet
        $prQuery = DB::table('purchase_requisition_items as pri')
            ->join('purchase_requisitions as pr', 'pri.purchase_requisition_id', '=', 'pr.id')
            ->join('purchase_requisition_categories as cat', 'pri.category_id', '=', 'cat.id')
            ->leftJoin('users as u', 'pr.created_by', '=', 'u.id')
            ->whereIn('pr.mode', ['pr_ops', 'purchase_payment'])
            ->where('pri.outlet_id', $outletId)
            ->whereBetween('pr.created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);

        if ($filters['status'] !== 'all') {
            $prQuery->where('pr.status', $filters['status']);
        }

        $prsByCategory = $prQuery->select(
                'cat.id as category_id',
                'cat.name as category_name',
                'cat.division as division_name',
                'pr.id as pr_id',
                'pr.pr_number',
                'pr.created_at as pr_date',
                'pr.status as pr_status',
                'u.id as creator_id',
                'u.nama_lengkap as creator_name',
                'u.avatar as creator_avatar',
                'pri.item_name',
                'pri.qty',
                'pri.unit_price',
                'pri.subtotal'
            )
            ->orderBy('cat.division')
            ->orderBy('cat.name')
            ->orderBy('pr.created_at', 'desc')
            ->get()
            ->groupBy('category_id')
            ->map(function($items, $categoryId) {
                $firstItem = $items->first();
                $prs = $items->groupBy('pr_id')->map(function($prItems, $prId) {
                    $firstPrItem = $prItems->first();
                    
                    // Get latest approved approval flow for this PR
                    $latestApproved = DB::table('purchase_requisition_approval_flows as praf')
                        ->leftJoin('users as approver', 'praf.approver_id', '=', 'approver.id')
                        ->where('praf.purchase_requisition_id', $prId)
                        ->where('praf.status', 'APPROVED')
                        ->orderBy('praf.approved_at', 'desc')
                        ->select(
                            'praf.status',
                            'praf.approved_at',
                            'approver.id as approver_id',
                            'approver.nama_lengkap as approver_name',
                            'approver.avatar as approver_avatar'
                        )
                        ->first();
                    
                    return [
                        'id' => $prId,
                        'pr_number' => $firstPrItem->pr_number,
                        'date' => $firstPrItem->pr_date,
                        'status' => $firstPrItem->pr_status,
                        'total_amount' => $prItems->sum('subtotal'),
                        'creator' => $firstPrItem->creator_id ? [
                            'id' => $firstPrItem->creator_id,
                            'name' => $firstPrItem->creator_name,
                            'avatar' => $firstPrItem->creator_avatar,
                        ] : null,
                        'approval' => $latestApproved ? [
                            'status' => $latestApproved->status,
                            'approved_at' => $latestApproved->approved_at ? date('Y-m-d H:i:s', strtotime($latestApproved->approved_at)) : null,
                            'approver' => $latestApproved->approver_id ? [
                                'id' => $latestApproved->approver_id,
                                'name' => $latestApproved->approver_name,
                                'avatar' => $latestApproved->approver_avatar,
                            ] : null,
                        ] : null,
                        'items' => $prItems->map(function($item) {
                            return [
                                'item_name' => $item->item_name,
                                'qty' => $item->qty,
                                'unit_price' => $item->unit_price,
                                'subtotal' => $item->subtotal,
                            ];
                        }),
                    ];
                })->values();

                return [
                    'category_id' => $categoryId,
                    'category_name' => $firstItem->category_name,
                    'division_name' => $firstItem->division_name ?? '',
                    'display_name' => ($firstItem->division_name ? $firstItem->division_name . ' - ' : '') . $firstItem->category_name,
                    'pr_count' => $prs->count(),
                    'pr_total' => $prs->sum('total_amount'),
                    'purchase_requisitions' => $prs,
                ];
            })->values();

        // Get Retail Non Food grouped by category for this outlet
        $rnfQuery = DB::table('retail_non_food as rnf')
            ->join('purchase_requisition_categories as cat', 'rnf.category_budget_id', '=', 'cat.id')
            ->leftJoin('suppliers as s', 'rnf.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'rnf.created_by', '=', 'u.id')
            ->where('rnf.status', 'approved')
            ->where('rnf.outlet_id', $outletId)
            ->whereBetween('rnf.transaction_date', [$filters['date_from'], $filters['date_to']])
            ->select(
                'cat.id as category_id',
                'cat.name as category_name',
                'cat.division as division_name',
                'rnf.id',
                'rnf.retail_number',
                'rnf.transaction_date',
                'rnf.total_amount',
                's.name as supplier_name',
                'rnf.notes',
                'u.id as creator_id',
                'u.nama_lengkap as creator_name',
                'u.avatar as creator_avatar'
            )
            ->orderBy('cat.division')
            ->orderBy('cat.name')
            ->orderBy('rnf.transaction_date', 'desc')
            ->get();

        $rnfIds = $rnfQuery->pluck('id')->toArray();
        
        // Get RNF items
        $rnfItems = [];
        if (!empty($rnfIds)) {
            $itemsQuery = DB::table('retail_non_food_items')
                ->whereIn('retail_non_food_id', $rnfIds)
                ->select('retail_non_food_id', 'item_name', 'qty', 'unit', 'price', 'subtotal')
                ->orderBy('retail_non_food_id')
                ->orderBy('item_name')
                ->get();
            
            foreach ($itemsQuery as $item) {
                if (!isset($rnfItems[$item->retail_non_food_id])) {
                    $rnfItems[$item->retail_non_food_id] = [];
                }
                $rnfItems[$item->retail_non_food_id][] = [
                    'item_name' => $item->item_name,
                    'qty' => (float)$item->qty,
                    'unit' => $item->unit,
                    'price' => (float)$item->price,
                    'subtotal' => (float)$item->subtotal,
                ];
            }
        }

        $rnfByCategory = $rnfQuery->groupBy('category_id')
            ->map(function($items, $categoryId) use ($rnfItems) {
                $firstItem = $items->first();
                $rnfs = $items->map(function($item) use ($rnfItems) {
                    return [
                        'id' => $item->id,
                        'retail_number' => $item->retail_number,
                        'date' => $item->transaction_date,
                        'supplier_name' => $item->supplier_name ?? 'N/A',
                        'total_amount' => (float)$item->total_amount,
                        'notes' => $item->notes,
                        'creator' => $item->creator_id ? [
                            'id' => $item->creator_id,
                            'name' => $item->creator_name,
                            'avatar' => $item->creator_avatar,
                        ] : null,
                        'items' => $rnfItems[$item->id] ?? [],
                    ];
                })->values();

                return [
                    'category_id' => $categoryId,
                    'category_name' => $firstItem->category_name,
                    'division_name' => $firstItem->division_name ?? '',
                    'display_name' => ($firstItem->division_name ? $firstItem->division_name . ' - ' : '') . $firstItem->category_name,
                    'rnf_count' => $rnfs->count(),
                    'rnf_total' => $rnfs->sum('total_amount'),
                    'retail_non_food' => $rnfs,
                ];
            });

        // Merge PR and RNF by category
        $categories = $prsByCategory->map(function($prCategory) use ($rnfByCategory) {
            $rnfCategory = $rnfByCategory->get($prCategory['category_id']);
            return [
                'category_id' => $prCategory['category_id'],
                'category_name' => $prCategory['category_name'],
                'division_name' => $prCategory['division_name'],
                'display_name' => $prCategory['display_name'],
                'pr_count' => $prCategory['pr_count'],
                'pr_total' => $prCategory['pr_total'],
                'purchase_requisitions' => $prCategory['purchase_requisitions'],
                'rnf_count' => $rnfCategory ? $rnfCategory['rnf_count'] : 0,
                'rnf_total' => $rnfCategory ? $rnfCategory['rnf_total'] : 0,
                'retail_non_food' => $rnfCategory ? $rnfCategory['retail_non_food'] : [],
                'total' => $prCategory['pr_total'] + ($rnfCategory ? $rnfCategory['rnf_total'] : 0),
            ];
        });

        // Add categories that only have RNF
        $rnfByCategory->each(function($rnfCategory, $categoryId) use ($categories) {
            if (!$categories->contains('category_id', $categoryId)) {
                $categories->push([
                    'category_id' => $categoryId,
                    'category_name' => $rnfCategory['category_name'],
                    'division_name' => $rnfCategory['division_name'] ?? '',
                    'display_name' => $rnfCategory['display_name'],
                    'pr_count' => 0,
                    'pr_total' => 0,
                    'purchase_requisitions' => [],
                    'rnf_count' => $rnfCategory['rnf_count'],
                    'rnf_total' => $rnfCategory['rnf_total'],
                    'retail_non_food' => $rnfCategory['retail_non_food'],
                    'total' => $rnfCategory['rnf_total'],
                ]);
            }
        });

        $categories = $categories->sortByDesc('total')->values();

        // Calculate totals
        $totalPR = $categories->sum('pr_total');
        $totalRNF = $categories->sum('rnf_total');

        return response()->json([
            'success' => true,
            'outlet' => [
                'id' => $outlet->id_outlet,
                'name' => $outlet->nama_outlet,
            ],
            'summary' => [
                'total_pr' => $categories->sum('pr_count'),
                'total_pr_amount' => $totalPR,
                'total_rnf' => $categories->sum('rnf_count'),
                'total_rnf_amount' => $totalRNF,
                'grand_total' => $totalPR + $totalRNF,
            ],
            'categories' => $categories,
        ]);
    }

    public function getDivisionDetail(Request $request, $divisionId)
    {
        $filters = [
            'date_from' => $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'status' => $request->input('status', 'all'),
        ];

        // Get division info
        $division = Divisi::find($divisionId);
        if (!$division) {
            return response()->json(['success' => false, 'message' => 'Division not found'], 404);
        }

        // Get PR Ops for this division
        $prQuery = PurchaseRequisition::with(['items', 'creator', 'approvalFlows.approver'])
            ->whereIn('mode', ['pr_ops', 'purchase_payment'])
            ->where('division_id', $divisionId)
            ->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59'])
            ->withoutGlobalScopes();

        if ($filters['status'] !== 'all') {
            $prQuery->where('status', $filters['status']);
        }

        $prs = $prQuery->orderBy('created_at', 'desc')
            ->get()
            ->map(function($pr) {
                // Get latest approved approval flow
                $latestApproved = $pr->approvalFlows->where('status', 'APPROVED')->sortByDesc('approved_at')->first();
                
                return [
                    'id' => $pr->id,
                    'pr_number' => $pr->pr_number,
                    'date' => $pr->created_at->format('Y-m-d'),
                    'status' => $pr->status,
                    'description' => $pr->description,
                    'total_amount' => $pr->items->sum('subtotal'),
                    'items_count' => $pr->items->count(),
                    'creator' => $pr->creator ? [
                        'id' => $pr->creator->id,
                        'name' => $pr->creator->nama_lengkap,
                        'avatar' => $pr->creator->avatar,
                    ] : null,
                    'approval' => $latestApproved ? [
                        'status' => $latestApproved->status,
                        'approved_at' => $latestApproved->approved_at ? $latestApproved->approved_at->format('Y-m-d H:i:s') : null,
                        'approver' => $latestApproved->approver ? [
                            'id' => $latestApproved->approver->id,
                            'name' => $latestApproved->approver->nama_lengkap,
                            'avatar' => $latestApproved->approver->avatar,
                        ] : null,
                    ] : null,
                    'items' => $pr->items->map(function($item) {
                        return [
                            'item_name' => $item->item_name,
                            'qty' => $item->qty,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                ];
            });

        // Calculate totals
        $prTotal = $prs->sum('total_amount');

        return response()->json([
            'success' => true,
            'division' => [
                'id' => $division->id,
                'name' => $division->nama_divisi,
            ],
            'summary' => [
                'pr_count' => $prs->count(),
                'pr_total' => $prTotal,
            ],
            'purchase_requisitions' => $prs,
        ]);
    }
}

