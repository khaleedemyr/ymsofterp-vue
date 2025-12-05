<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Set up Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST OUTLET REJECTION FILTER ===\n\n";

// Test parameters
$outletId = 1; // Main Store
$warehouseId = 1; // Main Store

echo "Testing filter for:\n";
echo "Outlet ID: {$outletId}\n";
echo "Warehouse ID: {$warehouseId}\n\n";

// 1. Cek semua delivery orders yang ada
echo "1. SEMUA DELIVERY ORDERS:\n";
$allDeliveryOrders = DB::table('delivery_orders as do')
    ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
    ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
    ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
    ->leftJoin('outlet_food_good_receives as gr', 'do.id', '=', 'gr.delivery_order_id')
    ->leftJoin('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
    ->where('o.id_outlet', $outletId)
    ->where('pl.warehouse_division_id', $warehouseId)
    ->where('gri.remaining_qty', '>', 0)
    ->select(
        'do.id as delivery_order_id',
        'do.number as delivery_order_number',
        'gr.number as good_receive_number',
        'gri.remaining_qty'
    )
    ->get();

foreach ($allDeliveryOrders as $do) {
    echo "DO ID: {$do->delivery_order_id}, Number: {$do->delivery_order_number}, GR: {$do->good_receive_number}, Remaining: {$do->remaining_qty}\n";
}

echo "\n";

// 2. Cek outlet rejections yang sudah ada
echo "2. OUTLET REJECTIONS YANG SUDAH ADA:\n";
$existingRejections = DB::table('outlet_rejections')
    ->select('id', 'delivery_order_id', 'number', 'status')
    ->get();

foreach ($existingRejections as $rejection) {
    echo "Rejection ID: {$rejection->id}, DO ID: {$rejection->delivery_order_id}, Number: {$rejection->number}, Status: {$rejection->status}\n";
}

echo "\n";

// 3. Test filter yang sudah diperbaiki
echo "3. DELIVERY ORDERS SETELAH FILTER (TIDAK ADA OUTLET REJECTION):\n";
$filteredDeliveryOrders = DB::table('delivery_orders as do')
    ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
    ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
    ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
    ->leftJoin('outlet_food_good_receives as gr', 'do.id', '=', 'gr.delivery_order_id')
    ->leftJoin('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
    ->where('o.id_outlet', $outletId)
    ->where('pl.warehouse_division_id', $warehouseId)
    ->where('gri.remaining_qty', '>', 0)
    ->whereNotExists(function($query) {
        $query->select(DB::raw(1))
              ->from('outlet_rejections as or')
              ->whereRaw('or.delivery_order_id = do.id')
              ->where('or.status', '!=', 'cancelled');
    })
    ->select(
        'do.id as delivery_order_id',
        'do.number as delivery_order_number',
        'gr.number as good_receive_number',
        'gri.remaining_qty'
    )
    ->distinct()
    ->get();

foreach ($filteredDeliveryOrders as $do) {
    echo "DO ID: {$do->delivery_order_id}, Number: {$do->delivery_order_number}, GR: {$do->good_receive_number}, Remaining: {$do->remaining_qty}\n";
}

echo "\n";

// 4. Verifikasi filter bekerja dengan benar
echo "4. VERIFIKASI FILTER:\n";
$excludedDOs = $allDeliveryOrders->pluck('delivery_order_id')->diff($filteredDeliveryOrders->pluck('delivery_order_id'));

if ($excludedDOs->count() > 0) {
    echo "Delivery Orders yang di-exclude:\n";
    foreach ($excludedDOs as $doId) {
        $rejection = DB::table('outlet_rejections')->where('delivery_order_id', $doId)->first();
        if ($rejection) {
            echo "DO ID: {$doId} - Sudah ada rejection: {$rejection->number} (Status: {$rejection->status})\n";
        }
    }
} else {
    echo "Tidak ada delivery order yang di-exclude (semua belum ada rejection)\n";
}

echo "\n=== TEST SELESAI ===\n";
