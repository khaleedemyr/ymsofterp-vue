<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = DB::table('users')->where('nama_lengkap', 'like', '%Iqbal Hamdani%')->first();
if (!$u) { echo "User not found\n"; exit(1); }

echo "User: {$u->nama_lengkap} id={$u->id} nik={$u->nik}\n";
echo "Current outlet: {$u->id_outlet} status={$u->status}\n";

$outlet = DB::table('tbl_data_outlet')->where('id_outlet', $u->id_outlet)->first(['id_outlet','nama_outlet']);
echo "Current outlet name: {$outlet->nama_outlet}\n\n";

$movements = DB::table('employee_movements')
    ->where('employee_id', $u->id)
    ->where('employment_type', 'mutation')
    ->orderByDesc('employment_effective_date')
    ->get();
echo "Mutations:\n";
foreach ($movements as $m) {
    echo json_encode([
        'id' => $m->id,
        'from' => $m->unit_property_from,
        'to' => $m->unit_property_to,
        'effective' => $m->employment_effective_date,
        'status' => $m->status,
    ], JSON_PRETTY_PRINT) . "\n";
}

// Simulate May 2026 payroll at source outlet
$year = 2026; $month = 5;
$start = date('Y-m-d', strtotime("$year-$month-26 -1 month"));
$end = date('Y-m-d', strtotime("$year-$month-25"));
echo "\nPayroll period May 2026 gajian1: $start to $end\n";

foreach ($movements as $m) {
    $fromName = $m->unit_property_from;
    $fromOutlet = DB::table('tbl_data_outlet')->where('nama_outlet', $fromName)->first();
    $fromId = $fromOutlet->id_outlet ?? '?';
    echo "\n--- Check source outlet: $fromName (id=$fromId) ---\n";
    
    $found = DB::table('employee_movements')
        ->where('employment_type', 'mutation')
        ->where('unit_property_from', $fromName)
        ->whereNotNull('employment_effective_date')
        ->where('employment_effective_date', '>', $start)
        ->where('employment_effective_date', '<=', $end)
        ->whereIn('status', ['executed', 'approved', 'pending'])
        ->where('employee_id', $u->id)
        ->exists();
    echo "Would appear in source outlet payroll query: " . ($found ? 'YES' : 'NO') . "\n";
    
    // Active users at source
    $activeAtSource = DB::table('users')->where('status','A')->where('id_outlet', $fromId)->where('id', $u->id)->exists();
    echo "Active user at source outlet now: " . ($activeAtSource ? 'YES' : 'NO') . "\n";
}

// Destination outlet 24
echo "\n--- Destination Tempayan Bandung (24) ---\n";
$destName = DB::table('tbl_data_outlet')->where('id_outlet', 24)->value('nama_outlet');
$foundDest = DB::table('employee_movements')
    ->where('employment_type', 'mutation')
    ->where('unit_property_from', $destName)
    ->where('employee_id', $u->id)
    ->exists();
echo "Mutation FROM dest (should be no): " . ($foundDest ? 'YES' : 'NO') . "\n";
$activeDest = $u->id_outlet == 24;
echo "Active at dest: " . ($activeDest ? 'YES' : 'NO') . "\n";

// Attendance before mutation at source
if ($movements->isNotEmpty()) {
    $m = $movements->first();
    $fromOutlet = DB::table('tbl_data_outlet')->where('nama_outlet', $m->unit_property_from)->first();
    if ($fromOutlet) {
        $eff = $m->employment_effective_date;
        $att = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) { $q->on('a.pin','=','up.pin')->on('o.id_outlet','=','up.outlet_id'); })
            ->where('up.user_id', $u->id)
            ->where('o.id_outlet', $fromOutlet->id_outlet)
            ->where('a.scan_date', '>=', $start)
            ->where('a.scan_date', '<', $eff)
            ->count();
        echo "\nAttendance at source before mutation: $att scans\n";
    }
}
