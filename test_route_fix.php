<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Route Fix ===\n\n";

try {
    // Clear route cache
    echo "1. Clearing route cache...\n";
    \Artisan::call('route:clear');
    echo "   ✓ Route cache cleared\n";

    // List all routes containing 'outlets'
    echo "\n2. Checking all routes with 'outlets':\n";
    \Artisan::call('route:list', ['--name' => 'outlets']);
    $output = \Artisan::output();
    echo $output;

    // Test the specific route
    echo "\n3. Testing /api/outlets/investor route:\n";
    try {
        $response = \Illuminate\Support\Facades\Http::get(url('/api/outlets/investor'));
        echo "   Status: " . $response->status() . "\n";
        if ($response->successful()) {
            echo "   ✓ Route is working!\n";
            $data = $response->json();
            if (isset($data['outlets'])) {
                echo "   Found " . count($data['outlets']) . " outlets\n";
            } else if (is_array($data)) {
                echo "   Found " . count($data) . " outlets\n";
            }
        } else {
            echo "   ✗ Route returned error: " . $response->body() . "\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Route test failed: " . $e->getMessage() . "\n";
    }

    echo "\n=== Summary ===\n";
    echo "Route fix testing completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
