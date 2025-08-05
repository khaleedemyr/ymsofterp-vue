<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\ReportWeeklyOutletFbRevenueController3;
use Illuminate\Http\Request;

echo "Testing Controller Response - FINAL TEST...\n";

$controller = new ReportWeeklyOutletFbRevenueController3();
$request = new Request(['month' => '8', 'year' => '2025', 'outlet' => 'SH017']);

$response = $controller->index($request);
$data = json_decode($response->getContent(), true);

echo "Controller response weeks: " . count($data['weekly_data']) . "\n\n";

// Check Week 4 data
if (isset($data['weekly_data'][4])) {
    echo "Week 4 exists with " . count($data['weekly_data'][4]) . " days\n";
    echo "First day: " . $data['weekly_data'][4][0]['date'] . " (week: " . $data['weekly_data'][4][0]['week'] . ")\n";
    echo "Last day: " . $data['weekly_data'][4][count($data['weekly_data'][4])-1]['date'] . " (week: " . $data['weekly_data'][4][count($data['weekly_data'][4])-1]['week'] . ")\n";
    
    echo "\nWeek 4 all dates:\n";
    foreach ($data['weekly_data'][4] as $day) {
        echo "- " . $day['date'] . " (week: " . $day['week'] . ")\n";
    }
} else {
    echo "Week 4 does not exist!\n";
}

echo "\nExpected Week 4: 22-31 Aug (10 days, week: 4)\n";
echo "Actual Week 4: " . (isset($data['weekly_data'][4]) ? $data['weekly_data'][4][0]['date'] . " to " . $data['weekly_data'][4][count($data['weekly_data'][4])-1]['date'] . " (" . count($data['weekly_data'][4]) . " days)" : "NOT FOUND") . "\n";

// Check Week 3 data
if (isset($data['weekly_data'][3])) {
    echo "\nWeek 3 data:\n";
    echo "First day: " . $data['weekly_data'][3][0]['date'] . " (week: " . $data['weekly_data'][3][0]['week'] . ")\n";
    echo "Last day: " . $data['weekly_data'][3][count($data['weekly_data'][3])-1]['date'] . " (week: " . $data['weekly_data'][3][count($data['weekly_data'][3])-1]['week'] . ")\n";
} 