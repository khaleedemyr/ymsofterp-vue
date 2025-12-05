<?php

/**
 * Script untuk debug masalah PO generation
 * Cek apakah items tersimpan ke table purchase_order_food_items
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG PO GENERATION ===\n\n";

try {
    // Cek PO terbaru
    $latestPO = DB::table('purchase_order_foods')
        ->orderBy('created_at', 'desc')
        ->first();
    
    if (!$latestPO) {
        echo "Tidak ada PO ditemukan!\n";
        exit;
    }
    
    echo "PO Terbaru:\n";
    echo "ID: {$latestPO->id}\n";
    echo "Number: {$latestPO->number}\n";
    echo "Supplier ID: {$latestPO->supplier_id}\n";
    echo "Status: {$latestPO->status}\n";
    echo "Created: {$latestPO->created_at}\n";
    echo "Source Type: {$latestPO->source_type}\n";
    echo "Source ID: {$latestPO->source_id}\n\n";
    
    // Cek items di PO ini
    $poItems = DB::table('purchase_order_food_items')
        ->where('purchase_order_food_id', $latestPO->id)
        ->get();
    
    echo "Items di PO {$latestPO->number}:\n";
    if ($poItems->count() == 0) {
        echo "❌ TIDAK ADA ITEMS TERSIMPAN!\n\n";
        
        // Cek apakah ada data di request yang dikirim
        echo "=== CEK DATA REQUEST ===\n";
        
        // Cek log terbaru untuk melihat data yang dikirim
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logs = file_get_contents($logFile);
            $lines = explode("\n", $logs);
            $recentLogs = array_slice($lines, -100); // Ambil 100 baris terakhir
            
            foreach ($recentLogs as $line) {
                if (strpos($line, 'Generate PO Request Data:') !== false || 
                    strpos($line, 'Creating PO item:') !== false ||
                    strpos($line, 'PO item created successfully:') !== false) {
                    echo $line . "\n";
                }
            }
        }
        
    } else {
        echo "✅ Ditemukan {$poItems->count()} items:\n\n";
        
        foreach ($poItems as $item) {
            echo "Item ID: {$item->id}\n";
            echo "  - PR Food Item ID: {$item->pr_food_item_id}\n";
            echo "  - Item ID: {$item->item_id}\n";
            echo "  - Quantity: {$item->quantity}\n";
            echo "  - Unit ID: {$item->unit_id}\n";
            echo "  - Price: {$item->price}\n";
            echo "  - Total: {$item->total}\n";
            echo "  - Source Type: {$item->source_type}\n";
            echo "  - Source ID: {$item->source_id}\n";
            echo "  - RO ID: {$item->ro_id}\n";
            echo "  - RO Number: {$item->ro_number}\n";
            echo "  - Arrival Date: {$item->arrival_date}\n\n";
        }
    }
    
    // Cek struktur table
    echo "=== CEK STRUKTUR TABLE ===\n";
    
    $columns = DB::select("DESCRIBE purchase_order_food_items");
    echo "Kolom di table purchase_order_food_items:\n";
    foreach ($columns as $column) {
        echo "  - {$column->Field} ({$column->Type})\n";
    }
    
    echo "\n=== CEK DATA SAMPLE ===\n";
    
    // Cek beberapa PO terbaru
    $recentPOs = DB::table('purchase_order_foods')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    echo "5 PO terbaru:\n";
    foreach ($recentPOs as $po) {
        $itemCount = DB::table('purchase_order_food_items')
            ->where('purchase_order_food_id', $po->id)
            ->count();
        
        echo "  - {$po->number}: {$itemCount} items (Status: {$po->status})\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
