<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\PayrollReportController;
use App\Services\PayrollScTraceService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$nik = '240212';
$user = DB::table('users')->where('nik', $nik)->first();
if (! $user) {
    echo "User not found\n";
    exit(1);
}

echo "=== AGUS SUPARMAN (id={$user->id}) ===\n";
echo "id_outlet={$user->id_outlet}\n\n";

$movements = DB::table('employee_movements')
    ->where('employee_id', $user->id)
    ->orderByDesc('id')
    ->get();

echo "--- Movements ---\n";
foreach ($movements as $m) {
    echo json_encode([
        'id' => $m->id,
        'effective' => $m->employment_effective_date,
        'from' => $m->unit_property_from,
        'to' => $m->unit_property_to,
        'status' => $m->status,
    ], JSON_UNESCAPED_UNICODE)."\n";
}

$year = 2026;
$month = 5;
$outletId = 37; // SMB — outlet payroll mutasi
$outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');

$tracer = app(PayrollScTraceService::class);
$result = $tracer->run($outletId, $year, $month, 22750551);
$row = collect($result['rows'])->first(fn ($r) => stripos($r['nama'], 'agus') !== false && stripos($r['nama'], 'suparman') !== false);

if (! $row) {
    echo "Not found at outlet {$outletId}, searching SMB outlets...\n";
    $smbOutlets = DB::table('tbl_data_outlet')->where('nama_outlet', 'like', '%SMB%')->get();
    foreach ($smbOutlets as $o) {
        $r2 = $tracer->run((int) $o->id_outlet, $year, $month, 22750551);
        $found = collect($r2['rows'])->first(fn ($r) => stripos($r['nama'], 'agus') !== false);
        if ($found) {
            $outletId = (int) $o->id_outlet;
            $outletName = $o->nama_outlet;
            $row = $found;
            break;
        }
    }
}

if ($row) {
    echo "\n--- Payroll trace outlet {$outletId} ({$outletName}) {$year}-{$month} ---\n";
    echo "hari_kerja (tampilan): {$row['hari_kerja']}\n";
    echo "hari_kerja_gajian2 (SC pool): {$row['hari_kerja_gajian2']}\n";
    echo 'is_mutasi: '.($row['is_mutasi'] ? 'ya' : 'tidak')."\n";
    echo "erp_sc: {$row['erp_sc']}\n";
    echo "uang_makan implied: ".($row['hari_kerja'] * 15000)." ({$row['hari_kerja']} x 15000)\n";
} else {
    echo "Employee not found in payroll trace\n";
    exit(1);
}

$m = $movements->first(fn ($mv) => $mv->employment_type === 'mutation' && $mv->status === 'executed');
if (! $m) {
    $m = $movements->first();
}
if ($m) {
    $ctrl = app(PayrollReportController::class);
    $ref = new ReflectionMethod($ctrl, 'getAttendanceData');
    $ref->setAccessible(true);
    $ref2 = new ReflectionMethod($ctrl, 'countMutationSegmentScDays');
    $ref2->setAccessible(true);

    $gajian1Start = Carbon::parse('2026-04-26');
    $gajian1End = Carbon::parse('2026-05-25');
    $effective = Carbon::parse($m->employment_effective_date);
    $segStart = $effective->gte($gajian1Start) ? $effective : $gajian1Start;

    echo "\n--- Segmen mutasi role=to: {$segStart->toDateString()} .. {$gajian1End->toDateString()} ---\n";
    $rows = $ref->invoke($ctrl, $user->id, $outletId, $segStart, $gajian1End);
    $hkOnly = $rows->filter(fn ($r) => ! empty($r['has_check_in']) && empty($r['is_off']))->count();
    $offDays = $rows->filter(fn ($r) => ! empty($r['is_off']))->count();
    $scDays = $ref2->invoke($ctrl, (int) $user->id, $outletId, $segStart, $gajian1End, 'to');

    echo "getAttendanceData total rows: {$rows->count()}\n";
    echo "hari kerja absensi (IN dan bukan OFF): {$hkOnly}\n";
    echo "hari OFF: {$offDays}\n";
    echo "countMutationSegmentScDays (SC pool): {$scDays}\n";

    echo "\nPer hari:\n";
    foreach ($rows as $r) {
        $flag = (! empty($r['has_check_in']) && empty($r['is_off'])) ? 'HK' : ($r['is_off'] ? 'OFF' : '  ');
        echo sprintf(
            "  %s %s off=%s in=%s\n",
            $flag,
            $r['tanggal'],
            $r['is_off'] ? 'Y' : 'N',
            $r['has_check_in'] ? 'Y' : 'N'
        );
    }

    $cal = $segStart->diffInDays($gajian1End) + 1;
    echo "\nHari kalender segmen: {$cal}\n";
}

// Full gajian1 scans all outlets
echo "\n--- Raw scan full gajian1 (2026-04-26 .. 2026-05-25) ---\n";
$scansFull = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', $user->id)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), ['2026-04-26', '2026-05-25'])
    ->select('o.id_outlet', 'o.nama_outlet', DB::raw('DATE(a.scan_date) as d'))
    ->distinct()
    ->orderBy('d')
    ->get()
    ->groupBy('id_outlet');
foreach ($scansFull as $oid => $rows) {
    $dates = $rows->pluck('d')->unique()->sort()->values();
    echo "Outlet {$oid} ({$rows->first()->nama_outlet}): {$dates->count()} hari\n";
    foreach ($dates as $d) {
        echo "  {$d}\n";
    }
}

$scans = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', $user->id)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), [$segStart->toDateString(), $gajian1End->toDateString()])
    ->select('o.id_outlet', 'o.nama_outlet', DB::raw('DATE(a.scan_date) as d'), 'a.inoutmode')
    ->orderBy('d')
    ->get()
    ->groupBy('id_outlet');

foreach ($scans as $oid => $rows) {
    $dates = $rows->pluck('d')->unique()->sort()->values();
    echo "Outlet {$oid} ({$rows->first()->nama_outlet}): {$dates->count()} hari scan\n";
    foreach ($dates as $d) {
        echo "  {$d}\n";
    }
}

echo "\n--- mutCtx hariKerjaGajian1 (kalender) ---\n";
$mutCtx = app(PayrollReportController::class);
$ref3 = new ReflectionMethod(PayrollReportController::class, 'resolveMutationPayrollContext');
$ref3->setAccessible(true);
$mutationData = ['effective_date' => '2026-05-08', 'role' => 'to'];
$ctx = $ref3->invoke($mutCtx, $mutationData, Carbon::parse('2026-04-26'), Carbon::parse('2026-05-25'), Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'));
echo 'hariKerjaGajian1 (kalender): '.$ctx['hariKerjaGajian1']."\n";
echo 'hariKerjaGajian2 (kalender): '.$ctx['hariKerjaGajian2']."\n";
