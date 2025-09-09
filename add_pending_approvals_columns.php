<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Adding pending approvals count columns...\n";

try {
    // Add columns
    DB::statement("ALTER TABLE users ADD COLUMN pending_approvals_count INT DEFAULT 0");
    echo "✓ Added pending_approvals_count column\n";
    
    DB::statement("ALTER TABLE users ADD COLUMN pending_hrd_approvals_count INT DEFAULT 0");
    echo "✓ Added pending_hrd_approvals_count column\n";
    
    // Update pending approvals count for all users
    $users = DB::table('users')->get();
    
    foreach ($users as $user) {
        // Count pending approvals for this user as approver
        $pendingCount = DB::table('approval_requests')
            ->where('approver_id', $user->id)
            ->where('status', 'pending')
            ->count();
            
        // Count pending HRD approvals for HRD users
        $hrdPendingCount = 0;
        if ($user->division_id == 6 && $user->status == 'A') {
            $hrdPendingCount = DB::table('approval_requests')
                ->where('status', 'approved')
                ->where('hrd_status', 'pending')
                ->count();
        }
        
        // Update user
        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'pending_approvals_count' => $pendingCount,
                'pending_hrd_approvals_count' => $hrdPendingCount
            ]);
            
        if ($pendingCount > 0 || $hrdPendingCount > 0) {
            echo "✓ User {$user->id} ({$user->nama_lengkap}): pending={$pendingCount}, hrd_pending={$hrdPendingCount}\n";
        }
    }
    
    echo "\n✅ Successfully added columns and updated counts!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
