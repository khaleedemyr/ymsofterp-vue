<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\PayrollReportController;
use App\Services\PayrollScTraceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$outletId = 24;
$year = 2026;
$month = 5;
$userId = (int) DB::table('users')->where('nik', '251411')->value('id');

echo "=== DEBUG IQBAL SC HARI KERJA (user {$userId}, outlet {$outletId}, {$year}-{$month}) ===\n\n";

// 1. Movement
$movement = DB::table('employee_movements')
    ->where('employee_id', $userId)
    ->where('employment_type', 'mutation')
    ->orderByDesc('id')
    ->first();
echo "--- Movement ---\n";
echo json_encode($movement, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n";

// 2. ERP payroll row
$tracer = app(PayrollScTraceService::class);
$result = $tracer->run($outletId, $year, $month, 22750551);
$iqbal = collect($result['rows'])->first(fn ($r) => stripos($r['nama'], 'iqbal') !== false);
echo "--- ERP Payroll row ---\n";
echo json_encode($iqbal, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n";

// 3. Per-outlet scan dates in gajian1
$start = '2026-04-26';
$end = '2026-05-25';
$effective = '2026-05-04';

echo "--- Scan per outlet (gajian1 {$start}..{$end}) ---\n";
$scansByOutlet = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', $userId)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end])
    ->select('o.id_outlet', 'o.nama_outlet', DB::raw('DATE(a.scan_date) as d'))
    ->distinct()
    ->orderBy('d')
    ->get()
    ->groupBy('id_outlet');

foreach ($scansByOutlet as $oid => $rows) {
    $name = $rows->first()->nama_outlet;
    $dates = $rows->pluck('d')->unique()->sort()->values();
    echo "Outlet {$oid} ({$name}): {$dates->count()} hari\n";
    foreach ($dates as $d) {
        $flag = $d >= $effective ? ' [>=eff]' : ' [<eff]';
        echo "  {$d}{$flag}\n";
    }
}

// 4. Outlet 24 only counts
$out24since = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', $userId)
    ->where('o.id_outlet', $outletId)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end])
    ->distinct()
    ->pluck(DB::raw('DATE(a.scan_date)'))
    ->sort()
    ->values();

echo "\n--- Outlet 24 only ---\n";
echo "Full gajian1: {$out24since->count()} hari\n";
$sinceEff = $out24since->filter(fn ($d) => $d >= $effective);
echo "Since effective ({$effective}): {$sinceEff->count()} hari\n";
foreach ($sinceEff as $d) {
    echo "  {$d}\n";
}

// 5. Calendar days (mutation calc)
$startC = Carbon::parse($start)->startOfDay();
$endC = Carbon::parse($end)->startOfDay();
$effC = Carbon::parse($effective)->startOfDay();
$calTo = $effC->gt($startC) ? $effC->copy() : $startC->copy();
$calDays = $calTo->diffInDays($endC) + 1;
echo "\n--- Kalender mutasi role=to (gajian1) ---\n";
echo "Segment {$calTo->toDateString()}..{$endC->toDateString()} = {$calDays} hari kalender\n";

// 6. Employee summary style - call attendance report if possible
echo "\n--- User pins ---\n";
$pins = DB::table('user_pins as up')
    ->join('tbl_data_outlet as o', 'up.outlet_id', '=', 'o.id_outlet')
    ->where('up.user_id', $userId)
    ->select('up.*', 'o.nama_outlet')
    ->get();
foreach ($pins as $p) {
    echo "pin={$p->pin} outlet={$p->outlet_id} ({$p->nama_outlet})\n";
}
