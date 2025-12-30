<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SalesInventoryCorrelationService
{
    /**
     * Correlate sales and inventory data
     * 
     * @param int|null $outletId Outlet ID
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @return array
     */
    public function correlateSalesInventory($outletId = null, $dateFrom = null, $dateTo = null)
    {
        // Default to last 30 days if not provided
        if (!$dateFrom) {
            $dateFrom = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$dateTo) {
            $dateTo = date('Y-m-d');
        }

        $query = "
            SELECT 
                o.id_outlet,
                o.nama_outlet,
                o.qr_code as outlet_code,
                i.id as item_id,
                i.name as item_name,
                i.sku,
                -- Sales data
                COALESCE(SUM(oi.qty), 0) as total_qty_sold,
                COALESCE(SUM(oi.subtotal), 0) as total_revenue,
                COALESCE(COUNT(DISTINCT oi.order_id), 0) as order_count,
                COALESCE(AVG(oi.price), 0) as avg_price,
                -- Stock data
                COALESCE(MAX(s.qty_small), 0) as current_stock_small,
                COALESCE(MAX(s.qty_medium), 0) as current_stock_medium,
                COALESCE(MAX(s.qty_large), 0) as current_stock_large,
                COALESCE(MAX(s.value), 0) as current_stock_value,
                -- Stock movements
                COALESCE(SUM(CASE WHEN c.out_qty_small > 0 THEN c.out_qty_small ELSE 0 END), 0) as total_stock_out_small,
                COALESCE(SUM(CASE WHEN c.out_qty_medium > 0 THEN c.out_qty_medium ELSE 0 END), 0) as total_stock_out_medium,
                COALESCE(SUM(CASE WHEN c.out_qty_large > 0 THEN c.out_qty_large ELSE 0 END), 0) as total_stock_out_large,
                -- Correlation metrics
                CASE 
                    WHEN COALESCE(SUM(oi.qty), 0) > 0 AND COALESCE(MAX(s.qty_small), 0) > 0 
                    THEN COALESCE(MAX(s.qty_small), 0) / SUM(oi.qty)
                    ELSE 0
                END as stock_to_sales_ratio,
                CASE 
                    WHEN COALESCE(SUM(oi.qty), 0) > 0 
                    THEN COALESCE(SUM(CASE WHEN c.out_qty_small > 0 THEN c.out_qty_small ELSE 0 END), 0) / SUM(oi.qty)
                    ELSE 0
                END as stock_out_to_sales_ratio
            FROM items i
            LEFT JOIN order_items oi ON i.id = oi.item_id
            LEFT JOIN orders o2 ON oi.order_id = o2.id AND DATE(o2.created_at) BETWEEN ? AND ?
            LEFT JOIN tbl_data_outlet o ON o2.kode_outlet = o.qr_code
            LEFT JOIN outlet_food_inventory_items ifi ON i.id = ifi.item_id
            LEFT JOIN outlet_food_inventory_stocks s ON ifi.id = s.inventory_item_id 
                AND s.id_outlet = COALESCE(?, o.id_outlet)
            LEFT JOIN outlet_food_inventory_cards c ON ifi.id = c.inventory_item_id 
                AND c.id_outlet = COALESCE(?, o.id_outlet)
                AND c.date BETWEEN ? AND ?
        ";

        $params = [$dateFrom, $dateTo, $outletId, $outletId, $dateFrom, $dateTo];

        if ($outletId) {
            $query .= " AND o.id_outlet = ?";
            $params[] = $outletId;
        }

        $query .= " 
            GROUP BY o.id_outlet, o.nama_outlet, o.qr_code, i.id, i.name, i.sku
            HAVING total_qty_sold > 0 OR current_stock_small > 0 OR current_stock_medium > 0 OR current_stock_large > 0
            ORDER BY total_revenue DESC
            LIMIT 100
        ";

        return DB::select($query, $params);
    }

    /**
     * Detect stock-out impact on sales
     * 
     * @param int $itemId Item ID
     * @param int|null $outletId Outlet ID (optional)
     * @param string $date Date to check
     * @return array|null
     */
    public function detectStockOutImpact($itemId, $outletId = null, $date = null)
    {
        if (!$date) {
            $date = date('Y-m-d');
        }

        // Find first stock-out date
        $stockOutQuery = "
            SELECT 
                c.id_outlet,
                MIN(c.date) as first_stock_out_date
            FROM outlet_food_inventory_cards c
            INNER JOIN outlet_food_inventory_items ifi ON c.inventory_item_id = ifi.id
            WHERE ifi.item_id = ?
                AND c.saldo_qty_small <= 0 
                AND c.saldo_qty_medium <= 0 
                AND c.saldo_qty_large <= 0
        ";

        $stockOutParams = [$itemId];

        if ($outletId) {
            $stockOutQuery .= " AND c.id_outlet = ?";
            $stockOutParams[] = $outletId;
        }

        $stockOutQuery .= " GROUP BY c.id_outlet";

        $stockOutDates = DB::select($stockOutQuery, $stockOutParams);

        if (empty($stockOutDates)) {
            return null;
        }

        $results = [];

        foreach ($stockOutDates as $stockOut) {
            $outletId = $stockOut->id_outlet;
            $stockOutDate = $stockOut->first_stock_out_date;

            // Sales before stock-out (30 days before)
            $beforeDateFrom = date('Y-m-d', strtotime($stockOutDate . ' -30 days'));
            $beforeDateTo = date('Y-m-d', strtotime($stockOutDate . ' -1 day'));

            // Sales after stock-out (30 days after)
            $afterDateFrom = $stockOutDate;
            $afterDateTo = date('Y-m-d', strtotime($stockOutDate . ' +30 days'));

            $impactQuery = "
                SELECT 
                    o.id_outlet,
                    o.nama_outlet,
                    i.id as item_id,
                    i.name as item_name,
                    -- Before stock-out
                    SUM(CASE WHEN DATE(o2.created_at) BETWEEN ? AND ? THEN oi.qty ELSE 0 END) as qty_sold_before,
                    SUM(CASE WHEN DATE(o2.created_at) BETWEEN ? AND ? THEN oi.subtotal ELSE 0 END) as revenue_before,
                    COUNT(DISTINCT CASE WHEN DATE(o2.created_at) BETWEEN ? AND ? THEN oi.order_id ELSE NULL END) as orders_before,
                    -- After stock-out
                    SUM(CASE WHEN DATE(o2.created_at) BETWEEN ? AND ? THEN oi.qty ELSE 0 END) as qty_sold_after,
                    SUM(CASE WHEN DATE(o2.created_at) BETWEEN ? AND ? THEN oi.subtotal ELSE 0 END) as revenue_after,
                    COUNT(DISTINCT CASE WHEN DATE(o2.created_at) BETWEEN ? AND ? THEN oi.order_id ELSE NULL END) as orders_after
                FROM items i
                INNER JOIN order_items oi ON i.id = oi.item_id
                INNER JOIN orders o2 ON oi.order_id = o2.id
                INNER JOIN tbl_data_outlet o ON o2.kode_outlet = o.qr_code
                WHERE i.id = ?
                    AND o.id_outlet = ?
            ";

            $impactParams = [
                $beforeDateFrom, $beforeDateTo,
                $beforeDateFrom, $beforeDateTo,
                $beforeDateFrom, $beforeDateTo,
                $afterDateFrom, $afterDateTo,
                $afterDateFrom, $afterDateTo,
                $afterDateFrom, $afterDateTo,
                $itemId, $outletId
            ];

            $impact = DB::select($impactQuery, $impactParams)[0] ?? null;

            if ($impact && $impact->qty_sold_before > 0) {
                $impact->first_stock_out_date = $stockOutDate;
                $impact->revenue_impact = $impact->revenue_before - $impact->revenue_after;
                $impact->qty_impact = $impact->qty_sold_before - $impact->qty_sold_after;
                $impact->revenue_impact_percent = ($impact->revenue_impact / $impact->revenue_before) * 100;
                $impact->qty_impact_percent = ($impact->qty_impact / $impact->qty_sold_before) * 100;

                $results[] = $impact;
            }
        }

        return $results;
    }

    /**
     * Analyze overstock items
     * 
     * @param int|null $outletId Outlet ID
     * @param float $threshold Threshold ratio (stock to sales)
     * @return array
     */
    public function analyzeOverstock($outletId = null, $threshold = 3.0)
    {
        $dateFrom = date('Y-m-d', strtotime('-90 days'));
        $dateTo = date('Y-m-d');

        $query = "
            SELECT 
                o.id_outlet,
                o.nama_outlet,
                i.id as item_id,
                i.name as item_name,
                i.sku,
                -- Current stock
                COALESCE(MAX(s.qty_small), 0) as current_stock_small,
                COALESCE(MAX(s.qty_medium), 0) as current_stock_medium,
                COALESCE(MAX(s.qty_large), 0) as current_stock_large,
                COALESCE(MAX(s.value), 0) as current_stock_value,
                -- Sales (last 90 days)
                COALESCE(SUM(oi.qty), 0) as total_qty_sold_90d,
                COALESCE(SUM(oi.subtotal), 0) as total_revenue_90d,
                COALESCE(AVG(oi.qty), 0) as avg_daily_qty_sold,
                -- Overstock ratio
                CASE 
                    WHEN COALESCE(SUM(oi.qty), 0) > 0 
                    THEN COALESCE(MAX(s.qty_small), 0) / (SUM(oi.qty) / 90)
                    ELSE 999
                END as days_of_stock,
                CASE 
                    WHEN COALESCE(SUM(oi.qty), 0) > 0 
                    THEN COALESCE(MAX(s.qty_small), 0) / SUM(oi.qty)
                    ELSE 999
                END as stock_to_sales_ratio
            FROM items i
            LEFT JOIN outlet_food_inventory_items ifi ON i.id = ifi.item_id
            LEFT JOIN outlet_food_inventory_stocks s ON ifi.id = s.inventory_item_id
            LEFT JOIN tbl_data_outlet o ON s.id_outlet = o.id_outlet
            LEFT JOIN order_items oi ON i.id = oi.item_id
            LEFT JOIN orders o2 ON oi.order_id = o2.id AND DATE(o2.created_at) BETWEEN ? AND ?
            WHERE (s.qty_small > 0 OR s.qty_medium > 0 OR s.qty_large > 0)
        ";

        $params = [$dateFrom, $dateTo];

        if ($outletId) {
            $query .= " AND s.id_outlet = ?";
            $params[] = $outletId;
        }

        $query .= " 
            GROUP BY o.id_outlet, o.nama_outlet, i.id, i.name, i.sku
            HAVING stock_to_sales_ratio >= ?
            ORDER BY stock_to_sales_ratio DESC, current_stock_value DESC
        ";

        $params[] = $threshold;

        return DB::select($query, $params);
    }

    /**
     * Forecast demand based on historical sales
     * 
     * @param int $itemId Item ID
     * @param int|null $outletId Outlet ID (optional)
     * @param int $days Days to forecast
     * @return array
     */
    public function forecastDemand($itemId, $outletId = null, $days = 30)
    {
        // Get historical sales (last 90 days)
        $dateFrom = date('Y-m-d', strtotime('-90 days'));
        $dateTo = date('Y-m-d');

        $query = "
            SELECT 
                DATE(o2.created_at) as date,
                SUM(oi.qty) as daily_qty,
                COUNT(DISTINCT oi.order_id) as daily_orders,
                DAYOFWEEK(o2.created_at) as day_of_week,
                CASE 
                    WHEN DAYOFWEEK(o2.created_at) IN (1, 7) THEN 'weekend'
                    ELSE 'weekday'
                END as day_type
            FROM order_items oi
            INNER JOIN orders o2 ON oi.order_id = o2.id
            INNER JOIN tbl_data_outlet o ON o2.kode_outlet = o.qr_code
            WHERE oi.item_id = ?
                AND DATE(o2.created_at) BETWEEN ? AND ?
        ";

        $params = [$itemId, $dateFrom, $dateTo];

        if ($outletId) {
            $query .= " AND o.id_outlet = ?";
            $params[] = $outletId;
        }

        $query .= " 
            GROUP BY DATE(o2.created_at), DAYOFWEEK(o2.created_at)
            ORDER BY date ASC
        ";

        $historicalData = DB::select($query, $params);

        if (empty($historicalData)) {
            return [
                'forecast_qty' => 0,
                'forecast_orders' => 0,
                'confidence' => 'low',
                'method' => 'insufficient_data'
            ];
        }

        // Calculate average daily sales
        $totalQty = array_sum(array_column($historicalData, 'daily_qty'));
        $totalOrders = array_sum(array_column($historicalData, 'daily_orders'));
        $daysWithSales = count($historicalData);
        $avgDailyQty = $totalQty / max($daysWithSales, 1);
        $avgDailyOrders = $totalOrders / max($daysWithSales, 1);

        // Simple forecast: average daily * forecast days
        $forecastQty = $avgDailyQty * $days;
        $forecastOrders = $avgDailyOrders * $days;

        // Calculate confidence based on data consistency
        $qtyValues = array_column($historicalData, 'daily_qty');
        $mean = $avgDailyQty;
        $variance = 0;
        foreach ($qtyValues as $qty) {
            $variance += pow($qty - $mean, 2);
        }
        $stdDev = sqrt($variance / max(count($qtyValues), 1));
        $coefficientOfVariation = $mean > 0 ? $stdDev / $mean : 1;

        $confidence = 'medium';
        if ($coefficientOfVariation < 0.3) {
            $confidence = 'high';
        } elseif ($coefficientOfVariation > 0.7) {
            $confidence = 'low';
        }

        return [
            'forecast_qty' => round($forecastQty, 2),
            'forecast_orders' => round($forecastOrders, 2),
            'avg_daily_qty' => round($avgDailyQty, 2),
            'avg_daily_orders' => round($avgDailyOrders, 2),
            'days_with_sales' => $daysWithSales,
            'total_days' => 90,
            'confidence' => $confidence,
            'coefficient_of_variation' => round($coefficientOfVariation, 2),
            'method' => 'simple_average'
        ];
    }

    /**
     * Get items with low stock but high sales (potential stock-out risk)
     * 
     * @param int|null $outletId Outlet ID
     * @param int $days Days to analyze
     * @return array
     */
    public function getHighRiskItems($outletId = null, $days = 30)
    {
        $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
        $dateTo = date('Y-m-d');

        $query = "
            SELECT 
                o.id_outlet,
                o.nama_outlet,
                i.id as item_id,
                i.name as item_name,
                i.sku,
                i.min_stock,
                -- Current stock
                COALESCE(MAX(s.qty_small), 0) as current_stock_small,
                -- Sales (last N days)
                COALESCE(SUM(oi.qty), 0) as total_qty_sold,
                COALESCE(AVG(oi.qty), 0) as avg_daily_qty_sold,
                -- Days until stock-out
                CASE 
                    WHEN COALESCE(AVG(oi.qty), 0) > 0 
                    THEN COALESCE(MAX(s.qty_small), 0) / AVG(oi.qty)
                    ELSE 999
                END as days_until_stockout,
                -- Risk level
                CASE 
                    WHEN COALESCE(MAX(s.qty_small), 0) <= i.min_stock THEN 'critical'
                    WHEN COALESCE(MAX(s.qty_small), 0) / NULLIF(AVG(oi.qty), 0) < 7 THEN 'high'
                    WHEN COALESCE(MAX(s.qty_small), 0) / NULLIF(AVG(oi.qty), 0) < 14 THEN 'medium'
                    ELSE 'low'
                END as risk_level
            FROM items i
            LEFT JOIN outlet_food_inventory_items ifi ON i.id = ifi.item_id
            LEFT JOIN outlet_food_inventory_stocks s ON ifi.id = s.inventory_item_id
            LEFT JOIN tbl_data_outlet o ON s.id_outlet = o.id_outlet
            LEFT JOIN order_items oi ON i.id = oi.item_id
            LEFT JOIN orders o2 ON oi.order_id = o2.id AND DATE(o2.created_at) BETWEEN ? AND ?
            WHERE (s.qty_small > 0 OR s.qty_medium > 0 OR s.qty_large > 0)
                AND i.min_stock > 0
        ";

        $params = [$dateFrom, $dateTo];

        if ($outletId) {
            $query .= " AND s.id_outlet = ?";
            $params[] = $outletId;
        }

        $query .= " 
            GROUP BY o.id_outlet, o.nama_outlet, i.id, i.name, i.sku, i.min_stock
            HAVING total_qty_sold > 0
                AND (current_stock_small <= i.min_stock OR days_until_stockout < 14)
            ORDER BY risk_level DESC, days_until_stockout ASC
        ";

        return DB::select($query, $params);
    }
}

