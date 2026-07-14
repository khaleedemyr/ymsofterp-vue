<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$outletId = 23;
$date = '2026-07-12';

echo "=== Columns outlet_serial_receive_headers ===\n";
if (Schema::hasTable('outlet_serial_receive_headers')) {
    foreach (DB::select('SHOW COLUMNS FROM outlet_serial_receive_headers') as $c) {
        echo "  {$c->Field}\n";
    }
    $gsr = DB::table('outlet_serial_receive_headers')->where('outlet_id', $outletId)->whereDate('receive_date', $date)->get();
    echo "GSR count: " . $gsr->count() . "\n";
    foreach ($gsr as $g) {
        echo "  id={$g->id} number=" . ($g->number ?? $g->receive_number ?? '?') . "\n";
        print_r((array) $g);
        break; // sample one
    }
}

echo "\n=== All GSR on date ===\n";
$rows = DB::table('outlet_serial_receive_headers as h')
    ->where('h.outlet_id', $outletId)
    ->whereDate('h.receive_date', $date)
    ->get();
foreach ($rows as $r) {
    $arr = (array) $r;
    $num = $arr['number'] ?? $arr['receive_number'] ?? $arr['gr_number'] ?? $r->id;
    echo "  id={$r->id} number={$num}\n";
}

// food GR with MAIN STORE style warehouse outlets
echo "\n=== warehouse_outlets for Bintaro ===\n";
$wos = DB::table('warehouse_outlets')->where('outlet_id', $outletId)->get(['id', 'name']);
foreach ($wos as $w) echo "  {$w->id} {$w->name}\n";

echo "\n=== All OGR any warehouse that day (recheck) ===\n";
$grs = DB::table('outlet_food_good_receives')->where('outlet_id', $outletId)->whereDate('receive_date', $date)->whereNull('deleted_at')->get();
echo "count=" . $grs->count() . "\n";
foreach ($grs as $g) echo "  {$g->id} {$g->number} wo={$g->warehouse_outlet_id}\n";

// Maybe Main Store GRs use delivery from packing to outlet without deleted / different date field?
echo "\n=== Nearby dates OGR count ===\n";
foreach (['2026-07-11','2026-07-12','2026-07-13'] as $d) {
    $c = DB::table('outlet_food_good_receives')->where('outlet_id', $outletId)->whereDate('receive_date', $d)->whereNull('deleted_at')->count();
    echo "  {$d}: {$c}\n";
}
