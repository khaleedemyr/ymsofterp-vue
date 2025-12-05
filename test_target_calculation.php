<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\ReportDailyRevenueForecastController;
use Illuminate\Http\Request;
use Carbon\Carbon;

echo "Testing Target Calculation Logic...\n\n";

$controller = new ReportDailyRevenueForecastController();
$request = new Request(['month' => '8', 'year' => '2025']);

$response = $controller->index($request);
$data = json_decode($response->getContent(), true);

// Test specific outlet with budget
$outlet28 = $data['outlets_data'][27]; // Justus Steak House Lebak Bulus

echo "Outlet: " . $outlet28['outlet_name'] . "\n";
echo "Actual MTD: " . number_format($outlet28['mtd_data']['actual_mtd']) . "\n";
echo "Budget MTD: " . number_format($outlet28['monthly_budget']) . "\n";
echo "Variance: " . number_format($outlet28['performance_metrics']['variance']) . "\n\n";

echo "Dynamic Targets:\n";
echo "- Remaining Days: " . $outlet28['dynamic_targets']['remaining_days'] . "\n";
echo "- Remaining Weekdays: " . $outlet28['dynamic_targets']['remaining_weekdays'] . "\n";
echo "- Remaining Weekends: " . $outlet28['dynamic_targets']['remaining_weekends'] . "\n";
echo "- Weekday Target: " . number_format($outlet28['dynamic_targets']['weekday_target']) . "\n";
echo "- Weekend Target: " . number_format($outlet28['dynamic_targets']['weekend_target']) . "\n\n";

// Verify calculation
$calculatedTotal = ($outlet28['dynamic_targets']['weekday_target'] * $outlet28['dynamic_targets']['remaining_weekdays']) + 
                   ($outlet28['dynamic_targets']['weekend_target'] * $outlet28['dynamic_targets']['remaining_weekends']);

echo "Verification:\n";
echo "Weekday Total: " . number_format($outlet28['dynamic_targets']['weekday_target'] * $outlet28['dynamic_targets']['remaining_weekdays']) . "\n";
echo "Weekend Total: " . number_format($outlet28['dynamic_targets']['weekend_target'] * $outlet28['dynamic_targets']['remaining_weekends']) . "\n";
echo "Calculated Total: " . number_format($calculatedTotal) . "\n";
echo "Variance: " . number_format($outlet28['performance_metrics']['variance']) . "\n";
echo "Match: " . (abs($calculatedTotal - $outlet28['performance_metrics']['variance']) < 1 ? "YES" : "NO") . "\n\n";

echo "Daily Targets (with count multiplication):\n";
$weekdayTotal = 0;
$weekendTotal = 0;

foreach ($outlet28['daily_targets'] as $day) {
    $dayTotal = $day['target_revenue'] * $day['count'];
    if ($day['is_weekend']) {
        $weekendTotal += $dayTotal;
    } else {
        $weekdayTotal += $dayTotal;
    }
    
    echo "- " . $day['day_name'] . " (Weekend: " . ($day['is_weekend'] ? 'Yes' : 'No') . "): " . 
         number_format($day['target_revenue']) . " × " . $day['count'] . " = " . number_format($dayTotal) . "\n";
}

echo "\nCalculated Totals:\n";
echo "Weekday Total: " . number_format($weekdayTotal) . "\n";
echo "Weekend Total: " . number_format($weekendTotal) . "\n";
echo "Grand Total: " . number_format($weekdayTotal + $weekendTotal) . "\n";
echo "Variance: " . number_format($outlet28['performance_metrics']['variance']) . "\n";
echo "Match: " . (abs(($weekdayTotal + $weekendTotal) - $outlet28['performance_metrics']['variance']) < 1 ? "YES" : "NO") . "\n"; 