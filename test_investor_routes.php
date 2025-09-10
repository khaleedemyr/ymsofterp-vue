<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Investor Routes ===\n\n";

try {
    // Test route registration
    echo "1. Checking route registration:\n";
    
    $routes = Route::getRoutes();
    $investorRoutes = [];
    
    foreach ($routes as $route) {
        $uri = $route->uri();
        if (strpos($uri, 'investor') !== false || strpos($uri, 'outlets/investor') !== false) {
            $investorRoutes[] = [
                'method' => implode('|', $route->methods()),
                'uri' => $uri,
                'name' => $route->getName(),
                'action' => $route->getActionName()
            ];
        }
    }
    
    if (count($investorRoutes) > 0) {
        echo "   Found investor-related routes:\n";
        foreach ($investorRoutes as $route) {
            echo "   - {$route['method']} {$route['uri']} -> {$route['action']}\n";
        }
    } else {
        echo "   ✗ No investor routes found!\n";
    }

    // Test database connection
    echo "\n2. Testing database connection:\n";
    try {
        $outletCount = DB::table('tbl_data_outlet')->count();
        echo "   ✓ Database connected - Found {$outletCount} outlets\n";
    } catch (Exception $e) {
        echo "   ✗ Database error: " . $e->getMessage() . "\n";
    }

    // Test controller method
    echo "\n3. Testing controller method:\n";
    try {
        $controller = new \App\Http\Controllers\InvestorController();
        $response = $controller->outlets();
        $data = $response->getData();
        
        if (isset($data->outlets)) {
            echo "   ✓ Controller method works - Found " . count($data->outlets) . " outlets\n";
        } else if (is_array($data)) {
            echo "   ✓ Controller method works - Found " . count($data) . " outlets\n";
        } else {
            echo "   ⚠️  Controller method returned unexpected format\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Controller error: " . $e->getMessage() . "\n";
    }

    // Test URL generation
    echo "\n4. Testing URL generation:\n";
    try {
        $url = url('/api/outlets/investor');
        echo "   ✓ Generated URL: {$url}\n";
    } catch (Exception $e) {
        echo "   ✗ URL generation error: " . $e->getMessage() . "\n";
    }

    echo "\n=== Summary ===\n";
    echo "Route testing completed. Check the results above.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
