<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Traits\ReportHelperTrait;
use App\Support\ItemUnitCost;
use Illuminate\Support\Facades\DB;

$customer = 'Justus Steak House Cipete';
$from = '2026-06-08';
$to = '2026-06-14';
$itemId = 52985;
$item = DB::table('items')->where('id', $itemId)->first();

echo "=== Serial GSR rows {$from}..{$to} ===\n";
$rows = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->where('o.nama_outlet', $customer)
    ->where('si.item_id', $itemId)
    ->whereBetween('h.receive_date', [$from, $to])
    ->whereNull('h.deleted_at')
    ->select('h.receive_date', 'si.qty', 'si.cost_small', 'si.cost_source', 'si.unit_id')
    ->get();

$expr = function ($costSmall, $unitId) use ($item) {
    if ((int) $unitId === (int) $item->large_unit_id) {
        return (float) $costSmall * (float) $item->small_conversion_qty * (float) $item->medium_conversion_qty;
    }
    if ((int) $unitId === (int) $item->medium_unit_id) {
        return (float) $costSmall * (float) $item->small_conversion_qty;
    }

    return (float) $costSmall;
};

foreach ($rows as $r) {
    $p = $expr($r->cost_small, $r->unit_id);
    echo "{$r->receive_date} qty={$r->qty} cost_small={$r->cost_small} src={$r->cost_source} report_price=" . number_format($p, 2) . "\n";
}

$totalQty = $rows->sum('qty');
$totalSub = $rows->sum(fn ($r) => (float) $r->qty * $expr($r->cost_small, $r->unit_id));
echo "Serial total: qty={$totalQty} weighted=" . ($totalQty > 0 ? number_format($totalSub / $totalQty, 2) : '0') . "\n";

class T extends \App\Http\Controllers\Controller { use \App\Http\Traits\ReportHelperTrait; }
$t = new T();
$food = $t->rekapFjFetchFoodGrDetailRows($customer, $from, $to, 'MAIN STORE', null, ['Chemical', 'Stationary', 'Marketing']);
$serial = $t->rekapFjFetchSerialGrDetailRows($customer, $from, $to, 'MAIN STORE', null, ['Chemical', 'Stationary', 'Marketing']);
$merged = $t->rekapFjMergeFjDetailRows($food, $serial);
$row = $merged->first(fn ($r) => $r->item_name === 'Beef Tenderloin Aussie 250gr');
echo "\nMerged 250gr: qty={$row->received_qty} price=" . number_format($row->price, 2) . " subtotal=" . number_format($row->subtotal, 2) . "\n";
