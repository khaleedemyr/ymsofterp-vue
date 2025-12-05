<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\ReportWeeklyOutletFbRevenueController2;
use Illuminate\Http\Request;

echo "Testing Controller Response - DEBUG...\n";

$controller = new ReportWeeklyOutletFbRevenueController2();
$request = new Request(['month' => '8', 'year' => '2025', 'outlet' => 'SH017']);

$response = $controller->index($request);
$rawContent = $response->getContent();

echo "Raw response length: " . strlen($rawContent) . "\n";
echo "Raw response (first 500 chars): " . substr($rawContent, 0, 500) . "\n\n";

$data = json_decode($rawContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON decode error: " . json_last_error_msg() . "\n";
    exit;
}

echo "JSON decoded successfully\n";
echo "Weekly data keys: " . implode(', ', array_keys($data['weekly_data'])) . "\n";

// Check Week 4 data
if (isset($data['weekly_data'][4])) {
    echo "Week 4 count: " . count($data['weekly_data'][4]) . "\n";
    echo "Week 4 first day: " . json_encode($data['weekly_data'][4][0]) . "\n";
    echo "Week 4 last day: " . json_encode($data['weekly_data'][4][count($data['weekly_data'][4])-1]) . "\n";
} else {
    echo "Week 4 not found!\n";
} 