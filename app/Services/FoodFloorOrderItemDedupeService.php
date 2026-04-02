<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FoodFloorOrderItemDedupeService
{
    /**
     * Gabungkan baris food_floor_order_items dengan item_id sama per floor_order_id.
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

                $totalQty = $rows->sum(fn ($r) => (float) $r->qty);
                $totalSubtotal = $rows->sum(fn ($r) => (float) $r->subtotal);
                $keepRow = $rows->first(fn ($r) => (int) $r->id === (int) $keepId);
                $price = $totalQty > 0
                    ? round($totalSubtotal / $totalQty, 4)
                    : (float) ($keepRow->price ?? 0);

                foreach ($rows as $r) {
                    if ((int) $r->id === (int) $keepId) {
                        continue;
                    }
                    DB::table('food_packing_list_items')
                        ->where('food_floor_order_item_id', $r->id)
                        ->update(['food_floor_order_item_id' => $keepId]);
                }

                $this->mergePackingListItemsForFoItem($keepId);

                DB::table('food_floor_order_items')->where('id', $keepId)->update([
                    'qty' => $totalQty,
                    'subtotal' => $totalSubtotal,
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
    private function mergePackingListItemsForFoItem(int $foodFloorOrderItemId): void
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
            $totalQty = $pliRows->sum(fn ($r) => (float) $r->qty);

            DB::table('food_packing_list_items')->where('id', $keep->id)->update([
                'qty' => $totalQty,
            ]);

            $restIds = $pliRows->pluck('id')->slice(1)->all();
            if ($restIds !== []) {
                DB::table('food_packing_list_items')->whereIn('id', $restIds)->delete();
            }
        }
    }
}
