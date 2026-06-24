<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$userId = 1850;
$outletId = 24;
$start = Carbon::parse('2026-05-04');
$end = Carbon::parse('2026-05-25');

echo "=== SHIFT + SCAN per hari (outlet {$outletId}, {$start->toDateString()}..{$end->toDateString()}) ===\n";

$ctrl = app(\App\Http\Controllers\AttendanceReportController::class);
$dt = $start->copy();
$offCount = 0;
$hkCount = 0;
$bothCount = 0;

while ($dt->lte($end)) {
    $tanggal = $dt->toDateString();
    $shift = DB::table('user_shifts as us')
        ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
        ->where('us.user_id', $userId)
        ->where('us.tanggal', $tanggal)
        ->where('us.outlet_id', $outletId)
        ->select('s.shift_name', 'us.shift_id')
        ->first();

    $isOff = $ctrl->isShiftOff($shift);

    $hasIn = DB::table('att_log as a')
        ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
        ->join('user_pins as up', function ($q) {
            $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
        })
        ->where('up.user_id', $userId)
        ->where('o.id_outlet', $outletId)
        ->where('a.inoutmode', 1)
        ->where('a.scan_date', '>=', $tanggal.' 00:00:00')
        ->where('a.scan_date', '<', date('Y-m-d', strtotime($tanggal.' +1 day')).' 00:00:00')
        ->exists();

    $counts = ($isOff || $hasIn);
    if ($isOff) {
        $offCount++;
    }
    if ($hasIn) {
        $hkCount++;
    }
    if ($counts) {
        $bothCount++;
    }

    $shiftName = $shift->shift_name ?? ($shift ? 'no-name' : 'no-shift');
    echo sprintf("  %s shift=%s OFF=%s IN=%s count=%s\n", $tanggal, $shiftName, $isOff ? 'Y' : 'N', $hasIn ? 'Y' : 'N', $counts ? 'Y' : 'N');
    $dt->addDay();
}

echo "\nOFF days: {$offCount}\n";
echo "IN days: {$hkCount}\n";
echo "OFF or IN (SC days): {$bothCount}\n";
