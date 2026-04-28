<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MemberAppsMember;
use Illuminate\Support\Facades\DB;

// Find member JT2604RMLS
$member = MemberAppsMember::where('member_id', 'JT2604RMLS')->first();

if ($member) {
    echo "=== Member Info ===\n";
    echo "ID: " . $member->id . "\n";
    echo "Member ID: " . $member->member_id . "\n";
    echo "Name: " . $member->nama_lengkap . "\n";
    echo "Email: " . $member->email . "\n";
    echo "Current Points: " . $member->just_points . "\n";
    echo "Created At: " . $member->created_at . "\n";
    echo "Orders Count: " . $member->orders_count . "\n\n";
    
    // Check referral bonuses
    echo "=== Referral Bonus Transactions ===\n";
    $referralBonuses = DB::table('member_apps_point_transactions')
        ->where('member_id', $member->id)
        ->where('transaction_type', 'bonus')
        ->where('channel', 'referral')
        ->orderBy('created_at')
        ->get();
    
    echo "Total Referral Bonuses: " . count($referralBonuses) . "\n";
    $totalReferralPoints = 0;
    
    foreach ($referralBonuses as $tx) {
        echo "TX ID: " . $tx->id . " | Points: " . $tx->point_amount . " | Ref ID: " . $tx->reference_id . " | Date: " . $tx->created_at . "\n";
        $totalReferralPoints += $tx->point_amount;
    }
    
    echo "\nTotal Referral Points: " . $totalReferralPoints . "\n";
    echo "\n=== Summary ===\n";
    echo "Member DB ID: " . $member->id . "\n";
    echo "Total Referral Bonus Points to Remove: " . $totalReferralPoints . "\n";
    
} else {
    echo "Member JT2604RMLS not found\n";
}
