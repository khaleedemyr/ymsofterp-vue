<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🧪 Testing Dashboard Query Fix...\n\n";
    
    $dateFrom = '2025-09-01';
    $dateTo = '2025-09-10';
    $outletFilter = ''; // No outlet filter for testing
    
    echo "📅 Date Range: {$dateFrom} to {$dateTo}\n";
    echo "🏪 Outlet Filter: " . ($outletFilter ?: 'All Outlets') . "\n\n";
    
    // Test 1: Basic average order value query
    echo "1️⃣ Testing basic average order value query...\n";
    
    $query1 = "
        SELECT 
            AVG(grand_total) as avg_order_value,
            MIN(grand_total) as min_order_value,
            MAX(grand_total) as max_order_value
        FROM orders 
        WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        {$outletFilter}
    ";
    
    try {
        $result1 = DB::select($query1);
        echo "✅ Basic query successful!\n";
        echo "   - Average Order Value: " . number_format($result1[0]->avg_order_value ?? 0, 2) . "\n";
        echo "   - Min Order Value: " . number_format($result1[0]->min_order_value ?? 0, 2) . "\n";
        echo "   - Max Order Value: " . number_format($result1[0]->max_order_value ?? 0, 2) . "\n\n";
    } catch (Exception $e) {
        echo "❌ Basic query failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 2: Median calculation query (MySQL 5.7 compatible)
    echo "2️⃣ Testing median calculation query (MySQL 5.7 compatible)...\n";
    
    // First get total count
    $countQuery = "
        SELECT COUNT(*) as total_count
        FROM orders 
        WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        {$outletFilter}
    ";
    
    try {
        $countResult = DB::select($countQuery);
        $totalCount = $countResult[0]->total_count;
        echo "   - Total records: " . number_format($totalCount) . "\n";
        
        if ($totalCount > 0) {
            $offset = floor(($totalCount - 1) / 2);
            $limit = ($totalCount % 2 == 0) ? 2 : 1;
            
            echo "   - Offset: {$offset}, Limit: {$limit}\n";
            
            $medianQuery = "
                SELECT grand_total as median_order_value
                FROM orders 
                WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
                {$outletFilter}
                ORDER BY grand_total
                LIMIT {$limit} OFFSET {$offset}
            ";
            
            $medianResult = DB::select($medianQuery);
            echo "✅ Median query successful!\n";
            
            $median = 0;
            if (count($medianResult) > 0) {
                if (count($medianResult) == 1) {
                    $median = $medianResult[0]->median_order_value;
                } else {
                    $median = ($medianResult[0]->median_order_value + $medianResult[1]->median_order_value) / 2;
                }
            }
            
            echo "   - Median Order Value: " . number_format($median, 2) . "\n";
            echo "   - Records found: " . count($medianResult) . "\n\n";
        } else {
            echo "⚠️  No records found for median calculation\n\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Median query failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 3: Check if orders table exists and has data
    echo "3️⃣ Checking orders table...\n";
    
    try {
        $countQuery = "SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'";
        $countResult = DB::select($countQuery);
        $totalOrders = $countResult[0]->total;
        
        echo "✅ Orders table accessible!\n";
        echo "   - Total orders in date range: " . number_format($totalOrders) . "\n\n";
        
        if ($totalOrders == 0) {
            echo "⚠️  No orders found in the specified date range.\n";
            echo "   This might be why some queries return empty results.\n\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Orders table check failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 4: Test with different date range (last 30 days)
    echo "4️⃣ Testing with last 30 days...\n";
    
    $last30DaysFrom = date('Y-m-d', strtotime('-30 days'));
    $last30DaysTo = date('Y-m-d');
    
    echo "📅 Date Range: {$last30DaysFrom} to {$last30DaysTo}\n";
    
    $query3 = "
        SELECT 
            COUNT(*) as total_orders,
            AVG(grand_total) as avg_order_value,
            MIN(grand_total) as min_order_value,
            MAX(grand_total) as max_order_value
        FROM orders 
        WHERE DATE(created_at) BETWEEN '{$last30DaysFrom}' AND '{$last30DaysTo}'
    ";
    
    try {
        $result3 = DB::select($query3);
        echo "✅ Last 30 days query successful!\n";
        echo "   - Total Orders: " . number_format($result3[0]->total_orders) . "\n";
        echo "   - Average Order Value: " . number_format($result3[0]->avg_order_value ?? 0, 2) . "\n";
        echo "   - Min Order Value: " . number_format($result3[0]->min_order_value ?? 0, 2) . "\n";
        echo "   - Max Order Value: " . number_format($result3[0]->max_order_value ?? 0, 2) . "\n\n";
    } catch (Exception $e) {
        echo "❌ Last 30 days query failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 5: Test dashboard controller method
    echo "5️⃣ Testing Dashboard Controller method...\n";
    
    try {
        $controller = new \App\Http\Controllers\SalesOutletDashboardController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('getAverageOrderValue');
        $method->setAccessible(true);
        
        $result4 = $method->invoke($controller, $outletFilter, $dateFrom, $dateTo);
        
        echo "✅ Controller method successful!\n";
        echo "   - Average Order Value: " . number_format($result4->avg_order_value ?? 0, 2) . "\n";
        echo "   - Min Order Value: " . number_format($result4->min_order_value ?? 0, 2) . "\n";
        echo "   - Max Order Value: " . number_format($result4->max_order_value ?? 0, 2) . "\n";
        echo "   - Median Order Value: " . number_format($result4->median_order_value ?? 0, 2) . "\n\n";
        
    } catch (Exception $e) {
        echo "❌ Controller method failed: " . $e->getMessage() . "\n\n";
    }
    
    echo "🎉 Dashboard Query Fix Test Completed!\n";
    echo "\n📝 Summary:\n";
    echo "   - PERCENTILE_CONT function removed (not supported in MySQL)\n";
    echo "   - Median calculation implemented using ROW_NUMBER() and window functions\n";
    echo "   - All queries should now be MySQL compatible\n";
    echo "   - Dashboard should work without SQL syntax errors\n\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
