<?php

/**
 * Perbaiki MAC anomali Sauce Chilli Botol (item 52537 / inventory 4673).
 *
 * Usage:
 *   php scripts/fix_sauce_chilli_mac.php           # dry-run
 *   php scripts/fix_sauce_chilli_mac.php --apply   # commit ke DB
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FoodGrLastPurchaseForItem;
use App\Support\OutletInventoryCostResolver;
use Illuminate\Support\Facades\DB;

$itemId = 52537;
$apply = in_array('--apply', $argv ?? [], true);

$item = DB::table('items')->where('id', $itemId)->first();
$inv = DB::table('outlet_food_inventory_items')->where('item_id', $itemId)->first();
if (! $item || ! $inv) {
    echo "Item atau inventory item tidak ditemukan.\n";
    exit(1);
}

$gr = FoodGrLastPurchaseForItem::lastLine($itemId);
$baselineMac = $gr ? round((float) $gr['cost_small'] * 1.12, 4) : 51.8209;
$macFloor = $gr ? (float) $gr['cost_small'] * 0.9 : 40.0;
$macCeiling = 120.0;

echo "=== Fix MAC: {$item->name} (item={$itemId}, inv={$inv->id}) ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo "Baseline MAC per ml (GR+12%): {$baselineMac}\n";
echo "Valid range: {$macFloor} – {$macCeiling}\n\n";

$smallConv = (float) ($item->small_conversion_qty ?: 1);
$mediumConv = (float) ($item->medium_conversion_qty ?: 1);

function resolveCorrectMac(int $outletId, int $warehouseId, int $inventoryItemId, float $baselineMac, float $macFloor, float $macCeiling): float
{
    $hist = DB::table('outlet_food_inventory_cost_histories')
        ->where('inventory_item_id', $inventoryItemId)
        ->where('id_outlet', $outletId)
        ->where('warehouse_outlet_id', $warehouseId)
        ->whereNotNull('new_cost')
        ->where('new_cost', '>', 0)
        ->whereBetween('new_cost', [$macFloor, $macCeiling])
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first(['new_cost']);

    if ($hist) {
        return round((float) $hist->new_cost, 4);
    }

    $peer = DB::table('outlet_food_inventory_stocks')
        ->where('inventory_item_id', $inventoryItemId)
        ->where('id_outlet', $outletId)
        ->whereBetween('last_cost_small', [$macFloor, $macCeiling])
        ->orderBy('last_cost_small')
        ->value('last_cost_small');

    if ($peer !== null) {
        return round((float) $peer, 4);
    }

    return $baselineMac;
}

function macLooksAnomalous(float $mac, float $macFloor, float $macCeiling): bool
{
    if ($mac <= 0) {
        return false;
    }

    return $mac < $macFloor || $mac > $macCeiling;
}

$stocks = DB::table('outlet_food_inventory_stocks as s')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 's.id_outlet')
    ->where('s.inventory_item_id', $inv->id)
    ->orderBy('s.id_outlet')
    ->orderBy('s.warehouse_outlet_id')
    ->get(['s.*', 'o.nama_outlet']);

$toFix = [];
foreach ($stocks as $stock) {
    $current = (float) $stock->last_cost_small;
    if (! macLooksAnomalous($current, $macFloor, $macCeiling)) {
        continue;
    }

    $correct = resolveCorrectMac(
        (int) $stock->id_outlet,
        (int) $stock->warehouse_outlet_id,
        (int) $inv->id,
        $baselineMac,
        $macFloor,
        $macCeiling
    );

    $qtySmall = (float) $stock->qty_small;
    $newValue = OutletInventoryCostResolver::stockTotalValue($qtySmall, $correct);
    $newMedium = $smallConv > 0 ? $correct * $smallConv : $correct;
    $newLarge = ($smallConv > 0 && $mediumConv > 0) ? $correct * $smallConv * $mediumConv : $correct;

    $toFix[] = [
        'stock' => $stock,
        'current' => $current,
        'correct' => $correct,
        'new_value' => $newValue,
        'new_medium' => round($newMedium, 4),
        'new_large' => round($newLarge, 4),
    ];
}

if ($toFix === []) {
    echo "Tidak ada stok dengan MAC anomali.\n";
    exit(0);
}

echo 'Ditemukan ' . count($toFix) . " baris stok perlu diperbaiki:\n\n";
foreach ($toFix as $row) {
    $s = $row['stock'];
    $ratio = $row['current'] > 0 ? round($row['current'] / $row['correct'], 1) : '-';
    echo sprintf(
        "outlet=%d %s wh=%d | MAC %.4f -> %.4f (%sx) | qty_small=%s | value %.2f -> %.2f\n",
        $s->id_outlet,
        $s->nama_outlet,
        $s->warehouse_outlet_id,
        $row['current'],
        $row['correct'],
        $ratio,
        $s->qty_small,
        (float) $s->value,
        $row['new_value']
    );
}

if (! $apply) {
    echo "\nJalankan dengan --apply untuk menyimpan perubahan.\n";
    exit(0);
}

DB::transaction(function () use ($toFix, $inv, $item) {
    $today = now()->toDateString();
    foreach ($toFix as $row) {
        $s = $row['stock'];
        DB::table('outlet_food_inventory_stocks')
            ->where('id', $s->id)
            ->update([
                'last_cost_small' => $row['correct'],
                'last_cost_medium' => $row['new_medium'],
                'last_cost_large' => $row['new_large'],
                'value' => $row['new_value'],
                'updated_at' => now(),
            ]);

        $lastHist = DB::table('outlet_food_inventory_cost_histories')
            ->where('inventory_item_id', $inv->id)
            ->where('id_outlet', $s->id_outlet)
            ->where('warehouse_outlet_id', $s->warehouse_outlet_id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        DB::table('outlet_food_inventory_cost_histories')->insert([
            'inventory_item_id' => $inv->id,
            'id_outlet' => $s->id_outlet,
            'warehouse_outlet_id' => $s->warehouse_outlet_id,
            'date' => $today,
            'old_cost' => $lastHist ? $lastHist->new_cost : $row['current'],
            'new_cost' => $row['correct'],
            'mac' => $row['correct'],
            'type' => 'mac_correction',
            'reference_type' => 'mac_correction',
            'reference_id' => null,
            'created_at' => now(),
        ]);
    }
});

echo "\nBerhasil memperbaiki " . count($toFix) . " baris stok.\n";

// Simulasi subtotal CIU draft 28583
$draft = DB::table('outlet_internal_use_waste_headers')->where('id', 28583)->first();
if ($draft) {
    $stock = DB::table('outlet_food_inventory_stocks')
        ->where('inventory_item_id', $inv->id)
        ->where('id_outlet', $draft->outlet_id)
        ->where('warehouse_outlet_id', $draft->warehouse_outlet_id)
        ->first();
    echo "\nDraft CIU 28583 warehouse {$draft->warehouse_outlet_id} MAC sekarang: " . ($stock->last_cost_small ?? 'n/a') . "\n";
    echo "9380 ml × MAC = " . number_format(9380 * (float) ($stock->last_cost_small ?? 0), 2) . "\n";
}
