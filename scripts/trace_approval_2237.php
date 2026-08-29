<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$approvalId = 2237;
$userId = 1436;

$ar = DB::table('approval_requests as ar')
    ->leftJoin('leave_types as lt', 'lt.id', '=', 'ar.leave_type_id')
    ->leftJoin('users as u', 'u.id', '=', 'ar.user_id')
    ->where('ar.id', $approvalId)
    ->select('ar.*', 'lt.name as leave_type_name', 'u.nama_lengkap')
    ->first();

echo "=== APPROVAL #{$approvalId} ===\n";
if (!$ar) {
    echo "Not found\n";
    exit(1);
}

foreach ((array) $ar as $k => $v) {
    if (!is_object($v) && $v !== null) {
        echo "  {$k}: {$v}\n";
    }
}

echo "\n=== APPROVAL HISTORY / STEPS ===\n";
$history = DB::table('approval_histories')
    ->where('approval_request_id', $approvalId)
    ->orderBy('created_at')
    ->get();
if ($history->isEmpty()) {
    $history = DB::table('approval_request_histories')
        ->where('approval_request_id', $approvalId)
        ->orderBy('created_at')
        ->get();
}
foreach ($history as $h) {
    $actor = isset($h->user_id) ? DB::table('users')->where('id', $h->user_id)->value('nama_lengkap') : null;
    echo json_encode($h) . ($actor ? " actor={$actor}" : '') . "\n";
}

echo "\n=== LEAVE TRANSACTIONS sekitar waktu approval ===\n";
$around = DB::table('leave_transactions')
    ->where('user_id', $userId)
    ->whereBetween('created_at', [
        date('Y-m-d H:i:s', strtotime((string) $ar->updated_at) - 86400),
        date('Y-m-d H:i:s', strtotime((string) $ar->updated_at) + 86400 * 7),
    ])
    ->orderBy('created_at')
    ->get();
foreach ($around as $t) {
    echo "#{$t->id} {$t->created_at} {$t->transaction_type} amt={$t->amount} bal={$t->balance_after} | {$t->description}\n";
}

echo "\n=== CUTI BALANCE user sebelum/sesudah (dari tx) ===\n";
$before = DB::table('leave_transactions')
    ->where('user_id', $userId)
    ->where('created_at', '<', $ar->updated_at ?? $ar->created_at)
    ->orderByDesc('created_at')
    ->first();
echo "Tx terakhir sebelum approval updated: ";
if ($before) {
    echo "#{$before->id} {$before->created_at} bal={$before->balance_after}\n";
} else {
    echo "none\n";
}

echo "\n=== NOTIFICATIONS terkait ===\n";
$notifs = DB::table('notifications')
    ->where('data', 'like', '%"approval_request_id":' . $approvalId . '%')
    ->orWhere('data', 'like', '%"approval_request_id": ' . $approvalId . '%')
    ->orderBy('created_at')
    ->limit(10)
    ->get();
foreach ($notifs as $n) {
    echo "{$n->created_at} type={$n->type}\n";
}

echo "\n=== CEK: apakah leave_type_name match 'Annual Leave' ===\n";
echo "leave_type_name: [{$ar->leave_type_name}]\n";
echo "strcasecmp Annual Leave: " . (strcasecmp(trim((string) $ar->leave_type_name), 'Annual Leave') === 0 ? 'MATCH' : 'NO MATCH') . "\n";
