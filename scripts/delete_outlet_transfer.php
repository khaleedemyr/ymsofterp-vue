<?php

/**
 * One-off safe delete for approved outlet transfer (mirrors OutletTransferController::destroy).
 * Usage: php scripts/delete_outlet_transfer.php OT-20260701-0002 [--dry-run]
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OutletTransfer;
use App\Support\OutletInventoryCostResolver;
use Illuminate\Support\Facades\DB;

$transferNumber = $argv[1] ?? null;
$dryRun = in_array('--dry-run', $argv, true);

if (!$transferNumber) {
    echo "Usage: php scripts/delete_outlet_transfer.php <transfer_number> [--dry-run]\n";
    exit(1);
}

$transfer = OutletTransfer::with(['items'])->where('transfer_number', $transferNumber)->first();
if (!$transfer) {
    echo "Transfer {$transferNumber} not found.\n";
    exit(1);
}

echo "Deleting transfer #{$transfer->id} ({$transfer->transfer_number}), status={$transfer->status}\n";

$warehouseFrom = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_from_id)->first();
$warehouseTo = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_to_id)->first();

if (!$warehouseFrom || !$warehouseTo) {
    echo "Warehouse outlet not found.\n";
    exit(1);
}

$relatedCounts = [
    'items' => DB::table('outlet_transfer_items')->where('outlet_transfer_id', $transfer->id)->count(),
    'serial_items' => DB::table('outlet_transfer_serial_items')->where('outlet_transfer_id', $transfer->id)->count(),
    'approval_flows' => DB::table('outlet_transfer_approval_flows')->where('outlet_transfer_id', $transfer->id)->count(),
    'serial_movements' => DB::table('inventory_serial_movements')->where('outlet_transfer_id', $transfer->id)->count(),
    'inventory_cards' => DB::table('outlet_food_inventory_cards')->where('reference_type', 'outlet_transfer')->where('reference_id', $transfer->id)->count(),
    'cost_histories' => DB::table('outlet_food_inventory_cost_histories')->where('reference_type', 'outlet_transfer')->where('reference_id', $transfer->id)->count(),
];

echo 'Related rows: ' . json_encode($relatedCounts) . "\n";

if ($dryRun) {
    echo "DRY RUN — no changes made.\n";
    exit(0);
}

DB::beginTransaction();
try {
    // Rollback serial items
    $serialItems = DB::table('outlet_transfer_serial_items')
        ->where('outlet_transfer_id', $transfer->id)
        ->get();

    foreach ($serialItems as $si) {
        $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $si->item_id)->first();
        if (!$inventoryItem) {
            continue;
        }
        $inventoryItemId = $inventoryItem->id;

        $stockFrom = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $warehouseFrom->outlet_id)
            ->where('warehouse_outlet_id', $transfer->warehouse_outlet_from_id)
            ->first();
        if ($stockFrom) {
            $qtyAfter = (float) $stockFrom->qty_small + (float) $si->qty_small;
            $mac = OutletInventoryCostResolver::resolveMacFromStockRow($stockFrom);
            DB::table('outlet_food_inventory_stocks')->where('id', $stockFrom->id)->update([
                'qty_small' => $stockFrom->qty_small + $si->qty_small,
                'qty_medium' => $stockFrom->qty_medium + $si->qty_medium,
                'qty_large' => $stockFrom->qty_large + $si->qty_large,
                'value' => OutletInventoryCostResolver::stockTotalValue($qtyAfter, $mac),
                'updated_at' => now(),
            ]);
        }

        $stockTo = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $warehouseTo->outlet_id)
            ->where('warehouse_outlet_id', $transfer->warehouse_outlet_to_id)
            ->first();
        if ($stockTo) {
            $qtyAfter = (float) $stockTo->qty_small - (float) $si->qty_small;
            $mac = OutletInventoryCostResolver::resolveMacFromStockRow($stockTo);
            DB::table('outlet_food_inventory_stocks')->where('id', $stockTo->id)->update([
                'qty_small' => $stockTo->qty_small - $si->qty_small,
                'qty_medium' => $stockTo->qty_medium - $si->qty_medium,
                'qty_large' => $stockTo->qty_large - $si->qty_large,
                'value' => OutletInventoryCostResolver::stockTotalValue(max(0, $qtyAfter), $mac),
                'updated_at' => now(),
            ]);
        }

        DB::table('inventory_item_serials')->where('id', $si->serial_id)->update([
            'is_transferred' => 0,
            'transferred_at' => null,
            'transfer_id' => null,
            'transfer_from_outlet_id' => null,
            'transfer_to_outlet_id' => null,
            'transfer_to_warehouse_outlet_id' => null,
        ]);
    }

    DB::table('inventory_serial_movements')->where('outlet_transfer_id', $transfer->id)->delete();
    DB::table('outlet_transfer_serial_items')->where('outlet_transfer_id', $transfer->id)->delete();

    foreach ($transfer->items as $item) {
        $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item->item_id)->first();
        if (!$inventoryItem) {
            continue;
        }
        $inventoryItemId = $inventoryItem->id;
        $qtySmall = (float) ($item->qty_small ?? 0);
        $qtyMedium = (float) ($item->qty_medium ?? 0);
        $qtyLarge = (float) ($item->qty_large ?? 0);

        $stockFrom = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $warehouseFrom->outlet_id)
            ->where('warehouse_outlet_id', $transfer->warehouse_outlet_from_id)
            ->first();
        if ($stockFrom) {
            $qtyAfter = (float) $stockFrom->qty_small + $qtySmall;
            $mac = OutletInventoryCostResolver::resolveMacFromStockRow($stockFrom);
            DB::table('outlet_food_inventory_stocks')->where('id', $stockFrom->id)->update([
                'qty_small' => $stockFrom->qty_small + $qtySmall,
                'qty_medium' => $stockFrom->qty_medium + $qtyMedium,
                'qty_large' => $stockFrom->qty_large + $qtyLarge,
                'value' => OutletInventoryCostResolver::stockTotalValue($qtyAfter, $mac),
                'updated_at' => now(),
            ]);
            echo "Rollback FROM stock wh {$transfer->warehouse_outlet_from_id}: +{$qtySmall} small\n";
        }

        $stockTo = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $warehouseTo->outlet_id)
            ->where('warehouse_outlet_id', $transfer->warehouse_outlet_to_id)
            ->first();
        if ($stockTo) {
            $qtyAfter = (float) $stockTo->qty_small - $qtySmall;
            $mac = OutletInventoryCostResolver::resolveMacFromStockRow($stockTo);
            DB::table('outlet_food_inventory_stocks')->where('id', $stockTo->id)->update([
                'qty_small' => $stockTo->qty_small - $qtySmall,
                'qty_medium' => $stockTo->qty_medium - $qtyMedium,
                'qty_large' => $stockTo->qty_large - $qtyLarge,
                'value' => OutletInventoryCostResolver::stockTotalValue(max(0, $qtyAfter), $mac),
                'updated_at' => now(),
            ]);
            echo "Rollback TO stock wh {$transfer->warehouse_outlet_to_id}: -{$qtySmall} small\n";
        }
    }

    DB::table('outlet_food_inventory_cards')
        ->where('reference_type', 'outlet_transfer')
        ->where('reference_id', $transfer->id)
        ->delete();

    DB::table('outlet_food_inventory_cost_histories')
        ->where('reference_type', 'outlet_transfer')
        ->where('reference_id', $transfer->id)
        ->delete();

    DB::table('outlet_transfer_approval_flows')->where('outlet_transfer_id', $transfer->id)->delete();
    DB::table('outlet_transfer_items')->where('outlet_transfer_id', $transfer->id)->delete();
    DB::table('outlet_transfers')->where('id', $transfer->id)->delete();

    // Optional: clean notifications mentioning this transfer number
    DB::table('notifications')
        ->where('message', 'like', '%' . $transferNumber . '%')
        ->delete();

    DB::commit();
    echo "SUCCESS: {$transferNumber} deleted with inventory rollback.\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo 'FAILED: ' . $e->getMessage() . "\n";
    exit(1);
}
