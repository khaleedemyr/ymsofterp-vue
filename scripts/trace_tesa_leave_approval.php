<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$user = DB::table('users')->where('nama_lengkap', 'like', '%Tesa Frasmawati%')->first();
echo "=== USER ===\n";
if ($user) {
    echo "id={$user->id} nama={$user->nama_lengkap} nik=" . ($user->nik ?? '-') . " jabatan={$user->id_jabatan}\n";
} else {
    echo "NOT FOUND\n";
    $alts = DB::table('users')->where('nama_lengkap', 'like', '%Tesa%')->limit(10)->get(['id', 'nama_lengkap']);
    foreach ($alts as $a) echo "alt: {$a->id} {$a->nama_lengkap}\n";
    exit(1);
}

echo "\n=== approval_requests 16 Aug 2026 Public Holiday ===\n";
$reqs = DB::table('approval_requests as ar')
    ->leftJoin('leave_types as lt', 'lt.id', '=', 'ar.leave_type_id')
    ->leftJoin('users as appr', 'appr.id', '=', 'ar.approver_id')
    ->leftJoin('users as hrd', 'hrd.id', '=', 'ar.hrd_approver_id')
    ->where('ar.user_id', $user->id)
    ->where('ar.date_from', '>=', '2026-08-01')
    ->select('ar.*', 'lt.name as leave_type', 'appr.nama_lengkap as approver_name', 'hrd.nama_lengkap as hrd_name')
    ->orderByDesc('ar.id')
    ->get();
foreach ($reqs as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n\n";
}

echo "=== absent_requests ===\n";
$abs = DB::table('absent_requests')->where('user_id', $user->id)->where('date_from', '>=', '2026-08-01')->get();
foreach ($abs as $a) {
    echo json_encode($a) . "\n";
}

echo "\n=== absent_request_approval_flows ===\n";
foreach ($abs as $a) {
    $flows = DB::table('absent_request_approval_flows as f')
        ->leftJoin('users as u', 'u.id', '=', 'f.approver_id')
        ->where('f.absent_request_id', $a->id)
        ->orderBy('f.approval_level')
        ->get(['f.*', 'u.nama_lengkap']);
    echo "absent_request_id={$a->id} approval_request_id={$a->approval_request_id}\n";
    foreach ($flows as $f) {
        echo "  level={$f->approval_level} status={$f->status} approver={$f->nama_lengkap}({$f->approver_id}) approved_at={$f->approved_at}\n";
    }
}

echo "\n=== notifications leave_hrd for this user/period ===\n";
$notifs = DB::table('notifications')
    ->where('message', 'like', '%Tesa Frasmawati%')
    ->whereIn('type', ['leave_hrd_approval_request', 'leave_approval_request', 'leave_approved'])
    ->orderByDesc('id')
    ->limit(20)
    ->get();
foreach ($notifs as $n) {
    echo "id={$n->id} user_id={$n->user_id} type={$n->type} read={$n->is_read} at={$n->created_at} msg=" . substr($n->message, 0, 120) . "\n";
}

echo "\n=== Nida user ===\n";
$nida = DB::table('users')->where('nama_lengkap', 'like', '%Nida Farihah%')->first(['id', 'nama_lengkap', 'id_jabatan', 'id_role']);
echo json_encode($nida) . "\n";

echo "\n=== Leonardo ===\n";
$leo = DB::table('users')->where('nama_lengkap', 'like', '%Leonardo Salakerti%')->first(['id', 'nama_lengkap', 'id_jabatan']);
echo json_encode($leo) . "\n";
