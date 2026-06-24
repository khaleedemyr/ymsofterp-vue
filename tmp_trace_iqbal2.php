<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Outlets with Tempayan:\n";
$outlets = DB::table('tbl_data_outlet')->where('nama_outlet', 'like', '%Tempayan%')->get(['id_outlet','nama_outlet','status']);
foreach ($outlets as $o) echo "  {$o->id_outlet}: {$o->nama_outlet}\n";

$m = DB::table('employee_movements')->where('id', 294)->first();
echo "\nMovement 294 full:\n";
print_r($m);

// Resolve unit_property_to if numeric
if ($m && $m->unit_property_to) {
    $to = DB::table('tbl_data_outlet')->where('id_outlet', $m->unit_property_to)->orWhere('nama_outlet', $m->unit_property_to)->first();
    echo "Resolved TO: " . json_encode($to) . "\n";
}

// Where should Iqbal show for May 2026?
$year=2026;$month=5;
$start = date('Y-m-d', strtotime("$year-$month-26 -1 month"));
$end = date('Y-m-d', strtotime("$year-$month-25"));
$gajian2Start = '2026-05-01';
$gajian2End = '2026-05-31';

foreach ($outlets as $o) {
    $name = $o->nama_outlet;
    $id = $o->id_outlet;
    $mutFrom = DB::table('employee_movements')
        ->where('employment_type','mutation')
        ->where('unit_property_from', $name)
        ->where('employee_id', 1850)
        ->where(function($q) use ($start,$end,$gajian2Start,$gajian2End) {
            $q->whereBetween('employment_effective_date', [$start,$end])
              ->orWhereBetween('employment_effective_date', [$gajian2Start,$gajian2End]);
        })
        ->where('employment_effective_date', '>', $start)
        ->exists();
    $mutTo = DB::table('employee_movements')
        ->where('employment_type','mutation')
        ->where(function($q) use ($name, $id) {
            $q->where('unit_property_to', $name)->orWhere('unit_property_to', (string)$id);
        })
        ->where('employee_id', 1850)
        ->exists();
    $active = DB::table('users')->where('id',1850)->where('id_outlet',$id)->where('status','A')->exists();
    echo "\nOutlet $id $name: mutFROM_query=" . ($mutFrom?'Y':'N') . " mutTO=" . ($mutTo?'Y':'N') . " activeUser=" . ($active?'Y':'N') . "\n";
}
