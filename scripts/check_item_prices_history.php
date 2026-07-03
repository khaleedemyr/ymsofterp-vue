<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Support\FoodGrLastPurchaseForItem;
use Illuminate\Support\Facades\DB;

foreach (['Kacang Tanah', 'Vetcin Powder'] as $name) {
    $item = DB::table('items')->where('name', $name)->first();
    echo "=== {$name} id={$item->id} ===\n";
    echo "item_prices history:\n";
    foreach (DB::table('item_prices')->where('item_id', $item->id)->orderByDesc('id')->limit(5)->get() as $p) {
        echo "  id={$p->id} type={$p->availability_price_type} price={$p->price} mode=" . ($p->pricing_mode ?? '-') . " updated={$p->updated_at}\n";
    }
    $last = FoodGrLastPurchaseForItem::lastLine((int)$item->id);
    print_r($last);
    echo "\n";
}
