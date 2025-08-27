<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG OUTLET REJECTION STOCK ===\n\n";

// Check latest outlet rejection
$latestRejection = DB::table('outlet_rejections')
    ->orderByDesc('id')
    ->first();

if (!$latestRejection) {
    echo "No outlet rejections found!\n";
    exit;
}

echo "Latest Outlet Rejection:\n";
echo "ID: {$latestRejection->id}\n";
echo "Number: {$latestRejection->number}\n";
echo "Status: {$latestRejection->status}\n";
echo "Warehouse ID: {$latestRejection->warehouse_id}\n";
echo "Created: {$latestRejection->created_at}\n";
echo "Completed: {$latestRejection->completed_at}\n\n";

// Check items
$items = DB::table('outlet_rejection_items')
    ->where('outlet_rejection_id', $latestRejection->id)
    ->get();

echo "Items:\n";
foreach ($items as $item) {
    echo "- Item ID: {$item->item_id}, Qty Rejected: {$item->qty_rejected}, Qty Received: {$item->qty_received}, MAC Cost: {$item->mac_cost}\n";
}
echo "\n";

// Check if items have qty_received > 0
$itemsWithQty = $items->where('qty_received', '>', 0);
echo "Items with qty_received > 0: " . $itemsWithQty->count() . "\n\n";

if ($itemsWithQty->count() > 0) {
    foreach ($itemsWithQty as $item) {
        echo "Processing item ID: {$item->item_id}\n";
        
        // Check inventory item
        $inventoryItem = DB::table('food_inventory_items')
            ->where('item_id', $item->item_id)
            ->first();
            
        if (!$inventoryItem) {
            echo "  ❌ Inventory item not found!\n";
            continue;
        }
        
        echo "  ✅ Inventory item found: {$inventoryItem->id}\n";
        
        // Check existing stock
        $existingStock = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $latestRejection->warehouse_id)
            ->first();
            
        if ($existingStock) {
            echo "  ✅ Existing stock found:\n";
            echo "    - Qty Small: {$existingStock->qty_small}\n";
            echo "    - Qty Medium: {$existingStock->qty_medium}\n";
            echo "    - Qty Large: {$existingStock->qty_large}\n";
            echo "    - Value: {$existingStock->value}\n";
        } else {
            echo "  ℹ️  No existing stock found (will create new)\n";
        }
        
        // Check stock cards
        $stockCards = DB::table('food_inventory_cards')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $latestRejection->warehouse_id)
            ->where('reference_type', 'outlet_rejection')
            ->where('reference_id', $latestRejection->id)
            ->get();
            
        echo "  Stock cards for this rejection: " . $stockCards->count() . "\n";
        
        // Check cost histories
        $costHistories = DB::table('food_inventory_cost_histories')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $latestRejection->warehouse_id)
            ->where('reference_type', 'outlet_rejection')
            ->where('reference_id', $latestRejection->id)
            ->get();
            
        echo "  Cost histories for this rejection: " . $costHistories->count() . "\n\n";
    }
} else {
    echo "❌ No items with qty_received > 0 found!\n";
    echo "This means the approval process didn't set qty_received properly.\n\n";
}

// Check if the rejection was actually completed
if ($latestRejection->status === 'completed') {
    echo "✅ Rejection status is 'completed'\n";
} else {
    echo "❌ Rejection status is '{$latestRejection->status}' (should be 'completed')\n";
}

if ($latestRejection->completed_at) {
    echo "✅ Rejection has completed_at timestamp\n";
} else {
    echo "❌ Rejection has no completed_at timestamp\n";
}

echo "\n=== END DEBUG ===\n";

