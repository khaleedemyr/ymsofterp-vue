<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class SalesOutletDashboardController extends Controller
{
    /**
     * API endpoint for mobile app - Sales Outlet Dashboard
     */
    public function dashboardApi(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        // Get dashboard data (always use daily period)
        $dashboardData = $this->getDashboardData($dateFrom, $dateTo, 'daily');
        
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
        
        // 12.1. Revenue per Outlet by Region (Lunch/Dinner)
        $revenuePerOutletLunchDinner = $this->getRevenuePerOutletLunchDinner($dateFrom, $dateTo);
        
        // 12.2. Revenue per Outlet by Region (Weekend/Weekday)
        $revenuePerOutletWeekendWeekday = $this->getRevenuePerOutletWeekendWeekday($dateFrom, $dateTo);
        
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
            'revenuePerOutletLunchDinner' => $revenuePerOutletLunchDinner,
            'revenuePerOutletWeekendWeekday' => $revenuePerOutletWeekendWeekday,
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
        // Count orders with promo AND discount > 0 (consistent with modal logic)
        $query = "
            SELECT 
                COUNT(DISTINCT op.order_id) as orders_with_promo,
                COUNT(op.id) as total_promo_usage
            FROM order_promos op
            INNER JOIN orders o ON op.order_id = o.id
            INNER JOIN promos p ON op.promo_id = p.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            AND p.status = 'active'
            AND o.discount > 0
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
        // Get orders with bank promo discount - use same logic as getBankPromoDiscountTransactions
        $query = "
            SELECT 
                COUNT(*) as orders_with_bank_promo,
                SUM(o.manual_discount_amount) as total_bank_discount_amount,
                AVG(o.manual_discount_amount) as avg_bank_discount_amount
            FROM orders o
            LEFT JOIN order_payment op ON o.id = op.order_id
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            AND o.manual_discount_reason LIKE '%BANK%'
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

    private function getRevenuePerOutletLunchDinner($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code,
                CASE 
                    WHEN HOUR(o.created_at) BETWEEN 11 AND 15 THEN 'Lunch'
                    WHEN HOUR(o.created_at) BETWEEN 17 AND 22 THEN 'Dinner'
                    ELSE 'Other'
                END as meal_period,
                COUNT(*) as order_count,
                SUM(o.grand_total) as total_revenue,
                SUM(o.pax) as total_pax,
                AVG(o.grand_total) as avg_order_value
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
            AND HOUR(o.created_at) BETWEEN 11 AND 22
            GROUP BY o.kode_outlet, outlet.nama_outlet, region.name, region.code, meal_period
            ORDER BY total_revenue DESC
        ";

        $results = DB::select($query);

        // Group by region and meal period
        $data = [];
        foreach ($results as $result) {
            $regionName = $result->region_name;
            $regionCode = $result->region_code;
            $mealPeriod = $result->meal_period;
            
            if (!isset($data[$regionName])) {
                $data[$regionName] = [
                    'region_code' => $regionCode,
                    'outlets' => [],
                    'lunch' => [
                        'total_revenue' => 0,
                        'total_orders' => 0,
                        'total_pax' => 0
                    ],
                    'dinner' => [
                        'total_revenue' => 0,
                        'total_orders' => 0,
                        'total_pax' => 0
                    ],
                    'total_revenue' => 0,
                    'total_orders' => 0,
                    'total_pax' => 0
                ];
            }
            
            // Initialize outlet if not exists
            $outletKey = $result->outlet_name;
            if (!isset($data[$regionName]['outlets'][$outletKey])) {
                $data[$regionName]['outlets'][$outletKey] = [
                    'outlet_code' => $result->kode_outlet,
                    'outlet_name' => $result->outlet_name,
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
                    ],
                    'total_revenue' => 0,
                    'total_orders' => 0,
                    'total_pax' => 0
                ];
            }
            
            // Add data to appropriate meal period
            if ($mealPeriod === 'Lunch') {
                $data[$regionName]['outlets'][$outletKey]['lunch'] = [
                    'order_count' => (int) $result->order_count,
                    'total_revenue' => (float) $result->total_revenue,
                    'total_pax' => (int) $result->total_pax,
                    'avg_order_value' => (float) $result->avg_order_value
                ];
                
                $data[$regionName]['lunch']['total_revenue'] += (float) $result->total_revenue;
                $data[$regionName]['lunch']['total_orders'] += (int) $result->order_count;
                $data[$regionName]['lunch']['total_pax'] += (int) $result->total_pax;
            } elseif ($mealPeriod === 'Dinner') {
                $data[$regionName]['outlets'][$outletKey]['dinner'] = [
                    'order_count' => (int) $result->order_count,
                    'total_revenue' => (float) $result->total_revenue,
                    'total_pax' => (int) $result->total_pax,
                    'avg_order_value' => (float) $result->avg_order_value
                ];
                
                $data[$regionName]['dinner']['total_revenue'] += (float) $result->total_revenue;
                $data[$regionName]['dinner']['total_orders'] += (int) $result->order_count;
                $data[$regionName]['dinner']['total_pax'] += (int) $result->total_pax;
            }
            
            // Update outlet totals
            $data[$regionName]['outlets'][$outletKey]['total_revenue'] += (float) $result->total_revenue;
            $data[$regionName]['outlets'][$outletKey]['total_orders'] += (int) $result->order_count;
            $data[$regionName]['outlets'][$outletKey]['total_pax'] += (int) $result->total_pax;
            
            // Update region totals
            $data[$regionName]['total_revenue'] += (float) $result->total_revenue;
            $data[$regionName]['total_orders'] += (int) $result->order_count;
            $data[$regionName]['total_pax'] += (int) $result->total_pax;
        }

        // Convert outlets from associative array to indexed array
        foreach ($data as $regionName => $regionData) {
            $data[$regionName]['outlets'] = array_values($regionData['outlets']);
        }

        return $data;
    }

    private function getRevenuePerOutletWeekendWeekday($dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                o.kode_outlet,
                COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                COALESCE(region.name, 'Unknown Region') as region_name,
                COALESCE(region.code, 'UNK') as region_code,
                CASE 
                    WHEN DAYOFWEEK(o.created_at) IN (1, 7) THEN 'Weekend'
                    ELSE 'Weekday'
                END as day_type,
                COUNT(*) as order_count,
                SUM(o.grand_total) as total_revenue,
                SUM(o.pax) as total_pax,
                AVG(o.grand_total) as avg_order_value
            FROM orders o
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            LEFT JOIN regions region ON outlet.region_id = region.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
            GROUP BY o.kode_outlet, outlet.nama_outlet, region.name, region.code, day_type
            ORDER BY total_revenue DESC
        ";

        $results = DB::select($query);

        // Group by region and day type
        $data = [];
        foreach ($results as $result) {
            $regionName = $result->region_name;
            $regionCode = $result->region_code;
            $dayType = $result->day_type;
            
            if (!isset($data[$regionName])) {
                $data[$regionName] = [
                    'region_code' => $regionCode,
                    'outlets' => [],
                    'weekend' => [
                        'total_revenue' => 0,
                        'total_orders' => 0,
                        'total_pax' => 0
                    ],
                    'weekday' => [
                        'total_revenue' => 0,
                        'total_orders' => 0,
                        'total_pax' => 0
                    ],
                    'total_revenue' => 0,
                    'total_orders' => 0,
                    'total_pax' => 0
                ];
            }
            
            // Initialize outlet if not exists
            $outletKey = $result->outlet_name;
            if (!isset($data[$regionName]['outlets'][$outletKey])) {
                $data[$regionName]['outlets'][$outletKey] = [
                    'outlet_code' => $result->kode_outlet,
                    'outlet_name' => $result->outlet_name,
                    'weekend' => [
                        'order_count' => 0,
                        'total_revenue' => 0,
                        'total_pax' => 0,
                        'avg_order_value' => 0
                    ],
                    'weekday' => [
                        'order_count' => 0,
                        'total_revenue' => 0,
                        'total_pax' => 0,
                        'avg_order_value' => 0
                    ],
                    'total_revenue' => 0,
                    'total_orders' => 0,
                    'total_pax' => 0
                ];
            }
            
            // Add data to appropriate day type
            if ($dayType === 'Weekend') {
                $data[$regionName]['outlets'][$outletKey]['weekend'] = [
                    'order_count' => (int) $result->order_count,
                    'total_revenue' => (float) $result->total_revenue,
                    'total_pax' => (int) $result->total_pax,
                    'avg_order_value' => (float) $result->avg_order_value
                ];
                
                $data[$regionName]['weekend']['total_revenue'] += (float) $result->total_revenue;
                $data[$regionName]['weekend']['total_orders'] += (int) $result->order_count;
                $data[$regionName]['weekend']['total_pax'] += (int) $result->total_pax;
            } elseif ($dayType === 'Weekday') {
                $data[$regionName]['outlets'][$outletKey]['weekday'] = [
                    'order_count' => (int) $result->order_count,
                    'total_revenue' => (float) $result->total_revenue,
                    'total_pax' => (int) $result->total_pax,
                    'avg_order_value' => (float) $result->avg_order_value
                ];
                
                $data[$regionName]['weekday']['total_revenue'] += (float) $result->total_revenue;
                $data[$regionName]['weekday']['total_orders'] += (int) $result->order_count;
                $data[$regionName]['weekday']['total_pax'] += (int) $result->total_pax;
            }
            
            // Update outlet totals
            $data[$regionName]['outlets'][$outletKey]['total_revenue'] += (float) $result->total_revenue;
            $data[$regionName]['outlets'][$outletKey]['total_orders'] += (int) $result->order_count;
            $data[$regionName]['outlets'][$outletKey]['total_pax'] += (int) $result->total_pax;
            
            // Update region totals
            $data[$regionName]['total_revenue'] += (float) $result->total_revenue;
            $data[$regionName]['total_orders'] += (int) $result->order_count;
            $data[$regionName]['total_pax'] += (int) $result->total_pax;
        }

        // Convert outlets from associative array to indexed array
        foreach ($data as $regionName => $regionData) {
            $data[$regionName]['outlets'] = array_values($regionData['outlets']);
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
            AND DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
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
            AND DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
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
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
                    WHERE DATE(o2.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
                    AND p2.status = 'active'
                    AND o2.discount > 0
                    GROUP BY o2.id
                ) as promo_count ON o.id = promo_count.order_id
                WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
        ";
        $totalDiscountAll = DB::select($totalDiscountQuery)[0]->total_discount_all_orders;

        // Get total discount from promo orders only (with discount > 0)
        // Use EXISTS subquery to avoid double counting when one order has multiple promos
        $totalPromoDiscountQuery = "
            SELECT SUM(o.discount) as total_discount_promo_orders
            FROM orders o
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
                WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
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
