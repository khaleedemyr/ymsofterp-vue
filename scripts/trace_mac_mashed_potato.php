<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\OutletInventoryCostResolver;
use Illuminate\Support\Facades\DB;

$serialSku = 'SR-20260617-7888';
$itemNameLike = 'Mashed Potato';

echo "=== Trace MAC terakhir: {$itemNameLike} (sku {$serialSku}) ===\n\n";

$item = DB::table('items')
    ->where('sku', $serialSku)
    ->orWhere(function ($q) use ($itemNameLike) {
        $q->where('name', $itemNameLike);
    })
    ->orderByRaw('CASE WHEN sku = ? THEN 0 WHEN name = ? THEN 1 ELSE 2 END', [$serialSku, $itemNameLike])
    ->first();

if (! $item) {
    echo "Item tidak ditemukan.\n";
    exit(1);
}

$itemId = $item->id;
echo "Item: id={$item->id} name={$item->name} sku={$item->sku}\n";

$serial = DB::table('inventory_item_serials')
    ->where('item_id', $itemId)
    ->orderByDesc('id')
    ->first();
if ($serial) {
    echo "Latest serial batch: {$serial->serial_number} cost_small={$serial->cost_small} out_outlet_id={$serial->out_outlet_id}\n";
}
echo "\n";

$invItem = DB::table('outlet_food_inventory_items')
    ->where('item_id', $itemId)
    ->first();

if (! $invItem) {
    echo "Tidak ada outlet_food_inventory_items untuk item_id={$itemId}\n";
    exit(1);
}

echo "inventory_item_id={$invItem->id}\n\n";

$stocks = DB::table('outlet_food_inventory_stocks as st')
    ->join('tbl_data_outlet as o', 'st.id_outlet', '=', 'o.id_outlet')
    ->leftJoin('warehouse_outlets as wo', 'st.warehouse_outlet_id', '=', 'wo.id')
    ->where('st.inventory_item_id', $invItem->id)
    ->where(function ($q) {
        $q->where('st.qty_small', '>', 0)
            ->orWhere('st.qty_medium', '>', 0)
            ->orWhere('st.qty_large', '>', 0);
    })
    ->select(
        'st.*',
        'o.nama_outlet as outlet_name',
        'wo.name as warehouse_name'
    )
    ->orderBy('o.nama_outlet')
    ->get();

if ($stocks->isEmpty()) {
    echo "Tidak ada outlet dengan stock > 0.\n";
    exit(0);
}

printf("%-28s %-22s %12s %14s %14s %12s %s\n", 'Outlet', 'Gudang', 'Qty Small', 'last_cost_sm', 'MAC resolved', 'Stock value', 'Last MAC history');
echo str_repeat('-', 130) . "\n";

foreach ($stocks as $st) {
    $macResolved = OutletInventoryCostResolver::resolveMacFromStockRow($st);

    $lastHist = DB::table('outlet_food_inventory_cost_histories')
        ->where('id_outlet', $st->id_outlet)
        ->where('warehouse_outlet_id', $st->warehouse_outlet_id)
        ->where('inventory_item_id', $st->inventory_item_id)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();

    $histStr = $lastHist
        ? sprintf('%s | new_cost=%.4f | %s | ref=%s#%s', $lastHist->date, (float) $lastHist->new_cost, $lastHist->reference_type ?? '-', $lastHist->reference_type ?? '', $lastHist->reference_id ?? '')
        : '-';

    printf(
        "%-28s %-22s %12.2f %14.4f %14.4f %12.2f %s\n",
        mb_substr($st->outlet_name, 0, 28),
        mb_substr($st->warehouse_name ?? '-', 0, 22),
        (float) $st->qty_small,
        (float) $st->last_cost_small,
        $macResolved,
        (float) $st->value,
        $histStr
    );
}

echo "\n=== Last inbound (GR/serial) per outlet ===\n";
$outletIds = $stocks->pluck('id_outlet')->unique();
foreach ($outletIds as $outletId) {
    $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');

    $lastGr = DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->where('h.outlet_id', $outletId)
        ->where('si.item_id', $itemId)
        ->orderByDesc('h.receive_date')
        ->orderByDesc('h.id')
        ->first(['h.number', 'h.receive_date', 'si.cost_small', 'si.cost_source', 'si.qty']);

  $lastFoodGr = DB::table('outlet_food_good_receive_headers as h')
        ->join('outlet_food_good_receive_items as di', 'h.id', '=', 'di.header_id')
        ->where('h.outlet_id', $outletId)
        ->where('di.item_id', $itemId)
        ->orderByDesc('h.receive_date')
        ->orderByDesc('h.id')
        ->first(['h.number', 'h.receive_date', 'di.cost_small']);

    echo "\n[{$outletName}] outlet_id={$outletId}\n";
    if ($lastGr) {
        echo "  Last GR Nomor Seri: {$lastGr->number} @ {$lastGr->receive_date} cost_small={$lastGr->cost_small} src={$lastGr->cost_source} qty={$lastGr->qty}\n";
    } else {
        echo "  Last GR Nomor Seri: (none)\n";
    }
    if ($lastFoodGr) {
        echo "  Last GR Food: {$lastFoodGr->number} @ {$lastFoodGr->receive_date} cost_small={$lastFoodGr->cost_small}\n";
    } else {
        echo "  Last GR Food: (none)\n";
    }
}

echo "\nDone.\n";
