<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== items Mashed Potato ===\n";
foreach (DB::table('items')->where('name', 'like', '%Mashed Potato%')->select('id', 'name', 'sku')->orderBy('name')->get() as $r) {
    echo "{$r->id} | {$r->name} | {$r->sku}\n";
}

echo "\n=== serial SR-20260617* ===\n";
foreach (DB::table('inventory_item_serials')->where('serial_number', 'like', 'SR-20260617%')->limit(20)->get() as $r) {
    $item = DB::table('items')->where('id', $r->item_id)->first(['name', 'sku']);
    echo "{$r->serial_number} | item_id={$r->item_id} | {$item->name} | cost_small={$r->cost_small}\n";
}

echo "\n=== serial *7888* ===\n";
foreach (DB::table('inventory_item_serials')->where('serial_number', 'like', '%7888%')->limit(10)->get() as $r) {
    $item = DB::table('items')->where('id', $r->item_id)->first(['name', 'sku']);
    echo "{$r->serial_number} | item_id={$r->item_id} | {$item->name}\n";
}
