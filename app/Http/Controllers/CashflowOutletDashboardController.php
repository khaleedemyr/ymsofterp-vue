<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class CashflowOutletDashboardController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $outletId = $request->get('outlet_id', 'ALL');
        $compareMode = $request->get('compare_mode', 'none'); // none, previous_period, previous_year, other_outlets

        // Get dashboard data
        $dashboardData = $this->getDashboardData($dateFrom, $dateTo, $outletId);

        // Get comparison data if needed
        $comparisonData = null;
        if ($compareMode === 'previous_period') {
            $daysDiff = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo));
            $prevDateFrom = Carbon::parse($dateFrom)->subDays($daysDiff + 1)->format('Y-m-d');
            $prevDateTo = Carbon::parse($dateFrom)->subDay()->format('Y-m-d');
            $comparisonData = $this->getDashboardData($prevDateFrom, $prevDateTo, $outletId);
        } elseif ($compareMode === 'previous_year') {
            $prevDateFrom = Carbon::parse($dateFrom)->subYear()->format('Y-m-d');
            $prevDateTo = Carbon::parse($dateTo)->subYear()->format('Y-m-d');
            $comparisonData = $this->getDashboardData($prevDateFrom, $prevDateTo, $outletId);
        }

        // Get outlets for dropdown
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name', 'qr_code')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('CashflowOutletDashboard/Index', [
            'dashboardData' => $dashboardData,
            'comparisonData' => $comparisonData,
            'outlets' => $outlets,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'outlet_id' => $outletId,
                'compare_mode' => $compareMode
            ]
        ]);
    }

    private function getDashboardData($dateFrom, $dateTo, $outletId)
    {
        // 1. Overview Metrics
        $overview = $this->getOverviewMetrics($dateFrom, $dateTo, $outletId);

        // 2. Cash In Trend
        $cashInTrend = $this->getCashInTrend($dateFrom, $dateTo, $outletId);

        // 3. Cash Out Trend
        $cashOutTrend = $this->getCashOutTrend($dateFrom, $dateTo, $outletId);

        // 4. Combined Cashflow Trend
        $cashflowTrend = $this->getCashflowTrend($dateFrom, $dateTo, $outletId);

        // 5. Cash Out Breakdown
        $cashOutBreakdown = $this->getCashOutBreakdown($dateFrom, $dateTo, $outletId);

        // 6. Outlet Summary (if multiple outlets)
        $outletSummary = $this->getOutletSummary($dateFrom, $dateTo, $outletId);

        return [
            'overview' => $overview,
            'cashInTrend' => $cashInTrend,
            'cashOutTrend' => $cashOutTrend,
            'cashflowTrend' => $cashflowTrend,
            'cashOutBreakdown' => $cashOutBreakdown,
            'outletSummary' => $outletSummary
        ];
    }

    private function getOverviewMetrics($dateFrom, $dateTo, $outletId)
    {
        // Cash In from orders
        $outletFilter = $outletId !== 'ALL' 
            ? "AND o.kode_outlet IN (SELECT qr_code FROM tbl_data_outlet WHERE id_outlet = {$outletId})"
            : '';

        $cashInQuery = "
            SELECT 
                SUM(grand_total) as total_revenue
            FROM orders o
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
            {$outletFilter}
        ";

        $cashInResult = DB::select($cashInQuery)[0];

        // Cash Out calculations
        $cashOut = $this->calculateCashOut($dateFrom, $dateTo, $outletId);

        // Cash In = total revenue (grand_total) - tidak dikurangi discount
        $totalCashIn = (float) ($cashInResult->total_revenue ?? 0);
        $totalCashOut = $cashOut['total'];
        $netCashflow = $totalCashIn - $totalCashOut;

        // Calculate cash out percentage against cash in
        $cashOutPercentage = $totalCashIn > 0 ? ($totalCashOut / $totalCashIn) * 100 : 0;

        // Get opening balance (saldo akhir periode sebelumnya)
        // For simplicity, we'll use 0 as opening balance for now
        // In production, this should be calculated from previous period's closing balance
        $openingBalance = 0;
        $closingBalance = $openingBalance + $netCashflow;

        return [
            'total_cash_in' => $totalCashIn,
            'total_cash_out' => $totalCashOut,
            'net_cashflow' => $netCashflow,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'cash_out_percentage' => round($cashOutPercentage, 2),
            'cash_in_details' => [
                'total_revenue' => $totalCashIn
            ],
            'cash_out_details' => $cashOut
        ];
    }

    private function calculateCashOut($dateFrom, $dateTo, $outletId)
    {
        // 1. Retail Food = Good Receive (GR) + Retail Food Table
        // 1a. Good Receive (GR - Good Receives)
        $grQuery = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->join('food_floor_order_items as ffoi', function($join) {
                $join->on('do.floor_order_id', '=', 'ffoi.floor_order_id')
                     ->on('gri.item_id', '=', 'ffoi.item_id');
            })
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'completed')
            ->whereBetween(DB::raw('DATE(gr.receive_date)'), [$dateFrom, $dateTo])
            ->select(DB::raw('SUM(gri.received_qty * ffoi.price) as total'));

        if ($outletId !== 'ALL') {
            $grQuery->where('gr.outlet_id', $outletId);
        }

        $grTotal = (float) ($grQuery->first()->total ?? 0);

        // 1b. Retail Food (from retail_food table)
        $retailFoodQuery = DB::table('retail_food')
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('DATE(transaction_date)'), [$dateFrom, $dateTo])
            ->select(DB::raw('SUM(total_amount) as total'));

        if ($outletId !== 'ALL') {
            $retailFoodQuery->where('outlet_id', $outletId);
        }

        $retailFoodTableTotal = (float) ($retailFoodQuery->first()->total ?? 0);

        // 2. Retail Non Food (RWS - Retail Warehouse Sales)
        $retailNonFoodQuery = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->where('rws.status', 'completed')
            ->whereBetween(DB::raw('DATE(rws.sale_date)'), [$dateFrom, $dateTo])
            ->select(DB::raw('SUM(rws.total_amount) as total'));

        if ($outletId !== 'ALL') {
            $retailNonFoodQuery->where('c.id_outlet', $outletId);
        }

        $retailNonFood = (float) ($retailNonFoodQuery->first()->total ?? 0);

        // 3. Payment (outlet_payments)
        $paymentQuery = DB::table('outlet_payments')
            ->where('status', 'paid')
            ->whereBetween(DB::raw('DATE(date)'), [$dateFrom, $dateTo])
            ->select(DB::raw('SUM(total_amount) as total'));

        if ($outletId !== 'ALL') {
            $paymentQuery->where('outlet_id', $outletId);
        }

        $payment = (float) ($paymentQuery->first()->total ?? 0);

        $total = $grTotal + $retailFoodTableTotal + $retailNonFood + $payment;

        return [
            'gr' => $grTotal,
            'retail_food' => $retailFoodTableTotal,
            'retail_non_food' => $retailNonFood,
            'payment' => $payment,
            'total' => $total
        ];
    }

    private function getCashInTrend($dateFrom, $dateTo, $outletId)
    {
        $outletFilter = $outletId !== 'ALL' 
            ? "AND o.kode_outlet IN (SELECT qr_code FROM tbl_data_outlet WHERE id_outlet = {$outletId})"
            : '';

        $query = "
            SELECT 
                DATE(o.created_at) as date,
                SUM(o.grand_total) as revenue
            FROM orders o
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
            {$outletFilter}
            GROUP BY DATE(o.created_at)
            ORDER BY date ASC
        ";

        return DB::select($query);
    }

    private function getCashOutTrend($dateFrom, $dateTo, $outletId)
    {
        // Get daily cash out breakdown
        $dates = [];
        $current = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);
        
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            
            // Retail Food for this date = GR + Retail Food Table
            // GR
            $grQuery = DB::table('outlet_food_good_receives as gr')
                ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->join('food_floor_order_items as ffoi', function($join) {
                    $join->on('do.floor_order_id', '=', 'ffoi.floor_order_id')
                         ->on('gri.item_id', '=', 'ffoi.item_id');
                })
                ->whereNull('gr.deleted_at')
                ->where('gr.status', 'completed')
                ->whereDate('gr.receive_date', $date)
                ->select(DB::raw('SUM(gri.received_qty * ffoi.price) as total'));

            if ($outletId !== 'ALL') {
                $grQuery->where('gr.outlet_id', $outletId);
            }

            $grTotal = (float) ($grQuery->first()->total ?? 0);

            // Retail Food Table
            $retailFoodTableQuery = DB::table('retail_food')
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->whereDate('transaction_date', $date)
                ->select(DB::raw('SUM(total_amount) as total'));

            if ($outletId !== 'ALL') {
                $retailFoodTableQuery->where('outlet_id', $outletId);
            }

            $retailFoodTableTotal = (float) ($retailFoodTableQuery->first()->total ?? 0);

            // Retail Non Food for this date
            $retailNonFoodQuery = DB::table('retail_warehouse_sales as rws')
                ->join('customers as c', 'rws.customer_id', '=', 'c.id')
                ->where('rws.status', 'completed')
                ->whereDate('rws.sale_date', $date)
                ->select(DB::raw('SUM(rws.total_amount) as total'));

            if ($outletId !== 'ALL') {
                $retailNonFoodQuery->where('c.id_outlet', $outletId);
            }

            $retailNonFood = (float) ($retailNonFoodQuery->first()->total ?? 0);

            // Payment for this date
            $paymentQuery = DB::table('outlet_payments')
                ->where('status', 'paid')
                ->whereDate('date', $date)
                ->select(DB::raw('SUM(total_amount) as total'));

            if ($outletId !== 'ALL') {
                $paymentQuery->where('outlet_id', $outletId);
            }

            $payment = (float) ($paymentQuery->first()->total ?? 0);

            $dates[] = (object) [
                'date' => $date,
                'gr' => $grTotal,
                'retail_food' => $retailFoodTableTotal,
                'retail_non_food' => $retailNonFood,
                'payment' => $payment,
                'total' => $grTotal + $retailFoodTableTotal + $retailNonFood + $payment
            ];

            $current->addDay();
        }

        return $dates;
    }

    private function getCashflowTrend($dateFrom, $dateTo, $outletId)
    {
        $cashInTrend = $this->getCashInTrend($dateFrom, $dateTo, $outletId);
        $cashOutTrend = $this->getCashOutTrend($dateFrom, $dateTo, $outletId);

        // Combine into single array by date
        $combined = [];
        $cashOutMap = collect($cashOutTrend)->keyBy('date');

        foreach ($cashInTrend as $in) {
            $date = $in->date;
            $cashOut = $cashOutMap->get($date);
            $cashOutTotal = $cashOut ? $cashOut->total : 0;
            $netCashflow = $in->revenue - $cashOutTotal;

            $combined[] = (object) [
                'date' => $date,
                'cash_in' => (float) $in->revenue,
                'cash_out' => (float) $cashOutTotal,
                'net_cashflow' => (float) $netCashflow
            ];
        }

        return $combined;
    }

    private function getCashOutBreakdown($dateFrom, $dateTo, $outletId)
    {
        $cashOut = $this->calculateCashOut($dateFrom, $dateTo, $outletId);
        
        return [
            [
                'name' => 'GR',
                'value' => $cashOut['gr'],
                'percentage' => $cashOut['total'] > 0 ? ($cashOut['gr'] / $cashOut['total']) * 100 : 0
            ],
            [
                'name' => 'Retail Food',
                'value' => $cashOut['retail_food'],
                'percentage' => $cashOut['total'] > 0 ? ($cashOut['retail_food'] / $cashOut['total']) * 100 : 0
            ],
            [
                'name' => 'Retail Non Food',
                'value' => $cashOut['retail_non_food'],
                'percentage' => $cashOut['total'] > 0 ? ($cashOut['retail_non_food'] / $cashOut['total']) * 100 : 0
            ],
            [
                'name' => 'Payment',
                'value' => $cashOut['payment'],
                'percentage' => $cashOut['total'] > 0 ? ($cashOut['payment'] / $cashOut['total']) * 100 : 0
            ]
        ];
    }

    private function getOutletSummary($dateFrom, $dateTo, $outletId)
    {
        // If ALL outlets, get summary per outlet
        if ($outletId === 'ALL') {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name', 'qr_code')
                ->get();

            $summary = [];
            foreach ($outlets as $outlet) {
                $outletData = $this->getDashboardData($dateFrom, $dateTo, $outlet->id);
                $summary[] = [
                    'outlet_id' => $outlet->id,
                    'outlet_name' => $outlet->name,
                    'cash_in' => $outletData['overview']['total_cash_in'],
                    'cash_out' => $outletData['overview']['total_cash_out'],
                    'net_cashflow' => $outletData['overview']['net_cashflow'],
                    'opening_balance' => $outletData['overview']['opening_balance'],
                    'closing_balance' => $outletData['overview']['closing_balance']
                ];
            }

            return $summary;
        }

        return [];
    }

    public function getDetail(Request $request)
    {
        $type = $request->get('type'); // cash_in, cash_out
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id', 'ALL');
        $category = $request->get('category'); // retail_food, retail_non_food, payment (for cash_out)

        if ($type === 'cash_in') {
            return $this->getCashInDetail($dateFrom, $dateTo, $outletId);
        } elseif ($type === 'cash_out') {
            // If category is not provided, return all categories
            if (!$category) {
                return $this->getAllCashOutDetail($dateFrom, $dateTo, $outletId);
            }
            return $this->getCashOutDetail($dateFrom, $dateTo, $outletId, $category);
        }

        return response()->json(['error' => 'Invalid type'], 400);
    }

    private function getCashInDetail($dateFrom, $dateTo, $outletId)
    {
        $outletFilter = $outletId !== 'ALL' 
            ? "AND o.kode_outlet IN (SELECT qr_code FROM tbl_data_outlet WHERE id_outlet = {$outletId})"
            : '';

        // Group by date and sum revenue
        $query = "
            SELECT 
                DATE(o.created_at) as date,
                COUNT(*) as transaction_count,
                SUM(o.grand_total) as revenue,
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
            {$outletFilter}
            GROUP BY DATE(o.created_at), o.kode_outlet, outlet.nama_outlet
            ORDER BY date DESC
        ";

        $orders = DB::select($query);

        return response()->json([
            'type' => 'cash_in',
            'data' => $orders
        ]);
    }

    private function getCashOutDetail($dateFrom, $dateTo, $outletId, $category)
    {
        // Normalize category name
        $category = strtolower(str_replace(' ', '_', $category));
        
        if ($category === 'gr') {
            // GR (Good Receive) only
            $query = DB::table('outlet_food_good_receives as gr')
                ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
                ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->join('food_floor_order_items as ffoi', function($join) {
                    $join->on('do.floor_order_id', '=', 'ffoi.floor_order_id')
                         ->on('gri.item_id', '=', 'ffoi.item_id');
                })
                ->whereNull('gr.deleted_at')
                ->where('gr.status', 'completed')
                ->whereBetween(DB::raw('DATE(gr.receive_date)'), [$dateFrom, $dateTo])
                ->select(
                    'gr.id',
                    'gr.number',
                    'gr.receive_date as date',
                    'o.nama_outlet as outlet_name',
                    DB::raw('SUM(gri.received_qty * ffoi.price) as total'),
                    DB::raw("'GR' as source")
                )
                ->groupBy('gr.id', 'gr.number', 'gr.receive_date', 'o.nama_outlet');

            if ($outletId !== 'ALL') {
                $query->where('gr.outlet_id', $outletId);
            }

            $data = $query->get();

        } elseif ($category === 'retail_food') {
            // Retail Food (from retail_food table only, not GR)
            $query = DB::table('retail_food as rf')
                ->join('tbl_data_outlet as o', 'rf.outlet_id', '=', 'o.id_outlet')
                ->where('rf.status', 'approved')
                ->whereNull('rf.deleted_at')
                ->whereBetween(DB::raw('DATE(rf.transaction_date)'), [$dateFrom, $dateTo])
                ->select(
                    'rf.id',
                    'rf.retail_number as number',
                    'rf.transaction_date as date',
                    'o.nama_outlet as outlet_name',
                    'rf.total_amount as total',
                    DB::raw("'Retail Food' as source")
                );

            if ($outletId !== 'ALL') {
                $query->where('rf.outlet_id', $outletId);
            }

            $data = $query->get();

        } elseif ($category === 'retail_non_food') {
            $query = DB::table('retail_warehouse_sales as rws')
                ->join('customers as c', 'rws.customer_id', '=', 'c.id')
                ->leftJoin('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')
                ->where('rws.status', 'completed')
                ->whereBetween(DB::raw('DATE(rws.sale_date)'), [$dateFrom, $dateTo])
                ->select(
                    'rws.id',
                    'rws.number',
                    'rws.sale_date as date',
                    'o.nama_outlet as outlet_name',
                    'rws.total_amount as total'
                );

            if ($outletId !== 'ALL') {
                $query->where('c.id_outlet', $outletId);
            }

            $data = $query->get();

        } elseif ($category === 'payment') {
            $query = DB::table('outlet_payments as op')
                ->join('tbl_data_outlet as o', 'op.outlet_id', '=', 'o.id_outlet')
                ->where('op.status', 'paid')
                ->whereBetween(DB::raw('DATE(op.date)'), [$dateFrom, $dateTo])
                ->select(
                    'op.id',
                    'op.payment_number as number',
                    'op.date',
                    'o.nama_outlet as outlet_name',
                    'op.total_amount as total'
                );

            if ($outletId !== 'ALL') {
                $query->where('op.outlet_id', $outletId);
            }

            $data = $query->get();
        } else {
            return response()->json(['error' => 'Invalid category'], 400);
        }

        return response()->json([
            'type' => 'cash_out',
            'category' => $category,
            'data' => $data
        ]);
    }

    private function getAllCashOutDetail($dateFrom, $dateTo, $outletId)
    {
        // Get all cash out categories - GR and Retail Food separated
        $allData = [
            'gr' => [],
            'retail_food' => [],
            'retail_non_food' => [],
            'payment' => []
        ];

        // GR (Good Receive)
        $grQuery = DB::table('outlet_food_good_receives as gr')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->join('food_floor_order_items as ffoi', function($join) {
                $join->on('do.floor_order_id', '=', 'ffoi.floor_order_id')
                     ->on('gri.item_id', '=', 'ffoi.item_id');
            })
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'completed')
            ->whereBetween(DB::raw('DATE(gr.receive_date)'), [$dateFrom, $dateTo])
            ->select(
                'gr.id',
                'gr.number',
                'gr.receive_date as date',
                'o.nama_outlet as outlet_name',
                DB::raw('SUM(gri.received_qty * ffoi.price) as total'),
                DB::raw("'GR' as source")
            )
            ->groupBy('gr.id', 'gr.number', 'gr.receive_date', 'o.nama_outlet');

        if ($outletId !== 'ALL') {
            $grQuery->where('gr.outlet_id', $outletId);
        }

        $allData['gr'] = $grQuery->get();

        // Retail Food Table (only from retail_food table)
        $retailFoodTableQuery = DB::table('retail_food as rf')
            ->join('tbl_data_outlet as o', 'rf.outlet_id', '=', 'o.id_outlet')
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereBetween(DB::raw('DATE(rf.transaction_date)'), [$dateFrom, $dateTo])
            ->select(
                'rf.id',
                'rf.retail_number as number',
                'rf.transaction_date as date',
                'o.nama_outlet as outlet_name',
                'rf.total_amount as total',
                DB::raw("'Retail Food' as source")
            );

        if ($outletId !== 'ALL') {
            $retailFoodTableQuery->where('rf.outlet_id', $outletId);
        }

        $allData['retail_food'] = $retailFoodTableQuery->get();

        // Retail Non Food
        $retailNonFoodQuery = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->leftJoin('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')
            ->where('rws.status', 'completed')
            ->whereBetween(DB::raw('DATE(rws.sale_date)'), [$dateFrom, $dateTo])
            ->select(
                'rws.id',
                'rws.number',
                'rws.sale_date as date',
                'o.nama_outlet as outlet_name',
                'rws.total_amount as total'
            );

        if ($outletId !== 'ALL') {
            $retailNonFoodQuery->where('c.id_outlet', $outletId);
        }

        $allData['retail_non_food'] = $retailNonFoodQuery->get();

        // Payment
        $paymentQuery = DB::table('outlet_payments as op')
            ->join('tbl_data_outlet as o', 'op.outlet_id', '=', 'o.id_outlet')
            ->where('op.status', 'paid')
            ->whereBetween(DB::raw('DATE(op.date)'), [$dateFrom, $dateTo])
            ->select(
                'op.id',
                'op.payment_number as number',
                'op.date',
                'o.nama_outlet as outlet_name',
                'op.total_amount as total'
            );

        if ($outletId !== 'ALL') {
            $paymentQuery->where('op.outlet_id', $outletId);
        }

        $allData['payment'] = $paymentQuery->get();

        return response()->json([
            'type' => 'cash_out',
            'category' => 'all',
            'data' => $allData
        ]);
    }
}

