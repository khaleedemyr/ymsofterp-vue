<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class SalesOutletDashboardController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        // Get dashboard data (always use daily period)
        $dashboardData = $this->getDashboardData($dateFrom, $dateTo, 'daily');
        
        return Inertia::render('SalesOutletDashboard/Index', [
            'dashboardData' => $dashboardData,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ]);
    }

    private function getDashboardData($dateFrom, $dateTo, $period)
    {
        // 1. Overview Metrics
        $overview = $this->getOverviewMetrics($dateFrom, $dateTo);
        
        // 2. Sales Trend
        $salesTrend = $this->getSalesTrend($dateFrom, $dateTo, $period);
        
        // 3. Top Items
        $topItems = $this->getTopItems($dateFrom, $dateTo);
        
        // 4. Payment Methods
        $paymentMethods = $this->getPaymentMethods($dateFrom, $dateTo);
        
        // 5. Hourly Sales
        $hourlySales = $this->getHourlySales($dateFrom, $dateTo);
        
        // 6. Recent Orders
        $recentOrders = $this->getRecentOrders($dateFrom, $dateTo);
        
        // 7. Promo Usage
        $promoUsage = $this->getPromoUsage($dateFrom, $dateTo);
        
        // 7.1. Bank Promo Discount
        $bankPromoDiscount = $this->getBankPromoDiscount($dateFrom, $dateTo);
        
        // 8. Average Order Value
        $avgOrderValue = $this->getAverageOrderValue($dateFrom, $dateTo);
        
        // 9. Peak Hours Analysis
        $peakHours = $this->getPeakHoursAnalysis($dateFrom, $dateTo);
        
        // 10. Lunch/Dinner Orders
        $lunchDinnerOrders = $this->getLunchDinnerOrders($dateFrom, $dateTo);
        
        // 11. Weekday/Weekend Revenue
        $weekdayWeekendRevenue = $this->getWeekdayWeekendRevenue($dateFrom, $dateTo);
        
        // 12. Revenue per Outlet by Region
        $revenuePerOutlet = $this->getRevenuePerOutlet($dateFrom, $dateTo);
        
        // 13. Revenue per Region
        $revenuePerRegion = $this->getRevenuePerRegion($dateFrom, $dateTo);

        return [
            'overview' => $overview,
            'salesTrend' => $salesTrend,
            'topItems' => $topItems,
            'paymentMethods' => $paymentMethods,
            'hourlySales' => $hourlySales,
            'recentOrders' => $recentOrders,
            'promoUsage' => $promoUsage,
            'bankPromoDiscount' => $bankPromoDiscount,
            'avgOrderValue' => $avgOrderValue,
            'peakHours' => $peakHours,
            'lunchDinnerOrders' => $lunchDinnerOrders,
            'weekdayWeekendRevenue' => $weekdayWeekendRevenue,
            'revenuePerOutlet' => $revenuePerOutlet,
            'revenuePerRegion' => $revenuePerRegion
        ];
    }

    private function getOverviewMetrics($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                COUNT(*) as total_orders,
                SUM(grand_total) as total_revenue,
                AVG(grand_total) as avg_order_value,
                SUM(pax) as total_customers,
                AVG(pax) as avg_pax_per_order,
                SUM(discount) as total_discount,
                SUM(service) as total_service_charge,
                SUM(commfee) as total_commission_fee,
                SUM(manual_discount_amount) as total_manual_discount
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
        ";

        $result = DB::select($query)[0];

        // Calculate growth (compare with previous period)
        $prevDateFrom = Carbon::parse($dateFrom)->subDays(Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)))->format('Y-m-d');
        $prevDateTo = Carbon::parse($dateFrom)->subDay()->format('Y-m-d');
        
        $prevQuery = "
            SELECT 
                COUNT(*) as total_orders,
                SUM(grand_total) as total_revenue
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$prevDateFrom}' AND '{$prevDateTo}' 
        ";
        
        $prevResult = DB::select($prevQuery)[0];
        
        $revenueGrowth = $prevResult->total_revenue > 0 
            ? (($result->total_revenue - $prevResult->total_revenue) / $prevResult->total_revenue) * 100 
            : 0;
            
        $orderGrowth = $prevResult->total_orders > 0 
            ? (($result->total_orders - $prevResult->total_orders) / $prevResult->total_orders) * 100 
            : 0;

        // Calculate Average Check (total omzet / total pax)
        $avg_check = $result->total_customers > 0 ? $result->total_revenue / $result->total_customers : 0;

        return [
            'total_orders' => (int) $result->total_orders,
            'total_revenue' => (float) $result->total_revenue,
            'avg_order_value' => (float) $result->avg_order_value,
            'total_customers' => (int) $result->total_customers,
            'avg_pax_per_order' => (float) $result->avg_pax_per_order,
            'avg_check' => (float) $avg_check,
            'total_discount' => (float) $result->total_discount,
            'total_service_charge' => (float) $result->total_service_charge,
            'total_commission_fee' => (float) $result->total_commission_fee,
            'total_manual_discount' => (float) $result->total_manual_discount,
            'revenue_growth' => (float) $revenueGrowth,
            'order_growth' => (float) $orderGrowth
        ];
    }

    private function getSalesTrend($dateFrom, $dateTo, $period)
    {
        // Always use daily period for sales trend
        $query = "
            SELECT 
                DATE(created_at) as period,
                COUNT(*) as orders,
                SUM(grand_total) as revenue,
                AVG(grand_total) as avg_order_value,
                SUM(pax) as customers
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
            GROUP BY DATE(created_at)
            ORDER BY period ASC
        ";

        return DB::select($query);
    }

    private function getTopItems($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                oi.item_name,
                SUM(oi.qty) as total_qty,
                SUM(oi.subtotal) as total_revenue,
                COUNT(DISTINCT oi.order_id) as order_count,
                AVG(oi.price) as avg_price
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            GROUP BY oi.item_name
            ORDER BY total_revenue DESC
            LIMIT 10
        ";

        return DB::select($query);
    }

    private function getPaymentMethods($dateFrom, $dateTo)
    {
        // Get payment methods grouped by payment_code for chart
        $chartQuery = "
            SELECT 
                op.payment_code,
                COUNT(*) as transaction_count,
                SUM(op.amount) as total_amount,
                AVG(op.amount) as avg_amount
            FROM order_payment op
            INNER JOIN orders o ON op.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            GROUP BY op.payment_code
            ORDER BY total_amount DESC
        ";

        $chartData = DB::select($chartQuery);

        // Get detailed breakdown by payment_type for each payment_code
        $detailQuery = "
            SELECT 
                op.payment_code,
                op.payment_type,
                COUNT(*) as transaction_count,
                SUM(op.amount) as total_amount,
                AVG(op.amount) as avg_amount
            FROM order_payment op
            INNER JOIN orders o ON op.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            GROUP BY op.payment_code, op.payment_type
            ORDER BY op.payment_code, total_amount DESC
        ";

        $detailData = DB::select($detailQuery);

        // Group detail data by payment_code
        $groupedDetails = [];
        foreach ($detailData as $detail) {
            if (!isset($groupedDetails[$detail->payment_code])) {
                $groupedDetails[$detail->payment_code] = [];
            }
            $groupedDetails[$detail->payment_code][] = $detail;
        }

        // Combine chart data with details
        $result = [];
        foreach ($chartData as $chart) {
            $result[] = [
                'payment_code' => $chart->payment_code,
                'transaction_count' => $chart->transaction_count,
                'total_amount' => $chart->total_amount,
                'avg_amount' => $chart->avg_amount,
                'details' => $groupedDetails[$chart->payment_code] ?? []
            ];
        }

        return $result;
    }

    private function getHourlySales($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as orders,
                SUM(grand_total) as revenue,
                AVG(grand_total) as avg_order_value
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC
        ";

        return DB::select($query);
    }


    private function getRecentOrders($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                o.id,
                o.nomor,
                o.table,
                o.member_name,
                o.pax,
                o.grand_total,
                o.status,
                o.created_at,
                o.waiters,
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            ORDER BY o.created_at DESC
            LIMIT 20
        ";

        return DB::select($query);
    }

    private function getPromoUsage($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                COUNT(DISTINCT op.order_id) as orders_with_promo,
                COUNT(op.id) as total_promo_usage
            FROM order_promos op
            INNER JOIN orders o ON op.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        ";

        $result = DB::select($query)[0];

        // Get total orders for percentage calculation
        $totalOrdersQuery = "
            SELECT COUNT(*) as total_orders
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        ";

        $totalOrders = DB::select($totalOrdersQuery)[0]->total_orders;

        return [
            'orders_with_promo' => (int) $result->orders_with_promo,
            'total_promo_usage' => (int) $result->total_promo_usage,
            'promo_usage_percentage' => $totalOrders > 0 ? (($result->orders_with_promo / $totalOrders) * 100) : 0
        ];
    }

    private function getBankPromoDiscount($dateFrom, $dateTo)
    {
        // Get orders with bank promo discount
        $query = "
            SELECT 
                COUNT(*) as orders_with_bank_promo,
                SUM(manual_discount_amount) as total_bank_discount_amount,
                AVG(manual_discount_amount) as avg_bank_discount_amount
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            AND manual_discount_reason LIKE '%BANK%'
        ";

        $result = DB::select($query)[0];

        // Get total orders for percentage calculation
        $totalOrdersQuery = "
            SELECT COUNT(*) as total_orders
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        ";

        $totalOrders = DB::select($totalOrdersQuery)[0]->total_orders;

        return [
            'orders_with_bank_promo' => (int) $result->orders_with_bank_promo,
            'total_bank_discount_amount' => (float) $result->total_bank_discount_amount,
            'avg_bank_discount_amount' => (float) $result->avg_bank_discount_amount,
            'bank_promo_percentage' => $totalOrders > 0 ? (($result->orders_with_bank_promo / $totalOrders) * 100) : 0
        ];
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
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
                WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
                WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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

    private function getAverageOrderValue($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                AVG(grand_total) as avg_order_value,
                MIN(grand_total) as min_order_value,
                MAX(grand_total) as max_order_value
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        ";

        $result = DB::select($query)[0];
        
        // Skip median calculation for now to avoid MySQL compatibility issues
        // Use average as median approximation for simplicity
        $result->median_order_value = $result->avg_order_value;
        
        return $result;
    }

    private function getPeakHoursAnalysis($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as order_count,
                SUM(grand_total) as revenue,
                AVG(grand_total) as avg_order_value,
                SUM(pax) as total_customers
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            GROUP BY HOUR(created_at)
            ORDER BY order_count DESC
            LIMIT 5
        ";

        return DB::select($query);
    }


    private function getLunchDinnerOrders($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                CASE 
                    WHEN HOUR(created_at) <= 17 THEN 'Lunch'
                    ELSE 'Dinner'
                END as period,
                COUNT(*) as order_count,
                SUM(grand_total) as total_revenue,
                SUM(pax) as total_pax,
                AVG(grand_total) as avg_order_value
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            GROUP BY 
                CASE 
                    WHEN HOUR(created_at) <= 17 THEN 'Lunch'
                    ELSE 'Dinner'
                END
            ORDER BY period
        ";

        $results = DB::select($query);
        
        // Initialize with default values
        $data = [
            'lunch' => [
                'order_count' => 0,
                'total_revenue' => 0,
                'total_pax' => 0,
                'avg_order_value' => 0
            ],
            'dinner' => [
                'order_count' => 0,
                'total_revenue' => 0,
                'total_pax' => 0,
                'avg_order_value' => 0
            ]
        ];
        
        // Fill in actual data
        foreach ($results as $result) {
            $period = strtolower($result->period);
            $data[$period] = [
                'order_count' => (int) $result->order_count,
                'total_revenue' => (float) $result->total_revenue,
                'total_pax' => (int) $result->total_pax,
                'avg_order_value' => (float) $result->avg_order_value
            ];
        }
        
        return $data;
    }

    private function getWeekdayWeekendRevenue($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                CASE 
                    WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend'
                    ELSE 'Weekday'
                END as period,
                COUNT(*) as order_count,
                SUM(grand_total) as total_revenue,
                SUM(pax) as total_pax,
                AVG(grand_total) as avg_order_value
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            GROUP BY 
                CASE 
                    WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend'
                    ELSE 'Weekday'
                END
            ORDER BY period
        ";

        $results = DB::select($query);
        
        // Initialize with default values
        $data = [
            'weekday' => [
                'order_count' => 0,
                'total_revenue' => 0,
                'total_pax' => 0,
                'avg_order_value' => 0
            ],
            'weekend' => [
                'order_count' => 0,
                'total_revenue' => 0,
                'total_pax' => 0,
                'avg_order_value' => 0
            ]
        ];
        
        // Fill in actual data
        foreach ($results as $result) {
            $period = strtolower($result->period);
            $data[$period] = [
                'order_count' => (int) $result->order_count,
                'total_revenue' => (float) $result->total_revenue,
                'total_pax' => (int) $result->total_pax,
                'avg_order_value' => (float) $result->avg_order_value
            ];
        }
        
        return $data;
    }


    private function getRevenuePerOutlet($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code,
                COUNT(*) as order_count,
                SUM(o.grand_total) as total_revenue,
                SUM(o.pax) as total_pax,
                AVG(o.grand_total) as avg_order_value
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
            GROUP BY o.kode_outlet, outlet.nama_outlet, region.name, region.code
            ORDER BY total_revenue DESC
        ";

        $results = DB::select($query);

        // Group by region
        $data = [];
        foreach ($results as $result) {
            $regionName = $result->region_name;
            $regionCode = $result->region_code;
            
            if (!isset($data[$regionName])) {
                $data[$regionName] = [
                    'region_code' => $regionCode,
                    'outlets' => [],
                    'total_revenue' => 0,
                    'total_orders' => 0,
                    'total_pax' => 0
                ];
            }
            
            $data[$regionName]['outlets'][] = [
                'outlet_code' => $result->kode_outlet,
                'outlet_name' => $result->outlet_name,
                'order_count' => (int) $result->order_count,
                'total_revenue' => (float) $result->total_revenue,
                'total_pax' => (int) $result->total_pax,
                'avg_order_value' => (float) $result->avg_order_value
            ];
            
            $data[$regionName]['total_revenue'] += (float) $result->total_revenue;
            $data[$regionName]['total_orders'] += (int) $result->order_count;
            $data[$regionName]['total_pax'] += (int) $result->total_pax;
        }

        return $data;
    }

    private function getRevenuePerRegion($dateFrom, $dateTo)
    {
        // 1. Total Revenue per Region
        $totalRevenueQuery = "
            SELECT 
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code,
                COUNT(*) as total_orders,
                SUM(o.grand_total) as total_revenue,
                SUM(o.pax) as total_pax,
                AVG(o.grand_total) as avg_order_value
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
            GROUP BY region.name, region.code
            ORDER BY total_revenue DESC
        ";

        $totalRevenueResults = DB::select($totalRevenueQuery);

        // 2. Lunch/Dinner Revenue per Region
        $lunchDinnerQuery = "
            SELECT 
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code,
                CASE 
                    WHEN HOUR(o.created_at) <= 17 THEN 'Lunch'
                    ELSE 'Dinner'
                END as period,
                COUNT(*) as order_count,
                SUM(o.grand_total) as total_revenue,
                SUM(o.pax) as total_pax,
                AVG(o.grand_total) as avg_order_value
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
            GROUP BY region.name, region.code, 
                CASE 
                    WHEN HOUR(o.created_at) <= 17 THEN 'Lunch'
                    ELSE 'Dinner'
                END
            ORDER BY region.name, period
        ";

        $lunchDinnerResults = DB::select($lunchDinnerQuery);

        // 3. Weekday/Weekend Revenue per Region
        $weekdayWeekendQuery = "
            SELECT 
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code,
                CASE 
                    WHEN DAYOFWEEK(o.created_at) IN (1, 7) THEN 'Weekend'
                    ELSE 'Weekday'
                END as period,
                COUNT(*) as order_count,
                SUM(o.grand_total) as total_revenue,
                SUM(o.pax) as total_pax,
                AVG(o.grand_total) as avg_order_value
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
            GROUP BY region.name, region.code, 
                CASE 
                    WHEN DAYOFWEEK(o.created_at) IN (1, 7) THEN 'Weekend'
                    ELSE 'Weekday'
                END
            ORDER BY region.name, period
        ";

        $weekdayWeekendResults = DB::select($weekdayWeekendQuery);

        // Process data
        $data = [
            'total_revenue' => [],
            'lunch_dinner' => [],
            'weekday_weekend' => []
        ];

        // Process total revenue
        foreach ($totalRevenueResults as $result) {
            $data['total_revenue'][] = [
                'region_name' => $result->region_name,
                'region_code' => $result->region_code,
                'total_orders' => (int) $result->total_orders,
                'total_revenue' => (float) $result->total_revenue,
                'total_pax' => (int) $result->total_pax,
                'avg_order_value' => (float) $result->avg_order_value
            ];
        }

        // Process lunch/dinner data
        $lunchDinnerData = [];
        foreach ($lunchDinnerResults as $result) {
            $regionName = $result->region_name;
            $period = strtolower($result->period);
            
            if (!isset($lunchDinnerData[$regionName])) {
                $lunchDinnerData[$regionName] = [
                    'region_code' => $result->region_code,
                    'lunch' => [
                        'order_count' => 0,
                        'total_revenue' => 0,
                        'total_pax' => 0,
                        'avg_order_value' => 0
                    ],
                    'dinner' => [
                        'order_count' => 0,
                        'total_revenue' => 0,
                        'total_pax' => 0,
                        'avg_order_value' => 0
                    ]
                ];
            }
            
            $lunchDinnerData[$regionName][$period] = [
                'order_count' => (int) $result->order_count,
                'total_revenue' => (float) $result->total_revenue,
                'total_pax' => (int) $result->total_pax,
                'avg_order_value' => (float) $result->avg_order_value
            ];
        }
        $data['lunch_dinner'] = $lunchDinnerData;

        // Process weekday/weekend data
        $weekdayWeekendData = [];
        foreach ($weekdayWeekendResults as $result) {
            $regionName = $result->region_name;
            $period = strtolower($result->period);
            
            if (!isset($weekdayWeekendData[$regionName])) {
                $weekdayWeekendData[$regionName] = [
                    'region_code' => $result->region_code,
                    'weekday' => [
                        'order_count' => 0,
                        'total_revenue' => 0,
                        'total_pax' => 0,
                        'avg_order_value' => 0
                    ],
                    'weekend' => [
                        'order_count' => 0,
                        'total_revenue' => 0,
                        'total_pax' => 0,
                        'avg_order_value' => 0
                    ]
                ];
            }
            
            $weekdayWeekendData[$regionName][$period] = [
                'order_count' => (int) $result->order_count,
                'total_revenue' => (float) $result->total_revenue,
                'total_pax' => (int) $result->total_pax,
                'avg_order_value' => (float) $result->avg_order_value
            ];
        }
        $data['weekday_weekend'] = $weekdayWeekendData;

        return $data;
    }

    public function getMenuRegionData(Request $request)
    {
        $itemName = $request->get('item_name');
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

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
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
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
            WHERE DATE(o.created_at) = '{$date}'
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
            AND DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
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
            ->whereDate('created_at', $date)
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
            ->whereDate('orders.created_at', $date)
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

}
