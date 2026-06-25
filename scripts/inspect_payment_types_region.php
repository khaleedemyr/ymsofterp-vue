<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$names = ['BCA MERCHANT(QRIS)', 'Gopay', 'Grabfood'];

foreach ($names as $name) {
    $pt = DB::table('payment_types')->where('name', $name)->first();
    if (! $pt) {
        echo "NOT FOUND: {$name}\n";
        continue;
    }
    echo "=== {$pt->name} (id={$pt->id}) ===\n";
    $outlets = DB::table('payment_type_outlets as pto')
        ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'pto.outlet_id')
        ->where('pto.payment_type_id', $pt->id)
        ->select('pto.outlet_id', 'o.nama_outlet', 'o.region_id')
        ->get();
    echo 'Outlets: '.$outlets->count()."\n";
    $regions = $outlets->pluck('region_id')->unique()->filter()->values();
    echo 'Unique regions from outlets: '.json_encode($regions->all())."\n";
    $ptr = DB::table('payment_type_regions as ptr')
        ->join('regions as r', 'r.id', '=', 'ptr.region_id')
        ->where('ptr.payment_type_id', $pt->id)
        ->select('ptr.*', 'r.name as region_name')
        ->get();
    echo 'Current regions: '.json_encode($ptr)."\n\n";
}

echo "=== All active regions ===\n";
$regions = DB::table('regions')->where('status', 'active')->get(['id', 'name']);
foreach ($regions as $r) {
    echo "  [{$r->id}] {$r->name}\n";
}

$create = DB::select('SHOW CREATE TABLE payment_type_regions');
echo "\n=== SHOW CREATE TABLE payment_type_regions ===\n";
echo ($create[0]->{'Create Table'} ?? '')."\n";
