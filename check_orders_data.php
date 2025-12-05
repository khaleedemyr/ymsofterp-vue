<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking orders data by year and month...\n\n";

// Check available years
$years = DB::table('orders')
    ->select(DB::raw('YEAR(created_at) as year'))
    ->where('status', '!=', 'cancelled')
    ->where('grand_total', '>', 0)
    ->groupBy(DB::raw('YEAR(created_at)'))
    ->orderBy('year', 'desc')
    ->pluck('year');

echo "Available years: " . implode(', ', $years->toArray()) . "\n\n";

// Check available months for each year
foreach ($years as $year) {
    $months = DB::table('orders')
        ->select(DB::raw('MONTH(created_at) as month'))
        ->where('status', '!=', 'cancelled')
        ->where('grand_total', '>', 0)
        ->whereYear('created_at', $year)
        ->groupBy(DB::raw('MONTH(created_at)'))
        ->orderBy('month')
        ->pluck('month');
    
    echo "Year " . $year . " - Available months: " . implode(', ', $months->toArray()) . "\n";
    
    // Show sample data for first month
    if ($months->count() > 0) {
        $firstMonth = $months->first();
        $sampleData = DB::table('orders')
            ->select('kode_outlet', DB::raw('COUNT(*) as total_orders'), DB::raw('SUM(grand_total) as total_revenue'))
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $firstMonth)
            ->groupBy('kode_outlet')
            ->orderBy('total_revenue', 'desc')
            ->limit(3)
            ->get();
        
        echo "  Sample data for month " . $firstMonth . " (Top 3):\n";
        foreach ($sampleData as $data) {
            echo "    " . $data->kode_outlet . " - " . number_format($data->total_orders) . " orders - " . number_format($data->total_revenue) . " revenue\n";
        }
    }
    echo "\n";
} 