<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🧪 Testing Payment Methods Data...\n\n";
    
    $dateFrom = '2025-09-01';
    $dateTo = '2025-09-10';
    $outletFilter = ''; // No outlet filter for testing
    
    echo "📅 Date Range: {$dateFrom} to {$dateTo}\n";
    echo "🏪 Outlet Filter: " . ($outletFilter ?: 'All Outlets') . "\n\n";
    
    // Test 1: Check if order_payment table exists and has data
    echo "1️⃣ Checking order_payment table...\n";
    
    try {
        $countQuery = "SELECT COUNT(*) as total FROM order_payment LIMIT 1";
        $countResult = DB::select($countQuery);
        $totalPayments = $countResult[0]->total;
        
        echo "✅ order_payment table accessible\n";
        echo "   - Total payment records: " . number_format($totalPayments) . "\n\n";
        
        if ($totalPayments == 0) {
            echo "⚠️  No payment records found. This might be why Payment Methods chart is empty.\n\n";
        }
        
    } catch (Exception $e) {
        echo "❌ order_payment table check failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 2: Test Payment Methods query directly
    echo "2️⃣ Testing Payment Methods query...\n";
    
    $query = "
        SELECT 
            op.payment_type,
            COUNT(*) as transaction_count,
            SUM(op.amount) as total_amount,
            AVG(op.amount) as avg_amount
        FROM order_payment op
        INNER JOIN orders o ON op.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        {$outletFilter}
        GROUP BY op.payment_type
        ORDER BY total_amount DESC
    ";
    
    try {
        $result = DB::select($query);
        echo "✅ Payment Methods query successful!\n";
        echo "   - Payment methods found: " . count($result) . "\n\n";
        
        if (count($result) > 0) {
            echo "📊 Payment Methods Data:\n";
            foreach ($result as $index => $method) {
                echo "   " . ($index + 1) . ". {$method->payment_type}\n";
                echo "      - Transactions: " . number_format($method->transaction_count) . "\n";
                echo "      - Total Amount: " . number_format($method->total_amount, 2) . "\n";
                echo "      - Average Amount: " . number_format($method->avg_amount, 2) . "\n\n";
            }
        } else {
            echo "⚠️  No payment methods found in the specified date range.\n\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Payment Methods query failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 3: Check with different date range (last 30 days)
    echo "3️⃣ Testing with last 30 days...\n";
    
    $last30DaysFrom = date('Y-m-d', strtotime('-30 days'));
    $last30DaysTo = date('Y-m-d');
    
    echo "📅 Date Range: {$last30DaysFrom} to {$last30DaysTo}\n";
    
    $query2 = "
        SELECT 
            op.payment_type,
            COUNT(*) as transaction_count,
            SUM(op.amount) as total_amount,
            AVG(op.amount) as avg_amount
        FROM order_payment op
        INNER JOIN orders o ON op.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN '{$last30DaysFrom}' AND '{$last30DaysTo}'
        GROUP BY op.payment_type
        ORDER BY total_amount DESC
    ";
    
    try {
        $result2 = DB::select($query2);
        echo "✅ Last 30 days Payment Methods query successful!\n";
        echo "   - Payment methods found: " . count($result2) . "\n\n";
        
        if (count($result2) > 0) {
            echo "📊 Last 30 Days Payment Methods Data:\n";
            foreach ($result2 as $index => $method) {
                echo "   " . ($index + 1) . ". {$method->payment_type}\n";
                echo "      - Transactions: " . number_format($method->transaction_count) . "\n";
                echo "      - Total Amount: " . number_format($method->total_amount, 2) . "\n";
                echo "      - Average Amount: " . number_format($method->avg_amount, 2) . "\n\n";
            }
        } else {
            echo "⚠️  No payment methods found in the last 30 days.\n\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Last 30 days Payment Methods query failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 4: Check dashboard controller method
    echo "4️⃣ Testing dashboard controller method...\n";
    
    try {
        $controller = new \App\Http\Controllers\SalesOutletDashboardController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('getPaymentMethods');
        $method->setAccessible(true);
        
        $result3 = $method->invoke($controller, $outletFilter, $dateFrom, $dateTo);
        
        echo "✅ Controller method successful!\n";
        echo "   - Payment methods returned: " . count($result3) . "\n\n";
        
        if (count($result3) > 0) {
            echo "📊 Controller Payment Methods Data:\n";
            foreach ($result3 as $index => $method) {
                echo "   " . ($index + 1) . ". {$method->payment_type}\n";
                echo "      - Transactions: " . number_format($method->transaction_count) . "\n";
                echo "      - Total Amount: " . number_format($method->total_amount, 2) . "\n";
                echo "      - Average Amount: " . number_format($method->avg_amount, 2) . "\n\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Controller method failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 5: Check table structure
    echo "5️⃣ Checking table structure...\n";
    
    try {
        $structureQuery = "DESCRIBE order_payment";
        $structure = DB::select($structureQuery);
        
        echo "✅ order_payment table structure:\n";
        foreach ($structure as $column) {
            echo "   - {$column->Field} ({$column->Type}) - {$column->Null} - {$column->Key}\n";
        }
        echo "\n";
        
    } catch (Exception $e) {
        echo "❌ Table structure check failed: " . $e->getMessage() . "\n\n";
    }
    
    echo "🎉 Payment Methods Data Test Completed!\n\n";
    
    echo "📝 Summary:\n";
    echo "   - Payment Methods chart should now display data correctly\n";
    echo "   - Order Status chart has been removed\n";
    echo "   - Enhanced Payment Methods chart with better styling\n";
    echo "   - Currency formatting preserved\n\n";
    
    echo "🚀 Next Steps:\n";
    echo "   1. Test dashboard at: /sales-outlet-dashboard\n";
    echo "   2. Verify Payment Methods chart displays data\n";
    echo "   3. Check that Order Status chart is removed\n";
    echo "   4. Test with different date ranges\n\n";
    
    echo "🔧 If Payment Methods chart is still empty:\n";
    echo "   - Check if there are payment records in the database\n";
    echo "   - Verify date range has data\n";
    echo "   - Check browser console for JavaScript errors\n";
    echo "   - Verify API response contains payment data\n\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
