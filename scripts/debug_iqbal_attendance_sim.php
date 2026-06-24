<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\AttendanceReportController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$userId = 1850;
$outletId = 24;
$year = 2026;
$month = 5;
$effective = Carbon::parse('2026-05-04')->startOfDay();

$start = Carbon::parse("$year-$month-26")->subMonth()->format('Y-m-d'); // gajian1 start
$end = date('Y-m-d', strtotime("$year-$month-25"));
$gajian2End = Carbon::create($year, $month, 1)->endOfMonth();

echo "=== SIMULASI ABSENSI IQBAL (payroll path) ===\n";
echo "Gajian1: {$start}..{$end}\n";
echo "Effective mutasi: {$effective->toDateString()}\n\n";

// Replicate payroll attendance query (with mutation include)
$mutatedIds = [$userId];
$rawData = collect();
$sub = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->join('users as u', 'up.user_id', '=', 'u.id')
    ->select('a.scan_date', 'a.inoutmode', 'u.id as user_id', 'o.id_outlet as scan_outlet', 'o.nama_outlet')
    ->where('u.id', $userId)
    ->where('a.scan_date', '>=', $start.' 00:00:00')
    ->where('a.scan_date', '<', $gajian2End->copy()->addDay()->format('Y-m-d').' 00:00:00');

$sub->orderBy('a.scan_date')->chunk(5000, function ($chunk) use (&$rawData) {
    $rawData = $rawData->merge($chunk);
});

echo "Raw scans loaded: {$rawData->count()}\n\n";

$ctrl = app(AttendanceReportController::class);
$ref = new ReflectionMethod($ctrl, 'processSmartCrossDayAttendance');
$ref->setAccessible(true);

$processedData = [];
foreach ($rawData as $scan) {
    $date = date('Y-m-d', strtotime($scan->scan_date));
    $key = $scan->user_id.'_'.$date;
    if (! isset($processedData[$key])) {
        $processedData[$key] = [
            'tanggal' => $date,
            'user_id' => $scan->user_id,
            'nama_lengkap' => 'Iqbal Hamdani',
            'division_id' => null,
            'scans' => [],
        ];
    }
    $processedData[$key]['scans'][] = [
        'scan_date' => $scan->scan_date,
        'inoutmode' => $scan->inoutmode,
        'outlet' => $scan->scan_outlet,
    ];
}

$allShiftData = DB::table('user_shifts as us')
    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
    ->where('us.user_id', $userId)
    ->whereBetween('us.tanggal', [$start, $gajian2End->format('Y-m-d')])
    ->select('us.user_id', 'us.tanggal', 's.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
    ->get()
    ->groupBy(fn ($item) => $item->user_id.'_'.$item->tanggal);

$holidays = DB::table('tbl_kalender_perusahaan')
    ->whereBetween('tgl_libur', [$start, $end])
    ->pluck('keterangan', 'tgl_libur');

$days = [];
foreach ($processedData as $data) {
    $result = $ref->invoke($ctrl, $data, $processedData);
    $shift = $allShiftData->get($userId.'_'.$data['tanggal'], collect())->first();
    $isOff = $ctrl->isShiftOff($shift);
    $tl = $ctrl->calculateDailyTelatLembur((object) $result, $shift, $data['tanggal'], $isOff);
    $dayRow = (object) [
        'tanggal' => $data['tanggal'],
        'jam_masuk' => $result['jam_masuk'] ?? null,
        'jam_keluar' => $result['jam_keluar'] ?? null,
        'is_off' => $isOff,
        'is_holiday' => isset($holidays[$data['tanggal']]),
        'telat' => $tl['telat'],
        'lembur' => $tl['lembur'],
    ];
    $ctrl->enrichAttendanceDayRow($dayRow, $shift, $holidays);
    $included = $ctrl->shouldIncludeAttendanceSummaryRow($dayRow);
    $counts = $ctrl->rowCountsAsHariKerja($dayRow);
    $outlets = collect($data['scans'])->pluck('outlet')->unique()->implode(',');
    $days[] = compact('dayRow', 'included', 'counts', 'outlets');
}

usort($days, fn ($a, $b) => strcmp($a['dayRow']->tanggal, $b['dayRow']->tanggal));

function printDays(array $days, ?Carbon $from = null, string $label = ''): void
{
    $filtered = $from
        ? array_filter($days, fn ($d) => Carbon::parse($d['dayRow']->tanggal)->gte($from))
        : $days;
    $hk = array_filter($filtered, fn ($d) => $d['counts']);
    echo "--- {$label} ---\n";
    echo 'Total included rows: '.count($filtered).', hari_kerja: '.count($hk)."\n";
    foreach ($filtered as $d) {
        $r = $d['dayRow'];
        $flag = $d['counts'] ? 'HK' : '  ';
        $masuk = $r->jam_masuk ? substr($r->jam_masuk, 11, 5) : '-';
        $off = $r->is_off ? 'OFF' : '';
        echo sprintf("  %s %s masuk=%s outlets=[%s] %s\n", $flag, $r->tanggal, $masuk, $d['outlets'], $off);
    }
    echo "\n";
}

printDays($days, null, 'Semua (gajian1+gajian2 overlap)');
printDays($days, Carbon::parse($start), 'Gajian1 full');
printDays($days, $effective, 'Segmen mutasi role=to (>= effective)');

// Employee summary style: u.id_outlet filter only, no mutation
echo "--- Employee Summary style (no mutasi filter) ---\n";
$esDays = array_filter($days, fn ($d) => $d['dayRow']->tanggal >= $start && $d['dayRow']->tanggal <= $end);
$esHk = array_filter($esDays, fn ($d) => $d['counts']);
echo 'hari_kerja ES style: '.count($esHk)."\n";

// Outlet 24 scans only (what Excel might manually count)
echo "\n--- Hanya scan di mesin outlet 24 ---\n";
$only24 = array_filter($days, function ($d) use ($start, $end) {
    if ($d['dayRow']->tanggal < $start || $d['dayRow']->tanggal > $end) {
        return false;
    }
    return str_contains($d['outlets'], '24') && ! str_contains($d['outlets'], ',');
});
$only24hk = array_filter($only24, fn ($d) => $d['counts']);
echo 'hari_kerja outlet-24-only: '.count($only24hk)."\n";
foreach ($only24 as $d) {
    if ($d['counts']) {
        echo '  '.$d['dayRow']->tanggal."\n";
    }
}
