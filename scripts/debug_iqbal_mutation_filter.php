<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$m = DB::table('employee_movements')->where('id', 294)->first();
$start = '2026-04-26';
$end = '2026-05-25';
$g2s = '2026-05-01';
$g2e = '2026-05-31';

echo "Movement 294:\n";
echo "  effective={$m->employment_effective_date}\n";
echo "  unit_property_change={$m->unit_property_change} (type: ".gettype($m->unit_property_change).")\n";
echo "  employment_type={$m->employment_type}\n";
echo "  status={$m->status}\n";
echo "  from={$m->unit_property_from} to={$m->unit_property_to}\n";

$inG1 = $m->employment_effective_date >= $start && $m->employment_effective_date <= $end;
$inG2 = $m->employment_effective_date >= $g2s && $m->employment_effective_date <= $g2e;
$afterStart = $m->employment_effective_date > $start;

echo "\nPeriod check (May payroll):\n";
echo "  in gajian1 ($start..$end): ".($inG1 ? 'yes' : 'no')."\n";
echo "  in gajian2 ($g2s..$g2e): ".($inG2 ? 'yes' : 'no')."\n";
echo "  effective > start: ".($afterStart ? 'yes' : 'no')."\n";
echo "  unit_property_change == 1: ".($m->unit_property_change == 1 ? 'yes' : 'NO - EXCLUDED')."\n";
echo "  unit_property_change == true: ".($m->unit_property_change == true ? 'yes' : 'no')."\n";

// Count Iqbal attendance May 4 - May 25 at outlet 24
$days = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', 1850)
    ->where('o.id_outlet', 24)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), ['2026-05-04', '2026-05-25'])
    ->distinct()
    ->pluck(DB::raw('DATE(a.scan_date)'));
echo "\nAttendance outlet 24 from effective date May 4 to May 25: ".$days->count()." days\n";

$daysG2 = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', 1850)
    ->where('o.id_outlet', 24)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), ['2026-05-04', '2026-05-31'])
    ->distinct()
    ->count(DB::raw('DATE(a.scan_date)'));
echo "Attendance outlet 24 May 4 - May 31 (gajian2 segment): {$daysG2} days\n";
