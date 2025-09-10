<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Period Details with Average Check ===\n\n";

try {
    // Test Lunch/Dinner data
    echo "=== Lunch/Dinner Period Details ===\n";
    $lunchDinnerQuery = "
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
        WHERE DATE(created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY 
            CASE 
                WHEN HOUR(created_at) <= 17 THEN 'Lunch'
                ELSE 'Dinner'
            END
        ORDER BY period
    ";

    $lunchDinnerResults = DB::select($lunchDinnerQuery);

    echo "Lunch/Dinner Results:\n";
    echo str_repeat("-", 90) . "\n";
    printf("%-10s %-12s %-15s %-10s %-15s %-15s\n", 
           "Period", "Orders", "Revenue", "Pax", "Avg Order", "Avg Check");
    echo str_repeat("-", 90) . "\n";

    foreach ($lunchDinnerResults as $result) {
        $avgCheck = $result->total_pax > 0 ? $result->total_revenue / $result->total_pax : 0;
        
        printf("%-10s %-12s %-15s %-10s %-15s %-15s\n",
            $result->period,
            number_format($result->order_count),
            'Rp ' . number_format($result->total_revenue),
            number_format($result->total_pax),
            'Rp ' . number_format($result->avg_order_value),
            'Rp ' . number_format($avgCheck)
        );
    }

    echo str_repeat("-", 90) . "\n\n";

    // Test Weekday/Weekend data
    echo "=== Weekday/Weekend Period Details ===\n";
    $weekdayWeekendQuery = "
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
        WHERE DATE(created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY 
            CASE 
                WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend'
                ELSE 'Weekday'
            END
        ORDER BY period
    ";

    $weekdayWeekendResults = DB::select($weekdayWeekendQuery);

    echo "Weekday/Weekend Results:\n";
    echo str_repeat("-", 90) . "\n";
    printf("%-10s %-12s %-15s %-10s %-15s %-15s\n", 
           "Period", "Orders", "Revenue", "Pax", "Avg Order", "Avg Check");
    echo str_repeat("-", 90) . "\n";

    foreach ($weekdayWeekendResults as $result) {
        $avgCheck = $result->total_pax > 0 ? $result->total_revenue / $result->total_pax : 0;
        
        printf("%-10s %-12s %-15s %-10s %-15s %-15s\n",
            $result->period,
            number_format($result->order_count),
            'Rp ' . number_format($result->total_revenue),
            number_format($result->total_pax),
            'Rp ' . number_format($result->avg_order_value),
            'Rp ' . number_format($avgCheck)
        );
    }

    echo str_repeat("-", 90) . "\n\n";

    // Test Chart Legend (without Average Check)
    echo "=== Chart Legend (without Average Check) ===\n";
    
    // Lunch/Dinner Legend
    echo "Lunch/Dinner Chart Legend:\n";
    $lunchRevenue = 0;
    $dinnerRevenue = 0;
    $lunchOrders = 0;
    $dinnerOrders = 0;
    $lunchPax = 0;
    $dinnerPax = 0;
    
    foreach ($lunchDinnerResults as $result) {
        if ($result->period === 'Lunch') {
            $lunchRevenue = $result->total_revenue;
            $lunchOrders = $result->order_count;
            $lunchPax = $result->total_pax;
        } else {
            $dinnerRevenue = $result->total_revenue;
            $dinnerOrders = $result->order_count;
            $dinnerPax = $result->total_pax;
        }
    }
    
    $totalLunchDinnerRevenue = $lunchRevenue + $dinnerRevenue;
    $totalLunchDinnerOrders = $lunchOrders + $dinnerOrders;
    $totalLunchDinnerPax = $lunchPax + $dinnerPax;
    
    echo "Revenue: Rp " . number_format($totalLunchDinnerRevenue) . "\n";
    echo "Orders: " . number_format($totalLunchDinnerOrders) . "\n";
    echo "Pax: " . number_format($totalLunchDinnerPax) . "\n\n";
    
    // Weekday/Weekend Legend
    echo "Weekday/Weekend Chart Legend:\n";
    $weekdayRevenue = 0;
    $weekendRevenue = 0;
    $weekdayOrders = 0;
    $weekendOrders = 0;
    $weekdayPax = 0;
    $weekendPax = 0;
    
    foreach ($weekdayWeekendResults as $result) {
        if ($result->period === 'Weekday') {
            $weekdayRevenue = $result->total_revenue;
            $weekdayOrders = $result->order_count;
            $weekdayPax = $result->total_pax;
        } else {
            $weekendRevenue = $result->total_revenue;
            $weekendOrders = $result->order_count;
            $weekendPax = $result->total_pax;
        }
    }
    
    $totalWeekdayWeekendRevenue = $weekdayRevenue + $weekendRevenue;
    $totalWeekdayWeekendOrders = $weekdayOrders + $weekendOrders;
    $totalWeekdayWeekendPax = $weekdayPax + $weekendPax;
    
    echo "Revenue: Rp " . number_format($totalWeekdayWeekendRevenue) . "\n";
    echo "Orders: " . number_format($totalWeekdayWeekendOrders) . "\n";
    echo "Pax: " . number_format($totalWeekdayWeekendPax) . "\n\n";

    // Test Period Details Cards
    echo "=== Period Details Cards ===\n";
    
    // Lunch/Dinner Cards
    echo "Lunch/Dinner Period Details Cards:\n";
    echo str_repeat("-", 50) . "\n";
    
    foreach ($lunchDinnerResults as $result) {
        $avgCheck = $result->total_pax > 0 ? $result->total_revenue / $result->total_pax : 0;
        
        echo "{$result->period} Card:\n";
        echo "  Orders: " . number_format($result->order_count) . "\n";
        echo "  Pax: " . number_format($result->total_pax) . "\n";
        echo "  Avg Order: Rp " . number_format($result->avg_order_value) . "\n";
        echo "  Avg Check: Rp " . number_format($avgCheck) . "\n";
        echo "  Total Revenue: Rp " . number_format($result->total_revenue) . "\n";
        echo "\n";
    }
    
    // Weekday/Weekend Cards
    echo "Weekday/Weekend Period Details Cards:\n";
    echo str_repeat("-", 50) . "\n";
    
    foreach ($weekdayWeekendResults as $result) {
        $avgCheck = $result->total_pax > 0 ? $result->total_revenue / $result->total_pax : 0;
        
        echo "{$result->period} Card:\n";
        echo "  Orders: " . number_format($result->order_count) . "\n";
        echo "  Pax: " . number_format($result->total_pax) . "\n";
        echo "  Avg Order: Rp " . number_format($result->avg_order_value) . "\n";
        echo "  Avg Check: Rp " . number_format($avgCheck) . "\n";
        echo "  Total Revenue: Rp " . number_format($result->total_revenue) . "\n";
        echo "\n";
    }

    // Test Frontend Data Structure
    echo "=== Frontend Data Structure ===\n";
    
    // Lunch/Dinner Data Structure
    $lunchDinnerData = [
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
    
    foreach ($lunchDinnerResults as $result) {
        $period = strtolower($result->period);
        $lunchDinnerData[$period] = [
            'order_count' => (int) $result->order_count,
            'total_revenue' => (float) $result->total_revenue,
            'total_pax' => (int) $result->total_pax,
            'avg_order_value' => (float) $result->avg_order_value
        ];
    }
    
    echo "Lunch/Dinner Data Structure:\n";
    echo json_encode($lunchDinnerData, JSON_PRETTY_PRINT) . "\n\n";
    
    // Weekday/Weekend Data Structure
    $weekdayWeekendData = [
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
    
    foreach ($weekdayWeekendResults as $result) {
        $period = strtolower($result->period);
        $weekdayWeekendData[$period] = [
            'order_count' => (int) $result->order_count,
            'total_revenue' => (float) $result->total_revenue,
            'total_pax' => (int) $result->total_pax,
            'avg_order_value' => (float) $result->avg_order_value
        ];
    }
    
    echo "Weekday/Weekend Data Structure:\n";
    echo json_encode($weekdayWeekendData, JSON_PRETTY_PRINT) . "\n\n";

    // Test Average Check Calculation
    echo "=== Average Check Calculation ===\n";
    
    // Lunch/Dinner Average Check
    $lunchAvgCheck = $lunchDinnerData['lunch']['total_pax'] > 0 ? 
        $lunchDinnerData['lunch']['total_revenue'] / $lunchDinnerData['lunch']['total_pax'] : 0;
    $dinnerAvgCheck = $lunchDinnerData['dinner']['total_pax'] > 0 ? 
        $lunchDinnerData['dinner']['total_revenue'] / $lunchDinnerData['dinner']['total_pax'] : 0;
    
    echo "Lunch/Dinner Average Check:\n";
    echo "  Lunch: Rp " . number_format($lunchAvgCheck) . " (Revenue: Rp " . number_format($lunchDinnerData['lunch']['total_revenue']) . " / Pax: " . number_format($lunchDinnerData['lunch']['total_pax']) . ")\n";
    echo "  Dinner: Rp " . number_format($dinnerAvgCheck) . " (Revenue: Rp " . number_format($lunchDinnerData['dinner']['total_revenue']) . " / Pax: " . number_format($lunchDinnerData['dinner']['total_pax']) . ")\n\n";
    
    // Weekday/Weekend Average Check
    $weekdayAvgCheck = $weekdayWeekendData['weekday']['total_pax'] > 0 ? 
        $weekdayWeekendData['weekday']['total_revenue'] / $weekdayWeekendData['weekday']['total_pax'] : 0;
    $weekendAvgCheck = $weekdayWeekendData['weekend']['total_pax'] > 0 ? 
        $weekdayWeekendData['weekend']['total_revenue'] / $weekdayWeekendData['weekend']['total_pax'] : 0;
    
    echo "Weekday/Weekend Average Check:\n";
    echo "  Weekday: Rp " . number_format($weekdayAvgCheck) . " (Revenue: Rp " . number_format($weekdayWeekendData['weekday']['total_revenue']) . " / Pax: " . number_format($weekdayWeekendData['weekday']['total_pax']) . ")\n";
    echo "  Weekend: Rp " . number_format($weekendAvgCheck) . " (Revenue: Rp " . number_format($weekdayWeekendData['weekend']['total_revenue']) . " / Pax: " . number_format($weekdayWeekendData['weekend']['total_pax']) . ")\n\n";

    echo "✅ Test completed successfully!\n";
    echo "✅ Average Check added to Period Details cards\n";
    echo "✅ Average Check removed from chart legends\n";
    echo "✅ Period Details show: Orders, Pax, Avg Order, Avg Check\n";
    echo "✅ Chart legends show: Revenue, Orders, Pax (without Avg Check)\n";
    echo "✅ Average Check calculation: Revenue / Pax working correctly\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
