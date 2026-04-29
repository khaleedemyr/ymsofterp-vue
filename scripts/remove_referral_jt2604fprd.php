<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MemberAppsMember;
use Illuminate\Support\Facades\DB;

$targetMemberId = 'JT2604FPRD';
$member = MemberAppsMember::where('member_id', $targetMemberId)->first();

if (!$member) {
    echo "Member {$targetMemberId} not found\n";
    exit(1);
}

echo "=== Removing Referral Bonus Points from {$targetMemberId} ===\n";
echo "Member DB ID: " . $member->id . "\n";
echo "Name: " . ($member->nama_lengkap ?? '-') . "\n";
echo "Current Points: " . ($member->just_points ?? 0) . "\n\n";

try {
    DB::beginTransaction();

    $referralBonuses = DB::table('member_apps_point_transactions')
        ->where('member_id', $member->id)
        ->where('transaction_type', 'bonus')
        ->where('channel', 'referral')
        ->orderBy('created_at')
        ->get();

    $totalPointsToRemove = 0;
    $transactionIds = [];

    echo "Referral Transactions to Remove:\n";
    foreach ($referralBonuses as $tx) {
        echo "  TX ID: {$tx->id} | Points: {$tx->point_amount} | Ref: {$tx->reference_id} | Date: {$tx->created_at}\n";
        $totalPointsToRemove += (int) $tx->point_amount;
        $transactionIds[] = $tx->id;
    }

    echo "\nTotal Referral TX: " . count($transactionIds) . "\n";
    echo "Total Points to Remove: {$totalPointsToRemove}\n";

    if (empty($transactionIds)) {
        echo "No referral bonus transactions found. Nothing to remove.\n";
        DB::rollBack();
        exit(0);
    }

    $pointEarningIds = DB::table('member_apps_point_earnings')
        ->whereIn('point_transaction_id', $transactionIds)
        ->pluck('id')
        ->toArray();

    if (!empty($pointEarningIds)) {
        DB::table('member_apps_point_earnings')
            ->whereIn('id', $pointEarningIds)
            ->delete();
        echo "Deleted point earning records: " . count($pointEarningIds) . "\n";
    }

    DB::table('member_apps_point_transactions')
        ->whereIn('id', $transactionIds)
        ->delete();
    echo "Deleted referral transaction records: " . count($transactionIds) . "\n";

    $oldPoints = (int) ($member->just_points ?? 0);
    $newPoints = $oldPoints - $totalPointsToRemove;
    $member->just_points = $newPoints;
    $member->save();

    DB::commit();

    echo "\n=== Result ===\n";
    echo "Old Points: {$oldPoints}\n";
    echo "Removed: {$totalPointsToRemove}\n";
    echo "New Points: {$newPoints}\n";
    echo "\nSUCCESS - Referral points removed from {$targetMemberId}\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}
