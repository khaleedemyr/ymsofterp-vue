<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Helper service untuk AI mengakses database dengan aman
 * Menyediakan methods untuk query data spesifik berdasarkan kebutuhan AI
 */
class AIDatabaseHelper
{
    /**
     * Get revenue data untuk periode tertentu
     */
    public function getRevenueData($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            $query = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_orders,
                    SUM(grand_total) as total_revenue,
                    SUM(pax) as total_customers,
                    AVG(grand_total) as avg_order_value,
                    SUM(discount) as total_discount,
                    SUM(service) as total_service_charge
                FROM orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
            ";
            
            $params = [$dateFrom, $dateTo];
            
            if ($outletCode) {
                $query .= " AND kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= " GROUP BY DATE(created_at) ORDER BY date ASC";
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Revenue Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get top items untuk periode tertentu
     */
    public function getTopItems($dateFrom, $dateTo, $limit = 20, $outletCode = null)
    {
        try {
            $query = "
                SELECT 
                    oi.item_name,
                    SUM(oi.qty) as total_qty,
                    SUM(oi.subtotal) as total_revenue,
                    COUNT(DISTINCT oi.order_id) as order_count,
                    AVG(oi.price) as avg_price
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.id
                WHERE DATE(o.created_at) BETWEEN ? AND ?
            ";
            
            $params = [$dateFrom, $dateTo];
            
            if ($outletCode) {
                $query .= " AND o.kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= " GROUP BY oi.item_name ORDER BY total_revenue DESC LIMIT ?";
            $params[] = $limit;
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Top Items Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get revenue comparison antara dua periode
     */
    public function getRevenueComparison($period1From, $period1To, $period2From, $period2To, $outletCode = null)
    {
        try {
            $query = "
                SELECT 
                    'period1' as period,
                    COUNT(*) as total_orders,
                    SUM(grand_total) as total_revenue,
                    SUM(pax) as total_customers,
                    AVG(grand_total) as avg_order_value
                FROM orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
            ";
            
            $params = [$period1From, $period1To];
            
            if ($outletCode) {
                $query .= " AND kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= "
                UNION ALL
                SELECT 
                    'period2' as period,
                    COUNT(*) as total_orders,
                    SUM(grand_total) as total_revenue,
                    SUM(pax) as total_customers,
                    AVG(grand_total) as avg_order_value
                FROM orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
            ";
            
            $params = array_merge($params, [$period2From, $period2To]);
            
            if ($outletCode) {
                $query .= " AND kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Revenue Comparison Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get hourly sales data
     */
    public function getHourlySales($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            $query = "
                SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as orders,
                    SUM(grand_total) as revenue,
                    AVG(grand_total) as avg_order_value,
                    SUM(pax) as customers
                FROM orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
            ";
            
            $params = [$dateFrom, $dateTo];
            
            if ($outletCode) {
                $query .= " AND kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= " GROUP BY HOUR(created_at) ORDER BY hour ASC";
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Hourly Sales Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get payment methods breakdown
     */
    public function getPaymentMethods($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            $query = "
                SELECT 
                    op.payment_code,
                    op.payment_type,
                    COUNT(*) as transaction_count,
                    SUM(op.amount) as total_amount,
                    AVG(op.amount) as avg_amount
                FROM order_payment op
                INNER JOIN orders o ON op.order_id = o.id
                WHERE DATE(o.created_at) BETWEEN ? AND ?
            ";
            
            $params = [$dateFrom, $dateTo];
            
            if ($outletCode) {
                $query .= " AND o.kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= " GROUP BY op.payment_code, op.payment_type ORDER BY total_amount DESC";
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Payment Methods Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get outlet performance comparison
     */
    public function getOutletPerformance($dateFrom, $dateTo, $limit = 10)
    {
        try {
            $query = "
                SELECT 
                    o.kode_outlet,
                    COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                    COALESCE(region.name, 'Unknown') as region_name,
                    COUNT(*) as total_orders,
                    SUM(o.grand_total) as total_revenue,
                    SUM(o.pax) as total_customers,
                    AVG(o.grand_total) as avg_order_value
                FROM orders o
                LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
                LEFT JOIN regions region ON outlet.region_id = region.id
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                GROUP BY o.kode_outlet, outlet.nama_outlet, region.name
                ORDER BY total_revenue DESC
                LIMIT ?
            ";
            
            return DB::select($query, [$dateFrom, $dateTo, $limit]);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Outlet Performance Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get item sales detail untuk item tertentu
     */
    public function getItemSalesDetail($itemName, $dateFrom, $dateTo, $outletCode = null)
    {
        try {
            $query = "
                SELECT 
                    DATE(o.created_at) as date,
                    o.kode_outlet,
                    COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
                    SUM(oi.qty) as total_qty,
                    SUM(oi.subtotal) as total_revenue,
                    COUNT(DISTINCT oi.order_id) as order_count,
                    AVG(oi.price) as avg_price
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.id
                LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
                WHERE oi.item_name = ?
                AND DATE(o.created_at) BETWEEN ? AND ?
            ";
            
            $params = [$itemName, $dateFrom, $dateTo];
            
            if ($outletCode) {
                $query .= " AND o.kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= " GROUP BY DATE(o.created_at), o.kode_outlet, outlet.nama_outlet ORDER BY date ASC";
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Item Sales Detail Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get custom query dengan validasi (untuk query kompleks)
     * Hanya allow SELECT queries dan validasi parameter
     */
    public function executeSafeQuery($query, $params = [])
    {
        try {
            // Validasi: hanya SELECT queries
            $trimmedQuery = trim($query);
            if (stripos($trimmedQuery, 'SELECT') !== 0) {
                throw new \Exception('Only SELECT queries are allowed');
            }
            
            // Validasi: tidak boleh ada DROP, DELETE, UPDATE, INSERT, ALTER, etc
            $dangerousKeywords = ['DROP', 'DELETE', 'UPDATE', 'INSERT', 'ALTER', 'CREATE', 'TRUNCATE', 'EXEC', 'EXECUTE'];
            foreach ($dangerousKeywords as $keyword) {
                if (stripos($trimmedQuery, $keyword) !== false) {
                    throw new \Exception("Dangerous keyword '{$keyword}' is not allowed");
                }
            }
            
            // Execute query dengan parameter binding untuk prevent SQL injection
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Safe Query Error: ' . $e->getMessage(), [
                'query' => $query,
                'params' => $params
            ]);
            throw $e;
        }
    }
}

