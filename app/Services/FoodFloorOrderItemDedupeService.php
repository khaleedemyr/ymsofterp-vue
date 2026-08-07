<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FoodFloorOrderItemDedupeService
{
    /**
     * Rapikan baris food_floor_order_items dengan item_id sama per floor_order_id.
     *
     * - Salinan identik (qty/unit/price sama — biasanya dari race autosave): sisakan 1, qty tidak dijumlah.
     * - Qty berbeda (payload ganda antar kategori): qty & subtotal dijumlahkan.
     * Mempertahankan id yang sudah dipakai packing list (id terkecil yang punya PLI, atau id terkecil).
     *
     * @return int jumlah baris FO item yang dihapus
     */
    public function dedupeFloorOrder(int $floorOrderId): int
    {
        $removed = 0;

        DB::transaction(function () use ($floorOrderId, &$removed) {
            $groups = DB::table('food_floor_order_items')
                ->where('floor_order_id', $floorOrderId)
                ->orderBy('id')
                ->get()
                ->groupBy('item_id');

            foreach ($groups as $rows) {
                if ($rows->count() < 2) {
                    continue;
                }

                $rows = $rows->values();
                $keepId = $this->resolveCanonicalItemId($rows);
                $keepRow = $rows->first(fn ($r) => (int) $r->id === (int) $keepId);
                $identicalCopies = $this->areIdenticalCopies($rows);

                if ($identicalCopies) {
                    $qty = (float) ($keepRow->qty ?? 0);
                    $subtotal = (float) ($keepRow->subtotal ?? 0);
                    $price = (float) ($keepRow->price ?? 0);
                } else {
                    $qty = $rows->sum(fn ($r) => (float) $r->qty);
                    $subtotal = $rows->sum(fn ($r) => (float) $r->subtotal);
                    $price = $qty > 0
                        ? round($subtotal / $qty, 4)
                        : (float) ($keepRow->price ?? 0);
                }

                foreach ($rows as $r) {
                    if ((int) $r->id === (int) $keepId) {
                        continue;
                    }
                    DB::table('food_packing_list_items')
                        ->where('food_floor_order_item_id', $r->id)
                        ->update(['food_floor_order_item_id' => $keepId]);
                }

                $this->mergePackingListItemsForFoItem($keepId, $identicalCopies);

                DB::table('food_floor_order_items')->where('id', $keepId)->update([
                    'qty' => $qty,
                    'subtotal' => $subtotal,
                    'price' => $price,
                    'updated_at' => now(),
                ]);

                $deleteIds = $rows->pluck('id')->filter(fn ($id) => (int) $id !== (int) $keepId)->all();
                if ($deleteIds !== []) {
                    DB::table('food_floor_order_items')->whereIn('id', $deleteIds)->delete();
                    $removed += count($deleteIds);
                }
            }
        });

        return $removed;
    }

    /**
     * Race autosave: baris kembar dengan qty/unit/price/subtotal sama.
     */
    private function areIdenticalCopies(Collection $rows): bool
    {
        $first = $rows->first();
        foreach ($rows as $r) {
            if ((string) $r->qty !== (string) $first->qty) {
                return false;
            }
            if ((string) ($r->unit ?? '') !== (string) ($first->unit ?? '')) {
                return false;
            }
            if ((float) $r->price !== (float) $first->price) {
                return false;
            }
            if ((float) $r->subtotal !== (float) $first->subtotal) {
                return false;
            }
        }

        return true;
    }

    private function resolveCanonicalItemId($rows): int
    {
        foreach ($rows->sortBy('id') as $r) {
            $exists = DB::table('food_packing_list_items')
                ->where('food_floor_order_item_id', $r->id)
                ->exists();
            if ($exists) {
                return (int) $r->id;
            }
        }

        return (int) $rows->min('id');
    }

    /**
     * Setelah repoint, bisa ada dua baris PLI untuk packing_list_id + food_floor_order_item_id yang sama.
     */
    private function mergePackingListItemsForFoItem(int $foodFloorOrderItemId, bool $identicalFoCopies): void
    {
        $dupPackingLists = DB::table('food_packing_list_items')
            ->select('packing_list_id')
            ->where('food_floor_order_item_id', $foodFloorOrderItemId)
            ->groupBy('packing_list_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('packing_list_id');

        foreach ($dupPackingLists as $packingListId) {
            $pliRows = DB::table('food_packing_list_items')
                ->where('packing_list_id', $packingListId)
                ->where('food_floor_order_item_id', $foodFloorOrderItemId)
                ->orderBy('id')
                ->get();

            $keep = $pliRows->first();
            $identicalPli = $identicalFoCopies || $this->areIdenticalPliQty($pliRows);
            $qty = $identicalPli
                ? (float) $keep->qty
                : $pliRows->sum(fn ($r) => (float) $r->qty);

            DB::table('food_packing_list_items')->where('id', $keep->id)->update([
                'qty' => $qty,
            ]);

            $restIds = $pliRows->pluck('id')->slice(1)->all();
            if ($restIds !== []) {
                DB::table('food_packing_list_items')->whereIn('id', $restIds)->delete();
            }
        }
    }

    private function areIdenticalPliQty(Collection $rows): bool
    {
        $firstQty = (string) $rows->first()->qty;
        foreach ($rows as $r) {
            if ((string) $r->qty !== $firstQty) {
                return false;
            }
        }

        return true;
    }
}
