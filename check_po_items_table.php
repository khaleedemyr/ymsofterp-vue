<?php

/**
 * Script untuk mengecek table purchase_order_food_items
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECK PO ITEMS TABLE ===\n\n";

try {
    // Cek struktur table
    echo "1. Struktur Table purchase_order_food_items:\n";
    $columns = DB::select("DESCRIBE purchase_order_food_items");
    foreach ($columns as $column) {
        echo "  - {$column->Field} ({$column->Type})";
        if ($column->Null === 'NO') echo " NOT NULL";
        if ($column->Default) echo " DEFAULT {$column->Default}";
        if ($column->Key === 'PRI') echo " PRIMARY KEY";
        echo "\n";
    }
    
    echo "\n2. Total records di purchase_order_food_items:\n";
    $totalItems = DB::table('purchase_order_food_items')->count();
    echo "  Total: {$totalItems} records\n";
    
    if ($totalItems > 0) {
        echo "\n3. Sample data (5 records terbaru):\n";
        $sampleItems = DB::table('purchase_order_food_items')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($sampleItems as $item) {
            echo "  ID: {$item->id}\n";
            echo "    - PO ID: {$item->purchase_order_food_id}\n";
            echo "    - Item ID: {$item->item_id}\n";
            echo "    - Quantity: {$item->quantity}\n";
            echo "    - Unit ID: {$item->unit_id}\n";
            echo "    - Price: {$item->price}\n";
            echo "    - Total: {$item->total}\n";
            echo "    - Source Type: {$item->source_type}\n";
            echo "    - Source ID: {$item->source_id}\n";
            echo "    - RO ID: {$item->ro_id}\n";
            echo "    - RO Number: {$item->ro_number}\n";
            echo "    - Created: {$item->created_at}\n\n";
        }
    }
    
    echo "4. Cek PO terbaru dan itemsnya:\n";
    $latestPO = DB::table('purchase_order_foods')
        ->orderBy('created_at', 'desc')
        ->first();
    
    if ($latestPO) {
        echo "  PO Terbaru: {$latestPO->number} (ID: {$latestPO->id})\n";
        
        $poItems = DB::table('purchase_order_food_items')
            ->where('purchase_order_food_id', $latestPO->id)
            ->get();
        
        echo "  Items di PO ini: {$poItems->count()} records\n";
        
        if ($poItems->count() > 0) {
            foreach ($poItems as $item) {
                echo "    - Item ID: {$item->item_id}, Qty: {$item->quantity}, Price: {$item->price}\n";
            }
        } else {
            echo "    ❌ TIDAK ADA ITEMS!\n";
        }
    }
    
    echo "\n5. Cek apakah ada PO tanpa items:\n";
    $posWithoutItems = DB::table('purchase_order_foods as po')
        ->leftJoin('purchase_order_food_items as poi', 'po.id', '=', 'poi.purchase_order_food_id')
        ->whereNull('poi.id')
        ->select('po.id', 'po.number', 'po.created_at')
        ->get();
    
    if ($posWithoutItems->count() > 0) {
        echo "  Ditemukan {$posWithoutItems->count()} PO tanpa items:\n";
        foreach ($posWithoutItems as $po) {
            echo "    - {$po->number} (ID: {$po->id}) - {$po->created_at}\n";
        }
    } else {
        echo "  ✅ Semua PO memiliki items\n";
    }
    
    echo "\n6. Cek constraint dan foreign keys:\n";
    $foreignKeys = DB::select("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'purchase_order_food_items'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    foreach ($foreignKeys as $fk) {
        echo "  - {$fk->CONSTRAINT_NAME}: {$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
