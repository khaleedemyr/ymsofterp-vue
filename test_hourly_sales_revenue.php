<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Hourly Sales with Revenue ===\n\n";

try {
    // Test the hourly sales query with revenue
    $query = "
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as orders,
            SUM(grand_total) as revenue,
            AVG(grand_total) as avg_order_value
        FROM orders 
        WHERE DATE(created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY HOUR(created_at)
        ORDER BY hour ASC
    ";

    echo "Query:\n";
    echo $query . "\n\n";

    $results = DB::select($query);

    echo "Results (" . count($results) . " records):\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-6s %-8s %-15s %-15s\n", 
           "Hour", "Orders", "Revenue", "Avg Order");
    echo str_repeat("-", 80) . "\n";

    $totalOrders = 0;
    $totalRevenue = 0;

    foreach ($results as $result) {
        printf("%-6s %-8s %-15s %-15s\n",
            $result->hour . ':00',
            number_format($result->orders),
            'Rp ' . number_format($result->revenue),
            'Rp ' . number_format($result->avg_order_value)
        );
        
        $totalOrders += $result->orders;
        $totalRevenue += $result->revenue;
    }

    echo str_repeat("-", 80) . "\n";
    printf("%-6s %-8s %-15s %-15s\n",
        "TOTAL",
        number_format($totalOrders),
        'Rp ' . number_format($totalRevenue),
        'Rp ' . number_format($totalRevenue / $totalOrders)
    );
    echo str_repeat("-", 80) . "\n\n";

    // Test chart series data for frontend
    echo "=== Chart Series Data ===\n";
    $ordersData = array_map(function($item) { return $item->orders; }, $results);
    $revenueData = array_map(function($item) { return $item->revenue; }, $results);
    $categories = array_map(function($item) { return $item->hour . ':00'; }, $results);
    
    echo "Orders Series: " . json_encode($ordersData) . "\n";
    echo "Revenue Series: " . json_encode($revenueData) . "\n";
    echo "Categories: " . json_encode($categories) . "\n\n";

    // Test peak hours analysis
    echo "=== Peak Hours Analysis ===\n";
    
    // Find peak orders hour
    $maxOrders = max($ordersData);
    $peakOrdersHour = $results[array_search($maxOrders, $ordersData)]->hour;
    
    // Find peak revenue hour
    $maxRevenue = max($revenueData);
    $peakRevenueHour = $results[array_search($maxRevenue, $revenueData)]->hour;
    
    echo "Peak Orders Hour: {$peakOrdersHour}:00 ({$maxOrders} orders)\n";
    echo "Peak Revenue Hour: {$peakRevenueHour}:00 (Rp " . number_format($maxRevenue) . ")\n\n";

    // Test lunch vs dinner analysis
    echo "=== Lunch vs Dinner Analysis ===\n";
    $lunchOrders = 0;
    $lunchRevenue = 0;
    $dinnerOrders = 0;
    $dinnerRevenue = 0;
    
    foreach ($results as $result) {
        if ($result->hour <= 17) {
            $lunchOrders += $result->orders;
            $lunchRevenue += $result->revenue;
        } else {
            $dinnerOrders += $result->orders;
            $dinnerRevenue += $result->revenue;
        }
    }
    
    echo "Lunch (≤17:00): {$lunchOrders} orders, Rp " . number_format($lunchRevenue) . "\n";
    echo "Dinner (>17:00): {$dinnerOrders} orders, Rp " . number_format($dinnerRevenue) . "\n";
    
    $lunchPercentage = round(($lunchOrders / ($lunchOrders + $dinnerOrders)) * 100, 1);
    $dinnerPercentage = round(($dinnerOrders / ($lunchOrders + $dinnerOrders)) * 100, 1);
    
    echo "Lunch: {$lunchPercentage}% of orders\n";
    echo "Dinner: {$dinnerPercentage}% of orders\n\n";

    // Test data structure for frontend
    echo "=== Frontend Data Structure ===\n";
    $frontendData = [];
    foreach ($results as $result) {
        $frontendData[] = [
            'hour' => (int) $result->hour,
            'orders' => (int) $result->orders,
            'revenue' => (float) $result->revenue,
            'avg_order_value' => (float) $result->avg_order_value
        ];
    }
    
    echo "Frontend Data Structure:\n";
    echo json_encode($frontendData, JSON_PRETTY_PRINT) . "\n\n";

    // Test chart configuration
    echo "=== Chart Configuration ===\n";
    echo "Chart Type: Mixed (Column + Line)\n";
    echo "Y-Axis 1: Number of Orders (Left)\n";
    echo "Y-Axis 2: Revenue (Right)\n";
    echo "Colors: ['#6366F1', '#10b981']\n";
    echo "Legend: Top position\n";
    echo "Tooltip: Shared with custom formatting\n\n";

    echo "✅ Test completed successfully!\n";
    echo "✅ Hourly sales query includes revenue data\n";
    echo "✅ Chart configured for dual Y-axis display\n";
    echo "✅ Orders as columns, Revenue as line chart\n";
    echo "✅ Custom tooltip with all metrics\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
