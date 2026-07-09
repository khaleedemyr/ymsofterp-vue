<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FoodGrLastPurchaseForItem;
use Illuminate\Support\Facades\DB;

$itemId = (int) ($argv[1] ?? 52337);
$from = $argv[2] ?? '2026-06-01';
$to = $argv[3] ?? '2026-06-30';
$outlet = $argv[4] ?? 'Justus Steakhouse SMB';
$warehouse = $argv[5] ?? 'Main Store';

echo "=== Item {$itemId} ===\n";

$rows = DB::table('food_floor_order_items as foi')
    ->join('food_floor_orders as fo', 'fo.id', '=', 'foi.floor_order_id')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'fo.id_outlet')
    ->where('foi.item_id', $itemId)
    ->select('fo.order_number', 'fo.tanggal', 'fo.status', 'foi.price', 'foi.unit', 'o.nama_outlet', 'foi.created_at')
    ->orderByDesc('fo.tanggal')
    ->limit(8)
    ->get();
echo "Recent FO prices:\n";
foreach ($rows as $r) {
    echo "  {$r->tanggal} | {$r->order_number} | {$r->nama_outlet} | {$r->unit} | price={$r->price}\n";
}

$ctrl = new class {
    use App\Http\Traits\ReportHelperTrait;

    public function rows(string $outlet, string $from, string $to, string $warehouse)
    {
        return $this->rekapFjFetchFoodGrDetailRows($outlet, $from, $to, $warehouse);
    }
};

$name = DB::table('items')->where('id', $itemId)->value('name');
echo "\nRekap FJ {$warehouse} {$from}..{$to} for {$name}:\n";
foreach ($ctrl->rows($outlet, $from, $to, $warehouse) as $r) {
    if ((int) ($r->item_id ?? 0) === $itemId || stripos((string) $r->item_name, 'Black') !== false) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
