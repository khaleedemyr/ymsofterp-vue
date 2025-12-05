<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesOutletDashboardService
{
    /**
     * Calculate comprehensive sales metrics for dashboard
     */
    public function calculateMetrics($outletCode = 'ALL', $dateFrom = null, $dateTo = null)
    {
        $dateFrom = $dateFrom ?: Carbon::now()->startOfMonth()->format('Y-m-d');
        $dateTo = $dateTo ?: Carbon::now()->format('Y-m-d');
        $outletFilter = $outletCode !== 'ALL' ? "AND kode_outlet = '{$outletCode}'" : '';

        return [
            'overview' => $this->getOverviewMetrics($outletFilter, $dateFrom, $dateTo),
            'sales_trend' => $this->getSalesTrend($outletFilter, $dateFrom, $dateTo),
            'top_items' => $this->getTopItems($outletFilter, $dateFrom, $dateTo),
            'payment_methods' => $this->getPaymentMethods($outletFilter, $dateFrom, $dateTo),
            'hourly_sales' => $this->getHourlySales($outletFilter, $dateFrom, $dateTo),
            'order_status' => $this->getOrderStatusDistribution($outletFilter, $dateFrom, $dateTo),
            'recent_orders' => $this->getRecentOrders($outletFilter, $dateFrom, $dateTo),
            'promo_usage' => $this->getPromoUsage($outletFilter, $dateFrom, $dateTo),
            'peak_hours' => $this->getPeakHoursAnalysis($outletFilter, $dateFrom, $dateTo),
            'customer_analysis' => $this->getCustomerAnalysis($outletFilter, $dateFrom, $dateTo),
            'revenue_breakdown' => $this->getRevenueBreakdown($outletFilter, $dateFrom, $dateTo)
        ];
    }

    /**
     * Get overview metrics with growth calculations
     */
    private function getOverviewMetrics($outletFilter, $dateFrom, $dateTo)
    {
        $currentPeriod = $this->getCurrentPeriodMetrics($outletFilter, $dateFrom, $dateTo);
        $previousPeriod = $this->getPreviousPeriodMetrics($outletFilter, $dateFrom, $dateTo);

        return [
            'total_orders' => $currentPeriod['total_orders'],
            'total_revenue' => $currentPeriod['total_revenue'],
            'avg_order_value' => $currentPeriod['avg_order_value'],
            'total_customers' => $currentPeriod['total_customers'],
            'avg_pax_per_order' => $currentPeriod['avg_pax_per_order'],
            'total_discount' => $currentPeriod['total_discount'],
            'total_service_charge' => $currentPeriod['total_service_charge'],
            'total_commission_fee' => $currentPeriod['total_commission_fee'],
            'total_manual_discount' => $currentPeriod['total_manual_discount'],
            'revenue_growth' => $this->calculateGrowth($currentPeriod['total_revenue'], $previousPeriod['total_revenue']),
            'order_growth' => $this->calculateGrowth($currentPeriod['total_orders'], $previousPeriod['total_orders']),
            'customer_growth' => $this->calculateGrowth($currentPeriod['total_customers'], $previousPeriod['total_customers'])
        ];
    }

    /**
     * Get current period metrics
     */
    private function getCurrentPeriodMetrics($outletFilter, $dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(grand_total), 0) as total_revenue,
                COALESCE(AVG(grand_total), 0) as avg_order_value,
                COALESCE(SUM(pax), 0) as total_customers,
                COALESCE(AVG(pax), 0) as avg_pax_per_order,
                COALESCE(SUM(discount), 0) as total_discount,
                COALESCE(SUM(service), 0) as total_service_charge,
                COALESCE(SUM(commfee), 0) as total_commission_fee,
                COALESCE(SUM(manual_discount_amount), 0) as total_manual_discount
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
        ";

        $result = DB::select($query)[0];
        
        return [
            'total_orders' => (int) $result->total_orders,
            'total_revenue' => (float) $result->total_revenue,
            'avg_order_value' => (float) $result->avg_order_value,
            'total_customers' => (int) $result->total_customers,
            'avg_pax_per_order' => (float) $result->avg_pax_per_order,
            'total_discount' => (float) $result->total_discount,
            'total_service_charge' => (float) $result->total_service_charge,
            'total_commission_fee' => (float) $result->total_commission_fee,
            'total_manual_discount' => (float) $result->total_manual_discount
        ];
    }

    /**
     * Get previous period metrics for comparison
     */
    private function getPreviousPeriodMetrics($outletFilter, $dateFrom, $dateTo)
    {
        $daysDiff = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo));
        $prevDateFrom = Carbon::parse($dateFrom)->subDays($daysDiff + 1)->format('Y-m-d');
        $prevDateTo = Carbon::parse($dateFrom)->subDay()->format('Y-m-d');

        $query = "
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(grand_total), 0) as total_revenue,
                COALESCE(SUM(pax), 0) as total_customers
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$prevDateFrom}' AND '{$prevDateTo}' 
            {$outletFilter}
        ";

        $result = DB::select($query)[0];
        
        return [
            'total_orders' => (int) $result->total_orders,
            'total_revenue' => (float) $result->total_revenue,
            'total_customers' => (int) $result->total_customers
        ];
    }

    /**
     * Calculate growth percentage
     */
    private function calculateGrowth($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return (($current - $previous) / $previous) * 100;
    }

    /**
     * Get sales trend data
     */
    private function getSalesTrend($outletFilter, $dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                DATE(created_at) as period,
                COUNT(*) as orders,
                COALESCE(SUM(grand_total), 0) as revenue,
                COALESCE(AVG(grand_total), 0) as avg_order_value,
                COALESCE(SUM(pax), 0) as customers
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
            GROUP BY DATE(created_at)
            ORDER BY period ASC
        ";

        return DB::select($query);
    }

    /**
     * Get top selling items
     */
    private function getTopItems($outletFilter, $dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                oi.item_name,
                SUM(oi.qty) as total_qty,
                SUM(oi.subtotal) as total_revenue,
                COUNT(DISTINCT oi.order_id) as order_count,
                AVG(oi.price) as avg_price,
                (SUM(oi.subtotal) / SUM(oi.qty)) as revenue_per_unit
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
            GROUP BY oi.item_name
            ORDER BY total_revenue DESC
            LIMIT 15
        ";

        return DB::select($query);
    }

    /**
     * Get payment methods analysis
     */
    private function getPaymentMethods($outletFilter, $dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                op.payment_type,
                COUNT(*) as transaction_count,
                SUM(op.amount) as total_amount,
                AVG(op.amount) as avg_amount,
                (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM order_payment op2 
                 INNER JOIN orders o2 ON op2.order_id = o2.id 
                 WHERE DATE(o2.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' {$outletFilter})) as percentage
            FROM order_payment op
            INNER JOIN orders o ON op.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
            GROUP BY op.payment_type
            ORDER BY total_amount DESC
        ";

        return DB::select($query);
    }

    /**
     * Get hourly sales analysis
     */
    private function getHourlySales($outletFilter, $dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as orders,
                COALESCE(SUM(grand_total), 0) as revenue,
                COALESCE(AVG(grand_total), 0) as avg_order_value,
                COALESCE(SUM(pax), 0) as customers
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC
        ";

        return DB::select($query);
    }

    /**
     * Get order status distribution
     */
    private function getOrderStatusDistribution($outletFilter, $dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                status,
                COUNT(*) as count,
                COALESCE(SUM(grand_total), 0) as total_revenue,
                (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' {$outletFilter})) as percentage
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
            GROUP BY status
            ORDER BY count DESC
        ";

        return DB::select($query);
    }

    /**
     * Get recent orders
     */
    private function getRecentOrders($outletFilter, $dateFrom, $dateTo)
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
                COUNT(oi.id) as item_count
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT 25
        ";

        return DB::select($query);
    }

    /**
     * Get promo usage analysis
     */
    private function getPromoUsage($outletFilter, $dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                COUNT(DISTINCT op.order_id) as orders_with_promo,
                COUNT(op.id) as total_promo_usage
            FROM order_promos op
            INNER JOIN orders o ON op.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
        ";

        $result = DB::select($query)[0];

        $totalOrdersQuery = "
            SELECT COUNT(*) as total_orders
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
        ";

        $totalOrders = DB::select($totalOrdersQuery)[0]->total_orders;

        return [
            'orders_with_promo' => (int) $result->orders_with_promo,
            'total_promo_usage' => (int) $result->total_promo_usage,
            'promo_usage_percentage' => $totalOrders > 0 ? (($result->orders_with_promo / $totalOrders) * 100) : 0
        ];
    }

    /**
     * Get peak hours analysis
     */
    private function getPeakHoursAnalysis($outletFilter, $dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as order_count,
                COALESCE(SUM(grand_total), 0) as revenue,
                COALESCE(AVG(grand_total), 0) as avg_order_value,
                COALESCE(SUM(pax), 0) as total_customers
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
            GROUP BY HOUR(created_at)
            ORDER BY order_count DESC
            LIMIT 5
        ";

        return DB::select($query);
    }

    /**
     * Get customer analysis
     */
    private function getCustomerAnalysis($outletFilter, $dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                COUNT(DISTINCT member_id) as unique_customers,
                COUNT(CASE WHEN member_id IS NOT NULL THEN 1 END) as member_orders,
                COUNT(CASE WHEN member_id IS NULL THEN 1 END) as walk_in_orders,
                AVG(pax) as avg_pax_per_order,
                MAX(pax) as max_pax_per_order,
                MIN(pax) as min_pax_per_order
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
        ";

        return DB::select($query)[0];
    }

    /**
     * Get revenue breakdown
     */
    private function getRevenueBreakdown($outletFilter, $dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                COALESCE(SUM(grand_total), 0) as gross_revenue,
                COALESCE(SUM(discount), 0) as total_discount,
                COALESCE(SUM(service), 0) as service_charge,
                COALESCE(SUM(commfee), 0) as commission_fee,
                COALESCE(SUM(manual_discount_amount), 0) as manual_discount,
                COALESCE(SUM(grand_total - discount - service - commfee - manual_discount_amount), 0) as net_revenue
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
        ";

        return DB::select($query)[0];
    }

    /**
     * Get outlet performance comparison
     */
    public function getOutletComparison($dateFrom = null, $dateTo = null)
    {
        $dateFrom = $dateFrom ?: Carbon::now()->startOfMonth()->format('Y-m-d');
        $dateTo = $dateTo ?: Carbon::now()->format('Y-m-d');

        $query = "
            SELECT 
                kode_outlet,
                COUNT(*) as total_orders,
                COALESCE(SUM(grand_total), 0) as total_revenue,
                COALESCE(AVG(grand_total), 0) as avg_order_value,
                COALESCE(SUM(pax), 0) as total_customers
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            AND kode_outlet IS NOT NULL
            GROUP BY kode_outlet
            ORDER BY total_revenue DESC
        ";

        return DB::select($query);
    }

    /**
     * Get daily performance summary
     */
    public function getDailyPerformance($outletCode = 'ALL', $date = null)
    {
        $date = $date ?: Carbon::now()->format('Y-m-d');
        $outletFilter = $outletCode !== 'ALL' ? "AND kode_outlet = '{$outletCode}'" : '';

        $query = "
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(grand_total), 0) as total_revenue,
                COALESCE(AVG(grand_total), 0) as avg_order_value,
                COALESCE(SUM(pax), 0) as total_customers,
                COALESCE(SUM(discount), 0) as total_discount,
                COALESCE(SUM(service), 0) as service_charge
            FROM orders 
            WHERE DATE(created_at) = '{$date}' 
            {$outletFilter}
        ";

        return DB::select($query)[0];
    }
}
