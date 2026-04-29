<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MemberAppsMember;
use Illuminate\Support\Facades\DB;

$targets = ['JT2604IJ5I', 'JT260376TM', 'JT2603L61G'];

foreach ($targets as $target) {
    $member = MemberAppsMember::where('member_id', $target)->first();
    if (!$member) {
        echo "Target {$target} not found\n\n";
        continue;
    }

    $tx = DB::table('member_apps_point_transactions')
        ->where('member_id', $member->id)
        ->where('transaction_type', 'bonus')
        ->where('channel', 'referral')
        ->orderBy('created_at')
        ->get();

    $refIds = $tx->pluck('reference_id')->filter()->unique()->values()->toArray();

    $children = DB::table('member_apps_members')
        ->whereIn('member_id', $refIds)
        ->select('member_id', 'nama_lengkap', 'email', 'mobile_phone', 'created_at', 'last_login_at', 'total_spending', 'is_active')
        ->get();

    $childCount = $children->count();
    $nullLogin = $children->filter(function ($c) {
        return is_null($c->last_login_at);
    })->count();
    $zeroSpending = $children->filter(function ($c) {
        return (float)($c->total_spending ?? 0) <= 0;
    })->count();

    echo "=== {$target} ({$member->nama_lengkap}) ===\n";
    echo "Referral TX: " . $tx->count() . " | Child members found: {$childCount}\n";
    echo "Children with last_login_at=NULL: {$nullLogin}/{$childCount}\n";
    echo "Children with total_spending<=0: {$zeroSpending}/{$childCount}\n";

    if ($childCount > 0) {
        $first = $children->sortBy('created_at')->first();
        $last = $children->sortByDesc('created_at')->first();
        echo "Child registration window: {$first->created_at} -> {$last->created_at}\n";
    }

    echo "Sample children (up to 8):\n";
    foreach ($children->take(8) as $c) {
        echo "  {$c->member_id} | {$c->nama_lengkap} | login=" . ($c->last_login_at ?? 'NULL') . " | spend=" . ($c->total_spending ?? 0) . "\n";
    }
    echo "\n";
}
