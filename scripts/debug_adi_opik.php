<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$users = DB::table('users')->where('nama_lengkap', 'like', '%Adi%opik%')->get();
echo "Found users: " . $users->count() . PHP_EOL;

foreach ($users as $u) {
    echo "=========================================" . PHP_EOL;
    echo "ID: {$u->id}, Name: {$u->nama_lengkap}, NIK: {$u->nik}, Status: {$u->status}" . PHP_EOL;
    
    $pins = DB::table('user_pins')->where('user_id', $u->id)->get();
    echo "Pins: " . json_encode($pins->toArray()) . PHP_EOL;

    $shifts = DB::table('user_shifts')
        ->where('user_id', $u->id)
        ->whereBetween('tanggal', ['2026-09-01', '2026-09-10'])
        ->get();
    echo "Shifts (Sept 1-10): " . PHP_EOL;
    foreach ($shifts as $s) {
        $shiftName = $s->shift_id ? DB::table('shifts')->where('id', $s->shift_id)->value('shift_name') : 'OFF/NULL';
        echo "  - Tanggal: {$s->tanggal}, Shift ID: {$s->shift_id} ({$shiftName})" . PHP_EOL;
    }

    $attLogs = DB::table('att_log as a')
        ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
        ->join('user_pins as up', function($q) {
            $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
        })
        ->where('up.user_id', $u->id)
        ->whereBetween('a.scan_date', ['2026-09-04 00:00:00', '2026-09-08 23:59:59'])
        ->select('a.*', 'o.nama_outlet')
        ->orderBy('a.scan_date')
        ->get();

    echo "Att Logs (Sept 4-8): " . PHP_EOL;
    foreach ($attLogs as $a) {
        $mode = $a->inoutmode == 1 ? 'IN' : ($a->inoutmode == 2 ? 'OUT' : $a->inoutmode);
        echo "  - Date: {$a->scan_date}, Mode: {$mode} ({$a->inoutmode}), PIN: {$a->pin}, SN: {$a->sn}, Outlet: {$a->nama_outlet}" . PHP_EOL;
    }

    $corrections = DB::table('schedule_attendance_correction_approvals')
        ->where('user_id', $u->id)
        ->get();
    echo "Correction Approvals: " . PHP_EOL;
    foreach ($corrections as $c) {
        echo "  - ID: {$c->id}, Type: {$c->type}, Status: {$c->status}, Old: {$c->old_value}, New: {$c->new_value}, Reason: {$c->reason}" . PHP_EOL;
    }

    $eot = DB::table('extra_off_transactions')->where('user_id', $u->id)->get();
    echo "Extra Off Transactions: " . json_encode($eot->toArray()) . PHP_EOL;

    $eob = DB::table('extra_off_balances')->where('user_id', $u->id)->first();
    echo "Extra Off Balance: " . json_encode($eob) . PHP_EOL;

    // Try testing extra off detection service for 2026-09-05
    $service = app(App\Services\ExtraOffService::class);
    $res = $service->detectUnscheduledWork('2026-09-05');
    echo "Detection Result for 2026-09-05: " . json_encode($res, JSON_PRETTY_PRINT) . PHP_EOL;
}

