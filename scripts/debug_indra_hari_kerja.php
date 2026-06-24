<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\EmployeeMovementController;
use App\Http\Controllers\PayrollReportController;
use App\Services\PayrollScTraceService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$nik = '240328';
$user = DB::table('users')->where('nik', $nik)->orWhere('nama_lengkap', 'like', '%Indra Puji%')->first();
if (! $user) {
    echo "User not found\n";
    exit(1);
}

echo "=== INDRA PUJI SAPUTRA (id={$user->id}) ===\n";
echo "Outlet sekarang: id_outlet={$user->id_outlet}\n\n";

$movements = DB::table('employee_movements')
    ->where('employee_id', $user->id)
    ->where('employment_type', 'mutation')
    ->orderByDesc('id')
    ->get();

echo "--- Movements ---\n";
foreach ($movements as $m) {
    echo json_encode([
        'id' => $m->id,
        'effective' => $m->employment_effective_date,
        'from' => $m->unit_property_from,
        'to' => $m->unit_property_to,
        'unit_property_change' => $m->unit_property_change,
        'employee_unit_property' => $m->employee_unit_property,
        'status' => $m->status,
    ], JSON_UNESCAPED_UNICODE)."\n";
}

// Coba Mei 2026 payroll (gajian1: 2026-04-26 .. 2026-05-25)
$year = 2026;
$month = 5;
$outletId = (int) $user->id_outlet;

// Cari outlet The Barn / SMB dari movement
$m = $movements->first();
if ($m && $m->unit_property_to) {
    $toId = is_numeric($m->unit_property_to)
        ? (int) $m->unit_property_to
        : (int) DB::table('tbl_data_outlet')->where('nama_outlet', $m->unit_property_to)->value('id_outlet');
    if ($toId) {
        $outletId = $toId;
    }
}

$outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');
echo "\n--- Payroll trace outlet {$outletId} ({$outletName}) {$year}-{$month} ---\n";

$tracer = app(PayrollScTraceService::class);
$result = $tracer->run($outletId, $year, $month, 0);
$indra = collect($result['rows'])->first(fn ($r) => stripos($r['nama'], 'indra') !== false);
if ($indra) {
    echo "hari_kerja (G1): {$indra['hari_kerja']}\n";
    echo "hari_kerja_gajian2: {$indra['hari_kerja_gajian2']}\n";
    echo "is_mutasi: ".($indra['is_mutasi'] ? 'ya' : 'tidak')."\n";
} else {
    echo "Tidak ada di payroll outlet {$outletId}\n";
}

// Simulasi getAttendanceData untuk segmen mutasi
$effective = $m ? Carbon::parse($m->employment_effective_date) : null;
$gajian1Start = Carbon::parse('2026-04-26');
$gajian1End = Carbon::parse('2026-05-25');

$ctrl = app(PayrollReportController::class);
$ref = new ReflectionMethod($ctrl, 'getAttendanceData');
$ref->setAccessible(true);
$ref2 = new ReflectionMethod($ctrl, 'countMutationSegmentScDays');
$ref2->setAccessible(true);

if ($effective) {
    $segStart = $effective->gte($gajian1Start) ? $effective : $gajian1Start;
    echo "\n--- Segmen mutasi role=to: {$segStart->toDateString()} .. {$gajian1End->toDateString()} ---\n";

    $rows = $ref->invoke($ctrl, $user->id, $outletId, $segStart, $gajian1End);
    $hkOnly = $rows->filter(fn ($r) => ! empty($r['has_check_in']) && empty($r['is_off']))->count();
    $offDays = $rows->filter(fn ($r) => ! empty($r['is_off']))->count();
    $scDays = $ref2->invoke($ctrl, (int) $user->id, $outletId, $segStart, $gajian1End, 'to');

    echo "getAttendanceData total rows: {$rows->count()}\n";
    echo "hari kerja (IN && !OFF): {$hkOnly}\n";
    echo "hari OFF: {$offDays}\n";
    echo "countMutationSegmentScDays (pool): {$scDays}\n";

    echo "\nPer hari:\n";
    foreach ($rows as $r) {
        $flag = (! empty($r['has_check_in']) && empty($r['is_off'])) ? 'HK' : ($r['is_off'] ? 'OFF' : '  ');
        echo sprintf("  %s %s off=%s in=%s\n", $flag, $r['tanggal'], $r['is_off'] ? 'Y' : 'N', $r['has_check_in'] ? 'Y' : 'N');
    }
}

// Full gajian1 tanpa segmen
echo "\n--- Full gajian1 {$gajian1Start->toDateString()} .. {$gajian1End->toDateString()} ---\n";
$full = $ref->invoke($ctrl, $user->id, $outletId, $gajian1Start, $gajian1End);
$fullHk = $full->filter(fn ($r) => ! empty($r['has_check_in']) && empty($r['is_off']))->count();
$fullSc = $ref2->invoke($ctrl, (int) $user->id, $outletId, $gajian1Start, $gajian1End, 'to');
echo "HK only: {$fullHk}, mutation SC days: {$fullSc}\n";

// Kalender
if ($effective) {
    $cal = $segStart->diffInDays($gajian1End) + 1;
    echo "\nHari kalender segmen: {$cal}\n";
}
