<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$outletId = 18;
$whId = 41;
$itemId = 52984;
$invItemId = 5080;

echo "=== Fix script: serial table + movements without crashing ===\n";
foreach (['item_serial_numbers', 'serials', 'food_serials', 'warehouse_item_serials', 'item_serial_stocks'] as $t) {
    if (Schema::hasTable($t)) {
        echo "has {$t}\n";
    }
}

// Find serial by id from movement
$mov = DB::table('inventory_serial_movements')->where('id', 429412)->first();
echo "serial_id={$mov->serial_id}\n";

// Try common serial master tables with id
foreach (Schema::getAllTables() as $table) {
    // skip - too heavy on some drivers
}
$tables = DB::select('SHOW TABLES');
$key = 'Tables_in_db_justus';
$serialTables = [];
foreach ($tables as $t) {
    $name = $t->$key ?? array_values((array)$t)[0];
    if (stripos($name, 'serial') !== false) {
        $serialTables[] = $name;
    }
}
echo "serial-related tables: " . implode(', ', $serialTables) . "\n";

echo "\n=== A) activity log for stock cut / stock updates 19 Nov ===\n";
if (Schema::hasTable('activity_log')) {
    $cols = Schema::getColumnListing('activity_log');
    echo "activity_log cols: " . implode(',', $cols) . "\n";
    $q = DB::table('activity_log')
        ->whereBetween('created_at', ['2026-11-19 00:00:00', '2026-11-19 23:59:59'])
        ->where(function ($q) {
            $q->where('description', 'like', '%Stock Cut%')
              ->orWhere('description', 'like', '%stock%')
              ->orWhere('subject_type', 'like', '%StockCut%');
        })
        ->orderBy('id');
    // also filter outlet if properties has it
    $rows = $q->limit(50)->get();
    echo "activity rows=" . $rows->count() . "\n";
    foreach ($rows as $r) {
        $props = is_string($r->properties ?? null) ? $r->properties : json_encode($r->properties ?? null);
        if (strpos($props, '18') === false && strpos((string)($r->description ?? ''), '18') === false) {
            // still show stock cut related
        }
        if (stripos($props, '"outlet_id":18') !== false
            || stripos($props, 'Buah Batu') !== false
            || stripos((string)$r->description, 'Stock Cut') !== false) {
            echo substr(json_encode($r), 0, 400) . "\n";
        }
    }
}

echo "\n=== B) Search for ANY card OUT of 1200 or 8 pcs on 19 Nov for this item ALL outlets ===\n";
$all = DB::table('outlet_food_inventory_cards as c')
    ->join('outlet_food_inventory_items as fi', 'fi.id', '=', 'c.inventory_item_id')
    ->where('fi.item_id', $itemId)
    ->whereDate('c.date', '2026-11-19')
    ->orderBy('c.id')
    ->select('c.*')
    ->get();
foreach ($all as $c) {
    echo sprintf(
        "id=%d outlet=%s wh=%s ref=%s in=%s out=%s saldo=%s created=%s\n",
        $c->id, $c->id_outlet, $c->warehouse_outlet_id, $c->reference_type,
        $c->in_qty_small, $c->out_qty_small, $c->saldo_qty_small, $c->created_at
    );
}

echo "\n=== C) Check stock_cut failed attempts / duplicate logs 19 Nov ===\n";
$logs = DB::table('stock_cut_logs')
    ->where('outlet_id', $outletId)
    ->where('tanggal', '2026-11-19')
    ->get();
foreach ($logs as $l) echo json_encode($l) . "\n";

echo "\n=== D) order_items sales qty for BOM parents on 19 Nov outlet 18 ===\n";
$qr = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('qr_code');
echo "qr_code={$qr}\n";

$parents = DB::table('item_bom')
    ->where('material_item_id', $itemId)
    ->where(function ($q) {
        $q->whereNull('stock_cut')->orWhere('stock_cut', 0);
    })
    ->get();

$totalBom = 0;
foreach ($parents as $p) {
    $sold = DB::table('order_items')
        ->where('kode_outlet', $qr)
        ->whereDate('created_at', '2026-11-19')
        ->where('id_item', $p->item_id) // might be item_id or menu_item_id
        ->sum('qty');
    // try alternate column names
    if ($sold == 0) {
        $oiCols = Schema::getColumnListing('order_items');
        $itemCol = in_array('item_id', $oiCols) ? 'item_id' : (in_array('id_menu', $oiCols) ? 'id_menu' : null);
        $qtyCol = in_array('qty', $oiCols) ? 'qty' : (in_array('quantity', $oiCols) ? 'quantity' : null);
        if ($itemCol && $qtyCol) {
            $sold = DB::table('order_items')
                ->where('kode_outlet', $qr)
                ->whereDate('created_at', '2026-11-19')
                ->where($itemCol, $p->item_id)
                ->sum($qtyCol);
        }
        echo "order_items cols sample: " . implode(',', array_slice($oiCols, 0, 25)) . "\n";
    }
    $name = DB::table('items')->where('id', $p->item_id)->value('name');
    $need = (float)$sold * (float)$p->qty;
    $totalBom += $need;
    if ($sold > 0 || true) {
        echo sprintf("menu=%s(%d) sold=%s bom_qty=%s need_g=%s\n", $name, $p->item_id, $sold, $p->qty, $need);
    }
}
echo "total BOM grams from order_items direct={$totalBom}\n";

echo "\n=== E) Did serial receive stock update use DB::raw with string that could fail silently? ===\n";
// Check if qty was ever stored as medium-only incorrectly in stock history - can't.
// Compare: after receive, cost history mac used qtyLama from stock.
// If we can infer qtyLama from MAC formula when costs differ...
// costs are both ~430, not useful.

echo "\n=== F) Check warehouse food_inventory card for item 52984 on DO 76857 ===\n";
$fi = DB::table('food_inventory_items')->where('item_id', $itemId)->value('id');
echo "fi={$fi}\n";
$fc = DB::table('food_inventory_cards')
    ->where('reference_type', 'delivery_order')
    ->where('reference_id', 76857)
    ->where('inventory_item_id', $fi)
    ->get();
foreach ($fc as $c) echo json_encode($c) . "\n";

echo "\n=== G) Hypothesis double-cut without card: search laravel log files for 5080 / 3150 / Potong Stock on 2026-11-19 ===\n";
$logFiles = array_merge(
    glob(storage_path('logs/laravel-2026-11-19*.log')) ?: [],
    glob(storage_path('logs/laravel.log')) ?: []
);
echo "log files: " . implode(', ', array_map('basename', $logFiles)) . "\n";

foreach ($logFiles as $file) {
    if (!is_file($file)) continue;
    $size = filesize($file);
    echo basename($file) . " size={$size}\n";
    if ($size > 200_000_000) {
        echo "too large, using rg\n";
        continue;
    }
}

echo "DONE partial\n";
