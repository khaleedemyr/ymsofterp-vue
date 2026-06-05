<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileOutletFoodInventoryCardSaldo extends Command
{
    protected $signature = 'inventory:reconcile-outlet-card-saldo
        {--item-id= : Item master id (items.id)}
        {--outlet-id= : Outlet id}
        {--warehouse-outlet-id= : Warehouse outlet id}
        {--from-date= : Recalculate saldo chain from this date (Y-m-d)}
        {--dry-run : Preview without updating}';

    protected $description = 'Selaraskan saldo kartu stok outlet food dengan outlet_food_inventory_stocks';

    public function handle(): int
    {
        $itemId = (int) $this->option('item-id');
        $outletId = (int) $this->option('outlet-id');
        $warehouseId = (int) $this->option('warehouse-outlet-id');
        $fromDate = $this->option('from-date');
        $dryRun = (bool) $this->option('dry-run');

        if ($itemId <= 0 || $outletId <= 0 || $warehouseId <= 0) {
            $this->error('Wajib: --item-id, --outlet-id, --warehouse-outlet-id');

            return self::FAILURE;
        }

        $inv = DB::table('outlet_food_inventory_items')->where('item_id', $itemId)->first();
        if (! $inv) {
            $this->error('outlet_food_inventory_items tidak ditemukan untuk item_id='.$itemId);

            return self::FAILURE;
        }

        $stock = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inv->id)
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseId)
            ->first();

        if (! $stock) {
            $this->error('Stok tidak ditemukan.');

            return self::FAILURE;
        }

        if ($fromDate) {
            return $this->recalculateChainFromDate($inv->id, $outletId, $warehouseId, $stock, (string) $fromDate, $dryRun);
        }

        return $this->syncLastCardToStock($inv->id, $outletId, $warehouseId, $stock, $dryRun);
    }

    private function syncLastCardToStock(int $inventoryItemId, int $outletId, int $warehouseId, object $stock, bool $dryRun): int
    {
        $lastCard = DB::table('outlet_food_inventory_cards')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if (! $lastCard) {
            $this->error('Kartu stok tidak ditemukan.');

            return self::FAILURE;
        }

        $target = [
            'saldo_qty_small' => (float) $stock->qty_small,
            'saldo_qty_medium' => (float) $stock->qty_medium,
            'saldo_qty_large' => (float) $stock->qty_large,
            'saldo_value' => (float) $stock->value,
        ];

        $this->table(
            ['Field', 'Card (last)', 'Stock (target)'],
            collect($target)->map(function ($targetVal, $key) use ($lastCard) {
                return [$key, $lastCard->{$key}, $targetVal];
            })->values()->all()
        );

        if (
            abs((float) $lastCard->saldo_qty_small - $target['saldo_qty_small']) < 0.01
            && abs((float) $lastCard->saldo_qty_medium - $target['saldo_qty_medium']) < 0.01
            && abs((float) $lastCard->saldo_qty_large - $target['saldo_qty_large']) < 0.01
        ) {
            $this->info('Saldo kartu sudah sinkron dengan stok riil.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry-run: tidak ada perubahan disimpan.');

            return self::SUCCESS;
        }

        DB::table('outlet_food_inventory_cards')
            ->where('id', $lastCard->id)
            ->update([
                'saldo_qty_small' => $target['saldo_qty_small'],
                'saldo_qty_medium' => $target['saldo_qty_medium'],
                'saldo_qty_large' => $target['saldo_qty_large'],
                'saldo_value' => $target['saldo_value'],
                'updated_at' => now(),
            ]);

        $this->info("Kartu stok id={$lastCard->id} diselaraskan ke stok riil.");

        return self::SUCCESS;
    }

    private function recalculateChainFromDate(
        int $inventoryItemId,
        int $outletId,
        int $warehouseId,
        object $stock,
        string $fromDate,
        bool $dryRun
    ): int {
        $prev = DB::table('outlet_food_inventory_cards')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseId)
            ->whereDate('date', '<', $fromDate)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        $runningSmall = $prev ? (float) $prev->saldo_qty_small : 0.0;
        $runningMedium = $prev ? (float) $prev->saldo_qty_medium : 0.0;
        $runningLarge = $prev ? (float) $prev->saldo_qty_large : 0.0;
        $mac = (float) ($stock->last_cost_small ?: 0);

        $cards = DB::table('outlet_food_inventory_cards')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $warehouseId)
            ->whereDate('date', '>=', $fromDate)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $updates = [];
        foreach ($cards as $card) {
            $runningSmall += (float) $card->in_qty_small - (float) $card->out_qty_small;
            $runningMedium += (float) $card->in_qty_medium - (float) $card->out_qty_medium;
            $runningLarge += (float) $card->in_qty_large - (float) $card->out_qty_large;
            $runningValue = max(0, $runningSmall * $mac);

            $updates[] = [
                'id' => $card->id,
                'date' => $card->date,
                'reference_type' => $card->reference_type,
                'old_saldo' => (float) $card->saldo_qty_small,
                'new_saldo' => $runningSmall,
            ];
        }

        $this->table(
            ['card_id', 'date', 'ref', 'saldo_lama', 'saldo_baru'],
            collect($updates)->map(fn ($u) => [
                $u['id'], $u['date'], $u['reference_type'], $u['old_saldo'], $u['new_saldo'],
            ])->all()
        );

        $lastNew = $runningSmall;
        $lastStock = (float) $stock->qty_small;
        if (abs($lastNew - $lastStock) > 0.01) {
            $this->warn("Peringatan: saldo akhir chain ({$lastNew}) != stok riil ({$lastStock}). Tetap lanjut koreksi tampilan kartu.");
        }

        if ($dryRun) {
            $this->warn('Dry-run: tidak ada perubahan disimpan.');

            return self::SUCCESS;
        }

        $runningSmall = $prev ? (float) $prev->saldo_qty_small : 0.0;
        $runningMedium = $prev ? (float) $prev->saldo_qty_medium : 0.0;
        $runningLarge = $prev ? (float) $prev->saldo_qty_large : 0.0;

        foreach ($cards as $card) {
            $runningSmall += (float) $card->in_qty_small - (float) $card->out_qty_small;
            $runningMedium += (float) $card->in_qty_medium - (float) $card->out_qty_medium;
            $runningLarge += (float) $card->in_qty_large - (float) $card->out_qty_large;

            DB::table('outlet_food_inventory_cards')
                ->where('id', $card->id)
                ->update([
                    'saldo_qty_small' => $runningSmall,
                    'saldo_qty_medium' => $runningMedium,
                    'saldo_qty_large' => $runningLarge,
                    'saldo_value' => max(0, $runningSmall * $mac),
                    'updated_at' => now(),
                ]);
        }

        // Pastikan kartu terakhir = stok riil
        $lastCard = $cards->last();
        if ($lastCard) {
            DB::table('outlet_food_inventory_cards')
                ->where('id', $lastCard->id)
                ->update([
                    'saldo_qty_small' => (float) $stock->qty_small,
                    'saldo_qty_medium' => (float) $stock->qty_medium,
                    'saldo_qty_large' => (float) $stock->qty_large,
                    'saldo_value' => (float) $stock->value,
                    'updated_at' => now(),
                ]);
        }

        $this->info('Chain kartu stok dari '.$fromDate.' telah dikoreksi.');

        return self::SUCCESS;
    }
}
