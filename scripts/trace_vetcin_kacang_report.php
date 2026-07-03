<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FoodGrLastPurchaseForItem;
use Illuminate\Support\Facades\DB;

$from = '2026-06-01';
$to = '2026-06-30';
$outlet = 'Justus Steakhouse SMB';
$warehouse = 'Main Store';

foreach (['Kacang Tanah', 'Vetcin Powder'] as $name) {
    $item = DB::table('items')->where('name', $name)->first();
    $itemId = (int) $item->id;
    $mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name');

    $ip = DB::table('item_prices')->where('item_id', $itemId)->where('availability_price_type', 'all')->orderByDesc('id')->first();
    $auto = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
    $last = FoodGrLastPurchaseForItem::lastLine($itemId);
    $resolved = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, (string) $mediumUnit);

    echo "=== {$name} ===\n";
    echo "item_prices stored large={$ip->price} mode={$ip->pricing_mode}\n";
    echo "auto suggested large={$auto} last GR=" . ($last['receive_date'] ?? '-') . " cost_large=" . ($last['cost_large'] ?? 0) . "\n";
    echo "resolveLineUnitPrice(medium)={$resolved}\n";

    // Rekap FJ detail exact (WarehouseReport pattern)
    $ctrl = new class {
        use \App\Http\Traits\ReportHelperTrait;
        public function food($c, $f, $t, $w) { return $this->rekapFjFetchFoodGrDetailRows($c, $f, $t, $w); }
        public function serial($c, $f, $t, $w) { return $this->rekapFjFetchSerialGrDetailRows($c, $f, $t, $w); }
        public function merge($a, $b) { return $this->rekapFjMergeFjDetailRows($a, $b); }
    };
    $food = $ctrl->food($outlet, $from, $to, $warehouse);
    $serial = $ctrl->serial($outlet, $from, $to, $warehouse);
    $merged = $ctrl->merge($food, $serial)->firstWhere('item_name', $name);

    if ($merged) {
        echo "REKAP FJ detail [{$outlet} / {$warehouse}]: qty={$merged->received_qty} price={$merged->price} subtotal={$merged->subtotal}\n";
        echo "  selisih vs resolved={$resolved}: " . ($merged->price - $resolved) . "\n";
        echo "  selisih vs stored large={$ip->price}: " . ($merged->price - (float) $ip->price) . "\n";
        echo "  selisih vs auto large={$auto}: " . ($merged->price - (float) $auto) . "\n";
    } else {
        echo "No Rekap FJ detail row\n";
    }

    // All outlets aggregate (pivot style)
    $foodRows = $ctrl->food($outlet, $from, $to, $warehouse);
    // Check serial rows with wrong cost_small still
    $badSerial = DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
        ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
        ->join('items as it', 'it.id', '=', 'si.item_id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->where('si.item_id', $itemId)
        ->where('o.nama_outlet', $outlet)
        ->whereBetween('h.receive_date', [$from, $to])
        ->whereNull('h.deleted_at')
        ->where('w.name', $warehouse)
        ->whereRaw('ABS(COALESCE(si.cost_small,0) - ?) > 0.0001', [
            round((float) $ip->price / max((float) $item->small_conversion_qty, 1), 4),
        ])
        ->select('si.id', 'si.cost_small', 'si.qty', 'h.receive_date')
        ->get();
    echo "bad serial rows (Main Store SMB): " . $badSerial->count() . "\n";
    foreach ($badSerial->take(5) as $b) {
        echo "  si_id={$b->id} date={$b->receive_date} cost_small={$b->cost_small} qty={$b->qty}\n";
    }

    // FO rows still wrong in June for SMB
    $badFo = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
        ->where('ffoi.item_id', $itemId)
        ->where('o.nama_outlet', $outlet)
        ->whereBetween('ffo.tanggal', [$from, $to])
        ->whereRaw('ABS(ffoi.price - ?) > 1', [$resolved])
        ->select('ffoi.id', 'ffoi.price', 'ffo.tanggal', 'ffo.fo_mode')
        ->get();
    echo "bad FO rows Jun SMB: " . $badFo->count() . "\n";
    foreach ($badFo as $b) {
        echo "  fo_id={$b->id} price={$b->price} tanggal={$b->tanggal} mode={$b->fo_mode}\n";
    }
    echo "\n";
}
