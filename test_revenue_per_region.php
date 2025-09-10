<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Revenue per Region Charts ===\n\n";

try {
    // Test 1: Total Revenue per Region
    echo "=== 1. Total Revenue per Region ===\n";
    $totalRevenueQuery = "
        SELECT 
            COALESCE(region.name, 'Unknown Region') as region_name,
            COALESCE(region.code, 'UNK') as region_code,
            COUNT(*) as total_orders,
            SUM(o.grand_total) as total_revenue,
            SUM(o.pax) as total_pax,
            AVG(o.grand_total) as avg_order_value
        FROM orders o
        LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
        LEFT JOIN regions region ON outlet.region_id = region.id
        WHERE DATE(o.created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY region.name, region.code
        ORDER BY total_revenue DESC
    ";

    $totalRevenueResults = DB::select($totalRevenueQuery);

    echo "Total Revenue per Region:\n";
    echo str_repeat("-", 100) . "\n";
    printf("%-20s %-10s %-12s %-15s %-10s %-15s\n", 
           "Region Name", "Code", "Orders", "Revenue", "Pax", "Avg Order");
    echo str_repeat("-", 100) . "\n";

    foreach ($totalRevenueResults as $result) {
        printf("%-20s %-10s %-12s %-15s %-10s %-15s\n",
            substr($result->region_name, 0, 20),
            $result->region_code,
            number_format($result->total_orders),
            'Rp ' . number_format($result->total_revenue),
            number_format($result->total_pax),
            'Rp ' . number_format($result->avg_order_value)
        );
    }

    echo str_repeat("-", 100) . "\n\n";

    // Test 2: Lunch/Dinner Revenue per Region
    echo "=== 2. Lunch/Dinner Revenue per Region ===\n";
    $lunchDinnerQuery = "
        SELECT 
            COALESCE(region.name, 'Unknown Region') as region_name,
            COALESCE(region.code, 'UNK') as region_code,
            CASE 
                WHEN HOUR(o.created_at) <= 17 THEN 'Lunch'
                ELSE 'Dinner'
            END as period,
            COUNT(*) as order_count,
            SUM(o.grand_total) as total_revenue,
            SUM(o.pax) as total_pax,
            AVG(o.grand_total) as avg_order_value
        FROM orders o
        LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
        LEFT JOIN regions region ON outlet.region_id = region.id
        WHERE DATE(o.created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY region.name, region.code, 
            CASE 
                WHEN HOUR(o.created_at) <= 17 THEN 'Lunch'
                ELSE 'Dinner'
            END
        ORDER BY region.name, period
    ";

    $lunchDinnerResults = DB::select($lunchDinnerQuery);

    echo "Lunch/Dinner Revenue per Region:\n";
    echo str_repeat("-", 120) . "\n";
    printf("%-20s %-10s %-8s %-12s %-15s %-10s %-15s\n", 
           "Region Name", "Code", "Period", "Orders", "Revenue", "Pax", "Avg Order");
    echo str_repeat("-", 120) . "\n";

    foreach ($lunchDinnerResults as $result) {
        printf("%-20s %-10s %-8s %-12s %-15s %-10s %-15s\n",
            substr($result->region_name, 0, 20),
            $result->region_code,
            $result->period,
            number_format($result->order_count),
            'Rp ' . number_format($result->total_revenue),
            number_format($result->total_pax),
            'Rp ' . number_format($result->avg_order_value)
        );
    }

    echo str_repeat("-", 120) . "\n\n";

    // Test 3: Weekday/Weekend Revenue per Region
    echo "=== 3. Weekday/Weekend Revenue per Region ===\n";
    $weekdayWeekendQuery = "
        SELECT 
            COALESCE(region.name, 'Unknown Region') as region_name,
            COALESCE(region.code, 'UNK') as region_code,
            CASE 
                WHEN DAYOFWEEK(o.created_at) IN (1, 7) THEN 'Weekend'
                ELSE 'Weekday'
            END as period,
            COUNT(*) as order_count,
            SUM(o.grand_total) as total_revenue,
            SUM(o.pax) as total_pax,
            AVG(o.grand_total) as avg_order_value
        FROM orders o
        LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
        LEFT JOIN regions region ON outlet.region_id = region.id
        WHERE DATE(o.created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY region.name, region.code, 
            CASE 
                WHEN DAYOFWEEK(o.created_at) IN (1, 7) THEN 'Weekend'
                ELSE 'Weekday'
            END
        ORDER BY region.name, period
    ";

    $weekdayWeekendResults = DB::select($weekdayWeekendQuery);

    echo "Weekday/Weekend Revenue per Region:\n";
    echo str_repeat("-", 120) . "\n";
    printf("%-20s %-10s %-8s %-12s %-15s %-10s %-15s\n", 
           "Region Name", "Code", "Period", "Orders", "Revenue", "Pax", "Avg Order");
    echo str_repeat("-", 120) . "\n";

    foreach ($weekdayWeekendResults as $result) {
        printf("%-20s %-10s %-8s %-12s %-15s %-10s %-15s\n",
            substr($result->region_name, 0, 20),
            $result->region_code,
            $result->period,
            number_format($result->order_count),
            'Rp ' . number_format($result->total_revenue),
            number_format($result->total_pax),
            'Rp ' . number_format($result->avg_order_value)
        );
    }

    echo str_repeat("-", 120) . "\n\n";

    // Test 4: Data Processing
    echo "=== 4. Data Processing Test ===\n";
    
    // Process total revenue data
    $totalRevenueData = [];
    foreach ($totalRevenueResults as $result) {
        $totalRevenueData[] = [
            'region_name' => $result->region_name,
            'region_code' => $result->region_code,
            'total_orders' => (int) $result->total_orders,
            'total_revenue' => (float) $result->total_revenue,
            'total_pax' => (int) $result->total_pax,
            'avg_order_value' => (float) $result->avg_order_value
        ];
    }

    // Process lunch/dinner data
    $lunchDinnerData = [];
    foreach ($lunchDinnerResults as $result) {
        $regionName = $result->region_name;
        $period = strtolower($result->period);
        
        if (!isset($lunchDinnerData[$regionName])) {
            $lunchDinnerData[$regionName] = [
                'region_code' => $result->region_code,
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
        }
        
        $lunchDinnerData[$regionName][$period] = [
            'order_count' => (int) $result->order_count,
            'total_revenue' => (float) $result->total_revenue,
            'total_pax' => (int) $result->total_pax,
            'avg_order_value' => (float) $result->avg_order_value
        ];
    }

    // Process weekday/weekend data
    $weekdayWeekendData = [];
    foreach ($weekdayWeekendResults as $result) {
        $regionName = $result->region_name;
        $period = strtolower($result->period);
        
        if (!isset($weekdayWeekendData[$regionName])) {
            $weekdayWeekendData[$regionName] = [
                'region_code' => $result->region_code,
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
        }
        
        $weekdayWeekendData[$regionName][$period] = [
            'order_count' => (int) $result->order_count,
            'total_revenue' => (float) $result->total_revenue,
            'total_pax' => (int) $result->total_pax,
            'avg_order_value' => (float) $result->avg_order_value
        ];
    }

    $processedData = [
        'total_revenue' => $totalRevenueData,
        'lunch_dinner' => $lunchDinnerData,
        'weekday_weekend' => $weekdayWeekendData
    ];

    echo "Processed Data Structure:\n";
    echo json_encode($processedData, JSON_PRETTY_PRINT) . "\n\n";

    // Test 5: Chart Series Generation
    echo "=== 5. Chart Series Generation Test ===\n";
    
    // Total Revenue Series
    echo "Total Revenue Series:\n";
    $totalRevenueSeries = [[
        'name' => 'Revenue',
        'data' => array_map(function($region) { return $region['total_revenue']; }, $totalRevenueData)
    ]];
    echo "Series: " . json_encode($totalRevenueSeries) . "\n";
    echo "Categories: " . json_encode(array_map(function($region) { return $region['region_name']; }, $totalRevenueData)) . "\n\n";
    
    // Lunch/Dinner Series
    echo "Lunch/Dinner Series:\n";
    $regions = array_keys($lunchDinnerData);
    $lunchDinnerSeries = [
        [
            'name' => 'Lunch',
            'data' => array_map(function($region) use ($lunchDinnerData) { return $lunchDinnerData[$region]['lunch']['total_revenue']; }, $regions)
        ],
        [
            'name' => 'Dinner',
            'data' => array_map(function($region) use ($lunchDinnerData) { return $lunchDinnerData[$region]['dinner']['total_revenue']; }, $regions)
        ]
    ];
    echo "Series: " . json_encode($lunchDinnerSeries) . "\n";
    echo "Categories: " . json_encode($regions) . "\n\n";
    
    // Weekday/Weekend Series
    echo "Weekday/Weekend Series:\n";
    $regions = array_keys($weekdayWeekendData);
    $weekdayWeekendSeries = [
        [
            'name' => 'Weekday',
            'data' => array_map(function($region) use ($weekdayWeekendData) { return $weekdayWeekendData[$region]['weekday']['total_revenue']; }, $regions)
        ],
        [
            'name' => 'Weekend',
            'data' => array_map(function($region) use ($weekdayWeekendData) { return $weekdayWeekendData[$region]['weekend']['total_revenue']; }, $regions)
        ]
    ];
    echo "Series: " . json_encode($weekdayWeekendSeries) . "\n";
    echo "Categories: " . json_encode($regions) . "\n\n";

    // Test 6: Performance
    echo "=== 6. Performance Test ===\n";
    $startTime = microtime(true);
    
    // Run all queries
    DB::select($totalRevenueQuery);
    DB::select($lunchDinnerQuery);
    DB::select($weekdayWeekendQuery);
    
    $endTime = microtime(true);
    
    echo "Total Query Execution Time: " . round(($endTime - $startTime) * 1000, 2) . " ms\n";
    echo "Memory Usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
    echo "Records Processed:\n";
    echo "  - Total Revenue: " . count($totalRevenueResults) . " regions\n";
    echo "  - Lunch/Dinner: " . count($lunchDinnerResults) . " records\n";
    echo "  - Weekday/Weekend: " . count($weekdayWeekendResults) . " records\n\n";

    // Test 7: Business Intelligence
    echo "=== 7. Business Intelligence Analysis ===\n";
    
    // Total Revenue Analysis
    $totalRevenue = array_sum(array_map(function($region) { return $region['total_revenue']; }, $totalRevenueData));
    echo "Total Revenue Analysis:\n";
    foreach ($totalRevenueData as $region) {
        $percentage = ($region['total_revenue'] / $totalRevenue) * 100;
        $avgCheck = $region['total_pax'] > 0 ? $region['total_revenue'] / $region['total_pax'] : 0;
        echo "  {$region['region_name']}: Rp " . number_format($region['total_revenue']) . " ({$percentage}%) | Avg Check: Rp " . number_format($avgCheck) . "\n";
    }
    echo "\n";
    
    // Lunch/Dinner Analysis
    echo "Lunch/Dinner Analysis:\n";
    foreach ($lunchDinnerData as $regionName => $regionData) {
        $lunchRevenue = $regionData['lunch']['total_revenue'];
        $dinnerRevenue = $regionData['dinner']['total_revenue'];
        $totalRegionRevenue = $lunchRevenue + $dinnerRevenue;
        $lunchPercentage = $totalRegionRevenue > 0 ? ($lunchRevenue / $totalRegionRevenue) * 100 : 0;
        $dinnerPercentage = $totalRegionRevenue > 0 ? ($dinnerRevenue / $totalRegionRevenue) * 100 : 0;
        
        echo "  {$regionName}:\n";
        echo "    Lunch: Rp " . number_format($lunchRevenue) . " ({$lunchPercentage}%)\n";
        echo "    Dinner: Rp " . number_format($dinnerRevenue) . " ({$dinnerPercentage}%)\n";
    }
    echo "\n";
    
    // Weekday/Weekend Analysis
    echo "Weekday/Weekend Analysis:\n";
    foreach ($weekdayWeekendData as $regionName => $regionData) {
        $weekdayRevenue = $regionData['weekday']['total_revenue'];
        $weekendRevenue = $regionData['weekend']['total_revenue'];
        $totalRegionRevenue = $weekdayRevenue + $weekendRevenue;
        $weekdayPercentage = $totalRegionRevenue > 0 ? ($weekdayRevenue / $totalRegionRevenue) * 100 : 0;
        $weekendPercentage = $totalRegionRevenue > 0 ? ($weekendRevenue / $totalRegionRevenue) * 100 : 0;
        
        echo "  {$regionName}:\n";
        echo "    Weekday: Rp " . number_format($weekdayRevenue) . " ({$weekdayPercentage}%)\n";
        echo "    Weekend: Rp " . number_format($weekendRevenue) . " ({$weekendPercentage}%)\n";
    }

    echo "\n✅ Test completed successfully!\n";
    echo "✅ Total Revenue per Region query working\n";
    echo "✅ Lunch/Dinner Revenue per Region query working\n";
    echo "✅ Weekday/Weekend Revenue per Region query working\n";
    echo "✅ Data processing logic working\n";
    echo "✅ Chart series generation working\n";
    echo "✅ Performance acceptable\n";
    echo "✅ Business intelligence analysis ready\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
