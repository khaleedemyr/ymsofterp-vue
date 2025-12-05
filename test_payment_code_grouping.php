<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🧪 Testing Payment Code Grouping...\n\n";
    
    $dateFrom = '2025-09-01';
    $dateTo = '2025-09-10';
    $outletFilter = ''; // No outlet filter for testing
    
    echo "📅 Date Range: {$dateFrom} to {$dateTo}\n";
    echo "🏪 Outlet Filter: " . ($outletFilter ?: 'All Outlets') . "\n\n";
    
    // Test 1: Check Payment Methods grouped by payment_code
    echo "1️⃣ Testing Payment Methods grouped by payment_code...\n";
    
    $chartQuery = "
        SELECT 
            op.payment_code,
            COUNT(*) as transaction_count,
            SUM(op.amount) as total_amount,
            AVG(op.amount) as avg_amount
        FROM order_payment op
        INNER JOIN orders o ON op.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        {$outletFilter}
        GROUP BY op.payment_code
        ORDER BY total_amount DESC
    ";
    
    try {
        $chartResult = DB::select($chartQuery);
        echo "✅ Payment Methods by payment_code query successful!\n";
        echo "   - Payment codes found: " . count($chartResult) . "\n\n";
        
        if (count($chartResult) > 0) {
            echo "📊 Payment Methods by Payment Code:\n";
            foreach ($chartResult as $index => $method) {
                echo "   " . ($index + 1) . ". {$method->payment_code}\n";
                echo "      - Transactions: " . number_format($method->transaction_count) . "\n";
                echo "      - Total Amount: " . number_format($method->total_amount, 2) . "\n";
                echo "      - Average Amount: " . number_format($method->avg_amount, 2) . "\n\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Payment Methods by payment_code query failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 2: Check detailed breakdown by payment_type
    echo "2️⃣ Testing detailed breakdown by payment_type...\n";
    
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
        {$outletFilter}
        GROUP BY op.payment_code, op.payment_type
        ORDER BY op.payment_code, total_amount DESC
    ";
    
    try {
        $detailResult = DB::select($detailQuery);
        echo "✅ Payment Methods detail query successful!\n";
        echo "   - Payment method combinations found: " . count($detailResult) . "\n\n";
        
        // Group by payment_code
        $groupedDetails = [];
        foreach ($detailResult as $detail) {
            if (!isset($groupedDetails[$detail->payment_code])) {
                $groupedDetails[$detail->payment_code] = [];
            }
            $groupedDetails[$detail->payment_code][] = $detail;
        }
        
        echo "📊 Payment Methods Detail by Payment Code:\n";
        foreach ($groupedDetails as $paymentCode => $details) {
            echo "   {$paymentCode}:\n";
            foreach ($details as $detail) {
                echo "      - {$detail->payment_type}: " . number_format($detail->total_amount, 2) . "\n";
            }
            echo "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Payment Methods detail query failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 3: Check dashboard controller method
    echo "3️⃣ Testing dashboard controller method...\n";
    
    try {
        $controller = new \App\Http\Controllers\SalesOutletDashboardController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('getPaymentMethods');
        $method->setAccessible(true);
        
        $result = $method->invoke($controller, $outletFilter, $dateFrom, $dateTo);
        
        echo "✅ Controller method successful!\n";
        echo "   - Payment methods returned: " . count($result) . "\n\n";
        
        if (count($result) > 0) {
            echo "📊 Controller Payment Methods Data:\n";
            foreach ($result as $index => $method) {
                echo "   " . ($index + 1) . ". {$method['payment_code']}\n";
                echo "      - Total Amount: " . number_format($method['total_amount'], 2) . "\n";
                echo "      - Details: " . count($method['details']) . " payment types\n";
                
                if (count($method['details']) > 0) {
                    foreach ($method['details'] as $detail) {
                        echo "         - {$detail->payment_type}: " . number_format($detail->total_amount, 2) . "\n";
                    }
                }
                echo "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Controller method failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 4: Check with different date range (last 30 days)
    echo "4️⃣ Testing with last 30 days...\n";
    
    $last30DaysFrom = date('Y-m-d', strtotime('-30 days'));
    $last30DaysTo = date('Y-m-d');
    
    echo "📅 Date Range: {$last30DaysFrom} to {$last30DaysTo}\n";
    
    $query2 = "
        SELECT 
            op.payment_code,
            COUNT(*) as transaction_count,
            SUM(op.amount) as total_amount
        FROM order_payment op
        INNER JOIN orders o ON op.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN '{$last30DaysFrom}' AND '{$last30DaysTo}'
        GROUP BY op.payment_code
        ORDER BY total_amount DESC
        LIMIT 10
    ";
    
    try {
        $result2 = DB::select($query2);
        echo "✅ Last 30 days Payment Methods query successful!\n";
        echo "   - Top payment codes: " . count($result2) . "\n\n";
        
        if (count($result2) > 0) {
            echo "📊 Top Payment Codes (Last 30 Days):\n";
            foreach ($result2 as $index => $method) {
                echo "   " . ($index + 1) . ". {$method->payment_code}\n";
                echo "      - Transactions: " . number_format($method->transaction_count) . "\n";
                echo "      - Total Amount: " . number_format($method->total_amount, 2) . "\n\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Last 30 days query failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test 5: Check sample data structure
    echo "5️⃣ Checking sample data structure...\n";
    
    try {
        $controller = new \App\Http\Controllers\SalesOutletDashboardController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('getPaymentMethods');
        $method->setAccessible(true);
        
        $result = $method->invoke($controller, $outletFilter, $dateFrom, $dateTo);
        
        if (count($result) > 0) {
            $sample = $result[0];
            echo "✅ Sample data structure:\n";
            echo "   - payment_code: " . $sample['payment_code'] . "\n";
            echo "   - transaction_count: " . $sample['transaction_count'] . "\n";
            echo "   - total_amount: " . $sample['total_amount'] . "\n";
            echo "   - avg_amount: " . $sample['avg_amount'] . "\n";
            echo "   - details count: " . count($sample['details']) . "\n";
            
            if (count($sample['details']) > 0) {
                echo "   - Sample detail:\n";
                $sampleDetail = $sample['details'][0];
                echo "     - payment_type: " . $sampleDetail->payment_type . "\n";
                echo "     - total_amount: " . $sampleDetail->total_amount . "\n";
            }
            echo "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Sample data structure check failed: " . $e->getMessage() . "\n\n";
    }
    
    echo "🎉 Payment Code Grouping Test Completed!\n\n";
    
    echo "📝 Summary:\n";
    echo "   - Payment Methods chart now groups by payment_code\n";
    echo "   - Detail breakdown shows payment_type under each payment_code\n";
    echo "   - Chart displays payment codes only\n";
    echo "   - Details section shows format: BCA-Credit = 250.000\n\n";
    
    echo "🚀 Next Steps:\n";
    echo "   1. Test dashboard at: /sales-outlet-dashboard\n";
    echo "   2. Verify Payment Methods chart shows payment codes only\n";
    echo "   3. Check detail section shows payment types under each code\n";
    echo "   4. Verify format: BCA-Credit = 250.000\n";
    echo "   5. Test responsive layout\n\n";
    
    echo "🔧 Features Updated:\n";
    echo "   - Chart: Groups by payment_code only\n";
    echo "   - Details: Shows payment_type breakdown under each code\n";
    echo "   - Format: payment_code-payment_type = amount\n";
    echo "   - Layout: Full screen responsive design\n\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
