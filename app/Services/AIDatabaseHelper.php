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
    
    /**
     * Get customer behavior data - Member vs Non-Member
     */
    public function getCustomerBehaviorData($dateFrom, $dateTo, $customerType = 'all', $outletCode = null)
    {
        try {
            $query = "
                SELECT 
                    CASE 
                        WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN 'member'
                        ELSE 'non_member'
                    END as customer_type,
                    COUNT(DISTINCT o.id) as total_orders,
                    COUNT(DISTINCT CASE WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id ELSE o.id END) as unique_customers,
                    SUM(o.grand_total) as total_revenue,
                    AVG(o.grand_total) as avg_order_value,
                    SUM(o.pax) as total_pax,
                    AVG(o.pax) as avg_pax,
                    SUM(o.discount) as total_discount,
                    AVG(o.discount) as avg_discount,
                    COUNT(DISTINCT DATE(o.created_at)) as active_days
                FROM orders o
                WHERE DATE(o.created_at) BETWEEN ? AND ?
            ";
            
            $params = [$dateFrom, $dateTo];
            
            if ($customerType !== 'all') {
                if ($customerType === 'member') {
                    $query .= " AND o.member_id IS NOT NULL AND o.member_id != ''";
                } else {
                    $query .= " AND (o.member_id IS NULL OR o.member_id = '')";
                }
            }
            
            if ($outletCode) {
                $query .= " AND o.kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= " GROUP BY customer_type";
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Customer Behavior Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get RFM Analysis (Recency, Frequency, Monetary) untuk members
     */
    public function getRFMAnalysis($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            $query = "
                SELECT 
                    o.member_id,
                    MAX(DATE(o.created_at)) as last_purchase_date,
                    DATEDIFF(?, MAX(DATE(o.created_at))) as recency_days,
                    COUNT(DISTINCT o.id) as frequency,
                    SUM(o.grand_total) as monetary_value,
                    AVG(o.grand_total) as avg_order_value,
                    COUNT(DISTINCT DATE(o.created_at)) as visit_days
                FROM orders o
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                AND o.member_id IS NOT NULL AND o.member_id != ''
            ";
            
            $params = [$dateTo, $dateFrom, $dateTo];
            
            if ($outletCode) {
                $query .= " AND o.kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= " GROUP BY o.member_id";
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get RFM Analysis Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get purchase frequency distribution
     */
    public function getPurchaseFrequencyDistribution($dateFrom, $dateTo, $customerType = 'all', $outletCode = null)
    {
        try {
            $query = "
                SELECT 
                    purchase_count,
                    COUNT(*) as customer_count
                FROM (
                    SELECT 
                        CASE 
                            WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id
                            ELSE CONCAT('non_member_', o.id)
                        END as customer_id,
                        COUNT(DISTINCT o.id) as purchase_count
                    FROM orders o
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
            ";
            
            $params = [$dateFrom, $dateTo];
            
            if ($customerType !== 'all') {
                if ($customerType === 'member') {
                    $query .= " AND o.member_id IS NOT NULL AND o.member_id != ''";
                } else {
                    $query .= " AND (o.member_id IS NULL OR o.member_id = '')";
                }
            }
            
            if ($outletCode) {
                $query .= " AND o.kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= "
                    GROUP BY customer_id
                ) as customer_purchases
                GROUP BY purchase_count
                ORDER BY purchase_count ASC
            ";
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Purchase Frequency Distribution Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get time pattern analysis (hour, day of week, month)
     */
    public function getTimePatternAnalysis($dateFrom, $dateTo, $customerType = 'all', $outletCode = null)
    {
        try {
            $query = "
                SELECT 
                    HOUR(o.created_at) as hour,
                    DAYNAME(o.created_at) as day_name,
                    DAYOFWEEK(o.created_at) as day_of_week,
                    MONTH(o.created_at) as month,
                    COUNT(*) as order_count,
                    SUM(o.grand_total) as revenue,
                    AVG(o.grand_total) as avg_order_value,
                    COUNT(DISTINCT CASE 
                        WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id 
                        ELSE CONCAT('non_member_', o.id) 
                    END) as unique_customers
                FROM orders o
                WHERE DATE(o.created_at) BETWEEN ? AND ?
            ";
            
            $params = [$dateFrom, $dateTo];
            
            if ($customerType !== 'all') {
                if ($customerType === 'member') {
                    $query .= " AND o.member_id IS NOT NULL AND o.member_id != ''";
                } else {
                    $query .= " AND (o.member_id IS NULL OR o.member_id = '')";
                }
            }
            
            if ($outletCode) {
                $query .= " AND o.kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= " GROUP BY hour, day_name, day_of_week, month ORDER BY hour, day_of_week";
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Time Pattern Analysis Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get product preference analysis
     */
    public function getProductPreferenceAnalysis($dateFrom, $dateTo, $customerType = 'all', $outletCode = null, $limit = 20)
    {
        try {
            $query = "
                SELECT 
                    oi.item_name,
                    oi.item_id,
                    COUNT(DISTINCT oi.order_id) as order_count,
                    SUM(oi.qty) as total_qty,
                    SUM(oi.subtotal) as total_revenue,
                    AVG(oi.price) as avg_price,
                    COUNT(DISTINCT CASE 
                        WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id 
                        ELSE CONCAT('non_member_', o.id) 
                    END) as unique_customers
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.id
                WHERE DATE(o.created_at) BETWEEN ? AND ?
            ";
            
            $params = [$dateFrom, $dateTo];
            
            if ($customerType !== 'all') {
                if ($customerType === 'member') {
                    $query .= " AND o.member_id IS NOT NULL AND o.member_id != ''";
                } else {
                    $query .= " AND (o.member_id IS NULL OR o.member_id = '')";
                }
            }
            
            if ($outletCode) {
                $query .= " AND o.kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= " GROUP BY oi.item_name, oi.item_id ORDER BY total_revenue DESC LIMIT ?";
            $params[] = $limit;
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Product Preference Analysis Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get churn risk analysis (members yang tidak belanja dalam X hari)
     */
    public function getChurnRiskAnalysis($daysThreshold = 30, $outletCode = null)
    {
        try {
            $cutoffDate = Carbon::now()->subDays($daysThreshold)->format('Y-m-d');
            
            $query = "
                SELECT 
                    o.member_id,
                    MAX(DATE(o.created_at)) as last_purchase_date,
                    DATEDIFF(?, MAX(DATE(o.created_at))) as days_since_last_purchase,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(o.grand_total) as lifetime_value,
                    AVG(o.grand_total) as avg_order_value
                FROM orders o
                WHERE o.member_id IS NOT NULL AND o.member_id != ''
                AND DATE(o.created_at) < ?
            ";
            
            $params = [Carbon::now()->format('Y-m-d'), $cutoffDate];
            
            if ($outletCode) {
                $query .= " AND o.kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            $query .= "
                GROUP BY o.member_id
                HAVING days_since_last_purchase >= ?
                ORDER BY days_since_last_purchase DESC
                LIMIT 100
            ";
            
            $params[] = $daysThreshold;
            
            return DB::select($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Churn Risk Analysis Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get member engagement metrics (promo usage, voucher usage, point redemption)
     */
    public function getMemberEngagementMetrics($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            $query = "
                SELECT 
                    COUNT(DISTINCT o.id) as total_orders,
                    COUNT(DISTINCT CASE WHEN o.promo_ids IS NOT NULL AND o.promo_ids != '' THEN o.id END) as promo_usage_count,
                    COUNT(DISTINCT CASE WHEN o.voucher_info IS NOT NULL AND o.voucher_info != '' THEN o.id END) as voucher_usage_count,
                    COUNT(DISTINCT CASE WHEN o.redeem_amount > 0 THEN o.id END) as point_redemption_count,
                    SUM(CASE WHEN o.promo_ids IS NOT NULL AND o.promo_ids != '' THEN 1 ELSE 0 END) as promo_orders,
                    SUM(CASE WHEN o.voucher_info IS NOT NULL AND o.voucher_info != '' THEN 1 ELSE 0 END) as voucher_orders,
                    SUM(o.redeem_amount) as total_point_redemption
                FROM orders o
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                AND o.member_id IS NOT NULL AND o.member_id != ''
            ";
            
            $params = [$dateFrom, $dateTo];
            
            if ($outletCode) {
                $query .= " AND o.kode_outlet = ?";
                $params[] = $outletCode;
            }
            
            return DB::selectOne($query, $params);
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Member Engagement Metrics Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get promo analytics (usage, top promos, member vs non-member)
     */
    public function getPromoAnalytics($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            $outletFilter = $outletCode ? " AND o.kode_outlet = ?" : "";
            $params = $outletCode ? [$dateFrom, $dateTo, $outletCode] : [$dateFrom, $dateTo];
            
            // Overall promo usage - hanya menghitung promo dengan status = 'active'
            $overallQuery = "
                SELECT 
                    COUNT(DISTINCT o.id) as total_orders,
                    COUNT(DISTINCT CASE WHEN EXISTS (
                        SELECT 1 FROM order_promos op2 
                        WHERE op2.order_id = o.id AND op2.status = 'active'
                    ) THEN o.id END) as orders_with_promo,
                    (SELECT COUNT(*) FROM order_promos op2 
                     INNER JOIN orders o2 ON op2.order_id = o2.id 
                     WHERE DATE(o2.created_at) BETWEEN ? AND ? 
                     AND op2.status = 'active' {$outletFilter}) as total_promo_usage,
                    SUM(CASE WHEN EXISTS (
                        SELECT 1 FROM order_promos op2 
                        WHERE op2.order_id = o.id AND op2.status = 'active'
                    ) THEN o.grand_total ELSE 0 END) as revenue_with_promo,
                    SUM(CASE WHEN NOT EXISTS (
                        SELECT 1 FROM order_promos op2 
                        WHERE op2.order_id = o.id AND op2.status = 'active'
                    ) THEN o.grand_total ELSE 0 END) as revenue_without_promo,
                    SUM(CASE WHEN EXISTS (
                        SELECT 1 FROM order_promos op2 
                        WHERE op2.order_id = o.id AND op2.status = 'active'
                    ) THEN o.discount ELSE 0 END) as total_promo_discount,
                    SUM(o.manual_discount_amount) as total_manual_discount,
                    COUNT(DISTINCT CASE WHEN o.manual_discount_amount > 0 THEN o.id END) as orders_with_manual_discount
                FROM orders o
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                {$outletFilter}
            ";
            
            $overallParams = $outletCode ? [$dateFrom, $dateTo, $outletCode, $dateFrom, $dateTo, $outletCode] : [$dateFrom, $dateTo, $dateFrom, $dateTo];
            $overall = DB::select($overallQuery, $overallParams)[0];
            
            // Member vs Non-Member promo usage - hanya menghitung promo dengan status = 'active'
            $memberQuery = "
                SELECT 
                    CASE 
                        WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN 'member'
                        ELSE 'non_member'
                    END as customer_type,
                    COUNT(DISTINCT o.id) as total_orders,
                    COUNT(DISTINCT CASE WHEN EXISTS (
                        SELECT 1 FROM order_promos op2 
                        WHERE op2.order_id = o.id AND op2.status = 'active'
                    ) THEN o.id END) as orders_with_promo,
                    SUM(CASE WHEN EXISTS (
                        SELECT 1 FROM order_promos op2 
                        WHERE op2.order_id = o.id AND op2.status = 'active'
                    ) THEN o.grand_total ELSE 0 END) as revenue_with_promo,
                    SUM(CASE WHEN NOT EXISTS (
                        SELECT 1 FROM order_promos op2 
                        WHERE op2.order_id = o.id AND op2.status = 'active'
                    ) THEN o.grand_total ELSE 0 END) as revenue_without_promo
                FROM orders o
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                {$outletFilter}
                GROUP BY customer_type
            ";
            
            $memberData = DB::select($memberQuery, $params);
            
            // Top promos - hanya menghitung promo dengan status = 'active'
            $topPromosQuery = "
                SELECT 
                    p.id,
                    p.name,
                    p.code,
                    p.type,
                    COUNT(DISTINCT op.order_id) as usage_count,
                    SUM(o.grand_total) as total_revenue,
                    SUM(o.discount) as total_discount
                FROM order_promos op
                INNER JOIN orders o ON op.order_id = o.id
                INNER JOIN promos p ON op.promo_id = p.id
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                AND op.status = 'active'
                {$outletFilter}
                GROUP BY p.id, p.name, p.code, p.type
                ORDER BY usage_count DESC
                LIMIT 10
            ";
            
            $topPromos = DB::select($topPromosQuery, $params);
            
            // Top manual discounts by reason - untuk bank, tampilkan reason asli (tidak digabung)
            $topManualDiscountsQuery = "
                SELECT 
                    CASE 
                        WHEN o.manual_discount_reason IS NULL OR TRIM(o.manual_discount_reason) = '' THEN 'No Reason'
                        WHEN LOWER(o.manual_discount_reason) LIKE '%bank%' THEN o.manual_discount_reason
                        WHEN LOWER(o.manual_discount_reason) LIKE '%investor%' THEN 'Investor'
                        WHEN LOWER(o.manual_discount_reason) LIKE '%founder%' THEN 'Founder'
                        WHEN LOWER(o.manual_discount_reason) LIKE '%entertainment%' THEN 'Entertainment'
                        WHEN LOWER(o.manual_discount_reason) LIKE '%guest satisfaction%' OR LOWER(o.manual_discount_reason) LIKE '%guest%' THEN 'Guest Satisfaction'
                        WHEN LOWER(o.manual_discount_reason) LIKE '%compliment%' THEN 'Compliment'
                        WHEN LOWER(o.manual_discount_reason) LIKE '%outlet city ledger%' OR LOWER(o.manual_discount_reason) LIKE '%city ledger%' THEN 'Outlet City Ledger'
                        ELSE 'Others'
                    END as discount_reason,
                    COUNT(DISTINCT o.id) as usage_count,
                    SUM(o.manual_discount_amount) as total_discount
                FROM orders o
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                AND o.manual_discount_amount > 0
                {$outletFilter}
                GROUP BY discount_reason
                ORDER BY total_discount DESC
                LIMIT 10
            ";
            
            $topManualDiscounts = DB::select($topManualDiscountsQuery, $params);
            
            $ordersWithPromo = (int) ($overall->orders_with_promo ?? 0);
            $totalOrders = (int) ($overall->total_orders ?? 0);
            $ordersWithoutPromo = $totalOrders - $ordersWithPromo;
            $revenueWithPromo = (float) ($overall->revenue_with_promo ?? 0);
            $revenueWithoutPromo = (float) ($overall->revenue_without_promo ?? 0);
            $totalPromoDiscount = (float) ($overall->total_promo_discount ?? 0);
            $totalPromoUsage = (int) ($overall->total_promo_usage ?? 0);
            $totalManualDiscount = (float) ($overall->total_manual_discount ?? 0);
            $ordersWithManualDiscount = (int) ($overall->orders_with_manual_discount ?? 0);
            
            return [
                'overall' => [
                    'total_orders' => $totalOrders,
                    'orders_with_promo' => $ordersWithPromo,
                    'orders_without_promo' => $ordersWithoutPromo,
                    'total_promo_usage' => $totalPromoUsage,
                    'promo_usage_percentage' => $totalOrders > 0 
                        ? round(($ordersWithPromo / $totalOrders) * 100, 2) 
                        : 0,
                    'revenue_with_promo' => $revenueWithPromo,
                    'revenue_without_promo' => $revenueWithoutPromo,
                    'total_promo_discount' => $totalPromoDiscount,
                    'total_manual_discount' => $totalManualDiscount,
                    'total_discount' => $totalPromoDiscount + $totalManualDiscount,
                    'orders_with_manual_discount' => $ordersWithManualDiscount,
                    'avg_order_value_with_promo' => $ordersWithPromo > 0 
                        ? round($revenueWithPromo / $ordersWithPromo, 2) 
                        : 0,
                    'avg_order_value_without_promo' => $ordersWithoutPromo > 0 
                        ? round($revenueWithoutPromo / $ordersWithoutPromo, 2) 
                        : 0
                ],
                'member_vs_non_member' => collect($memberData)->map(function($item) {
                    return [
                        'customer_type' => $item->customer_type,
                        'total_orders' => (int) $item->total_orders,
                        'orders_with_promo' => (int) $item->orders_with_promo,
                        'promo_usage_percentage' => $item->total_orders > 0 
                            ? round((($item->orders_with_promo / $item->total_orders) * 100), 2) 
                            : 0,
                        'revenue_with_promo' => (float) $item->revenue_with_promo,
                        'revenue_without_promo' => (float) $item->revenue_without_promo
                    ];
                })->values()->toArray(),
                'top_promos' => collect($topPromos)->map(function($promo) {
                    return [
                        'id' => $promo->id,
                        'name' => $promo->name,
                        'code' => $promo->code,
                        'type' => $promo->type,
                        'usage_count' => (int) $promo->usage_count,
                        'total_revenue' => (float) $promo->total_revenue,
                        'total_discount' => (float) $promo->total_discount,
                        'avg_revenue_per_usage' => $promo->usage_count > 0 
                            ? round($promo->total_revenue / $promo->usage_count, 2) 
                            : 0
                    ];
                })->values()->toArray(),
                'top_manual_discounts' => collect($topManualDiscounts)->map(function($discount) {
                    return [
                        'reason' => $discount->discount_reason,
                        'usage_count' => (int) $discount->usage_count,
                        'total_discount' => (float) $discount->total_discount
                    ];
                })->values()->toArray()
            ];
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Promo Analytics Error: ' . $e->getMessage());
            return [
                'overall' => [],
                'member_vs_non_member' => [],
                'top_promos' => [],
                'top_manual_discounts' => []
            ];
        }
    }

    /**
     * Get Customer Lifetime Value (CLV) Analysis
     * Menghitung total revenue per customer sepanjang waktu
     */
    public function getCustomerLifetimeValue($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            $outletFilter = $outletCode ? " AND o.kode_outlet = ?" : "";
            $params = $outletCode ? [$dateFrom, $dateTo, $outletCode] : [$dateFrom, $dateTo];

            // CLV untuk Members
            $memberCLVQuery = "
                SELECT 
                    o.member_id,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(o.grand_total) as total_revenue,
                    AVG(o.grand_total) as avg_order_value,
                    MIN(DATE(o.created_at)) as first_order_date,
                    MAX(DATE(o.created_at)) as last_order_date,
                    DATEDIFF(MAX(DATE(o.created_at)), MIN(DATE(o.created_at))) as customer_lifespan_days,
                    COUNT(DISTINCT DATE(o.created_at)) as unique_visit_days
                FROM orders o
                WHERE o.member_id IS NOT NULL 
                AND o.member_id != ''
                AND DATE(o.created_at) BETWEEN ? AND ?
                {$outletFilter}
                GROUP BY o.member_id
            ";

            $memberCLV = DB::select($memberCLVQuery, $params);

            // CLV untuk Non-Members (berdasarkan kombinasi kode_outlet + created_at untuk estimasi)
            // Non-member tidak punya ID unik, jadi kita estimasi berdasarkan pola
            $nonMemberCLVQuery = "
                SELECT 
                    CONCAT(o.kode_outlet, '-', DATE(o.created_at)) as customer_key,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(o.grand_total) as total_revenue,
                    AVG(o.grand_total) as avg_order_value,
                    MIN(DATE(o.created_at)) as first_order_date,
                    MAX(DATE(o.created_at)) as last_order_date,
                    DATEDIFF(MAX(DATE(o.created_at)), MIN(DATE(o.created_at))) as customer_lifespan_days,
                    COUNT(DISTINCT DATE(o.created_at)) as unique_visit_days
                FROM orders o
                WHERE (o.member_id IS NULL OR o.member_id = '')
                AND DATE(o.created_at) BETWEEN ? AND ?
                {$outletFilter}
                GROUP BY customer_key
            ";

            $nonMemberCLV = DB::select($nonMemberCLVQuery, $params);

            // Calculate statistics
            $memberCLVStats = $this->calculateCLVStats($memberCLV);
            $nonMemberCLVStats = $this->calculateCLVStats($nonMemberCLV);

            return [
                'member' => [
                    'data' => collect($memberCLV)->map(function($item) {
                        return [
                            'customer_id' => $item->member_id,
                            'total_orders' => (int) $item->total_orders,
                            'total_revenue' => (float) $item->total_revenue,
                            'avg_order_value' => (float) $item->avg_order_value,
                            'first_order_date' => $item->first_order_date,
                            'last_order_date' => $item->last_order_date,
                            'lifespan_days' => (int) $item->customer_lifespan_days,
                            'unique_visit_days' => (int) $item->unique_visit_days,
                            'clv' => (float) $item->total_revenue
                        ];
                    })->values()->toArray(),
                    'statistics' => $memberCLVStats
                ],
                'non_member' => [
                    'data' => collect($nonMemberCLV)->map(function($item) {
                        return [
                            'customer_key' => $item->customer_key,
                            'total_orders' => (int) $item->total_orders,
                            'total_revenue' => (float) $item->total_revenue,
                            'avg_order_value' => (float) $item->avg_order_value,
                            'first_order_date' => $item->first_order_date,
                            'last_order_date' => $item->last_order_date,
                            'lifespan_days' => (int) $item->customer_lifespan_days,
                            'unique_visit_days' => (int) $item->unique_visit_days,
                            'clv' => (float) $item->total_revenue
                        ];
                    })->values()->toArray(),
                    'statistics' => $nonMemberCLVStats
                ]
            ];
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get CLV Error: ' . $e->getMessage());
            return [
                'member' => ['data' => [], 'statistics' => []],
                'non_member' => ['data' => [], 'statistics' => []]
            ];
        }
    }

    /**
     * Calculate CLV Statistics
     */
    private function calculateCLVStats($clvData)
    {
        if (empty($clvData)) {
            return [
                'total_customers' => 0,
                'avg_clv' => 0,
                'median_clv' => 0,
                'min_clv' => 0,
                'max_clv' => 0,
                'total_clv' => 0,
                'avg_orders_per_customer' => 0,
                'avg_lifespan_days' => 0
            ];
        }

        $clvValues = collect($clvData)->pluck('total_revenue')->sort()->values();
        $totalCLV = $clvValues->sum();
        $count = $clvValues->count();
        $avgOrders = collect($clvData)->avg('total_orders');
        $avgLifespan = collect($clvData)->avg('customer_lifespan_days');

        return [
            'total_customers' => $count,
            'avg_clv' => $count > 0 ? round($totalCLV / $count, 2) : 0,
            'median_clv' => $count > 0 ? round($clvValues->median(), 2) : 0,
            'min_clv' => round($clvValues->min(), 2),
            'max_clv' => round($clvValues->max(), 2),
            'total_clv' => round($totalCLV, 2),
            'avg_orders_per_customer' => round($avgOrders, 2),
            'avg_lifespan_days' => round($avgLifespan, 2)
        ];
    }

    /**
     * Get Repeat Purchase Rate Analysis
     * Menghitung persentase customer yang melakukan repeat purchase
     */
    public function getRepeatPurchaseRate($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            $outletFilter = $outletCode ? " AND o.kode_outlet = ?" : "";
            $params = $outletCode ? [$dateFrom, $dateTo, $outletCode] : [$dateFrom, $dateTo];

            // Repeat Purchase Rate untuk Members
            $memberRepeatQuery = "
                SELECT 
                    o.member_id,
                    COUNT(DISTINCT o.id) as total_orders,
                    COUNT(DISTINCT DATE(o.created_at)) as unique_visit_days,
                    MIN(DATE(o.created_at)) as first_order_date,
                    MAX(DATE(o.created_at)) as last_order_date
                FROM orders o
                WHERE o.member_id IS NOT NULL 
                AND o.member_id != ''
                AND DATE(o.created_at) BETWEEN ? AND ?
                {$outletFilter}
                GROUP BY o.member_id
            ";

            $memberData = DB::select($memberRepeatQuery, $params);
            
            $memberTotalCustomers = count($memberData);
            $memberRepeatCustomers = collect($memberData)->filter(function($item) {
                return $item->total_orders > 1;
            })->count();
            $memberRepeatRate = $memberTotalCustomers > 0 
                ? round(($memberRepeatCustomers / $memberTotalCustomers) * 100, 2) 
                : 0;

            // Segmentasi berdasarkan jumlah order
            $memberSegments = [
                'one_time' => collect($memberData)->filter(fn($item) => $item->total_orders == 1)->count(),
                'repeat_2_5' => collect($memberData)->filter(fn($item) => $item->total_orders >= 2 && $item->total_orders <= 5)->count(),
                'repeat_6_10' => collect($memberData)->filter(fn($item) => $item->total_orders >= 6 && $item->total_orders <= 10)->count(),
                'repeat_11_plus' => collect($memberData)->filter(fn($item) => $item->total_orders > 10)->count()
            ];

            return [
                'member' => [
                    'total_customers' => $memberTotalCustomers,
                    'repeat_customers' => $memberRepeatCustomers,
                    'repeat_rate' => $memberRepeatRate,
                    'one_time_rate' => $memberTotalCustomers > 0 
                        ? round((($memberTotalCustomers - $memberRepeatCustomers) / $memberTotalCustomers) * 100, 2) 
                        : 0,
                    'segments' => $memberSegments,
                    'avg_orders_per_customer' => $memberTotalCustomers > 0 
                        ? round(collect($memberData)->avg('total_orders'), 2) 
                        : 0
                ],
                'overall' => [
                    'total_customers' => $memberTotalCustomers,
                    'repeat_customers' => $memberRepeatCustomers,
                    'repeat_rate' => $memberRepeatRate
                ]
            ];
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Repeat Purchase Rate Error: ' . $e->getMessage());
            return [
                'member' => [
                    'total_customers' => 0,
                    'repeat_customers' => 0,
                    'repeat_rate' => 0,
                    'one_time_rate' => 0,
                    'segments' => [],
                    'avg_orders_per_customer' => 0
                ],
                'overall' => [
                    'total_customers' => 0,
                    'repeat_customers' => 0,
                    'repeat_rate' => 0
                ]
            ];
        }
    }

    /**
     * Get Average Days Between Orders
     * Menghitung rata-rata hari antar pesanan per customer
     */
    public function getAverageDaysBetweenOrders($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            $outletFilter = $outletCode ? " AND o.kode_outlet = ?" : "";
            $params = $outletCode ? [$dateFrom, $dateTo, $outletCode] : [$dateFrom, $dateTo];

            // Untuk Members dengan multiple orders - ambil semua order dates per member
            $memberDaysQuery = "
                SELECT 
                    o.member_id,
                    DATE(o.created_at) as order_date
                FROM orders o
                WHERE o.member_id IS NOT NULL 
                AND o.member_id != ''
                AND DATE(o.created_at) BETWEEN ? AND ?
                {$outletFilter}
                ORDER BY o.member_id, o.created_at ASC
            ";

            $memberOrders = DB::select($memberDaysQuery, $params);
            
            // Group by member_id dan calculate days between orders
            $memberOrderDates = [];
            foreach ($memberOrders as $order) {
                if (!isset($memberOrderDates[$order->member_id])) {
                    $memberOrderDates[$order->member_id] = [];
                }
                $memberOrderDates[$order->member_id][] = $order->order_date;
            }

            $daysBetweenOrders = [];
            foreach ($memberOrderDates as $memberId => $dates) {
                if (count($dates) > 1) {
                    // Remove duplicates and sort
                    $uniqueDates = array_unique($dates);
                    sort($uniqueDates);
                    
                    for ($i = 1; $i < count($uniqueDates); $i++) {
                        $daysDiff = (strtotime($uniqueDates[$i]) - strtotime($uniqueDates[$i-1])) / 86400;
                        if ($daysDiff > 0) {
                            $daysBetweenOrders[] = $daysDiff;
                        }
                    }
                }
            }

            $avgDays = !empty($daysBetweenOrders) ? round(array_sum($daysBetweenOrders) / count($daysBetweenOrders), 2) : 0;
            $medianDays = !empty($daysBetweenOrders) ? round($this->calculateMedian($daysBetweenOrders), 2) : 0;

            // Segmentasi berdasarkan interval
            $segments = [
                '0_7_days' => count(array_filter($daysBetweenOrders, fn($d) => $d <= 7)),
                '8_14_days' => count(array_filter($daysBetweenOrders, fn($d) => $d >= 8 && $d <= 14)),
                '15_30_days' => count(array_filter($daysBetweenOrders, fn($d) => $d >= 15 && $d <= 30)),
                '31_60_days' => count(array_filter($daysBetweenOrders, fn($d) => $d >= 31 && $d <= 60)),
                '61_plus_days' => count(array_filter($daysBetweenOrders, fn($d) => $d > 60))
            ];

            return [
                'avg_days_between_orders' => $avgDays,
                'median_days_between_orders' => $medianDays,
                'min_days' => !empty($daysBetweenOrders) ? round(min($daysBetweenOrders), 2) : 0,
                'max_days' => !empty($daysBetweenOrders) ? round(max($daysBetweenOrders), 2) : 0,
                'total_intervals' => count($daysBetweenOrders),
                'segments' => $segments
            ];
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Average Days Between Orders Error: ' . $e->getMessage());
            return [
                'avg_days_between_orders' => 0,
                'median_days_between_orders' => 0,
                'min_days' => 0,
                'max_days' => 0,
                'total_intervals' => 0,
                'segments' => []
            ];
        }
    }

    /**
     * Calculate median value
     */
    private function calculateMedian($array)
    {
        sort($array);
        $count = count($array);
        $middle = floor(($count - 1) / 2);
        
        if ($count % 2) {
            return $array[$middle];
        } else {
            return ($array[$middle] + $array[$middle + 1]) / 2;
        }
    }

    /**
     * Get Basket Analysis / Product Affinity
     * Menemukan produk yang sering dibeli bersamaan
     */
    public function getBasketAnalysis($dateFrom, $dateTo, $outletCode = null, $limit = 10)
    {
        try {
            $outletFilter = $outletCode ? " AND o.kode_outlet = ?" : "";
            $params = $outletCode ? [$dateFrom, $dateTo, $outletCode] : [$dateFrom, $dateTo];

            // Ambil semua order dengan items
            $ordersQuery = "
                SELECT 
                    o.id as order_id,
                    o.member_id,
                    oi.item_id,
                    oi.item_name,
                    oi.qty,
                    oi.price,
                    COALESCE(oi.subtotal, oi.qty * oi.price) as total
                FROM orders o
                INNER JOIN order_items oi ON o.id = oi.order_id
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                {$outletFilter}
                ORDER BY o.id, oi.item_name
            ";

            $orderItems = DB::select($ordersQuery, $params);
            
            // Debug: Log jika tidak ada data
            if (empty($orderItems)) {
                Log::info('Basket Analysis - No order items found', [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'outlet_code' => $outletCode
                ]);
                return [
                    'top_pairs' => [],
                    'total_orders_analyzed' => 0,
                    'total_orders_with_multiple_items' => 0
                ];
            }
            
            Log::info('Basket Analysis - Order items found', [
                'count' => count($orderItems)
            ]);

            // Group items by order
            $orderGroups = [];
            foreach ($orderItems as $item) {
                if (!isset($orderGroups[$item->order_id])) {
                    $orderGroups[$item->order_id] = [];
                }
                $orderGroups[$item->order_id][] = $item;
            }

            // Calculate product pairs (items bought together)
            $productPairs = [];
            foreach ($orderGroups as $orderId => $items) {
                if (count($items) > 1) {
                    // Generate all pairs in this order
                    for ($i = 0; $i < count($items); $i++) {
                        for ($j = $i + 1; $j < count($items); $j++) {
                            $item1 = $items[$i];
                            $item2 = $items[$j];
                            
                            // Create pair key (alphabetically sorted)
                            $pairKey = $item1->item_name < $item2->item_name 
                                ? $item1->item_name . '|' . $item2->item_name
                                : $item2->item_name . '|' . $item1->item_name;
                            
                            if (!isset($productPairs[$pairKey])) {
                                $productPairs[$pairKey] = [
                                    'product1' => $item1->item_name,
                                    'product2' => $item2->item_name,
                                    'frequency' => 0,
                                    'total_revenue' => 0
                                ];
                            }
                            
                            $productPairs[$pairKey]['frequency']++;
                            $item1Total = is_numeric($item1->total) ? (float) $item1->total : ((float) $item1->qty * (float) $item1->price);
                            $item2Total = is_numeric($item2->total) ? (float) $item2->total : ((float) $item2->qty * (float) $item2->price);
                            $productPairs[$pairKey]['total_revenue'] += ($item1Total + $item2Total);
                        }
                    }
                }
            }

            // Sort by frequency and get top pairs
            usort($productPairs, function($a, $b) {
                return $b['frequency'] - $a['frequency'];
            });

            $topPairs = array_slice($productPairs, 0, $limit);

            return [
                'top_pairs' => array_map(function($pair) {
                    return [
                        'product1' => $pair['product1'],
                        'product2' => $pair['product2'],
                        'frequency' => $pair['frequency'],
                        'total_revenue' => round($pair['total_revenue'], 2),
                        'avg_revenue_per_pair' => round($pair['total_revenue'] / $pair['frequency'], 2)
                    ];
                }, $topPairs),
                'total_orders_analyzed' => count($orderGroups),
                'total_orders_with_multiple_items' => count(array_filter($orderGroups, fn($items) => count($items) > 1))
            ];
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Basket Analysis Error: ' . $e->getMessage());
            return [
                'top_pairs' => [],
                'total_orders_analyzed' => 0,
                'total_orders_with_multiple_items' => 0
            ];
        }
    }

    /**
     * Get Peak Hours & Day Analysis (Detailed)
     * Analisis jam-jam sibuk dan hari paling produktif
     */
    public function getPeakHoursDayAnalysis($dateFrom, $dateTo, $outletCode = null)
    {
        try {
            $outletFilter = $outletCode ? " AND o.kode_outlet = ?" : "";
            $params = $outletCode ? [$dateFrom, $dateTo, $outletCode] : [$dateFrom, $dateTo];

            // Peak Hours Analysis
            $peakHoursQuery = "
                SELECT 
                    HOUR(o.created_at) as hour,
                    DAYNAME(o.created_at) as day_name,
                    DAYOFWEEK(o.created_at) as day_of_week,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(o.grand_total) as total_revenue,
                    AVG(o.grand_total) as avg_order_value
                FROM orders o
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                {$outletFilter}
                GROUP BY HOUR(o.created_at), DAYNAME(o.created_at), DAYOFWEEK(o.created_at)
                ORDER BY day_of_week, hour
            ";

            $peakHoursData = DB::select($peakHoursQuery, $params);

            // Aggregate by hour (all days combined)
            $hourlyStats = [];
            foreach ($peakHoursData as $row) {
                $hour = (int) $row->hour;
                if (!isset($hourlyStats[$hour])) {
                    $hourlyStats[$hour] = [
                        'hour' => $hour,
                        'total_orders' => 0,
                        'total_revenue' => 0,
                        'avg_order_value' => 0
                    ];
                }
                $hourlyStats[$hour]['total_orders'] += $row->total_orders;
                $hourlyStats[$hour]['total_revenue'] += $row->total_revenue;
            }

            // Calculate average order value per hour
            foreach ($hourlyStats as $hour => &$stats) {
                $stats['avg_order_value'] = $stats['total_orders'] > 0 
                    ? round($stats['total_revenue'] / $stats['total_orders'], 2) 
                    : 0;
            }

            // Sort by total revenue
            usort($hourlyStats, function($a, $b) {
                return $b['total_revenue'] - $a['total_revenue'];
            });

            // Day of Week Analysis
            $dayOfWeekQuery = "
                SELECT 
                    DAYNAME(o.created_at) as day_name,
                    DAYOFWEEK(o.created_at) as day_of_week,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(o.grand_total) as total_revenue,
                    AVG(o.grand_total) as avg_order_value
                FROM orders o
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                {$outletFilter}
                GROUP BY DAYNAME(o.created_at), DAYOFWEEK(o.created_at)
                ORDER BY day_of_week
            ";

            $dayOfWeekData = DB::select($dayOfWeekQuery, $params);

            // Find peak hours and days
            $topPeakHours = array_slice($hourlyStats, 0, 5);
            $topPeakDays = collect($dayOfWeekData)->sortByDesc('total_revenue')->take(3)->values()->toArray();

            return [
                'hourly_stats' => array_values($hourlyStats),
                'top_peak_hours' => array_map(function($hour) {
                    return [
                        'hour' => $hour['hour'],
                        'hour_label' => sprintf('%02d:00', $hour['hour']),
                        'total_orders' => $hour['total_orders'],
                        'total_revenue' => round($hour['total_revenue'], 2),
                        'avg_order_value' => $hour['avg_order_value']
                    ];
                }, $topPeakHours),
                'day_of_week_stats' => collect($dayOfWeekData)->map(function($day) {
                    return [
                        'day_name' => $day->day_name,
                        'day_of_week' => (int) $day->day_of_week,
                        'total_orders' => (int) $day->total_orders,
                        'total_revenue' => round((float) $day->total_revenue, 2),
                        'avg_order_value' => round((float) $day->avg_order_value, 2)
                    ];
                })->values()->toArray(),
                'top_peak_days' => collect($topPeakDays)->map(function($day) {
                    return [
                        'day_name' => $day->day_name,
                        'day_of_week' => (int) $day->day_of_week,
                        'total_orders' => (int) $day->total_orders,
                        'total_revenue' => round((float) $day->total_revenue, 2),
                        'avg_order_value' => round((float) $day->avg_order_value, 2)
                    ];
                })->values()->toArray()
            ];
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Peak Hours Day Analysis Error: ' . $e->getMessage());
            return [
                'hourly_stats' => [],
                'top_peak_hours' => [],
                'day_of_week_stats' => [],
                'top_peak_days' => []
            ];
        }
    }

    /**
     * Get Customer Acquisition Trends
     * Menghitung jumlah customer baru per periode
     */
    public function getCustomerAcquisitionTrends($dateFrom, $dateTo, $outletCode = null, $groupBy = 'day')
    {
        try {
            $outletFilter = $outletCode ? " AND o.kode_outlet = ?" : "";
            $params = $outletCode ? [$dateFrom, $dateTo, $outletCode] : [$dateFrom, $dateTo];

            // Get first order date for each member
            $firstOrderQuery = "
                SELECT 
                    o.member_id,
                    MIN(DATE(o.created_at)) as first_order_date
                FROM orders o
                WHERE o.member_id IS NOT NULL 
                AND o.member_id != ''
                AND DATE(o.created_at) BETWEEN ? AND ?
                {$outletFilter}
                GROUP BY o.member_id
            ";

            $firstOrders = DB::select($firstOrderQuery, $params);

            // Group by period
            $acquisitionByPeriod = [];
            foreach ($firstOrders as $order) {
                $firstDate = $order->first_order_date;
                
                if ($groupBy === 'day') {
                    $period = $firstDate;
                } elseif ($groupBy === 'week') {
                    $date = new \DateTime($firstDate);
                    $period = $date->format('Y-W'); // Year-Week
                } elseif ($groupBy === 'month') {
                    $period = substr($firstDate, 0, 7); // YYYY-MM
                } else {
                    $period = $firstDate;
                }

                if (!isset($acquisitionByPeriod[$period])) {
                    $acquisitionByPeriod[$period] = [
                        'period' => $period,
                        'new_customers' => 0,
                        'period_label' => $firstDate
                    ];
                }
                $acquisitionByPeriod[$period]['new_customers']++;
            }

            // Sort by period
            ksort($acquisitionByPeriod);

            // Calculate growth rate
            $previousCount = 0;
            $trends = [];
            foreach ($acquisitionByPeriod as $period => $data) {
                $growthRate = $previousCount > 0 
                    ? round((($data['new_customers'] - $previousCount) / $previousCount) * 100, 2)
                    : 0;
                
                $trends[] = [
                    'period' => $data['period'],
                    'period_label' => $data['period_label'],
                    'new_customers' => $data['new_customers'],
                    'growth_rate' => $growthRate,
                    'trend' => $growthRate > 0 ? 'up' : ($growthRate < 0 ? 'down' : 'stable')
                ];
                
                $previousCount = $data['new_customers'];
            }

            // Calculate summary
            $totalNewCustomers = array_sum(array_column($acquisitionByPeriod, 'new_customers'));
            $avgNewCustomersPerPeriod = count($acquisitionByPeriod) > 0 
                ? round($totalNewCustomers / count($acquisitionByPeriod), 2) 
                : 0;

            return [
                'trends' => $trends,
                'summary' => [
                    'total_new_customers' => $totalNewCustomers,
                    'total_periods' => count($acquisitionByPeriod),
                    'avg_new_customers_per_period' => $avgNewCustomersPerPeriod,
                    'period_type' => $groupBy
                ]
            ];
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Customer Acquisition Trends Error: ' . $e->getMessage());
            return [
                'trends' => [],
                'summary' => [
                    'total_new_customers' => 0,
                    'total_periods' => 0,
                    'avg_new_customers_per_period' => 0,
                    'period_type' => $groupBy
                ]
            ];
        }
    }

    /**
     * Get Analysis by Region
     * Analisis performa marketing per region
     */
    public function getAnalysisByRegion($dateFrom, $dateTo)
    {
        try {
            // Check if regions table exists
            $regionsTableExists = DB::select("SHOW TABLES LIKE 'regions'");
            
            if (empty($regionsTableExists)) {
                // If regions table doesn't exist, use outlet grouping as fallback
                $query = "
                    SELECT 
                        COALESCE(outlet.region_id, 0) as region_id,
                        COALESCE(outlet.region_name, 'Unknown Region') as region_name,
                        COUNT(DISTINCT o.id) as total_orders,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id ELSE o.id END) as unique_customers,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id END) as member_customers,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NULL OR o.member_id = '' THEN o.id END) as non_member_orders,
                        SUM(o.grand_total) as total_revenue,
                        AVG(o.grand_total) as avg_order_value,
                        SUM(o.pax) as total_pax,
                        AVG(o.pax) as avg_pax,
                        SUM(o.discount) as total_discount,
                        COUNT(DISTINCT CASE WHEN EXISTS (
                            SELECT 1 FROM order_promos op2 WHERE op2.order_id = o.id AND op2.status = 'active'
                        ) THEN o.id END) as orders_with_promo,
                        SUM(CASE WHEN EXISTS (
                            SELECT 1 FROM order_promos op2 WHERE op2.order_id = o.id AND op2.status = 'active'
                        ) THEN o.grand_total ELSE 0 END) as revenue_with_promo,
                        SUM(o.manual_discount_amount) as total_manual_discount
                    FROM orders o
                    LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
                    GROUP BY outlet.region_id, outlet.region_name
                    HAVING region_id IS NOT NULL OR region_name IS NOT NULL
                    ORDER BY total_revenue DESC
                ";
                $params = [$dateFrom, $dateTo];
            } else {
                // Use regions table if it exists
                $query = "
                    SELECT 
                        r.id as region_id,
                        r.name as region_name,
                        COUNT(DISTINCT o.id) as total_orders,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id ELSE o.id END) as unique_customers,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id END) as member_customers,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NULL OR o.member_id = '' THEN o.id END) as non_member_orders,
                        SUM(o.grand_total) as total_revenue,
                        AVG(o.grand_total) as avg_order_value,
                        SUM(o.pax) as total_pax,
                        AVG(o.pax) as avg_pax,
                        SUM(o.discount) as total_discount,
                        COUNT(DISTINCT CASE WHEN EXISTS (
                            SELECT 1 FROM order_promos op2 WHERE op2.order_id = o.id AND op2.status = 'active'
                        ) THEN o.id END) as orders_with_promo,
                        SUM(CASE WHEN EXISTS (
                            SELECT 1 FROM order_promos op2 WHERE op2.order_id = o.id AND op2.status = 'active'
                        ) THEN o.grand_total ELSE 0 END) as revenue_with_promo,
                        SUM(o.manual_discount_amount) as total_manual_discount
                    FROM orders o
                    LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
                    LEFT JOIN regions r ON outlet.region_id = r.id
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
                    AND r.id IS NOT NULL
                    GROUP BY r.id, r.name
                    ORDER BY total_revenue DESC
                ";
                $params = [$dateFrom, $dateTo];
            }

            $regionData = DB::select($query, $params);

            return collect($regionData)->map(function($region) {
                $totalOrders = (int) $region->total_orders;
                $ordersWithPromo = (int) $region->orders_with_promo;
                
                return [
                    'region_id' => (int) $region->region_id,
                    'region_name' => $region->region_name,
                    'total_orders' => $totalOrders,
                    'unique_customers' => (int) $region->unique_customers,
                    'member_customers' => (int) $region->member_customers,
                    'non_member_orders' => (int) $region->non_member_orders,
                    'total_revenue' => round((float) $region->total_revenue, 2),
                    'avg_order_value' => round((float) $region->avg_order_value, 2),
                    'total_pax' => (int) $region->total_pax,
                    'avg_pax' => round((float) $region->avg_pax, 2),
                    'total_discount' => round((float) $region->total_discount, 2),
                    'orders_with_promo' => $ordersWithPromo,
                    'promo_usage_percentage' => $totalOrders > 0 
                        ? round(($ordersWithPromo / $totalOrders) * 100, 2) 
                        : 0,
                    'revenue_with_promo' => round((float) $region->revenue_with_promo, 2),
                    'total_manual_discount' => round((float) $region->total_manual_discount, 2)
                ];
            })->values()->toArray();
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Analysis By Region Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Analysis by Outlet
     * Analisis performa marketing per outlet
     */
    public function getAnalysisByOutlet($dateFrom, $dateTo, $regionId = null)
    {
        try {
            // Check if regions table exists
            $regionsTableExists = DB::select("SHOW TABLES LIKE 'regions'");
            
            $regionFilter = $regionId ? " AND outlet.region_id = ?" : "";
            $params = $regionId ? [$dateFrom, $dateTo, $regionId] : [$dateFrom, $dateTo];

            if (empty($regionsTableExists)) {
                // If regions table doesn't exist, use outlet.region_name directly
                $query = "
                    SELECT 
                        outlet.id_outlet,
                        outlet.nama_outlet,
                        outlet.qr_code as outlet_code,
                        COALESCE(outlet.region_id, 0) as region_id,
                        COALESCE(outlet.region_name, 'Unknown Region') as region_name,
                        COUNT(DISTINCT o.id) as total_orders,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id ELSE o.id END) as unique_customers,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id END) as member_customers,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NULL OR o.member_id = '' THEN o.id END) as non_member_orders,
                        SUM(o.grand_total) as total_revenue,
                        AVG(o.grand_total) as avg_order_value,
                        SUM(o.pax) as total_pax,
                        AVG(o.pax) as avg_pax,
                        SUM(o.discount) as total_discount,
                        COUNT(DISTINCT CASE WHEN EXISTS (
                            SELECT 1 FROM order_promos op2 WHERE op2.order_id = o.id AND op2.status = 'active'
                        ) THEN o.id END) as orders_with_promo,
                        SUM(CASE WHEN EXISTS (
                            SELECT 1 FROM order_promos op2 WHERE op2.order_id = o.id AND op2.status = 'active'
                        ) THEN o.grand_total ELSE 0 END) as revenue_with_promo,
                        SUM(o.manual_discount_amount) as total_manual_discount
                    FROM orders o
                    LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
                    AND outlet.id_outlet IS NOT NULL
                    {$regionFilter}
                    GROUP BY outlet.id_outlet, outlet.nama_outlet, outlet.qr_code, outlet.region_id, outlet.region_name
                    ORDER BY total_revenue DESC
                ";
            } else {
                // Use regions table if it exists
                $query = "
                    SELECT 
                        outlet.id_outlet,
                        outlet.nama_outlet,
                        outlet.qr_code as outlet_code,
                        r.id as region_id,
                        r.name as region_name,
                        COUNT(DISTINCT o.id) as total_orders,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id ELSE o.id END) as unique_customers,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NOT NULL AND o.member_id != '' THEN o.member_id END) as member_customers,
                        COUNT(DISTINCT CASE WHEN o.member_id IS NULL OR o.member_id = '' THEN o.id END) as non_member_orders,
                        SUM(o.grand_total) as total_revenue,
                        AVG(o.grand_total) as avg_order_value,
                        SUM(o.pax) as total_pax,
                        AVG(o.pax) as avg_pax,
                        SUM(o.discount) as total_discount,
                        COUNT(DISTINCT CASE WHEN EXISTS (
                            SELECT 1 FROM order_promos op2 WHERE op2.order_id = o.id AND op2.status = 'active'
                        ) THEN o.id END) as orders_with_promo,
                        SUM(CASE WHEN EXISTS (
                            SELECT 1 FROM order_promos op2 WHERE op2.order_id = o.id AND op2.status = 'active'
                        ) THEN o.grand_total ELSE 0 END) as revenue_with_promo,
                        SUM(o.manual_discount_amount) as total_manual_discount
                    FROM orders o
                    LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
                    LEFT JOIN regions r ON outlet.region_id = r.id
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
                    AND outlet.id_outlet IS NOT NULL
                    {$regionFilter}
                    GROUP BY outlet.id_outlet, outlet.nama_outlet, outlet.qr_code, r.id, r.name
                    ORDER BY total_revenue DESC
                ";
            }

            $outletData = DB::select($query, $params);

            return collect($outletData)->map(function($outlet) {
                $totalOrders = (int) $outlet->total_orders;
                $ordersWithPromo = (int) $outlet->orders_with_promo;
                
                return [
                    'outlet_id' => (int) $outlet->id_outlet,
                    'outlet_name' => $outlet->nama_outlet,
                    'outlet_code' => $outlet->outlet_code,
                    'region_id' => $outlet->region_id ? (int) $outlet->region_id : null,
                    'region_name' => $outlet->region_name ?? 'Unknown Region',
                    'total_orders' => $totalOrders,
                    'unique_customers' => (int) $outlet->unique_customers,
                    'member_customers' => (int) $outlet->member_customers,
                    'non_member_orders' => (int) $outlet->non_member_orders,
                    'total_revenue' => round((float) $outlet->total_revenue, 2),
                    'avg_order_value' => round((float) $outlet->avg_order_value, 2),
                    'total_pax' => (int) $outlet->total_pax,
                    'avg_pax' => round((float) $outlet->avg_pax, 2),
                    'total_discount' => round((float) $outlet->total_discount, 2),
                    'orders_with_promo' => $ordersWithPromo,
                    'promo_usage_percentage' => $totalOrders > 0 
                        ? round(($ordersWithPromo / $totalOrders) * 100, 2) 
                        : 0,
                    'revenue_with_promo' => round((float) $outlet->revenue_with_promo, 2),
                    'total_manual_discount' => round((float) $outlet->total_manual_discount, 2)
                ];
            })->values()->toArray();
        } catch (\Exception $e) {
            Log::error('AI Database Helper - Get Analysis By Outlet Error: ' . $e->getMessage());
            return [];
        }
    }
}

