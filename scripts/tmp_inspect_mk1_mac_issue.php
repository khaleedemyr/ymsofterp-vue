<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$itemId = 54667; // Barbeque Sauce
$warehouseName = 'MK1 Hot Kitchen';

$item = DB::table('items')->where('id', $itemId)->first();
$warehouse = DB::table('warehouses')->where('name', $warehouseName)->first();
$inventoryItem = DB::table('food_inventory_items')->where('item_id', $itemId)->first();

echo "=== Inspect MAC issue ===\n";
echo "item_id={$itemId} item={$item->name}\n";
echo "warehouse={$warehouseName} id={$warehouse->id}\n";
echo "inventory_item_id={$inventoryItem->id}\n";
echo "item conv small={$item->small_conversion_qty} medium={$item->medium_conversion_qty}\n\n";

$stock = DB::table('food_inventory_stocks')
    ->where('inventory_item_id', $inventoryItem->id)
    ->where('warehouse_id', $warehouse->id)
    ->first();

if ($stock) {
    $implied = (float) $stock->qty_small > 0 ? ((float) $stock->value / (float) $stock->qty_small) : 0.0;
    echo "stock.id={$stock->id}\n";
    echo "qty_small={$stock->qty_small} qty_medium={$stock->qty_medium} qty_large={$stock->qty_large}\n";
    echo "value={$stock->value}\n";
    echo "last_cost_small={$stock->last_cost_small} last_cost_medium={$stock->last_cost_medium} last_cost_large={$stock->last_cost_large}\n";
    echo "implied_mac_small={$implied}\n\n";
}

echo "--- last 10 cost histories ---\n";
$histories = DB::table('food_inventory_cost_histories')
    ->where('inventory_item_id', $inventoryItem->id)
    ->where('warehouse_id', $warehouse->id)
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->limit(10)
    ->get();

foreach ($histories as $h) {
    $row = (array) $h;
    echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n--- recent serials for this item in MK1 ---\n";
$serials = DB::table('inventory_item_serials')
    ->where('item_id', $itemId)
    ->where('warehouse_id', $warehouse->id)
    ->orderByDesc('id')
    ->limit(10)
    ->get(['id', 'serial_number', 'cost_small', 'unit_id', 'created_at']);

foreach ($serials as $s) {
    echo "serial_id={$s->id} sn={$s->serial_number} cost_small={$s->cost_small} unit_id={$s->unit_id} created_at={$s->created_at}\n";
}

