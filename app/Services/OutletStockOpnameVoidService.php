<?php

namespace App\Services;

use App\Models\StockOpname;
use App\Models\StockOpnameAdjustment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OutletStockOpnameVoidService
{
    public function __construct(
        private readonly StockCutVarianceService $varianceService
    ) {
    }

    /**
     * Void COMPLETED outlet stock opname and roll back all Process side effects.
     *
     * @return array{reversed_items: int, card_count: int, cost_hist_count: int, variance_reopened: int}
     */
    public function void(StockOpname $stockOpname, int $userId, string $reason): array
    {
        if (! $stockOpname->canBeVoided()) {
            throw new RuntimeException('Hanya stock opname berstatus COMPLETED yang dapat di-void.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Alasan void wajib diisi.');
        }

        $opnameId = (int) $stockOpname->id;
        $outletId = (int) $stockOpname->outlet_id;
        $warehouseOutletId = (int) $stockOpname->warehouse_outlet_id;

        return DB::transaction(function () use ($stockOpname, $userId, $reason, $opnameId, $outletId, $warehouseOutletId) {
            // Lock header
            $locked = StockOpname::query()->where('id', $opnameId)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'COMPLETED') {
                throw new RuntimeException('Stock opname tidak lagi berstatus COMPLETED.');
            }

            $adjustments = StockOpnameAdjustment::query()
                ->where('stock_opname_id', $opnameId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $cards = DB::table('outlet_food_inventory_cards')
                ->where('reference_type', 'stock_opname')
                ->where('reference_id', $opnameId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->assertNoLaterCards($cards, $outletId, $warehouseOutletId);

            if ($adjustments->isNotEmpty()) {
                foreach ($adjustments as $adj) {
                    $this->reverseStockFromAdjustment($adj);
                }
            } elseif ($cards->isNotEmpty()) {
                // Legacy/partial: no adjustment rows — reverse from card in/out
                foreach ($cards as $card) {
                    $this->reverseStockFromCard($card);
                }
            }

            $cardCount = DB::table('outlet_food_inventory_cards')
                ->where('reference_type', 'stock_opname')
                ->where('reference_id', $opnameId)
                ->delete();

            $costHistCount = (int) DB::table('outlet_food_inventory_cost_histories')
                ->where(function ($q) use ($opnameId) {
                    $q->where(function ($q2) use ($opnameId) {
                        $q2->where('reference_type', 'stock_opname')
                            ->where('reference_id', $opnameId);
                    })->orWhere(function ($q2) use ($opnameId) {
                        $q2->where('type', 'stock_opname')
                            ->where('reference_id', $opnameId);
                    });
                })
                ->delete();

            $reversedItems = $adjustments->isNotEmpty() ? $adjustments->count() : $cards->count();
            StockOpnameAdjustment::query()->where('stock_opname_id', $opnameId)->delete();

            $varianceReopened = $this->varianceService->reopenClosedByStockOpname($opnameId);

            $locked->update([
                'status' => 'VOIDED',
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => $reason,
            ]);

            return [
                'reversed_items' => $reversedItems,
                'card_count' => (int) $cardCount,
                'cost_hist_count' => (int) $costHistCount,
                'variance_reopened' => (int) $varianceReopened,
            ];
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $cards
     */
    private function assertNoLaterCards($cards, int $outletId, int $warehouseOutletId): void
    {
        if ($cards->isEmpty()) {
            return;
        }

        $blocked = [];

        foreach ($cards->groupBy('inventory_item_id') as $inventoryItemId => $itemCards) {
            $maxDate = null;
            $maxId = 0;
            foreach ($itemCards as $card) {
                $date = substr((string) $card->date, 0, 10);
                $id = (int) $card->id;
                if ($maxDate === null
                    || $date > $maxDate
                    || ($date === $maxDate && $id > $maxId)
                ) {
                    $maxDate = $date;
                    $maxId = $id;
                }
            }

            $laterExists = DB::table('outlet_food_inventory_cards')
                ->where('inventory_item_id', (int) $inventoryItemId)
                ->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->where(function ($q) use ($maxDate, $maxId) {
                    $q->whereDate('date', '>', $maxDate)
                        ->orWhere(function ($q2) use ($maxDate, $maxId) {
                            $q2->whereDate('date', $maxDate)->where('id', '>', $maxId);
                        });
                })
                ->exists();

            if ($laterExists) {
                $itemName = DB::table('outlet_food_inventory_items as fi')
                    ->join('items as i', 'fi.item_id', '=', 'i.id')
                    ->where('fi.id', (int) $inventoryItemId)
                    ->value('i.name');
                $blocked[] = ($itemName ?: ('inventory_item#'.$inventoryItemId));
            }
        }

        if ($blocked !== []) {
            $list = implode(', ', array_slice($blocked, 0, 10));
            $more = count($blocked) > 10 ? ' (+'.(count($blocked) - 10).' item lain)' : '';
            throw new RuntimeException(
                'Tidak bisa void: sudah ada mutasi stok setelah process opname untuk item: '.$list.$more
                .'. Koreksi lewat opname/adjustment baru, atau void setelah mutasi berikutnya dibatalkan.'
            );
        }
    }

    private function reverseStockFromAdjustment(StockOpnameAdjustment $adj): void
    {
        $stock = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', (int) $adj->inventory_item_id)
            ->where('id_outlet', (int) $adj->outlet_id)
            ->where('warehouse_outlet_id', (int) $adj->warehouse_outlet_id)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            throw new RuntimeException(
                'Stok tidak ditemukan untuk inventory_item_id='.$adj->inventory_item_id
                .' saat reverse void. Abort agar tidak meninggalkan data partial.'
            );
        }

        $newQtySmall = (float) $stock->qty_small - (float) $adj->qty_diff_small;
        $newQtyMedium = (float) $stock->qty_medium - (float) $adj->qty_diff_medium;
        $newQtyLarge = (float) $stock->qty_large - (float) $adj->qty_diff_large;
        $newValue = max(0, (float) $stock->value - (float) $adj->value_adjustment);

        DB::table('outlet_food_inventory_stocks')
            ->where('id', $stock->id)
            ->update([
                'qty_small' => round($newQtySmall, 4),
                'qty_medium' => round($newQtyMedium, 4),
                'qty_large' => round($newQtyLarge, 4),
                'value' => round($newValue, 2),
                'updated_at' => now(),
            ]);
    }

    private function reverseStockFromCard(object $card): void
    {
        $diffSmall = (float) $card->in_qty_small - (float) $card->out_qty_small;
        $diffMedium = (float) $card->in_qty_medium - (float) $card->out_qty_medium;
        $diffLarge = (float) $card->in_qty_large - (float) $card->out_qty_large;
        $valueAdj = (float) $card->value_in - (float) $card->value_out;

        $stock = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', (int) $card->inventory_item_id)
            ->where('id_outlet', (int) $card->id_outlet)
            ->where('warehouse_outlet_id', (int) $card->warehouse_outlet_id)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            throw new RuntimeException(
                'Stok tidak ditemukan untuk inventory_item_id='.$card->inventory_item_id
                .' saat reverse void dari kartu. Abort agar tidak meninggalkan data partial.'
            );
        }

        DB::table('outlet_food_inventory_stocks')
            ->where('id', $stock->id)
            ->update([
                'qty_small' => round((float) $stock->qty_small - $diffSmall, 4),
                'qty_medium' => round((float) $stock->qty_medium - $diffMedium, 4),
                'qty_large' => round((float) $stock->qty_large - $diffLarge, 4),
                'value' => round(max(0, (float) $stock->value - $valueAdj), 2),
                'updated_at' => now(),
            ]);
    }
}
