<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\ReportDailyRevenueForecastController;
use Illuminate\Http\Request;

echo "Testing Daily Revenue Forecast Controller (Multi-Outlet)...\n";

$controller = new ReportDailyRevenueForecastController();
$request = new Request(['month' => '8', 'year' => '2025']);

$response = $controller->index($request);
$data = json_decode($response->getContent(), true);

echo "Controller response received\n";
echo "Number of outlets: " . count($data['outlets_data']) . "\n\n";

// Debug specific outlet data
$outlet28 = $data['outlets_data'][27]; // Justus Steak House Lebak Bulus
echo "DEBUG - Outlet 28 (Justus Steak House Lebak Bulus):\n";
echo "Daily Targets Structure:\n";
print_r($outlet28['daily_targets']);
echo "\n";

echo "Total Data:\n";
echo "- Total Actual MTD: " . number_format($data['total_actual_mtd']) . "\n";
echo "- Total Cover MTD: " . number_format($data['total_cover_mtd']) . "\n";
echo "- Total Budget MTD: " . number_format($data['total_budget_mtd']) . "\n";
echo "- Total Variance: " . number_format($data['total_variance']) . "\n";
echo "- Total Performance %: " . $data['total_performance_percentage'] . "%\n";
echo "- Total Average Revenue Per Day: " . number_format($data['total_average_revenue_per_day']) . "\n";
echo "- Total To Be Achieved Per Day: " . number_format($data['total_to_be_achieved_per_day']) . "\n\n";

echo "Individual Outlet Data:\n";
foreach ($data['outlets_data'] as $index => $outlet) {
    echo ($index + 1) . ". " . $outlet['outlet_name'] . "\n";
    echo "   - Actual MTD: " . number_format($outlet['mtd_data']['actual_mtd']) . "\n";
    echo "   - Cover MTD: " . number_format($outlet['mtd_data']['cover_mtd']) . "\n";
    echo "   - Budget MTD: " . number_format($outlet['monthly_budget']) . "\n";
    echo "   - Performance %: " . $outlet['performance_metrics']['performance_percentage'] . "%\n";
    
    // Show dynamic targets for this outlet
    if (isset($outlet['dynamic_targets'])) {
        echo "   - Dynamic Targets:\n";
        echo "     * Remaining Days: " . $outlet['dynamic_targets']['remaining_days'] . "\n";
        echo "     * Weekday Target: " . number_format($outlet['dynamic_targets']['weekday_target']) . "\n";
        echo "     * Weekend Target: " . number_format($outlet['dynamic_targets']['weekend_target']) . "\n";
    }
    
    // Show daily targets for this outlet
    if (isset($outlet['daily_targets'])) {
        echo "   - Daily Targets:\n";
        foreach ($outlet['daily_targets'] as $day) {
            echo "     * " . $day['day_name'] . " (Weekend: " . ($day['is_weekend'] ? 'Yes' : 'No') . "): " . 
                 number_format($day['target_revenue']) . " (Count: " . $day['count'] . ")\n";
        }
    }
    echo "\n";
} 