<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing apiOutlets method...\n";

try {
    // Login user
    $user = App\Models\User::find(2);
    auth()->login($user);
    echo "Logged in as: " . $user->nama_lengkap . " (outlet_id: " . $user->id_outlet . ")\n";
    
    // Test controller method
    $controller = new App\Http\Controllers\ReportController();
    $response = $controller->apiOutlets();
    
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Response content: " . $response->getContent() . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
