<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Set up Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFIKASI DATA INVENTORY OUTLET REJECTION ===\n\n";

// Cek data outlet rejection
$rejection = DB::table('outlet_rejections')->where('id', 5)->first();
if ($rejection) {
    echo "Outlet Rejection ID: {$rejection->id}\n";
    echo "Number: {$rejection->number}\n";
    echo "Status: {$rejection->status}\n";
    echo "Rejection Date: {$rejection->rejection_date}\n";
    echo "Warehouse ID: {$rejection->warehouse_id}\n\n";
} else {
    echo "Outlet Rejection ID 5 tidak ditemukan!\n\n";
    exit;
}

// Cek items rejection
$rejectionItems = DB::table('outlet_rejection_items')->where('outlet_rejection_id', 5)->get();
echo "=== ITEMS REJECTION ===\n";
foreach ($rejectionItems as $item) {
    echo "Item ID: {$item->id}\n";
    echo "Item Master ID: {$item->item_id}\n";
    echo "Qty Rejected: {$item->qty_rejected}\n";
    echo "Qty Received: {$item->qty_received}\n";
    echo "MAC Cost: {$item->mac_cost}\n\n";
}

// Cek inventory item
$inventoryItem = DB::table('food_inventory_items')->where('item_id', 53080)->first();
if ($inventoryItem) {
    echo "=== INVENTORY ITEM ===\n";
    echo "Inventory Item ID: {$inventoryItem->id}\n";
    echo "Item ID: {$inventoryItem->item_id}\n";
    echo "Small Unit ID: {$inventoryItem->small_unit_id}\n";
    echo "Medium Unit ID: {$inventoryItem->medium_unit_id}\n";
    echo "Large Unit ID: {$inventoryItem->large_unit_id}\n\n";
} else {
    echo "Inventory Item untuk item_id 53080 tidak ditemukan!\n\n";
}

// Cek stock terbaru
$latestStock = DB::table('food_inventory_stocks')
    ->where('inventory_item_id', 4771)
    ->where('warehouse_id', 1)
    ->first();

if ($latestStock) {
    echo "=== LATEST STOCK ===\n";
    echo "Stock ID: {$latestStock->id}\n";
    echo "Inventory Item ID: {$latestStock->inventory_item_id}\n";
    echo "Warehouse ID: {$latestStock->warehouse_id}\n";
    echo "Qty Small: {$latestStock->qty_small}\n";
    echo "Qty Medium: {$latestStock->qty_medium}\n";
    echo "Qty Large: {$latestStock->qty_large}\n";
    echo "Value: {$latestStock->value}\n";
    echo "Last Cost Small: {$latestStock->last_cost_small}\n";
    echo "Last Cost Medium: {$latestStock->last_cost_medium}\n";
    echo "Last Cost Large: {$latestStock->last_cost_large}\n";
    echo "Updated At: {$latestStock->updated_at}\n\n";
} else {
    echo "Stock untuk inventory_item_id 4771 dan warehouse_id 1 tidak ditemukan!\n\n";
}

// Cek stock card terbaru untuk outlet rejection
$latestStockCard = DB::table('food_inventory_cards')
    ->where('reference_type', 'outlet_rejection')
    ->where('reference_id', 5)
    ->orderBy('created_at', 'desc')
    ->first();

if ($latestStockCard) {
    echo "=== LATEST STOCK CARD (OUTLET REJECTION) ===\n";
    echo "Stock Card ID: {$latestStockCard->id}\n";
    echo "Inventory Item ID: {$latestStockCard->inventory_item_id}\n";
    echo "Warehouse ID: {$latestStockCard->warehouse_id}\n";
    echo "Date: {$latestStockCard->date}\n";
    echo "Reference Type: {$latestStockCard->reference_type}\n";
    echo "Reference ID: {$latestStockCard->reference_id}\n";
    echo "In Qty Small: {$latestStockCard->in_qty_small}\n";
    echo "In Qty Medium: {$latestStockCard->in_qty_medium}\n";
    echo "In Qty Large: {$latestStockCard->in_qty_large}\n";
    echo "Cost Per Small: {$latestStockCard->cost_per_small}\n";
    echo "Cost Per Medium: {$latestStockCard->cost_per_medium}\n";
    echo "Cost Per Large: {$latestStockCard->cost_per_large}\n";
    echo "Value In: {$latestStockCard->value_in}\n";
    echo "Saldo Qty Small: {$latestStockCard->saldo_qty_small}\n";
    echo "Saldo Qty Medium: {$latestStockCard->saldo_qty_medium}\n";
    echo "Saldo Qty Large: {$latestStockCard->saldo_qty_large}\n";
    echo "Saldo Value: {$latestStockCard->saldo_value}\n";
    echo "Description: {$latestStockCard->description}\n";
    echo "Created At: {$latestStockCard->created_at}\n\n";
} else {
    echo "Stock Card untuk outlet rejection ID 5 tidak ditemukan!\n\n";
}

// Cek cost history terbaru untuk outlet rejection
$latestCostHistory = DB::table('food_inventory_cost_histories')
    ->where('reference_type', 'outlet_rejection')
    ->where('reference_id', 5)
    ->orderBy('created_at', 'desc')
    ->first();

if ($latestCostHistory) {
    echo "=== LATEST COST HISTORY (OUTLET REJECTION) ===\n";
    echo "Cost History ID: {$latestCostHistory->id}\n";
    echo "Inventory Item ID: {$latestCostHistory->inventory_item_id}\n";
    echo "Warehouse ID: {$latestCostHistory->warehouse_id}\n";
    echo "Warehouse Division ID: {$latestCostHistory->warehouse_division_id}\n";
    echo "Date: {$latestCostHistory->date}\n";
    echo "Old Cost: {$latestCostHistory->old_cost}\n";
    echo "New Cost: {$latestCostHistory->new_cost}\n";
    echo "MAC: {$latestCostHistory->mac}\n";
    echo "Type: {$latestCostHistory->type}\n";
    echo "Reference Type: {$latestCostHistory->reference_type}\n";
    echo "Reference ID: {$latestCostHistory->reference_id}\n";
    echo "Created At: {$latestCostHistory->created_at}\n\n";
} else {
    echo "Cost History untuk outlet rejection ID 5 tidak ditemukan!\n\n";
}

// Cek item master
$itemMaster = DB::table('items')->where('id', 53080)->first();
if ($itemMaster) {
    echo "=== ITEM MASTER ===\n";
    echo "Item ID: {$itemMaster->id}\n";
    echo "Name: {$itemMaster->name}\n";
    echo "SKU: {$itemMaster->sku}\n";
    echo "Warehouse Division ID: {$itemMaster->warehouse_division_id}\n";
    echo "Small Unit ID: {$itemMaster->small_unit_id}\n";
    echo "Medium Unit ID: {$itemMaster->medium_unit_id}\n";
    echo "Large Unit ID: {$itemMaster->large_unit_id}\n";
    echo "Small Conversion Qty: {$itemMaster->small_conversion_qty}\n";
    echo "Medium Conversion Qty: {$itemMaster->medium_conversion_qty}\n\n";
} else {
    echo "Item Master ID 53080 tidak ditemukan!\n\n";
}

echo "=== VERIFIKASI SELESAI ===\n";
