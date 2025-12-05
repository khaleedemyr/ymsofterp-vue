<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🧪 Testing Payment Code & Average Check Features...\n\n";
    
    $dateFrom = '2025-09-01';
    $dateTo = '2025-09-10';
    $outletFilter = ''; // No outlet filter for testing
    
    echo "📅 Date Range: {$dateFrom} to {$dateTo}\n";
    echo "🏪 Outlet Filter: " . ($outletFilter ?: 'All Outlets') . "\n\n";
    
    // Test 1: Check Payment Methods with Payment Code
    echo "1️⃣ Testing Payment Methods with Payment Code...\n";
    
    $query = "
        SELECT 
            op.payment_type,
            op.payment_code,
            COUNT(*) as transaction_count,
            SUM(op.amount) as total_amount,
            AVG(op.amount) as avg_amount
        FROM order_payment op
        INNER JOIN orders o ON op.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        {$outletFilter}
        GROUP BY op.payment_type, op.payment_code
        ORDER BY total_amount DESC
    ";
    
    try {
        $result = DB::select($query);
        echo "✅ Payment Methods with Payment Code query successful!\n";
        echo "   - Payment method combinations found: " . count($result) . "\n\n";
        
        if (count($result) > 0) {
            echo "📊 Payment Methods with Payment Code Data:\n";
            foreach ($result as $index => $method) {
                echo "   " . ($index + 1) . ". {$method->payment_type} ({$method->payment_code})\n";
                echo "      - Transactions: " . number_format($method->transaction_count) . "\n";
                echo "      - Total Amount: " . number_format($method->total_amount, 2) . "\n";
                echo "      - Average Amount: " . number_format($method->avg_amount, 2) . "\n\n";
            }
        } else {
            echo "⚠️  No payment methods found in the specified date range.\n\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Payment Methods with Payment Code query failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 2: Check Average Check Calculation
    echo "2️⃣ Testing Average Check Calculation...\n";
    
    $avgCheckQuery = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(grand_total) as total_revenue,
            SUM(pax) as total_customers
        FROM orders 
        WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        {$outletFilter}
    ";
    
    try {
        $avgCheckResult = DB::select($avgCheckQuery);
        $totalRevenue = $avgCheckResult[0]->total_revenue;
        $totalCustomers = $avgCheckResult[0]->total_customers;
        $avgCheck = $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0;
        
        echo "✅ Average Check calculation successful!\n";
        echo "   - Total Revenue: " . number_format($totalRevenue, 2) . "\n";
        echo "   - Total Customers: " . number_format($totalCustomers) . "\n";
        echo "   - Average Check: " . number_format($avgCheck, 2) . "\n\n";
        
    } catch (Exception $e) {
        echo "❌ Average Check calculation failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 3: Check Dashboard Controller Methods
    echo "3️⃣ Testing Dashboard Controller Methods...\n";
    
    try {
        $controller = new \App\Http\Controllers\SalesOutletDashboardController();
        $reflection = new ReflectionClass($controller);
        
        // Test getPaymentMethods
        $paymentMethod = $reflection->getMethod('getPaymentMethods');
        $paymentMethod->setAccessible(true);
        $paymentMethods = $paymentMethod->invoke($controller, $outletFilter, $dateFrom, $dateTo);
        
        echo "✅ getPaymentMethods method successful!\n";
        echo "   - Payment methods returned: " . count($paymentMethods) . "\n";
        
        if (count($paymentMethods) > 0) {
            echo "   - Sample data: {$paymentMethods[0]->payment_type} ({$paymentMethods[0]->payment_code})\n";
        }
        
        // Test getOverviewMetrics
        $overviewMethod = $reflection->getMethod('getOverviewMetrics');
        $overviewMethod->setAccessible(true);
        $overview = $overviewMethod->invoke($controller, $outletFilter, $dateFrom, $dateTo);
        
        echo "✅ getOverviewMetrics method successful!\n";
        echo "   - Average Check: " . number_format($overview['avg_check'], 2) . "\n";
        echo "   - Total Revenue: " . number_format($overview['total_revenue'], 2) . "\n";
        echo "   - Total Customers: " . number_format($overview['total_customers']) . "\n\n";
        
    } catch (Exception $e) {
        echo "❌ Controller methods test failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 4: Check with different date range (last 30 days)
    echo "4️⃣ Testing with last 30 days...\n";
    
    $last30DaysFrom = date('Y-m-d', strtotime('-30 days'));
    $last30DaysTo = date('Y-m-d');
    
    echo "📅 Date Range: {$last30DaysFrom} to {$last30DaysTo}\n";
    
    $query2 = "
        SELECT 
            op.payment_type,
            op.payment_code,
            COUNT(*) as transaction_count,
            SUM(op.amount) as total_amount
        FROM order_payment op
        INNER JOIN orders o ON op.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN '{$last30DaysFrom}' AND '{$last30DaysTo}'
        GROUP BY op.payment_type, op.payment_code
        ORDER BY total_amount DESC
        LIMIT 10
    ";
    
    try {
        $result2 = DB::select($query2);
        echo "✅ Last 30 days Payment Methods query successful!\n";
        echo "   - Top payment method combinations: " . count($result2) . "\n\n";
        
        if (count($result2) > 0) {
            echo "📊 Top Payment Method Combinations (Last 30 Days):\n";
            foreach ($result2 as $index => $method) {
                echo "   " . ($index + 1) . ". {$method->payment_type} ({$method->payment_code})\n";
                echo "      - Transactions: " . number_format($method->transaction_count) . "\n";
                echo "      - Total Amount: " . number_format($method->total_amount, 2) . "\n\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Last 30 days query failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 5: Check unique payment codes
    echo "5️⃣ Checking unique payment codes...\n";
    
    $uniqueCodesQuery = "
        SELECT DISTINCT payment_code, COUNT(*) as usage_count
        FROM order_payment op
        INNER JOIN orders o ON op.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
        GROUP BY payment_code
        ORDER BY usage_count DESC
    ";
    
    try {
        $uniqueCodes = DB::select($uniqueCodesQuery);
        echo "✅ Unique payment codes query successful!\n";
        echo "   - Unique payment codes found: " . count($uniqueCodes) . "\n\n";
        
        if (count($uniqueCodes) > 0) {
            echo "📊 Payment Codes Usage:\n";
            foreach ($uniqueCodes as $index => $code) {
                echo "   " . ($index + 1) . ". {$code->payment_code} - " . number_format($code->usage_count) . " transactions\n";
            }
            echo "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Unique payment codes query failed: " . $e->getMessage() . "\n\n";
    }
    
    echo "🎉 Payment Code & Average Check Test Completed!\n\n";
    
    echo "📝 Summary:\n";
    echo "   - Payment Methods chart now shows payment_code\n";
    echo "   - Average Check statistic added to overview cards\n";
    echo "   - Enhanced tooltips with detailed payment information\n";
    echo "   - Better chart labels with payment type and code\n\n";
    
    echo "🚀 Next Steps:\n";
    echo "   1. Test dashboard at: /sales-outlet-dashboard\n";
    echo "   2. Verify Payment Methods chart shows payment codes\n";
    echo "   3. Check Average Check card displays correctly\n";
    echo "   4. Test tooltips show detailed payment information\n";
    echo "   5. Verify responsive layout with 5 cards\n\n";
    
    echo "🔧 Features Added:\n";
    echo "   - Payment Methods: payment_type (payment_code) format\n";
    echo "   - Average Check: Total Revenue / Total Customers\n";
    echo "   - Enhanced tooltips with transaction details\n";
    echo "   - Better chart legends and labels\n";
    echo "   - Responsive 5-column layout\n\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
