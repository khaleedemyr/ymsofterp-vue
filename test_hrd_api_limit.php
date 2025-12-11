<?php

/**
 * Test dengan limit lebih besar
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

$testUserId = 136; // HRD user
Auth::loginUsingId($testUserId);

$request = new \Illuminate\Http\Request();
$request->merge(['limit' => 200]); // Limit besar

$controller = new \App\Http\Controllers\ApprovalController();
$response = $controller->getPendingHrdApprovals($request);
$responseData = json_decode($response->getContent(), true);

if ($responseData['success']) {
    $approvals = $responseData['approvals'] ?? [];
    echo "Total approvals: " . count($approvals) . "\n\n";
    
    // Cek apakah ID 503 ada
    $has503 = collect($approvals)->contains('id', 503);
    if ($has503) {
        echo "✓ Approval ID 503 ADA di response!\n";
        $approval503 = collect($approvals)->firstWhere('id', 503);
        echo "  User: {$approval503['user']['nama_lengkap']}\n";
        echo "  Date: {$approval503['date_from']} - {$approval503['date_to']}\n";
    } else {
        echo "✗ Approval ID 503 TIDAK ADA di response (dengan limit 200)\n";
    }
    
    // Cek semua ID
    $ids = collect($approvals)->pluck('id')->toArray();
    echo "\nApproval IDs (first 20): " . implode(', ', array_slice($ids, 0, 20)) . "\n";
    
    // Cek apakah 503 ada di list
    if (in_array(503, $ids)) {
        $index = array_search(503, $ids);
        echo "\n✓ Approval ID 503 ada di index: {$index}\n";
    } else {
        echo "\n✗ Approval ID 503 tidak ada dalam list\n";
        
        // Cek kenapa tidak ada
        $check503 = DB::table('approval_requests')
            ->where('id', 503)
            ->first();
        
        if ($check503) {
            echo "\nData approval_requests ID 503:\n";
            echo "  Status: {$check503->status}\n";
            echo "  HRD Status: " . ($check503->hrd_status ?? 'NULL') . "\n";
            echo "  HRD Approver ID: " . ($check503->hrd_approver_id ?? 'NULL') . "\n";
            echo "  Created At: {$check503->created_at}\n";
        }
    }
} else {
    echo "Error: " . ($responseData['message'] ?? 'Unknown') . "\n";
}

