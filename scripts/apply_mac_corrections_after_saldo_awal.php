<?php
/**
 * Koreksi MAC stok + cost history untuk partisi yang mismatch setelah saldo awal.
 *
 * Usage:
 *   php scripts/apply_mac_corrections_after_saldo_awal.php --date=2026-07-01
 *   php scripts/apply_mac_corrections_after_saldo_awal.php --date=2026-07-01 --apply
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\OutletInventoryCostResolver;
use Illuminate\Support\Facades\DB;

$opts = getopt('', ['date::', 'apply', 'tolerance::']);
$cutoverDate = $opts['date'] ?? date('Y-m-d');
$apply = array_key_exists('apply', $opts);
$tolerancePct = isset($opts['tolerance']) ? (float) $opts['tolerance'] : 1.0;

const INBOUND_CARD_REFS = [
    'initial_balance',
    'good_receive_outlet',
    'good_receive_supplier',
    'good_receive_outlet_supplier',
    'retail_food',
    'serial_receive',
    'internal_warehouse_transfer',
    'outlet_transfer',
    'mac_correction',
];

echo '=== Apply MAC corrections after saldo awal ===' . PHP_EOL;
echo "Date: {$cutoverDate}" . PHP_EOL;
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL . PHP_EOL;

$candidates = DB::table('outlet_food_inventory_cost_histories as ib')
    ->join('outlet_food_inventory_cost_histories as aft', function ($join) {
        $join->on('aft.inventory_item_id', '=', 'ib.inventory_item_id')
            ->on('aft.id_outlet', '=', 'ib.id_outlet')
            ->on('aft.warehouse_outlet_id', '=', 'ib.warehouse_outlet_id')
            ->whereIn('aft.reference_type', [
                'good_receive_outlet',
                'good_receive_supplier',
                'good_receive_outlet_supplier',
                'retail_food',
                'serial_receive',
                'internal_warehouse_transfer',
                'outlet_transfer',
                'mac_correction',
            ])
            ->whereRaw('(aft.date > ib.date OR (DATE(aft.date) = DATE(ib.date) AND aft.id > ib.id))');
    })
    ->join('outlet_food_inventory_items as ofii', 'ofii.id', '=', 'ib.inventory_item_id')
    ->join('items as i', 'i.id', '=', 'ofii.item_id')
    ->where('ib.reference_type', 'initial_balance')
    ->whereDate('ib.date', $cutoverDate)
    ->select([
        'ib.id as ib_hist_id',
        'ib.inventory_item_id',
        'ib.id_outlet',
        'ib.warehouse_outlet_id',
        'i.name as item_name',
        'i.id as item_id',
    ])
    ->distinct()
    ->get();

$fixes = [];

foreach ($candidates as $ib) {
    $ibCard = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $ib->inventory_item_id)
        ->where('id_outlet', $ib->id_outlet)
        ->where('warehouse_outlet_id', $ib->warehouse_outlet_id)
        ->where('reference_type', 'initial_balance')
        ->whereDate('date', $cutoverDate)
        ->orderBy('id')
        ->value('id');

    $cards = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $ib->inventory_item_id)
        ->where('id_outlet', $ib->id_outlet)
        ->where('warehouse_outlet_id', $ib->warehouse_outlet_id)
        ->whereDate('date', '>=', $cutoverDate)
        ->when($ibCard, fn ($q) => $q->where('id', '>=', $ibCard))
        ->orderBy('date')
        ->orderBy('id')
        ->get(['id', 'reference_type', 'reference_id', 'in_qty_small', 'out_qty_small', 'cost_per_small']);

    $qtySim = 0.0;
    $macSim = 0.0;
    foreach ($cards as $card) {
        $inQty = (float) ($card->in_qty_small ?? 0);
        $outQty = (float) ($card->out_qty_small ?? 0);
        $costIn = (float) ($card->cost_per_small ?? 0);
        if ($inQty > 0 && in_array($card->reference_type, INBOUND_CARD_REFS, true)) {
            $macSim = OutletInventoryCostResolver::weightedAverageMacPerSmall($qtySim, $macSim, $inQty, $costIn);
            $qtySim += $inQty;
        } elseif ($outQty > 0) {
            $qtySim = max(0.0, $qtySim - $outQty);
        }
    }

    $stock = DB::table('outlet_food_inventory_stocks')
        ->where('inventory_item_id', $ib->inventory_item_id)
        ->where('id_outlet', $ib->id_outlet)
        ->where('warehouse_outlet_id', $ib->warehouse_outlet_id)
        ->first();

    if (! $stock || $macSim <= 0) {
        continue;
    }

    $stockMac = (float) $stock->last_cost_small;
    $diffPct = abs($stockMac - $macSim) / $macSim * 100;
    if ($diffPct <= $tolerancePct) {
        continue;
    }

    $itemMaster = DB::table('items')->where('id', $ib->item_id)->first();
    if (! $itemMaster) {
        continue;
    }

    [$macSmall, $macMedium, $macLarge] = OutletInventoryCostResolver::macRatesPerSmallMediumLarge($macSim, $itemMaster);
    $newValue = OutletInventoryCostResolver::stockTotalValue((float) $stock->qty_small, $macSmall);

    $fixes[] = [
        'inventory_item_id' => (int) $ib->inventory_item_id,
        'id_outlet' => (int) $ib->id_outlet,
        'warehouse_outlet_id' => (int) $ib->warehouse_outlet_id,
        'item_name' => $ib->item_name,
        'stock_id' => (int) $stock->id,
        'mac_from' => round($stockMac, 4),
        'mac_to' => round($macSmall, 4),
        'diff_pct' => round($diffPct, 2),
        'mac_medium' => round($macMedium, 4),
        'mac_large' => round($macLarge, 4),
        'value_to' => round($newValue, 2),
    ];
}

if ($fixes === []) {
    echo "Tidak ada partisi yang perlu koreksi MAC.\n";
    exit(0);
}

echo 'Ditemukan ' . count($fixes) . " partisi perlu koreksi:\n\n";
foreach ($fixes as $f) {
    echo sprintf(
        "%s | outlet=%d wh=%d | MAC %.4f -> %.4f (%.2f%%) | value -> %.2f\n",
        $f['item_name'],
        $f['id_outlet'],
        $f['warehouse_outlet_id'],
        $f['mac_from'],
        $f['mac_to'],
        $f['diff_pct'],
        $f['value_to']
    );
}

if (! $apply) {
    echo "\nJalankan dengan --apply untuk menyimpan.\n";
    exit(0);
}

DB::transaction(function () use ($fixes, $cutoverDate) {
    $today = $cutoverDate;
    foreach ($fixes as $f) {
        DB::table('outlet_food_inventory_stocks')
            ->where('id', $f['stock_id'])
            ->update([
                'last_cost_small' => $f['mac_to'],
                'last_cost_medium' => $f['mac_medium'],
                'last_cost_large' => $f['mac_large'],
                'value' => $f['value_to'],
                'updated_at' => now(),
            ]);

        $latestHist = DB::table('outlet_food_inventory_cost_histories')
            ->where('inventory_item_id', $f['inventory_item_id'])
            ->where('id_outlet', $f['id_outlet'])
            ->where('warehouse_outlet_id', $f['warehouse_outlet_id'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['id', 'mac']);

        if ($latestHist && abs((float) ($latestHist->mac ?? 0) - (float) $f['mac_to']) > 0.0001) {
            DB::table('outlet_food_inventory_cost_histories')
                ->where('id', $latestHist->id)
                ->update(['mac' => $f['mac_to']]);
        }

        DB::table('outlet_food_inventory_cost_histories')->insert([
            'inventory_item_id' => $f['inventory_item_id'],
            'id_outlet' => $f['id_outlet'],
            'warehouse_outlet_id' => $f['warehouse_outlet_id'],
            'date' => $today,
            'old_cost' => $f['mac_from'],
            'new_cost' => $f['mac_to'],
            'mac' => $f['mac_to'],
            'type' => 'mac_correction',
            'reference_type' => 'mac_correction',
            'reference_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
});

echo "\nBerhasil koreksi " . count($fixes) . " partisi.\n";
