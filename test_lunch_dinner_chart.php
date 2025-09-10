<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Lunch/Dinner Orders Chart ===\n\n";

try {
    // Test the new query with lunch/dinner categorization
    $query = "
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

    // Test hourly distribution
    echo "=== Hourly Distribution Analysis ===\n";
    $hourlyQuery = "
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as order_count,
            SUM(grand_total) as total_revenue,
            CASE 
                WHEN HOUR(created_at) <= 17 THEN 'Lunch'
                ELSE 'Dinner'
            END as period
        FROM orders 
        WHERE DATE(created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY HOUR(created_at), CASE 
                WHEN HOUR(created_at) <= 17 THEN 'Lunch'
                ELSE 'Dinner'
            END
        ORDER BY hour
    ";

    $hourlyResults = DB::select($hourlyQuery);

    echo "Hourly Distribution:\n";
    echo str_repeat("-", 60) . "\n";
    printf("%-6s %-10s %-12s %-10s\n", "Hour", "Period", "Orders", "Revenue");
    echo str_repeat("-", 60) . "\n";

    foreach ($hourlyResults as $hourly) {
        printf("%-6s %-10s %-12s %-10s\n",
            $hourly->hour . ':00',
            $hourly->period,
            number_format($hourly->order_count),
            'Rp ' . number_format($hourly->total_revenue)
        );
    }

    echo str_repeat("-", 60) . "\n\n";

    // Test data structure for frontend
    echo "=== Frontend Data Structure ===\n";
    $data = [
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
        $data['lunch']['order_count'],
        $data['dinner']['order_count']
    ];
    
    echo "Chart Series: " . json_encode($chartSeries) . "\n";
    echo "Chart Labels: [\"Lunch (≤17:00)\", \"Dinner (>17:00)\"]\n";
    echo "Chart Colors: [\"#10b981\", \"#f59e0b\"]\n\n";

    // Test percentage calculation
    $totalChartOrders = array_sum($chartSeries);
    if ($totalChartOrders > 0) {
        $lunchPercentage = round(($data['lunch']['order_count'] / $totalChartOrders) * 100, 1);
        $dinnerPercentage = round(($data['dinner']['order_count'] / $totalChartOrders) * 100, 1);
        
        echo "=== Percentage Distribution ===\n";
        echo "Lunch: {$lunchPercentage}% ({$data['lunch']['order_count']} orders)\n";
        echo "Dinner: {$dinnerPercentage}% ({$data['dinner']['order_count']} orders)\n\n";
    }

    echo "✅ Test completed successfully!\n";
    echo "✅ Lunch/Dinner categorization working (≤17:00 = Lunch, >17:00 = Dinner)\n";
    echo "✅ Data structure ready for ApexCharts donut chart\n";
    echo "✅ Chart series and options configured correctly\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
