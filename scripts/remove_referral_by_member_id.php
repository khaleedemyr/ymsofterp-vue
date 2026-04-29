<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MemberAppsMember;
use Illuminate\Support\Facades\DB;

$targetMemberId = $argv[1] ?? null;

if (!$targetMemberId) {
    echo "Usage: php scripts/remove_referral_by_member_id.php <MEMBER_ID>\n";
    exit(1);
}

$member = MemberAppsMember::where('member_id', $targetMemberId)->first();
if (!$member) {
    echo "Member {$targetMemberId} not found\n";
    exit(1);
}

echo "=== Removing Referral Bonus Points from {$targetMemberId} ===\n";
echo "Member DB ID: {$member->id}\n";
echo "Name: " . ($member->nama_lengkap ?? '-') . "\n";
echo "Current Points: " . ((int)($member->just_points ?? 0)) . "\n\n";

try {
    DB::beginTransaction();

    $referralBonuses = DB::table('member_apps_point_transactions')
        ->where('member_id', $member->id)
        ->where('transaction_type', 'bonus')
        ->where('channel', 'referral')
        ->orderBy('created_at')
        ->get();

    $transactionIds = [];
    $totalPointsToRemove = 0;

    foreach ($referralBonuses as $tx) {
        $transactionIds[] = $tx->id;
        $totalPointsToRemove += (int)$tx->point_amount;
    }

    echo "Referral TX Count: " . count($transactionIds) . "\n";
    echo "Total Referral Points: {$totalPointsToRemove}\n";

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
    }

    DB::table('member_apps_point_transactions')
        ->whereIn('id', $transactionIds)
        ->delete();

    $oldPoints = (int)($member->just_points ?? 0);
    $newPoints = $oldPoints - $totalPointsToRemove;
    $member->just_points = $newPoints;
    $member->save();

    DB::commit();

    echo "Deleted point_earnings: " . count($pointEarningIds) . "\n";
    echo "Deleted transactions: " . count($transactionIds) . "\n";
    echo "Old Points: {$oldPoints}\n";
    echo "Removed: {$totalPointsToRemove}\n";
    echo "New Points: {$newPoints}\n";
    echo "SUCCESS\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
