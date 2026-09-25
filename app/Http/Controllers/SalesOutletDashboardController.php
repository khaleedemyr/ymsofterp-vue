<?php

namespace App\Http\Controllers;

use App\Services\SalesOutletDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class SalesOutletDashboardController extends Controller
{
    public function __construct(
        private SalesOutletDashboardService $dashboardService
    ) {
    }

    /**
     * API endpoint for mobile app - Sales Outlet Dashboard
     */
    public function dashboardApi(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $dashboardData = $this->dashboardService->getFullDashboard($dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'data' => $dashboardData,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ]);
    }

    public function index(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->endOfMonth()->format('Y-m-d'));

        // Shell only — heavy queries load via /section (progressive)
        return Inertia::render('SalesOutletDashboard/Index', [
            'dashboardData' => $this->dashboardService->emptyDashboard(),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ],
            'lazy' => true,
        ]);
    }

    /**
     * Progressive section loader for the web dashboard.
     */
    public function getSection(Request $request)
    {
        $section = (string) $request->get('section', '');
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $allowed = ['overview', 'trend', 'charts', 'catalog', 'promo', 'revenue', 'forecast'];
        if (! in_array($section, $allowed, true)) {
            return response()->json(['error' => 'Invalid section'], 400);
        }

        return response()->json(
            $this->dashboardService->buildSection($section, $dateFrom, $dateTo)
        );
    }

    /**
     * Get dashboard data (public method untuk AI service)
     */
    public function getDashboardDataPublic($dateFrom, $dateTo, $period = 'daily')
    {
        return $this->dashboardService->getFullDashboard($dateFrom, $dateTo);
    }

    private function getDashboardData($dateFrom, $dateTo, $period = 'daily')
    {
        return $this->dashboardService->getFullDashboard($dateFrom, $dateTo);
    }








    public function getBankPromoDiscountTransactions(Request $request)
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $search = $request->get('search', '');
        $outlet = $request->get('outlet', '');
        $region = $request->get('region', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 10);

        if (!$dateFrom || !$dateTo) {
            return response()->json(['error' => 'Date range is required'], 400);
        }

        // Build base query with payment information, outlet name, and region
        $query = "
            SELECT 
                o.id,
                o.paid_number,
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(region.name, 'N/A') as region_name,
                o.grand_total,
                o.manual_discount_amount,
                o.manual_discount_reason,
                o.created_at,
                op.payment_code,
                op.payment_type,
                op.kasir,
                op.card_first4,
                op.card_last4,
                op.approval_code
            FROM orders o
            LEFT JOIN order_payment op ON o.id = op.order_id
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
            AND o.manual_discount_reason LIKE '%BANK%'
        ";

        // Add search filter
        if (!empty($search)) {
            $searchEscaped = DB::getPdo()->quote('%' . $search . '%');
            $query .= " AND o.manual_discount_reason LIKE {$searchEscaped}";
        }

        // Add outlet filter
        if (!empty($outlet)) {
            $outletEscaped = DB::getPdo()->quote($outlet);
            $query .= " AND o.kode_outlet = {$outletEscaped}";
        }

        // Add region filter
        if (!empty($region)) {
            $regionEscaped = DB::getPdo()->quote($region);
            $query .= " AND outlet.region_id = {$regionEscaped}";
        }

        // Get total count for pagination
        $countQuery = "
            SELECT COUNT(DISTINCT o.id) as total
            FROM orders o
            LEFT JOIN order_payment op ON o.id = op.order_id
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
            AND o.manual_discount_reason LIKE '%BANK%'
        ";

        if (!empty($search)) {
            $searchEscaped = DB::getPdo()->quote('%' . $search . '%');
            $countQuery .= " AND o.manual_discount_reason LIKE {$searchEscaped}";
        }

        if (!empty($outlet)) {
            $outletEscaped = DB::getPdo()->quote($outlet);
            $countQuery .= " AND o.kode_outlet = {$outletEscaped}";
        }

        if (!empty($region)) {
            $regionEscaped = DB::getPdo()->quote($region);
            $countQuery .= " AND outlet.region_id = {$regionEscaped}";
        }

        $totalCount = DB::select($countQuery)[0]->total;

        // Add pagination
        $offset = ($page - 1) * $perPage;
        $query .= " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";

        $transactions = DB::select($query);

        // Calculate grand total for all matching transactions (not just current page)
        $grandTotalQuery = "
            SELECT 
                SUM(o.grand_total) as total_grand_total,
                SUM(o.manual_discount_amount) as total_discount_amount
            FROM orders o
            LEFT JOIN order_payment op ON o.id = op.order_id
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
            AND o.manual_discount_reason LIKE '%BANK%'
        ";

        if (!empty($search)) {
            $searchEscaped = DB::getPdo()->quote('%' . $search . '%');
            $grandTotalQuery .= " AND o.manual_discount_reason LIKE {$searchEscaped}";
        }

        if (!empty($outlet)) {
            $outletEscaped = DB::getPdo()->quote($outlet);
            $grandTotalQuery .= " AND o.kode_outlet = {$outletEscaped}";
        }

        if (!empty($region)) {
            $regionEscaped = DB::getPdo()->quote($region);
            $grandTotalQuery .= " AND outlet.region_id = {$regionEscaped}";
        }

        $grandTotalResult = DB::select($grandTotalQuery)[0];

        // Calculate pagination info
        $totalPages = ceil($totalCount / $perPage);
        $hasNextPage = $page < $totalPages;
        $hasPrevPage = $page > 1;

        return response()->json([
            'transactions' => $transactions,
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => (int) $totalCount,
                'total_pages' => (int) $totalPages,
                'has_next_page' => $hasNextPage,
                'has_prev_page' => $hasPrevPage
            ],
            'grand_total' => [
                'total_grand_total' => (float) ($grandTotalResult->total_grand_total ?? 0),
                'total_discount_amount' => (float) ($grandTotalResult->total_discount_amount ?? 0)
            ]
        ]);
    }

    public function exportBankPromoDiscountTransactions(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $search = $request->get('search', '');
            $outlet = $request->get('outlet', '');
            $region = $request->get('region', '');

            if (!$dateFrom || !$dateTo) {
                return response()->json(['error' => 'Date range is required'], 400);
            }

            // Build query for export (get all data, no pagination)
            $query = "
                SELECT 
                    o.id,
                    o.paid_number,
                    COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                    COALESCE(region.name, 'N/A') as region_name,
                    op.kasir,
                    CONCAT(
                        op.payment_code, 
                        CASE WHEN op.payment_type THEN CONCAT(' - ', op.payment_type) ELSE '' END,
                        CASE WHEN op.payment_type = 'credit' AND op.card_first4 AND op.card_last4 
                             THEN CONCAT(' (****', op.card_first4, '****', op.card_last4, ')') 
                             ELSE '' END,
                        CASE WHEN op.payment_type = 'credit' AND op.approval_code 
                             THEN CONCAT(' [', op.approval_code, ']') 
                             ELSE '' END
                    ) as payment_method,
                    o.grand_total,
                    o.manual_discount_amount,
                    o.manual_discount_reason,
                    o.created_at
                FROM orders o
                LEFT JOIN order_payment op ON o.id = op.order_id
                LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
                LEFT JOIN regions region ON outlet.region_id = region.id
                WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
                AND o.manual_discount_reason LIKE '%BANK%'
            ";

            // Add search filter
            if (!empty($search)) {
                $searchEscaped = DB::getPdo()->quote('%' . $search . '%');
                $query .= " AND o.manual_discount_reason LIKE {$searchEscaped}";
            }

            // Add outlet filter
            if (!empty($outlet)) {
                $outletEscaped = DB::getPdo()->quote($outlet);
                $query .= " AND o.kode_outlet = {$outletEscaped}";
            }

            // Add region filter
            if (!empty($region)) {
                $regionEscaped = DB::getPdo()->quote($region);
                $query .= " AND outlet.region_id = {$regionEscaped}";
            }

            $query .= " ORDER BY o.created_at DESC";

            $transactions = DB::select($query);

            // Calculate grand total
            $grandTotalQuery = "
                SELECT 
                    SUM(o.grand_total) as total_grand_total,
                    SUM(o.manual_discount_amount) as total_discount_amount,
                    COUNT(*) as total_transactions
                FROM orders o
                LEFT JOIN order_payment op ON o.id = op.order_id
                LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
                LEFT JOIN regions region ON outlet.region_id = region.id
                WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
                AND o.manual_discount_reason LIKE '%BANK%'
            ";

            if (!empty($search)) {
                $searchEscaped = DB::getPdo()->quote('%' . $search . '%');
                $grandTotalQuery .= " AND o.manual_discount_reason LIKE {$searchEscaped}";
            }

            if (!empty($outlet)) {
                $outletEscaped = DB::getPdo()->quote($outlet);
                $grandTotalQuery .= " AND o.kode_outlet = {$outletEscaped}";
            }

            if (!empty($region)) {
                $regionEscaped = DB::getPdo()->quote($region);
                $grandTotalQuery .= " AND outlet.region_id = {$regionEscaped}";
            }

            $grandTotalResult = DB::select($grandTotalQuery)[0];

            // Create filename
            $filename = 'Bank_Promo_Discount_Transactions_' . $dateFrom . '_to_' . $dateTo . '.xlsx';
            
            // Use Maatwebsite Excel like Report Rekap FJ
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\BankPromoDiscountExport($transactions, $grandTotalResult, $dateFrom, $dateTo, $search),
                $filename
            );
            
        } catch (\Exception $e) {
            \Log::error('Export Bank Promo Discount error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat export: ' . $e->getMessage()], 500);
        }
    }











    public function getMenuRegionData(Request $request)
    {
        $itemName = $request->get('item_name');
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->endOfMonth()->format('Y-m-d'));

        if (!$itemName) {
            return response()->json(['error' => 'Item name is required'], 400);
        }

        $query = "
            SELECT 
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code,
                COUNT(DISTINCT o.id) as order_count,
                SUM(oi.qty) as total_quantity,
                SUM(oi.subtotal) as total_revenue,
                AVG(oi.price) as avg_price,
                COUNT(DISTINCT o.kode_outlet) as outlet_count
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY)
            AND oi.item_name = '{$itemName}'
            GROUP BY region.name, region.code
            ORDER BY total_revenue DESC
        ";

        $results = DB::select($query);

        // Process data
        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'region_name' => $result->region_name,
                'region_code' => $result->region_code,
                'order_count' => (int) $result->order_count,
                'total_quantity' => (int) $result->total_quantity,
                'total_revenue' => (float) $result->total_revenue,
                'avg_price' => (float) $result->avg_price,
                'outlet_count' => (int) $result->outlet_count
            ];
        }

        return response()->json($data);
    }

    public function getOutletDetailsByDate(Request $request)
    {
        $date = $request->get('date');
        
        if (!$date) {
            return response()->json(['error' => 'Date is required'], 400);
        }

        $query = "
            SELECT 
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code,
                COUNT(*) as orders,
                SUM(o.grand_total) as revenue,
                SUM(o.pax) as customers,
                AVG(o.grand_total) as avg_order_value,
                CASE 
                    WHEN SUM(o.pax) > 0 THEN SUM(o.grand_total) / SUM(o.pax)
                    ELSE 0
                END as cover
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.created_at >= '{$date} 00:00:00' AND o.created_at < DATE_ADD('{$date}', INTERVAL 1 DAY)
            GROUP BY o.kode_outlet, outlet.nama_outlet, region.name, region.code
            ORDER BY revenue DESC
        ";

        $results = DB::select($query);

        // Process data
        $data = [];
        $totalRevenue = 0;
        $totalOrders = 0;
        $totalCustomers = 0;

        foreach ($results as $result) {
            $data[] = [
                'outlet_code' => $result->kode_outlet,
                'outlet_name' => $result->outlet_name,
                'region_name' => $result->region_name,
                'region_code' => $result->region_code,
                'orders' => (int) $result->orders,
                'revenue' => (float) $result->revenue,
                'revenue_formatted' => 'Rp ' . number_format($result->revenue, 0, ',', '.'),
                'customers' => (int) $result->customers,
                'avg_order_value' => (float) $result->avg_order_value,
                'avg_order_value_formatted' => 'Rp ' . number_format($result->avg_order_value, 0, ',', '.'),
                'cover' => (float) $result->cover,
                'cover_formatted' => 'Rp ' . number_format($result->cover, 0, ',', '.')
            ];

            $totalRevenue += (float) $result->revenue;
            $totalOrders += (int) $result->orders;
            $totalCustomers += (int) $result->customers;
        }

        return response()->json([
            'date' => $date,
            'date_formatted' => Carbon::parse($date)->format('d F Y'),
            'outlets' => $data,
            'summary' => [
                'total_outlets' => count($data),
                'total_revenue' => $totalRevenue,
                'total_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                'total_orders' => $totalOrders,
                'total_customers' => $totalCustomers,
                'avg_revenue_per_outlet' => count($data) > 0 ? $totalRevenue / count($data) : 0,
                'avg_revenue_per_outlet_formatted' => count($data) > 0 ? 'Rp ' . number_format($totalRevenue / count($data), 0, ',', '.') : 'Rp 0'
            ]
        ]);
    }

    public function getOutletDailyRevenue(Request $request)
    {
        $outletCode = $request->get('outlet_code');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        if (!$outletCode) {
            return response()->json(['error' => 'Outlet code is required'], 400);
        }

        if (!$dateFrom || !$dateTo) {
            return response()->json(['error' => 'Date range is required'], 400);
        }

        $query = "
            SELECT 
                DATE(o.created_at) as date,
                COUNT(*) as orders,
                SUM(o.grand_total) as revenue,
                SUM(o.pax) as customers,
                AVG(o.grand_total) as avg_order_value,
                CASE 
                    WHEN SUM(o.pax) > 0 THEN SUM(o.grand_total) / SUM(o.pax)
                    ELSE 0
                END as cover
            FROM orders o
            WHERE o.kode_outlet = '{$outletCode}'
            AND o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY)
            GROUP BY DATE(o.created_at)
            ORDER BY date ASC
        ";

        $results = DB::select($query);

        // Get outlet info
        $outletInfo = DB::select("
            SELECT 
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.kode_outlet = '{$outletCode}'
            LIMIT 1
        ");

        $outlet = $outletInfo[0] ?? null;

        // Process data
        $data = [];
        $totalRevenue = 0;
        $totalOrders = 0;
        $totalCustomers = 0;

        foreach ($results as $result) {
            $data[] = [
                'date' => $result->date,
                'date_formatted' => Carbon::parse($result->date)->format('d F Y'),
                'orders' => (int) $result->orders,
                'revenue' => (float) $result->revenue,
                'revenue_formatted' => 'Rp ' . number_format($result->revenue, 0, ',', '.'),
                'customers' => (int) $result->customers,
                'avg_order_value' => (float) $result->avg_order_value,
                'avg_order_value_formatted' => 'Rp ' . number_format($result->avg_order_value, 0, ',', '.'),
                'cover' => (float) $result->cover,
                'cover_formatted' => 'Rp ' . number_format($result->cover, 0, ',', '.')
            ];

            $totalRevenue += (float) $result->revenue;
            $totalOrders += (int) $result->orders;
            $totalCustomers += (int) $result->customers;
        }

        return response()->json([
            'outlet' => $outlet ? [
                'outlet_code' => $outlet->kode_outlet,
                'outlet_name' => $outlet->outlet_name,
                'region_name' => $outlet->region_name,
                'region_code' => $outlet->region_code
            ] : null,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
                'from_formatted' => Carbon::parse($dateFrom)->format('d F Y'),
                'to_formatted' => Carbon::parse($dateTo)->format('d F Y')
            ],
            'daily_data' => $data,
            'summary' => [
                'total_days' => count($data),
                'total_revenue' => $totalRevenue,
                'total_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                'total_orders' => $totalOrders,
                'total_customers' => $totalCustomers,
                'avg_daily_revenue' => count($data) > 0 ? $totalRevenue / count($data) : 0,
                'avg_daily_revenue_formatted' => count($data) > 0 ? 'Rp ' . number_format($totalRevenue / count($data), 0, ',', '.') : 'Rp 0',
                'avg_daily_orders' => count($data) > 0 ? $totalOrders / count($data) : 0,
                'avg_daily_customers' => count($data) > 0 ? $totalCustomers / count($data) : 0
            ]
        ]);
    }

    public function getHolidays(Request $request)
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        if (!$dateFrom || !$dateTo) {
            return response()->json(['error' => 'Date range is required'], 400);
        }

        $holidays = DB::table('tbl_kalender_perusahaan')
            ->whereBetween('tgl_libur', [$dateFrom, $dateTo])
            ->orderBy('tgl_libur')
            ->get()
            ->map(function ($holiday) {
                return [
                    'date' => $holiday->tgl_libur,
                    'description' => $holiday->keterangan
                ];
            });

        return response()->json($holidays);
    }

    public function getOutletOrders(Request $request)
    {
        $outletCode = $request->get('outlet_code');
        $date = $request->get('date');
        
        if (!$outletCode || !$date) {
            return response()->json(['error' => 'Outlet code and date are required'], 400);
        }

        // First, let's check what fields are available in the orders table
        $sampleOrder = DB::table('orders')
            ->where('kode_outlet', $outletCode)
            ->where('created_at', '>=', $date . ' 00:00:00')->where('created_at', '<', \Carbon\Carbon::parse($date)->addDay()->toDateTimeString())
            ->first();
            
        if (!$sampleOrder) {
            return response()->json([
                'orders' => [],
                'total_orders' => 0,
                'total_revenue' => 0,
                'total_pax' => 0
            ]);
        }
        
        // Log available fields for debugging
        \Log::info('Available order fields:', array_keys((array) $sampleOrder));

        $orders = DB::table('orders')
            ->leftJoin('order_payment', 'orders.id', '=', 'order_payment.order_id')
            ->where('orders.kode_outlet', $outletCode)
            ->where('orders.created_at', '>=', $date . ' 00:00:00')->where('orders.created_at', '<', \Carbon\Carbon::parse($date)->addDay()->toDateTimeString())
            ->select(
                'orders.id',
                'orders.paid_number',
                'orders.table',
                'orders.total',
                'orders.pb1',
                'orders.service',
                'orders.grand_total',
                'orders.pax',
                'orders.commfee',
                'orders.waiters',
                'orders.member_name as customer',
                'orders.created_at',
                'order_payment.kasir',
                'order_payment.payment_code',
                'order_payment.payment_type'
            )
            ->orderBy('orders.created_at', 'desc')
            ->get()
            ->map(function ($order) {
                // Format payment method
                $paymentMethod = 'Unknown';
                if ($order->payment_code && $order->payment_type) {
                    $paymentMethod = $order->payment_code . ' - ' . $order->payment_type;
                } elseif ($order->payment_code) {
                    $paymentMethod = $order->payment_code;
                } elseif ($order->payment_type) {
                    $paymentMethod = $order->payment_type;
                }
                
                return [
                    'id' => $order->id,
                    'order_id' => $order->id,
                    'paid_number' => $order->paid_number ?? '-',
                    'table' => $order->table ?? '-',
                    'total' => (float) ($order->total ?? 0),
                    'pb1' => (float) ($order->pb1 ?? 0),
                    'service' => (float) ($order->service ?? 0),
                    'grand_total' => (float) ($order->grand_total ?? 0),
                    'pax' => (int) ($order->pax ?? 0),
                    'commfee' => (float) ($order->commfee ?? 0),
                    'waiters' => $order->waiters ?? '-',
                    'kasir' => $order->kasir ?? '-',
                    'customer' => $order->customer ?? '-',
                    'payment_method' => $paymentMethod,
                    'created_at' => $order->created_at,
                    'created_at_formatted' => Carbon::parse($order->created_at)->format('d/m/Y H:i')
                ];
            });

        return response()->json([
            'orders' => $orders,
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('grand_total'),
            'total_pax' => $orders->sum('pax')
        ]);
    }

    public function getOutletLunchDinnerDetail(Request $request)
    {
        $outletCode = $request->get('outlet_code');
        $mealPeriod = $request->get('meal_period');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        if (!$outletCode) {
            return response()->json(['error' => 'Outlet code is required'], 400);
        }

        if (!$mealPeriod) {
            return response()->json(['error' => 'Meal period is required'], 400);
        }

        if (!$dateFrom || !$dateTo) {
            return response()->json(['error' => 'Date range is required'], 400);
        }

        // Determine hour range based on meal period
        $hourCondition = '';
        if ($mealPeriod === 'Lunch') {
            $hourCondition = 'AND HOUR(o.created_at) BETWEEN 11 AND 15';
        } elseif ($mealPeriod === 'Dinner') {
            $hourCondition = 'AND HOUR(o.created_at) BETWEEN 17 AND 22';
        } else {
            return response()->json(['error' => 'Invalid meal period'], 400);
        }

        $query = "
            SELECT 
                DATE(o.created_at) as date,
                COUNT(*) as orders,
                SUM(o.grand_total) as revenue,
                SUM(o.pax) as customers,
                AVG(o.grand_total) as avg_order_value,
                CASE 
                    WHEN SUM(o.pax) > 0 THEN SUM(o.grand_total) / SUM(o.pax)
                    ELSE 0
                END as avg_check
            FROM orders o
            WHERE o.kode_outlet = '{$outletCode}'
            AND o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY)
            {$hourCondition}
            GROUP BY DATE(o.created_at)
            ORDER BY date ASC
        ";

        $results = DB::select($query);

        // Get outlet info
        $outletInfo = DB::select("
            SELECT 
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.kode_outlet = '{$outletCode}'
            LIMIT 1
        ");

        $outlet = $outletInfo[0] ?? null;

        // Process data
        $data = [];
        $totalRevenue = 0;
        $totalOrders = 0;
        $totalCustomers = 0;

        foreach ($results as $result) {
            $data[] = [
                'date' => $result->date,
                'date_formatted' => Carbon::parse($result->date)->format('d F Y'),
                'orders' => (int) $result->orders,
                'revenue' => (float) $result->revenue,
                'revenue_formatted' => 'Rp ' . number_format($result->revenue, 0, ',', '.'),
                'pax' => (int) $result->customers,
                'customers' => (int) $result->customers,
                'avg_order_value' => (float) $result->avg_order_value,
                'avg_order_value_formatted' => 'Rp ' . number_format($result->avg_order_value, 0, ',', '.'),
                'avg_check' => (float) $result->avg_check,
                'avg_check_formatted' => 'Rp ' . number_format($result->avg_check, 0, ',', '.')
            ];

            $totalRevenue += (float) $result->revenue;
            $totalOrders += (int) $result->orders;
            $totalCustomers += (int) $result->customers;
        }

        return response()->json([
            'outlet' => $outlet ? [
                'outlet_code' => $outlet->kode_outlet,
                'outlet_name' => $outlet->outlet_name,
                'region_name' => $outlet->region_name,
                'region_code' => $outlet->region_code
            ] : null,
            'meal_period' => $mealPeriod,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
                'from_formatted' => Carbon::parse($dateFrom)->format('d F Y'),
                'to_formatted' => Carbon::parse($dateTo)->format('d F Y')
            ],
            'daily_data' => $data,
            'summary' => [
                'total_days' => count($data),
                'total_revenue' => $totalRevenue,
                'total_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                'total_orders' => $totalOrders,
                'total_pax' => $totalCustomers,
                'total_customers' => $totalCustomers,
                'avg_daily_revenue' => count($data) > 0 ? $totalRevenue / count($data) : 0,
                'avg_daily_revenue_formatted' => count($data) > 0 ? 'Rp ' . number_format($totalRevenue / count($data), 0, ',', '.') : 'Rp 0',
                'avg_daily_orders' => count($data) > 0 ? $totalOrders / count($data) : 0,
                'avg_daily_pax' => count($data) > 0 ? $totalCustomers / count($data) : 0,
                'avg_daily_customers' => count($data) > 0 ? $totalCustomers / count($data) : 0,
                'avg_check' => $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0,
                'avg_check_formatted' => $totalCustomers > 0 ? 'Rp ' . number_format($totalRevenue / $totalCustomers, 0, ',', '.') : 'Rp 0'
            ]
        ]);
    }

    public function getOutletWeekendWeekdayDetail(Request $request)
    {
        $outletCode = $request->get('outlet_code');
        $dayType = $request->get('day_type');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        if (!$outletCode) {
            return response()->json(['error' => 'Outlet code is required'], 400);
        }

        if (!$dayType) {
            return response()->json(['error' => 'Day type is required'], 400);
        }

        if (!$dateFrom || !$dateTo) {
            return response()->json(['error' => 'Date range is required'], 400);
        }

        // Determine day condition based on day type
        $dayCondition = '';
        if ($dayType === 'Weekend') {
            $dayCondition = 'AND DAYOFWEEK(o.created_at) IN (1, 7)';
        } elseif ($dayType === 'Weekday') {
            $dayCondition = 'AND DAYOFWEEK(o.created_at) NOT IN (1, 7)';
        } else {
            return response()->json(['error' => 'Invalid day type'], 400);
        }

        $query = "
            SELECT 
                DATE(o.created_at) as date,
                COUNT(*) as orders,
                SUM(o.grand_total) as revenue,
                SUM(o.pax) as customers,
                AVG(o.grand_total) as avg_order_value,
                CASE 
                    WHEN SUM(o.pax) > 0 THEN SUM(o.grand_total) / SUM(o.pax)
                    ELSE 0
                END as avg_check
            FROM orders o
            WHERE o.kode_outlet = '{$outletCode}'
            AND o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY)
            {$dayCondition}
            GROUP BY DATE(o.created_at)
            ORDER BY date ASC
        ";

        $results = DB::select($query);

        // Get outlet info
        $outletInfo = DB::select("
            SELECT 
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.kode_outlet = '{$outletCode}'
            LIMIT 1
        ");

        $outlet = $outletInfo[0] ?? null;

        // Process data
        $data = [];
        $totalRevenue = 0;
        $totalOrders = 0;
        $totalCustomers = 0;

        foreach ($results as $result) {
            $data[] = [
                'date' => $result->date,
                'date_formatted' => Carbon::parse($result->date)->format('d F Y'),
                'orders' => (int) $result->orders,
                'revenue' => (float) $result->revenue,
                'revenue_formatted' => 'Rp ' . number_format($result->revenue, 0, ',', '.'),
                'pax' => (int) $result->customers,
                'customers' => (int) $result->customers,
                'avg_order_value' => (float) $result->avg_order_value,
                'avg_order_value_formatted' => 'Rp ' . number_format($result->avg_order_value, 0, ',', '.'),
                'avg_check' => (float) $result->avg_check,
                'avg_check_formatted' => 'Rp ' . number_format($result->avg_check, 0, ',', '.')
            ];

            $totalRevenue += (float) $result->revenue;
            $totalOrders += (int) $result->orders;
            $totalCustomers += (int) $result->customers;
        }

        return response()->json([
            'outlet' => $outlet ? [
                'outlet_code' => $outlet->kode_outlet,
                'outlet_name' => $outlet->outlet_name,
                'region_name' => $outlet->region_name,
                'region_code' => $outlet->region_code
            ] : null,
            'day_type' => $dayType,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
                'from_formatted' => Carbon::parse($dateFrom)->format('d F Y'),
                'to_formatted' => Carbon::parse($dateTo)->format('d F Y')
            ],
            'daily_data' => $data,
            'summary' => [
                'total_days' => count($data),
                'total_revenue' => $totalRevenue,
                'total_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                'total_orders' => $totalOrders,
                'total_pax' => $totalCustomers,
                'total_customers' => $totalCustomers,
                'avg_daily_revenue' => count($data) > 0 ? $totalRevenue / count($data) : 0,
                'avg_daily_revenue_formatted' => count($data) > 0 ? 'Rp ' . number_format($totalRevenue / count($data), 0, ',', '.') : 'Rp 0',
                'avg_daily_orders' => count($data) > 0 ? $totalOrders / count($data) : 0,
                'avg_daily_pax' => count($data) > 0 ? $totalCustomers / count($data) : 0,
                'avg_daily_customers' => count($data) > 0 ? $totalCustomers / count($data) : 0,
                'avg_check' => $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0,
                'avg_check_formatted' => $totalCustomers > 0 ? 'Rp ' . number_format($totalRevenue / $totalCustomers, 0, ',', '.') : 'Rp 0'
            ]
        ]);
    }

    public function getNonPromoBankDiscountTransactions(Request $request)
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $search = $request->get('search', '');
        $outlet = $request->get('outlet', '');
        $region = $request->get('region', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 10);

        if (!$dateFrom || !$dateTo) {
            return response()->json(['error' => 'Date range is required'], 400);
        }

        // Build base query with payment information, outlet name, and region
        $baseQuery = "
            SELECT 
                o.id,
                o.paid_number,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(region.name, 'N/A') as region_name,
                op.kasir,
                CONCAT(
                    op.payment_code, 
                    CASE WHEN op.payment_type THEN CONCAT(' - ', op.payment_type) ELSE '' END,
                    CASE WHEN op.payment_type = 'credit' AND op.card_first4 AND op.card_last4 
                         THEN CONCAT(' (****', op.card_first4, '****', op.card_last4, ')') 
                         ELSE '' END,
                    CASE WHEN op.payment_type = 'credit' AND op.approval_code 
                         THEN CONCAT(' [', op.approval_code, ']') 
                         ELSE '' END
                ) as payment_method,
                o.grand_total,
                o.manual_discount_amount,
                o.manual_discount_reason,
                o.created_at
            FROM orders o
            LEFT JOIN order_payment op ON o.id = op.order_id
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
            AND o.manual_discount_amount > 0
            AND (o.manual_discount_reason IS NULL OR o.manual_discount_reason NOT LIKE '%BANK%')
        ";

        // Add search filter
        if (!empty($search)) {
            $searchEscaped = DB::getPdo()->quote('%' . $search . '%');
            $baseQuery .= " AND (o.paid_number LIKE {$searchEscaped} OR op.kasir LIKE {$searchEscaped} OR o.manual_discount_reason LIKE {$searchEscaped})";
        }

        // Add outlet filter
        if (!empty($outlet)) {
            $outletEscaped = DB::getPdo()->quote($outlet);
            $baseQuery .= " AND o.kode_outlet = {$outletEscaped}";
        }

        // Add region filter
        if (!empty($region)) {
            $regionEscaped = DB::getPdo()->quote($region);
            $baseQuery .= " AND outlet.region_id = {$regionEscaped}";
        }

        // Get total count for pagination
        $countQuery = "SELECT COUNT(*) as total FROM ({$baseQuery}) as count_query";
        $totalCount = DB::select($countQuery)[0]->total;

        // Add pagination
        $offset = ($page - 1) * $perPage;
        $query = $baseQuery . " ORDER BY o.created_at DESC LIMIT {$perPage} OFFSET {$offset}";

        $transactions = DB::select($query);

        // Get grand total
        $grandTotalQuery = "
            SELECT 
                SUM(o.grand_total) as total_grand_total,
                SUM(o.manual_discount_amount) as total_discount_amount
            FROM orders o
            LEFT JOIN order_payment op ON o.id = op.order_id
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
            AND o.manual_discount_amount > 0
            AND (o.manual_discount_reason IS NULL OR o.manual_discount_reason NOT LIKE '%BANK%')
        ";

        if (!empty($search)) {
            $searchEscaped = DB::getPdo()->quote('%' . $search . '%');
            $grandTotalQuery .= " AND (o.paid_number LIKE {$searchEscaped} OR op.kasir LIKE {$searchEscaped} OR o.manual_discount_reason LIKE {$searchEscaped})";
        }

        if (!empty($outlet)) {
            $outletEscaped = DB::getPdo()->quote($outlet);
            $grandTotalQuery .= " AND o.kode_outlet = {$outletEscaped}";
        }

        if (!empty($region)) {
            $regionEscaped = DB::getPdo()->quote($region);
            $grandTotalQuery .= " AND outlet.region_id = {$regionEscaped}";
        }

        $grandTotalResult = DB::select($grandTotalQuery)[0];

        // Get breakdown by reason with categorization
        $breakdownQuery = "
            SELECT 
                CASE 
                    WHEN o.manual_discount_reason IS NULL OR o.manual_discount_reason = '' THEN 'No Reason'
                    WHEN LOWER(o.manual_discount_reason) LIKE '%entertainment%' THEN 'Entertainment'
                    WHEN LOWER(o.manual_discount_reason) LIKE '%investor%' THEN 'Investor'
                    WHEN LOWER(o.manual_discount_reason) LIKE '%founder%' THEN 'Founder'
                    WHEN LOWER(o.manual_discount_reason) LIKE '%guest satisfaction%' OR LOWER(o.manual_discount_reason) LIKE '%guest%' THEN 'Guest Satisfaction'
                    WHEN LOWER(o.manual_discount_reason) LIKE '%compliment%' THEN 'Compliment'
                    WHEN LOWER(o.manual_discount_reason) LIKE '%outlet city ledger%' OR LOWER(o.manual_discount_reason) LIKE '%city ledger%' THEN 'Outlet City Ledger'
                    ELSE 'Others'
                END as discount_reason,
                COUNT(*) as transaction_count,
                SUM(o.grand_total) as total_grand_total,
                SUM(o.manual_discount_amount) as total_discount_amount
            FROM orders o
            LEFT JOIN order_payment op ON o.id = op.order_id
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
            AND o.manual_discount_amount > 0
            AND (o.manual_discount_reason IS NULL OR o.manual_discount_reason NOT LIKE '%BANK%')
        ";

        if (!empty($search)) {
            $searchEscaped = DB::getPdo()->quote('%' . $search . '%');
            $breakdownQuery .= " AND (o.paid_number LIKE {$searchEscaped} OR op.kasir LIKE {$searchEscaped} OR o.manual_discount_reason LIKE {$searchEscaped})";
        }

        if (!empty($outlet)) {
            $outletEscaped = DB::getPdo()->quote($outlet);
            $breakdownQuery .= " AND o.kode_outlet = {$outletEscaped}";
        }

        if (!empty($region)) {
            $regionEscaped = DB::getPdo()->quote($region);
            $breakdownQuery .= " AND outlet.region_id = {$regionEscaped}";
        }

        $breakdownQuery .= " GROUP BY discount_reason ORDER BY total_discount_amount DESC";

        $breakdownResults = DB::select($breakdownQuery);

        // Calculate pagination info
        $totalPages = ceil($totalCount / $perPage);
        $hasNextPage = $page < $totalPages;
        $hasPrevPage = $page > 1;

        return response()->json([
            'transactions' => $transactions,
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => (int) $totalCount,
                'total_pages' => (int) $totalPages,
                'has_next_page' => $hasNextPage,
                'has_prev_page' => $hasPrevPage
            ],
            'grand_total' => [
                'total_grand_total' => (float) ($grandTotalResult->total_grand_total ?? 0),
                'total_discount_amount' => (float) ($grandTotalResult->total_discount_amount ?? 0)
            ],
            'breakdown_by_reason' => $breakdownResults
        ]);
    }

    public function getPromoUsageByOutlet(Request $request)
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (!$dateFrom || !$dateTo) {
            return response()->json(['error' => 'Date range is required'], 400);
        }

        // Get promo usage by outlet with detailed breakdown
        // Fix: Use subquery to get unique (order_id, promo_id) combinations first
        $query = "
            SELECT 
                promo_usage.kode_outlet,
                promo_usage.outlet_name,
                promo_usage.region_name,
                promo_usage.region_code,
                promo_usage.promo_code,
                promo_usage.promo_name,
                COUNT(*) as usage_count,
                SUM(promo_usage.grand_total) as total_transaction_value,
                SUM(promo_usage.discount / promo_usage.promo_count) as total_discount_amount,
                AVG(promo_usage.discount / promo_usage.promo_count) as avg_discount_amount
            FROM (
                SELECT DISTINCT
                    o.id as order_id,
                    o.kode_outlet,
                    COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                    COALESCE(region.name, 'N/A') as region_name,
                    COALESCE(region.code, 'UNK') as region_code,
                    p.code as promo_code,
                    p.name as promo_name,
                    o.grand_total,
                    o.discount,
                    promo_count.promo_count
                FROM orders o
                INNER JOIN order_promos op ON o.id = op.order_id
                INNER JOIN promos p ON op.promo_id = p.id
                LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
                LEFT JOIN regions region ON outlet.region_id = region.id
                INNER JOIN (
                    SELECT 
                        o2.id as order_id,
                        COUNT(DISTINCT op2.promo_id) as promo_count
                    FROM orders o2
                    INNER JOIN order_promos op2 ON o2.id = op2.order_id
                    INNER JOIN promos p2 ON op2.promo_id = p2.id
                    WHERE o2.created_at >= '{$dateFrom} 00:00:00' AND o2.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
                    AND p2.status = 'active'
                    AND o2.discount > 0
                    GROUP BY o2.id
                ) as promo_count ON o.id = promo_count.order_id
                WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
                AND p.status = 'active'
                AND o.discount > 0
                GROUP BY o.id, p.id, o.kode_outlet, outlet.nama_outlet, region.name, region.code, p.code, p.name, o.grand_total, o.discount, promo_count.promo_count
            ) as promo_usage
            GROUP BY promo_usage.kode_outlet, promo_usage.outlet_name, promo_usage.region_name, promo_usage.region_code, promo_usage.promo_code, promo_usage.promo_name
            ORDER BY promo_usage.region_name, promo_usage.outlet_name, total_discount_amount DESC
        ";

        $results = DB::select($query);

        // Get outlet totals based on discount > 0 in orders table only
        // Use EXISTS subquery to avoid double counting when one order has multiple promos
        $outletTotalsQuery = "
            SELECT 
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(region.name, 'N/A') as region_name,
                COUNT(DISTINCT o.id) as total_usage_count,
                SUM(o.grand_total) as total_transaction_value,
                SUM(o.discount) as total_discount_amount
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
            AND o.discount > 0
            AND EXISTS (
                SELECT 1 FROM order_promos op
                INNER JOIN promos p ON op.promo_id = p.id
                WHERE op.order_id = o.id
                AND p.status = 'active'
            )
            GROUP BY o.kode_outlet, outlet.nama_outlet, region.name
            ORDER BY total_discount_amount DESC
        ";
        
        $outletTotals = DB::select($outletTotalsQuery);

        // Get region totals based on discount > 0 in orders table only
        // Use EXISTS subquery to avoid double counting when one order has multiple promos
        $regionTotalsQuery = "
            SELECT 
                COALESCE(region.name, 'N/A') as region_name,
                COALESCE(region.code, 'UNK') as region_code,
                COUNT(DISTINCT o.id) as total_usage_count,
                SUM(o.grand_total) as total_transaction_value,
                SUM(o.discount) as total_discount_amount,
                COUNT(DISTINCT o.kode_outlet) as total_outlets
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
            AND o.discount > 0
            AND EXISTS (
                SELECT 1 FROM order_promos op
                INNER JOIN promos p ON op.promo_id = p.id
                WHERE op.order_id = o.id
                AND p.status = 'active'
            )
            GROUP BY region.name, region.code
            ORDER BY total_discount_amount DESC
        ";
        
        $regionTotals = DB::select($regionTotalsQuery);

        // Group by region first, then by outlet
        $regionGroupedResults = [];
        $regionSummary = [];
        
        // Initialize region summary from totals query
        foreach ($regionTotals as $regionTotal) {
            $regionName = $regionTotal->region_name;
            $regionGroupedResults[$regionName] = [
                'region_name' => $regionName,
                'region_code' => $regionTotal->region_code,
                'total_usage_count' => (int) $regionTotal->total_usage_count,
                'total_transaction_value' => (float) $regionTotal->total_transaction_value,
                'total_discount_amount' => (float) $regionTotal->total_discount_amount,
                'total_outlets' => (int) $regionTotal->total_outlets,
                'outlets' => []
            ];
            
            $regionSummary[$regionName] = [
                'region_name' => $regionName,
                'region_code' => $regionTotal->region_code,
                'total_usage_count' => (int) $regionTotal->total_usage_count,
                'total_transaction_value' => (float) $regionTotal->total_transaction_value,
                'total_discount_amount' => (float) $regionTotal->total_discount_amount,
                'total_outlets' => (int) $regionTotal->total_outlets
            ];
        }
        
        // Initialize outlet totals in region grouped results
        foreach ($outletTotals as $outletTotal) {
            $regionName = $outletTotal->region_name;
            $outletKey = $outletTotal->kode_outlet;
            
            if (isset($regionGroupedResults[$regionName])) {
                $regionGroupedResults[$regionName]['outlets'][$outletKey] = [
                    'outlet_code' => $outletTotal->kode_outlet,
                    'outlet_name' => $outletTotal->outlet_name,
                    'total_usage_count' => (int) $outletTotal->total_usage_count,
                    'total_transaction_value' => (float) $outletTotal->total_transaction_value,
                    'total_discount_amount' => (float) $outletTotal->total_discount_amount,
                    'promos' => []
                ];
            }
        }

        // Now process detailed results for promos only
        foreach ($results as $result) {
            $regionName = $result->region_name;
            $outletKey = $result->kode_outlet;
            
            if (isset($regionGroupedResults[$regionName]['outlets'][$outletKey])) {
                $regionGroupedResults[$regionName]['outlets'][$outletKey]['promos'][] = [
                    'promo_code' => $result->promo_code,
                    'promo_name' => $result->promo_name,
                    'usage_count' => $result->usage_count,
                    'total_transaction_value' => $result->total_transaction_value,
                    'total_discount_amount' => $result->total_discount_amount,
                    'avg_discount_amount' => $result->avg_discount_amount
                ];
            }
        }

        // Convert outlets from associative array to indexed array and sort
        foreach ($regionGroupedResults as $regionName => $regionData) {
            $outlets = array_values($regionData['outlets']);
            usort($outlets, function($a, $b) {
                return $b['total_discount_amount'] <=> $a['total_discount_amount'];
            });
            $regionGroupedResults[$regionName]['outlets'] = $outlets;
        }

        // Convert to array and sort by total discount amount
        $finalResults = array_values($regionGroupedResults);
        usort($finalResults, function($a, $b) {
            return $b['total_discount_amount'] <=> $a['total_discount_amount'];
        });
        
        // Sort region summary by total discount amount
        $finalRegionSummary = array_values($regionSummary);
        usort($finalRegionSummary, function($a, $b) {
            return $b['total_discount_amount'] <=> $a['total_discount_amount'];
        });

        // Get total discount from all orders for comparison
        $totalDiscountQuery = "
            SELECT SUM(discount) as total_discount_all_orders
            FROM orders 
            WHERE created_at >= '{$dateFrom} 00:00:00' AND created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY)
        ";
        $totalDiscountAll = DB::select($totalDiscountQuery)[0]->total_discount_all_orders;

        // Get total discount from promo orders only (with discount > 0)
        // Use EXISTS subquery to avoid double counting when one order has multiple promos
        $totalPromoDiscountQuery = "
            SELECT SUM(o.discount) as total_discount_promo_orders
            FROM orders o
            WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
            AND o.discount > 0
            AND EXISTS (
                SELECT 1 FROM order_promos op
                INNER JOIN promos p ON op.promo_id = p.id
                WHERE op.order_id = o.id
                AND p.status = 'active'
            )
        ";
        $totalPromoDiscount = DB::select($totalPromoDiscountQuery)[0]->total_discount_promo_orders ?? 0;

        return response()->json([
            'success' => true,
            'data' => $finalResults,
            'region_summary' => $finalRegionSummary,
            'comparison' => [
                'total_discount_all_orders' => (float) $totalDiscountAll,
                'total_discount_promo_orders' => (float) $totalPromoDiscount,
                'difference' => (float) ($totalDiscountAll - $totalPromoDiscount)
            ]
        ]);
    }

    public function exportNonPromoBankDiscountTransactions(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $search = $request->get('search', '');
            $outlet = $request->get('outlet', '');
            $region = $request->get('region', '');

            if (!$dateFrom || !$dateTo) {
                return response()->json(['error' => 'Date range is required'], 400);
            }

            // Build query for export (get all data, no pagination)
            $query = "
                SELECT 
                    o.id,
                    o.paid_number,
                    COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                    COALESCE(region.name, 'N/A') as region_name,
                    op.kasir,
                    CONCAT(
                        op.payment_code, 
                        CASE WHEN op.payment_type THEN CONCAT(' - ', op.payment_type) ELSE '' END,
                        CASE WHEN op.payment_type = 'credit' AND op.card_first4 AND op.card_last4 
                             THEN CONCAT(' (****', op.card_first4, '****', op.card_last4, ')') 
                             ELSE '' END,
                        CASE WHEN op.payment_type = 'credit' AND op.approval_code 
                             THEN CONCAT(' [', op.approval_code, ']') 
                             ELSE '' END
                    ) as payment_method,
                    o.grand_total,
                    o.manual_discount_amount,
                    o.manual_discount_reason,
                    o.created_at
                FROM orders o
                LEFT JOIN order_payment op ON o.id = op.order_id
                LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
                LEFT JOIN regions region ON outlet.region_id = region.id
                WHERE o.created_at >= '{$dateFrom} 00:00:00' AND o.created_at < DATE_ADD('{$dateTo}', INTERVAL 1 DAY) 
                AND o.manual_discount_amount > 0
                AND (o.manual_discount_reason IS NULL OR o.manual_discount_reason NOT LIKE '%BANK%')
            ";

            // Add search filter
            if (!empty($search)) {
                $searchEscaped = DB::getPdo()->quote('%' . $search . '%');
                $query .= " AND (o.paid_number LIKE {$searchEscaped} OR op.kasir LIKE {$searchEscaped} OR o.manual_discount_reason LIKE {$searchEscaped})";
            }

            // Add outlet filter
            if (!empty($outlet)) {
                $outletEscaped = DB::getPdo()->quote($outlet);
                $query .= " AND o.kode_outlet = {$outletEscaped}";
            }

            // Add region filter
            if (!empty($region)) {
                $regionEscaped = DB::getPdo()->quote($region);
                $query .= " AND outlet.region_id = {$regionEscaped}";
            }

            $query .= " ORDER BY o.created_at DESC";

            $transactions = DB::select($query);

            // Generate CSV content
            $csvContent = "ID ORDER,PAID NUMBER,OUTLET,REGION,KASIR,PAYMENT METHOD,GRAND TOTAL,DISCOUNT AMOUNT,DISCOUNT REASON,CREATED AT\n";
            
            foreach ($transactions as $transaction) {
                $csvContent .= sprintf(
                    "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                    $transaction->id,
                    $transaction->paid_number,
                    $transaction->outlet_name,
                    $transaction->region_name,
                    $transaction->kasir,
                    $transaction->payment_method,
                    $transaction->grand_total,
                    $transaction->manual_discount_amount,
                    $transaction->manual_discount_reason ?? '',
                    $transaction->created_at
                );
            }

            $filename = 'non_promo_bank_discount_transactions_' . $dateFrom . '_to_' . $dateTo . '.csv';

            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

}
