<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Do not touch the initial account and already handled targets.
$excludedMemberIds = [
    'U110108',      // initial account - do not touch
    'JT2604RMLS',   // already handled
    'JT2604FPRD',   // already handled
];

// Pull referral bonus recipients with counts/sums.
$rows = DB::table('member_apps_point_transactions as t')
    ->join('member_apps_members as m', 'm.id', '=', 't.member_id')
    ->where('t.transaction_type', 'bonus')
    ->where('t.channel', 'referral')
    ->whereNotIn('m.member_id', $excludedMemberIds)
    ->groupBy('m.id', 'm.member_id', 'm.nama_lengkap', 'm.email', 'm.just_points', 'm.created_at')
    ->selectRaw('m.id as db_id, m.member_id, m.nama_lengkap, m.email, m.just_points, m.created_at, COUNT(*) as referral_tx_count, SUM(t.point_amount) as referral_points_total, MIN(t.created_at) as first_referral_at, MAX(t.created_at) as last_referral_at')
    ->orderByDesc('referral_tx_count')
    ->get();

if ($rows->isEmpty()) {
    echo "No referral bonus recipients found (after exclusions).\n";
    exit(0);
}

echo "=== Suspicious Referral Audit (excluding U110108, JT2604RMLS, JT2604FPRD) ===\n";

$suspicious = [];
foreach ($rows as $r) {
    $first = strtotime((string) $r->first_referral_at);
    $last = strtotime((string) $r->last_referral_at);
    $minutes = max(1, (int) ceil(($last - $first) / 60));

    // Heuristic: high count OR dense burst within short time.
    $isSuspicious = ((int)$r->referral_tx_count >= 8) || (((int)$r->referral_tx_count >= 5) && ($minutes <= 180));

    if ($isSuspicious) {
        $suspicious[] = [
            'db_id' => $r->db_id,
            'member_id' => $r->member_id,
            'name' => $r->nama_lengkap,
            'email' => $r->email,
            'current_points' => (int)$r->just_points,
            'referral_tx_count' => (int)$r->referral_tx_count,
            'referral_points_total' => (int)$r->referral_points_total,
            'first_referral_at' => $r->first_referral_at,
            'last_referral_at' => $r->last_referral_at,
            'burst_minutes' => $minutes,
        ];
    }
}

if (empty($suspicious)) {
    echo "No suspicious members found using current heuristics.\n";
    exit(0);
}

usort($suspicious, function ($a, $b) {
    if ($a['referral_tx_count'] === $b['referral_tx_count']) {
        return $b['referral_points_total'] <=> $a['referral_points_total'];
    }
    return $b['referral_tx_count'] <=> $a['referral_tx_count'];
});

echo "Found suspicious members: " . count($suspicious) . "\n\n";
echo str_pad('MemberID', 14)
    . str_pad('Name', 22)
    . str_pad('RefTX', 8)
    . str_pad('RefPts', 9)
    . str_pad('BurstMin', 10)
    . "CurrentPts\n";
echo str_repeat('-', 75) . "\n";

foreach ($suspicious as $s) {
    echo str_pad($s['member_id'], 14)
        . str_pad(substr((string)$s['name'], 0, 20), 22)
        . str_pad((string)$s['referral_tx_count'], 8)
        . str_pad((string)$s['referral_points_total'], 9)
        . str_pad((string)$s['burst_minutes'], 10)
        . $s['current_points'] . "\n";
}

echo "\n=== Detailed Top 10 ===\n";
$top = array_slice($suspicious, 0, 10);
foreach ($top as $i => $s) {
    $n = $i + 1;
    echo "#{$n} {$s['member_id']} ({$s['name']}) | DB ID {$s['db_id']}\n";
    echo "  Email: {$s['email']}\n";
    echo "  Referral TX: {$s['referral_tx_count']} | Referral Points: {$s['referral_points_total']}\n";
    echo "  Window: {$s['first_referral_at']} -> {$s['last_referral_at']} ({$s['burst_minutes']} min)\n";
    echo "  Current Points: {$s['current_points']}\n\n";
}
