<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\AttendanceReportController;
use App\Services\AttendanceWorkTimelineService;
use Illuminate\Support\Facades\DB;

$tanggal = '2026-06-26';
$nextDay = date('Y-m-d', strtotime($tanggal.' +1 day'));

$user = DB::table('users')
    ->where('nama_lengkap', 'like', '%Gilang%setiawan%')
    ->first(['id', 'nama_lengkap', 'id_outlet']);

if (! $user) {
    echo "User not found\n";
    exit(1);
}

$userId = (int) $user->id;
echo "=== TRACE ABSENSI: {$user->nama_lengkap} (ID: {$userId}) tanggal {$tanggal} ===\n\n";

$scans = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', $userId)
    ->where('a.scan_date', '>=', $tanggal.' 00:00:00')
    ->where('a.scan_date', '<', date('Y-m-d', strtotime($nextDay.' +1 day')).' 00:00:00')
    ->select('a.scan_date', 'a.inoutmode', 'a.pin', 'o.id_outlet', 'o.nama_outlet', 'a.sn')
    ->orderBy('a.scan_date')
    ->get();

echo "Raw scans ({$tanggal} s/d {$nextDay}):\n";
foreach ($scans as $s) {
    $mode = match ((int) $s->inoutmode) {
        1 => 'IN',
        2 => 'OUT',
        4 => 'KEMBALI',
        default => 'MODE_'.$s->inoutmode,
    };
    echo "  {$s->scan_date} | {$mode} | outlet={$s->nama_outlet} ({$s->id_outlet}) | pin={$s->pin} | sn={$s->sn}\n";
}
echo "\n";

$shift = DB::table('user_shifts as us')
    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
    ->where('us.user_id', $userId)
    ->where('us.tanggal', $tanggal)
    ->select('s.shift_name', 's.time_start', 's.time_end', 'us.shift_id')
    ->first();

echo "Shift:\n";
if ($shift) {
    echo "  {$shift->shift_name} | {$shift->time_start} - {$shift->time_end}\n";
} else {
    echo "  (tidak ada shift)\n";
}
echo "\n";

$ctrl = app(AttendanceReportController::class);
$refGroup = new ReflectionMethod($ctrl, 'groupAttendanceScansByUserDay');
$refGroup->setAccessible(true);
$refProcess = new ReflectionMethod($ctrl, 'processSmartCrossDayAttendance');
$refProcess->setAccessible(true);
$refCalc = new ReflectionMethod($ctrl, 'calculateDailyTelatLembur');
$refCalc->setAccessible(true);

$rawData = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->join('users as u', 'up.user_id', '=', 'u.id')
    ->where('u.id', $userId)
    ->where('a.scan_date', '>=', date('Y-m-d', strtotime($tanggal.' -1 day')).' 00:00:00')
    ->where('a.scan_date', '<', date('Y-m-d', strtotime($nextDay.' +1 day')).' 00:00:00')
    ->select('a.scan_date', 'a.inoutmode', 'u.id as user_id', 'u.nama_lengkap', 'o.id_outlet as outlet_id')
    ->orderBy('a.scan_date')
    ->get();

$processedData = $refGroup->invoke($ctrl, $rawData);
$key = $userId.'_'.$tanggal;

if (! isset($processedData[$key])) {
    echo "No processed data for {$key}\n";
    exit(0);
}

$result = $refProcess->invoke($ctrl, $processedData[$key], $processedData);

echo "Processed day result:\n";
foreach ($result as $k => $v) {
    if ($k === 'scans') {
        continue;
    }
    echo "  {$k}: ".(is_bool($v) ? ($v ? 'true' : 'false') : ($v ?? 'null'))."\n";
}
echo "\n";

$service = app(AttendanceWorkTimelineService::class);
$shiftStart = $shift->time_start ?? null;
$shiftEnd = $shift->time_end ?? null;
if ($shiftStart && $shiftEnd) {
    $shiftMinutes = $service->getShiftDurationMinutes($shiftStart, $shiftEnd);
    $overtime = $service->calculateOvertimeHours((int) $result['work_minutes'], $shiftStart, $shiftEnd);
    echo "Shift duration: {$shiftMinutes} menit\n";
    echo "Work minutes: {$result['work_minutes']}\n";
    echo "Excess minutes: ".((int) $result['work_minutes'] - $shiftMinutes)."\n";
    echo "Lembur (jam): {$overtime}\n\n";
}

$dayRow = (object) $result;
$isOff = $ctrl->isShiftOff($shift);
$tl = $refCalc->invoke($ctrl, $dayRow, $shift, $tanggal, $isOff);
echo "calculateDailyTelatLembur: telat={$tl['telat']}, lembur={$tl['lembur']}\n";

$extraOffRef = new ReflectionMethod($ctrl, 'getExtraOffOvertimeHoursForDate');
$extraOffRef->setAccessible(true);
$extraOff = $extraOffRef->invoke($ctrl, $userId, $tanggal);
echo "Extra Off overtime: {$extraOff}\n";
echo "Total lembur: ".floor($tl['lembur'] + $extraOff)."\n\n";

// Adjacent days
echo "=== Adjacent days ===\n";
$dates = ['2026-06-25', '2026-06-26', '2026-06-27', '2026-06-28'];
$rawDataWide = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->join('users as u', 'up.user_id', '=', 'u.id')
    ->where('u.id', $userId)
    ->where('a.scan_date', '>=', '2026-06-25 00:00:00')
    ->where('a.scan_date', '<', '2026-06-29 00:00:00')
    ->select('a.scan_date', 'a.inoutmode', 'u.id as user_id', 'u.nama_lengkap', 'o.id_outlet as outlet_id')
    ->orderBy('a.scan_date')
    ->get();

$processedWide = $refGroup->invoke($ctrl, $rawDataWide);
echo "Keys order: ".implode(', ', array_keys($processedWide))."\n";
$timeline = app(AttendanceWorkTimelineService::class);
$finalData = [];
foreach ($processedWide as $key => $data) {
    if (str_contains($key, '2026-06-27')) {
        echo 'Before processing '.$key.', scans count='.count($data['scans'])."\n";
    }
    $r = $timeline->processDay($data, $processedWide);
    $finalData[] = $r;
    if (str_contains($key, '2026-06-26')) {
        $nk = $userId.'_2026-06-27';
        echo 'After processing '.$key.', next day scans count='.count($processedWide[$nk]['scans'] ?? [])."\n";
    }
}
echo "Sequential processing (like controller):\n";
foreach ($finalData as $r) {
    $jm = $r['jam_masuk'] ? date('Y-m-d H:i:s', strtotime($r['jam_masuk'])) : '-';
    $jk = $r['jam_keluar'] ? date('Y-m-d H:i:s', strtotime($r['jam_keluar'])) : '-';
    echo "  {$r['tanggal']}: masuk={$jm} keluar={$jk} work_min={$r['work_minutes']} IN={$r['total_masuk']} OUT={$r['total_keluar']} cross=".((int) ($r['is_cross_day'] ?? 0))."\n";
}
