<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Steakhouse / Bandung outlets:\n";
$outlets = DB::table('tbl_data_outlet')
    ->where('nama_outlet', 'like', '%Steak%')
    ->orWhere('nama_outlet', 'like', '%Bandung%')
    ->orWhere('nama_outlet', 'like', '%Tempayan%')
    ->get(['id_outlet','nama_outlet']);
foreach ($outlets as $o) echo "  {$o->id_outlet}: {$o->nama_outlet}\n";

echo "\nDivisions:\n";
$divs = DB::table('tbl_data_divisi')->where('nama_divisi', 'like', '%Steak%')->orWhere('nama_divisi', 'like', '%Tempayan%')->get(['id','nama_divisi']);
foreach ($divs as $d) echo "  {$d->id}: {$d->nama_divisi}\n";

$u = DB::table('users')->where('id', 1850)->first();
echo "\nIqbal division_id: {$u->division_id}\n";
$div = DB::table('tbl_data_divisi')->where('id', $u->division_id)->first();
echo "Division name: {$div->nama_divisi}\n";

// Attendance by outlet May 2026 before May 4
$start = '2026-04-26';
$eff = '2026-05-04';
echo "\nAttendance Apr26-May3 by outlet:\n";
$att = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function($q) { $q->on('a.pin','=','up.pin')->on('o.id_outlet','=','up.outlet_id'); })
    ->where('up.user_id', 1850)
    ->where('a.scan_date', '>=', $start . ' 00:00:00')
    ->where('a.scan_date', '<', $eff . ' 00:00:00')
    ->select('o.id_outlet', 'o.nama_outlet', DB::raw('count(*) as cnt'))
    ->groupBy('o.id_outlet', 'o.nama_outlet')
    ->get();
foreach ($att as $a) echo "  {$a->id_outlet} {$a->nama_outlet}: {$a->cnt} scans\n";

// Previous movement from Ciwalk
$m48 = DB::table('employee_movements')->where('id', 48)->first();
echo "\nOld movement 48: from {$m48->unit_property_from} to {$m48->unit_property_to} eff {$m48->employment_effective_date}\n";
