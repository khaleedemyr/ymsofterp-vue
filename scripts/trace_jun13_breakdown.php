<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$item = DB::table('items')->where('id', 52985)->first();
$expr = function ($costSmall, $unitId) use ($item) {
    if ((int) $unitId === (int) $item->large_unit_id) {
        return (float) $costSmall * (float) $item->small_conversion_qty * (float) $item->medium_conversion_qty;
    }
    if ((int) $unitId === (int) $item->medium_unit_id) {
        return (float) $costSmall * (float) $item->small_conversion_qty;
    }
    return (float) $costSmall;
};

$rows = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->where('o.nama_outlet', 'Justus Steak House Cipete')
    ->where('si.item_id', 52985)
    ->whereDate('h.receive_date', '2026-06-13')
    ->select('si.cost_small', 'si.cost_source', 'si.qty', 'si.unit_id')
    ->get();

$manual = 0; $wrong = 0; $subManual = 0; $subWrong = 0;
foreach ($rows as $r) {
    $p = $expr($r->cost_small, $r->unit_id);
    $sub = $p * (float) $r->qty;
    if ($r->cost_source === 'item_prices') {
        $manual += (float) $r->qty;
        $subManual += $sub;
    } else {
        $wrong += (float) $r->qty;
        $subWrong += $sub;
    }
    echo "src={$r->cost_source} cost_small={$r->cost_small} => Rp " . number_format($p, 2) . "\n";
}
$total = $manual + $wrong;
echo "\nManual qty={$manual} sub=" . number_format($subManual, 2) . "\n";
echo "Wrong qty={$wrong} sub=" . number_format($subWrong, 2) . "\n";
echo "Total qty={$total} weighted=" . number_format(($subManual + $subWrong) / $total, 2) . " subtotal=" . number_format($subManual + $subWrong, 2) . "\n";
