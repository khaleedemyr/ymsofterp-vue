<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;

echo "Testing Simple Static Week Logic...\n";

$month = 8;
$year = 2025;
$daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

echo "Days in month: $daysInMonth\n\n";

// Test the static week logic
$weeklyData = [1 => [], 2 => [], 3 => [], 4 => []];

for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = Carbon::create($year, $month, $day);
    $dateStr = $date->format('Y-m-d');
    
    // Tentukan week statis
    if ($day >= 1 && $day <= 7) {
        $weekNum = 1;
    } elseif ($day >= 8 && $day <= 14) {
        $weekNum = 2;
    } elseif ($day >= 15 && $day <= 21) {
        $weekNum = 3;
    } else {
        $weekNum = 4;
    }

    $weeklyData[$weekNum][] = [
        'date' => $dateStr,
        'week' => $weekNum,
    ];
}

echo "Simple Static Week Logic Result:\n";
foreach ($weeklyData as $weekNum => $weekData) {
    echo "Week $weekNum: " . count($weekData) . " days\n";
    foreach ($weekData as $day) {
        echo "  - " . $day['date'] . " (week: " . $day['week'] . ")\n";
    }
    echo "\n";
}

echo "Expected vs Actual:\n";
echo "Expected Week 4: 22-31 Aug (10 days, week: 4)\n";
echo "Actual Week 4: " . $weeklyData[4][0]['date'] . " to " . $weeklyData[4][count($weeklyData[4])-1]['date'] . " (" . count($weeklyData[4]) . " days)\n"; 