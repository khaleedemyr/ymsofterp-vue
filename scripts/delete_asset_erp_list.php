<?php

/**
 * One-off delete for asset ERP test data (List Hapus ERP).
 * Usage: php scripts/delete_asset_erp_list.php [--dry-run]
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AssetInventoryStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

$dryRun = in_array('--dry-run', $argv, true);

function logLine(string $msg): void
{
    echo $msg . PHP_EOL;
}

function convertItemQty(int $itemId, ?int $unitId, float $qty): array
{
    $itemMaster = DB::table('items')->where('id', $itemId)->first();
    if (!$itemMaster) {
        return ['qty_small' => $qty, 'qty_medium' => 0, 'qty_large' => 0];
    }

    $smallConv = $itemMaster->small_conversion_qty ?: 1;
    $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
    $unitId = $unitId ?? $itemMaster->small_unit_id;

    if ($unitId == $itemMaster->small_unit_id) {
        $qtySmall = $qty;
        $qtyMedium = $mediumConv > 0 ? $qty / $mediumConv : 0;
        $qtyLarge = $smallConv > 0 ? $qty / $smallConv : 0;
    } elseif ($unitId == $itemMaster->medium_unit_id) {
        $qtyMedium = $qty;
        $qtySmall = $qty * $mediumConv;
        $qtyLarge = $smallConv > 0 ? ($qty * $mediumConv) / $smallConv : 0;
    } elseif ($unitId == $itemMaster->large_unit_id) {
        $qtyLarge = $qty;
        $qtySmall = $qty * $smallConv;
        $qtyMedium = $mediumConv > 0 ? ($qty * $smallConv) / $mediumConv : 0;
    } else {
        $qtySmall = $qty;
        $qtyMedium = $mediumConv > 0 ? $qty / $mediumConv : 0;
        $qtyLarge = $smallConv > 0 ? $qty / $smallConv : 0;
    }

    return [
        'qty_small' => (float) $qtySmall,
        'qty_medium' => (float) $qtyMedium,
        'qty_large' => (float) $qtyLarge,
    ];
}

function convertAdjustmentQty(object $itemMaster, string $selectedUnit, float $qty): array
{
    $smallConv = $itemMaster->small_conversion_qty ?: 1;
    $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

    $smallUnitName = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
    $mediumUnitName = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
    $largeUnitName = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');

    if ($selectedUnit == $smallUnitName || (!$mediumUnitName && !$largeUnitName)) {
        $qtySmall = $qty;
        $qtyMedium = $mediumConv > 0 ? $qty / $mediumConv : 0;
        $qtyLarge = $smallConv > 0 ? $qty / $smallConv : 0;
    } elseif ($selectedUnit == $mediumUnitName) {
        $qtyMedium = $qty;
        $qtySmall = $qty * $mediumConv;
        $qtyLarge = $smallConv > 0 ? ($qty * $mediumConv) / $smallConv : 0;
    } elseif ($selectedUnit == $largeUnitName) {
        $qtyLarge = $qty;
        $qtySmall = $qty * $smallConv;
        $qtyMedium = $mediumConv > 0 ? ($qty * $smallConv) / $mediumConv : 0;
    } else {
        $qtySmall = $qty;
        $qtyMedium = $mediumConv > 0 ? $qty / $mediumConv : 0;
        $qtyLarge = $smallConv > 0 ? $qty / $smallConv : 0;
    }

    return [
        'qty_small' => (float) $qtySmall,
        'qty_medium' => (float) $qtyMedium,
        'qty_large' => (float) $qtyLarge,
    ];
}

function inventoryItemIdFor(int $itemId): ?int
{
    $row = DB::table('asset_inventory_items')->where('item_id', $itemId)->first();

    return $row ? (int) $row->id : null;
}

function applyStockDelta(
    int $inventoryItemId,
    int $ownerOutletId,
    ?int $warehouseOutletId,
    float $deltaSmall,
    float $deltaMedium,
    float $deltaLarge,
    bool $dryRun,
    string $label
): void {
    $stock = AssetInventoryStockService::findStock($inventoryItemId, $ownerOutletId, $warehouseOutletId);
    if (!$stock) {
        logLine("  WARN {$label}: stock not found (delta {$deltaSmall})");
        return;
    }

    $cost = (float) ($stock->last_cost_small ?? 0);
    $newSmall = (float) $stock->qty_small + $deltaSmall;
    $newMedium = (float) $stock->qty_medium + $deltaMedium;
    $newLarge = (float) $stock->qty_large + $deltaLarge;

    logLine(sprintf(
        '  %s stock#%d: %.4f -> %.4f small',
        $label,
        $stock->id,
        (float) $stock->qty_small,
        $newSmall
    ));

    if ($dryRun) {
        return;
    }

    if ($newSmall <= 0 && $newMedium <= 0 && $newLarge <= 0) {
        DB::table('asset_inventory_stocks')->where('id', $stock->id)->delete();
        return;
    }

    DB::table('asset_inventory_stocks')->where('id', $stock->id)->update([
        'qty_small' => max(0, $newSmall),
        'qty_medium' => max(0, $newMedium),
        'qty_large' => max(0, $newLarge),
        'value' => max(0, $newSmall) * $cost,
        'updated_at' => now(),
    ]);
}

function deleteInventoryReferences(string $referenceType, int $referenceId, bool $dryRun): void
{
    logLine("  delete cards/cost_hist for {$referenceType}#{$referenceId}");
    if ($dryRun) {
        return;
    }
    DB::table('asset_inventory_cards')
        ->where('reference_type', $referenceType)
        ->where('reference_id', $referenceId)
        ->delete();
    DB::table('asset_inventory_cost_histories')
        ->where('reference_type', $referenceType)
        ->where('reference_id', $referenceId)
        ->delete();
}

function rollbackDisposal(int $id, bool $dryRun): void
{
    $disposal = DB::table('asset_disposals')->where('id', $id)->first();
    if (!$disposal) {
        logLine("ADP id={$id} not found");
        return;
    }
    if ($disposal->status !== 'approved') {
        logLine("ADP {$disposal->number} skipped rollback (status={$disposal->status})");
        return;
    }

    logLine("Rollback ADP {$disposal->number}");
    $items = DB::table('asset_disposal_items')->where('disposal_id', $id)->get();
    foreach ($items as $item) {
        $inventoryItemId = inventoryItemIdFor((int) $item->item_id);
        if (!$inventoryItemId) {
            continue;
        }
        $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
        $converted = convertAdjustmentQty($itemMaster, (string) $item->unit, (float) $item->qty);
        applyStockDelta(
            $inventoryItemId,
            (int) $disposal->owner_outlet_id,
            (int) $disposal->warehouse_outlet_id,
            $converted['qty_small'],
            $converted['qty_medium'],
            $converted['qty_large'],
            $dryRun,
            "ADP {$disposal->number} item#{$item->item_id}"
        );
    }
    deleteInventoryReferences('asset_disposal', $id, $dryRun);
}

function deleteDisposal(int $id, bool $dryRun): void
{
    $disposal = DB::table('asset_disposals')->where('id', $id)->first();
    if (!$disposal) {
        return;
    }
    logLine("Delete ADP {$disposal->number}");
    if ($dryRun) {
        return;
    }
    $photos = DB::table('asset_disposal_photos')->where('disposal_id', $id)->get();
    foreach ($photos as $photo) {
        if (!empty($photo->photo_path)) {
            Storage::disk('public')->delete($photo->photo_path);
        }
    }
    DB::table('asset_disposal_photos')->where('disposal_id', $id)->delete();
    DB::table('asset_disposal_approval_flows')->where('disposal_id', $id)->delete();
    DB::table('asset_disposal_items')->where('disposal_id', $id)->delete();
    DB::table('asset_disposals')->where('id', $id)->delete();
}

function rollbackAdjustment(int $id, bool $dryRun): void
{
    $adj = DB::table('asset_inventory_adjustments')->where('id', $id)->first();
    if (!$adj) {
        logLine("ASA id={$id} not found");
        return;
    }
    if ($adj->status !== 'approved') {
        logLine("ASA {$adj->number} skipped rollback (status={$adj->status})");
        return;
    }

    logLine("Rollback ASA {$adj->number} ({$adj->type})");
    $items = DB::table('asset_inventory_adjustment_items')->where('adjustment_id', $id)->get();
    $sign = $adj->type === 'in' ? -1 : 1;

    foreach ($items as $item) {
        $inventoryItemId = inventoryItemIdFor((int) $item->item_id);
        if (!$inventoryItemId) {
            continue;
        }
        $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
        $converted = convertAdjustmentQty($itemMaster, (string) $item->unit, (float) $item->qty);
        applyStockDelta(
            $inventoryItemId,
            (int) $adj->owner_outlet_id,
            (int) $adj->warehouse_outlet_id,
            $sign * $converted['qty_small'],
            $sign * $converted['qty_medium'],
            $sign * $converted['qty_large'],
            $dryRun,
            "ASA {$adj->number} item#{$item->item_id}"
        );
    }
    deleteInventoryReferences('asset_stock_adjustment', $id, $dryRun);
}

function deleteAdjustment(int $id, bool $dryRun): void
{
    $adj = DB::table('asset_inventory_adjustments')->where('id', $id)->first();
    if (!$adj) {
        return;
    }
    logLine("Delete ASA {$adj->number}");
    if ($dryRun) {
        return;
    }
    DB::table('asset_inventory_adjustment_approval_flows')->where('adjustment_id', $id)->delete();
    DB::table('asset_inventory_adjustment_items')->where('adjustment_id', $id)->delete();
    DB::table('asset_inventory_adjustments')->where('id', $id)->delete();
}

function rollbackOwnerTransfer(int $id, bool $dryRun): void
{
    $transfer = DB::table('asset_owner_transfers')->where('id', $id)->first();
    if (!$transfer) {
        logLine("AOT id={$id} not found");
        return;
    }
    if ($transfer->status !== 'approved') {
        logLine("AOT {$transfer->transfer_number} skipped rollback (status={$transfer->status})");
        return;
    }

    logLine("Rollback AOT {$transfer->transfer_number}");
    $items = DB::table('asset_owner_transfer_items')->where('asset_owner_transfer_id', $id)->get();
    foreach ($items as $item) {
        $inventoryItemId = inventoryItemIdFor((int) $item->item_id);
        if (!$inventoryItemId) {
            continue;
        }
        $qtySmall = (float) ($item->qty_small ?? 0);
        $qtyMedium = (float) ($item->qty_medium ?? 0);
        $qtyLarge = (float) ($item->qty_large ?? 0);
        $wh = (int) $transfer->warehouse_outlet_id;

        applyStockDelta(
            $inventoryItemId,
            (int) $transfer->owner_outlet_from_id,
            $wh,
            $qtySmall,
            $qtyMedium,
            $qtyLarge,
            $dryRun,
            "AOT {$transfer->transfer_number} from"
        );
        applyStockDelta(
            $inventoryItemId,
            (int) $transfer->owner_outlet_to_id,
            $wh,
            -$qtySmall,
            -$qtyMedium,
            -$qtyLarge,
            $dryRun,
            "AOT {$transfer->transfer_number} to"
        );
    }
    deleteInventoryReferences('asset_owner_transfer', $id, $dryRun);
}

function deleteOwnerTransfer(int $id, bool $dryRun): void
{
    $transfer = DB::table('asset_owner_transfers')->where('id', $id)->first();
    if (!$transfer) {
        return;
    }
    logLine("Delete AOT {$transfer->transfer_number}");
    if ($dryRun) {
        return;
    }
    DB::table('asset_owner_transfer_approval_flows')->where('asset_owner_transfer_id', $id)->delete();
    DB::table('asset_owner_transfer_items')->where('asset_owner_transfer_id', $id)->delete();
    DB::table('asset_owner_transfers')->where('id', $id)->delete();
}

function rollbackInventoryTransfer(int $id, bool $dryRun): void
{
    $transfer = DB::table('asset_inventory_transfers')->where('id', $id)->first();
    if (!$transfer) {
        logLine("AIT id={$id} not found");
        return;
    }
    if ($transfer->status !== 'approved') {
        logLine("AIT {$transfer->transfer_number} skipped rollback (status={$transfer->status})");
        return;
    }

    logLine("Rollback AIT {$transfer->transfer_number}");
    $items = DB::table('asset_inventory_transfer_items')->where('asset_inventory_transfer_id', $id)->get();
    foreach ($items as $item) {
        $inventoryItemId = inventoryItemIdFor((int) $item->item_id);
        if (!$inventoryItemId) {
            continue;
        }
        $qtySmall = (float) ($item->qty_small ?? 0);
        $qtyMedium = (float) ($item->qty_medium ?? 0);
        $qtyLarge = (float) ($item->qty_large ?? 0);
        $owner = (int) $transfer->owner_outlet_id;

        applyStockDelta(
            $inventoryItemId,
            $owner,
            (int) $transfer->warehouse_outlet_from_id,
            $qtySmall,
            $qtyMedium,
            $qtyLarge,
            $dryRun,
            "AIT {$transfer->transfer_number} from"
        );
        applyStockDelta(
            $inventoryItemId,
            $owner,
            (int) $transfer->warehouse_outlet_to_id,
            -$qtySmall,
            -$qtyMedium,
            -$qtyLarge,
            $dryRun,
            "AIT {$transfer->transfer_number} to"
        );
    }
    deleteInventoryReferences('asset_inventory_transfer', $id, $dryRun);
}

function deleteInventoryTransfer(int $id, bool $dryRun): void
{
    $transfer = DB::table('asset_inventory_transfers')->where('id', $id)->first();
    if (!$transfer) {
        return;
    }
    logLine("Delete AIT {$transfer->transfer_number}");
    if ($dryRun) {
        return;
    }
    DB::table('asset_inventory_transfer_approval_flows')->where('asset_inventory_transfer_id', $id)->delete();
    DB::table('asset_inventory_transfer_items')->where('asset_inventory_transfer_id', $id)->delete();
    DB::table('asset_inventory_transfers')->where('id', $id)->delete();
}

function rollbackGoodReceive(int $id, bool $dryRun): void
{
    $gr = DB::table('asset_good_receives')->where('id', $id)->first();
    if (!$gr) {
        logLine("GR id={$id} not found");
        return;
    }

    logLine("Rollback GR {$gr->gr_number}");
    $items = DB::table('asset_good_receive_items')->where('asset_good_receive_id', $id)->get();
    foreach ($items as $item) {
        $inventoryItemId = inventoryItemIdFor((int) $item->item_id);
        if (!$inventoryItemId) {
            continue;
        }
        $converted = convertItemQty((int) $item->item_id, (int) $item->unit_id, (float) $item->qty_received);
        applyStockDelta(
            $inventoryItemId,
            (int) $gr->owner_outlet_id,
            $gr->warehouse_outlet_id ? (int) $gr->warehouse_outlet_id : null,
            -$converted['qty_small'],
            -$converted['qty_medium'],
            -$converted['qty_large'],
            $dryRun,
            "GR {$gr->gr_number} item#{$item->item_id}"
        );
    }
    deleteInventoryReferences('asset_good_receive', $id, $dryRun);
}

function deleteGoodReceive(int $id, bool $dryRun): void
{
    $gr = DB::table('asset_good_receives')->where('id', $id)->first();
    if (!$gr) {
        return;
    }
    logLine("Delete GR {$gr->gr_number}");
    if ($dryRun) {
        return;
    }
    if (Schema::hasTable('lost_breakage_replacements') && Schema::hasColumn('lost_breakage_replacements', 'asset_good_receive_id')) {
        DB::table('lost_breakage_replacements')->where('asset_good_receive_id', $id)->delete();
    }
    DB::table('asset_good_receive_items')->where('asset_good_receive_id', $id)->delete();
    DB::table('asset_good_receives')->where('id', $id)->delete();
}

function cleanupAllStocksForSkus(array $skus, bool $dryRun): void
{
    logLine('Cleanup all asset stocks/cards for SKUs: ' . implode(', ', $skus));
    foreach ($skus as $sku) {
        $item = DB::table('items')->where('sku', $sku)->first();
        if (!$item) {
            logLine("  SKU {$sku} not found");
            continue;
        }
        $inv = DB::table('asset_inventory_items')->where('item_id', $item->id)->first();
        if (!$inv) {
            logLine("  {$sku}: no asset_inventory_items row");
            continue;
        }

        $stocks = DB::table('asset_inventory_stocks')->where('inventory_item_id', $inv->id)->get();
        $cards = DB::table('asset_inventory_cards')->where('inventory_item_id', $inv->id)->count();
        $costs = DB::table('asset_inventory_cost_histories')->where('inventory_item_id', $inv->id)->count();
        logLine("  {$item->name}: stocks={$stocks->count()} cards={$cards} cost_hist={$costs}");

        if ($dryRun) {
            continue;
        }

        DB::table('asset_inventory_cards')->where('inventory_item_id', $inv->id)->delete();
        DB::table('asset_inventory_cost_histories')->where('inventory_item_id', $inv->id)->delete();
        DB::table('asset_inventory_stocks')->where('inventory_item_id', $inv->id)->delete();
    }
}

$steps = [
    ['type' => 'adp', 'number' => 'ADP202606100002'],
    ['type' => 'adp', 'number' => 'ADP202606100001'],
    ['type' => 'asa', 'number' => 'ASA202606110002'],
    ['type' => 'asa', 'number' => 'ASA202606110001'],
    ['type' => 'aot', 'number' => 'AOT-20260610-0001'],
    ['type' => 'asa', 'number' => 'ASA202606100002'],
    ['type' => 'asa', 'number' => 'ASA202606100001'],
    ['type' => 'ait', 'number' => 'AIT-20260609-0002'],
    ['type' => 'aot', 'number' => 'AOT-20260609-0002'],
    ['type' => 'ait', 'number' => 'AIT-20260609-0001'],
    ['type' => 'aot', 'number' => 'AOT-20260609-0001'],
    ['type' => 'asa', 'number' => 'ASA202605210001'],
    ['type' => 'gr', 'number' => 'AGR202606060001'],
];

$saldoAwalSkus = [
    'ASSTS-20260429-5823',
    'ASSTK-20260429-9039',
    'ASSTK-20260429-3742',
    'ASSTS-20260429-2922',
];

logLine($dryRun ? '=== DRY RUN ===' : '=== EXECUTING DELETE ===');

if (!$dryRun) {
    DB::beginTransaction();
}

try {
    foreach ($steps as $step) {
        $id = null;
        switch ($step['type']) {
            case 'adp':
                $id = (int) DB::table('asset_disposals')->where('number', $step['number'])->value('id');
                if ($id) {
                    rollbackDisposal($id, $dryRun);
                    deleteDisposal($id, $dryRun);
                } else {
                    logLine("ADP {$step['number']} not found");
                }
                break;
            case 'asa':
                $id = (int) DB::table('asset_inventory_adjustments')->where('number', $step['number'])->value('id');
                if ($id) {
                    rollbackAdjustment($id, $dryRun);
                    deleteAdjustment($id, $dryRun);
                } else {
                    logLine("ASA {$step['number']} not found");
                }
                break;
            case 'aot':
                $id = (int) DB::table('asset_owner_transfers')->where('transfer_number', $step['number'])->value('id');
                if ($id) {
                    rollbackOwnerTransfer($id, $dryRun);
                    deleteOwnerTransfer($id, $dryRun);
                } else {
                    logLine("AOT {$step['number']} not found");
                }
                break;
            case 'ait':
                $id = (int) DB::table('asset_inventory_transfers')->where('transfer_number', $step['number'])->value('id');
                if ($id) {
                    rollbackInventoryTransfer($id, $dryRun);
                    deleteInventoryTransfer($id, $dryRun);
                } else {
                    logLine("AIT {$step['number']} not found");
                }
                break;
            case 'gr':
                $id = (int) DB::table('asset_good_receives')->where('gr_number', $step['number'])->value('id');
                if ($id) {
                    rollbackGoodReceive($id, $dryRun);
                    deleteGoodReceive($id, $dryRun);
                } else {
                    logLine("GR {$step['number']} not found");
                }
                break;
        }
        logLine('');
    }

    cleanupAllStocksForSkus($saldoAwalSkus, $dryRun);

    if (!$dryRun) {
        DB::commit();
        logLine('DONE — committed.');
    } else {
        logLine('DRY RUN complete — no changes made.');
    }
} catch (Throwable $e) {
    if (!$dryRun) {
        DB::rollBack();
    }
    logLine('ERROR: ' . $e->getMessage());
    logLine($e->getTraceAsString());
    exit(1);
}
