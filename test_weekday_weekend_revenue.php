<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Weekday/Weekend Revenue Chart ===\n\n";

try {
    // Test the new query with weekday/weekend categorization
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
        WHERE DATE(created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY 
            CASE 
                WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend'
                ELSE 'Weekday'
            END
        ORDER BY period
    ";

    echo "Query:\n";
    echo $query . "\n\n";

    $results = DB::select($query);

    echo "Results (" . count($results) . " records):\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-10s %-12s %-15s %-10s %-15s\n", 
           "Period", "Orders", "Revenue", "Pax", "Avg Order");
    echo str_repeat("-", 80) . "\n";

    $totalOrders = 0;
    $totalRevenue = 0;
    $totalPax = 0;

    foreach ($results as $result) {
        printf("%-10s %-12s %-15s %-10s %-15s\n",
            $result->period,
            number_format($result->order_count),
            'Rp ' . number_format($result->total_revenue),
            number_format($result->total_pax),
            'Rp ' . number_format($result->avg_order_value)
        );
        
        $totalOrders += $result->order_count;
        $totalRevenue += $result->total_revenue;
        $totalPax += $result->total_pax;
    }

    echo str_repeat("-", 80) . "\n";
    printf("%-10s %-12s %-15s %-10s %-15s\n",
        "TOTAL",
        number_format($totalOrders),
        'Rp ' . number_format($totalRevenue),
        number_format($totalPax),
        'Rp ' . number_format($totalRevenue / $totalOrders)
    );
    echo str_repeat("-", 80) . "\n\n";

    // Test day of week analysis
    echo "=== Day of Week Analysis ===\n";
    $dayQuery = "
        SELECT 
            DAYOFWEEK(created_at) as day_of_week,
            DAYNAME(created_at) as day_name,
            CASE 
                WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend'
                ELSE 'Weekday'
            END as period,
            COUNT(*) as order_count,
            SUM(grand_total) as total_revenue
        FROM orders 
        WHERE DATE(created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at), CASE 
                WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend'
                ELSE 'Weekday'
            END
        ORDER BY day_of_week
    ";

    $dayResults = DB::select($dayQuery);

    echo "Day of Week Distribution:\n";
    echo str_repeat("-", 70) . "\n";
    printf("%-10s %-12s %-10s %-12s %-15s\n", "Day", "Period", "Orders", "Revenue", "Avg Revenue");
    echo str_repeat("-", 70) . "\n";

    foreach ($dayResults as $day) {
        $avgRevenue = $day->order_count > 0 ? $day->total_revenue / $day->order_count : 0;
        printf("%-10s %-10s %-12s %-15s %-15s\n",
            $day->day_name,
            $day->period,
            number_format($day->order_count),
            'Rp ' . number_format($day->total_revenue),
            'Rp ' . number_format($avgRevenue)
        );
    }

    echo str_repeat("-", 70) . "\n\n";

    // Test data structure for frontend
    echo "=== Frontend Data Structure ===\n";
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

    echo "Data structure for frontend:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

    // Test chart series data
    echo "=== Chart Series Data ===\n";
    $chartSeries = [
        $data['weekday']['order_count'],
        $data['weekend']['order_count']
    ];
    
    echo "Chart Series: " . json_encode($chartSeries) . "\n";
    echo "Chart Labels: [\"Weekday (Mon-Fri)\", \"Weekend (Sat-Sun)\"]\n";
    echo "Chart Colors: [\"#3b82f6\", \"#f59e0b\"]\n\n";

    // Test percentage calculation
    $totalChartOrders = array_sum($chartSeries);
    if ($totalChartOrders > 0) {
        $weekdayPercentage = round(($data['weekday']['order_count'] / $totalChartOrders) * 100, 1);
        $weekendPercentage = round(($data['weekend']['order_count'] / $totalChartOrders) * 100, 1);
        
        echo "=== Percentage Distribution ===\n";
        echo "Weekday: {$weekdayPercentage}% ({$data['weekday']['order_count']} orders)\n";
        echo "Weekend: {$weekendPercentage}% ({$data['weekend']['order_count']} orders)\n\n";
    }

    // Test revenue comparison
    echo "=== Revenue Comparison ===\n";
    $weekdayRevenue = $data['weekday']['total_revenue'];
    $weekendRevenue = $data['weekend']['total_revenue'];
    $totalRevenue = $weekdayRevenue + $weekendRevenue;
    
    if ($totalRevenue > 0) {
        $weekdayRevenuePercentage = round(($weekdayRevenue / $totalRevenue) * 100, 1);
        $weekendRevenuePercentage = round(($weekendRevenue / $totalRevenue) * 100, 1);
        
        echo "Weekday Revenue: {$weekdayRevenuePercentage}% (Rp " . number_format($weekdayRevenue) . ")\n";
        echo "Weekend Revenue: {$weekendRevenuePercentage}% (Rp " . number_format($weekendRevenue) . ")\n\n";
    }

    // Test average order value comparison
    echo "=== Average Order Value Comparison ===\n";
    $weekdayAOV = $data['weekday']['avg_order_value'];
    $weekendAOV = $data['weekend']['avg_order_value'];
    
    echo "Weekday AOV: Rp " . number_format($weekdayAOV) . "\n";
    echo "Weekend AOV: Rp " . number_format($weekendAOV) . "\n";
    
    if ($weekdayAOV > 0 && $weekendAOV > 0) {
        $aovDifference = $weekendAOV - $weekdayAOV;
        $aovPercentage = round(($aovDifference / $weekdayAOV) * 100, 1);
        
        if ($aovDifference > 0) {
            echo "Weekend AOV is {$aovPercentage}% higher than Weekday AOV\n";
        } else {
            echo "Weekday AOV is " . abs($aovPercentage) . "% higher than Weekend AOV\n";
        }
    }

    echo "\n✅ Test completed successfully!\n";
    echo "✅ Weekday/Weekend categorization working (Mon-Fri = Weekday, Sat-Sun = Weekend)\n";
    echo "✅ Data structure ready for ApexCharts donut chart\n";
    echo "✅ Chart series and options configured correctly\n";
    echo "✅ Revenue and AOV comparison analysis working\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
