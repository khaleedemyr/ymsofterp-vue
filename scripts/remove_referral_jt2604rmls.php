<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MemberAppsMember;
use Illuminate\Support\Facades\DB;

$member = MemberAppsMember::where('member_id', 'JT2604RMLS')->first();

if (!$member) {
    echo "Member JT2604RMLS not found\n";
    exit(1);
}

echo "=== Removing Referral Bonus Points from JT2604RMLS ===\n";
echo "Member ID: " . $member->id . "\n";
echo "Current Points: " . $member->just_points . "\n\n";

try {
    DB::beginTransaction();
    
    // Get all referral bonus transactions
    $referralBonuses = DB::table('member_apps_point_transactions')
        ->where('member_id', $member->id)
        ->where('transaction_type', 'bonus')
        ->where('channel', 'referral')
        ->get();
    
    $totalPointsToRemove = 0;
    $transactionIds = [];
    
    echo "Referral Transactions to Remove:\n";
    foreach ($referralBonuses as $tx) {
        echo "  TX ID: " . $tx->id . " | Points: " . $tx->point_amount . " | Ref: " . $tx->reference_id . "\n";
        $totalPointsToRemove += $tx->point_amount;
        $transactionIds[] = $tx->id;
    }
    
    echo "\nTotal Points to Remove: " . $totalPointsToRemove . "\n";
    
    // Get corresponding point earnings
    $pointEarnings = DB::table('member_apps_point_earnings')
        ->whereIn('point_transaction_id', $transactionIds)
        ->get();
    
    echo "Point Earnings Records to Delete: " . count($pointEarnings) . "\n";
    $pointEarningIds = $pointEarnings->pluck('id')->toArray();
    
    // Delete point earnings first (FK constraint)
    if (!empty($pointEarningIds)) {
        DB::table('member_apps_point_earnings')
            ->whereIn('id', $pointEarningIds)
            ->delete();
        echo "Deleted " . count($pointEarningIds) . " point earning records\n";
    }
    
    // Delete transactions
    DB::table('member_apps_point_transactions')
        ->whereIn('id', $transactionIds)
        ->delete();
    echo "Deleted " . count($transactionIds) . " transaction records\n";
    
    // Update member points
    $newPoints = $member->just_points - $totalPointsToRemove;
    $member->just_points = $newPoints;
    $member->save();
    
    echo "\n=== Result ===\n";
    echo "Old Points: " . ($member->just_points + $totalPointsToRemove) . "\n";
    echo "Removed: " . $totalPointsToRemove . "\n";
    echo "New Points: " . $member->just_points . "\n";
    
    DB::commit();
    
    echo "\n✅ SUCCESS - Referral points removed from JT2604RMLS\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
