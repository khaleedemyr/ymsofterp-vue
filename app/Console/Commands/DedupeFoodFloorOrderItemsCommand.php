<?php

namespace App\Console\Commands;

use App\Models\FoodFloorOrder;
use App\Services\FoodFloorOrderItemDedupeService;
use Illuminate\Console\Command;

class DedupeFoodFloorOrderItemsCommand extends Command
{
    protected $signature = 'food-floor-order:dedupe-items
                            {order_number? : Nomor RO mis. RO-20260331-3187}
                            {--all : Rapikan semua FO yang punya duplikat item_id}';

    protected $description = 'Gabungkan baris duplikat food_floor_order_items (item_id sama per floor_order_id)';

    public function handle(FoodFloorOrderItemDedupeService $dedupe): int
    {
        if ($this->option('all') && $this->argument('order_number')) {
            $this->error('Gunakan hanya --all ATAU nomor order, tidak keduanya.');

            return self::FAILURE;
        }

        if ($this->option('all')) {
            $orderIds = $this->floorOrderIdsWithDuplicateItems();
            if ($orderIds === []) {
                $this->info('Tidak ada floor order dengan item_id duplikat.');

                return self::SUCCESS;
            }
            $this->info('Memproses '.count($orderIds).' floor order...');
            $totalRemoved = 0;
            foreach ($orderIds as $id) {
                $n = $dedupe->dedupeFloorOrder((int) $id);
                $totalRemoved += $n;
                if ($n > 0) {
                    $this->line("  FO id {$id}: {$n} baris duplikat dihapus.");
                }
            }
            $this->info("Selesai. Total baris duplikat dihapus: {$totalRemoved}.");

            return self::SUCCESS;
        }

        $orderNumber = $this->argument('order_number');
        if (! $orderNumber) {
            $this->error('Sertakan nomor order atau gunakan --all.');

            return self::FAILURE;
        }

        $order = FoodFloorOrder::where('order_number', $orderNumber)->first();
        if (! $order) {
            $this->error("Floor order \"{$orderNumber}\" tidak ditemukan.");

            return self::FAILURE;
        }

        $removed = $dedupe->dedupeFloorOrder($order->id);
        $this->info("FO {$orderNumber} (id {$order->id}): {$removed} baris duplikat digabung/dihapus.");

        return self::SUCCESS;
    }

    /**
     * @return list<int|string>
     */
    private function floorOrderIdsWithDuplicateItems(): array
    {
        $dupKeys = \DB::table('food_floor_order_items')
            ->select('floor_order_id', 'item_id')
            ->groupBy('floor_order_id', 'item_id')
            ->havingRaw('COUNT(*) > 1');

        return \DB::table('food_floor_order_items as i')
            ->joinSub($dupKeys, 'd', function ($join) {
                $join->on('i.floor_order_id', '=', 'd.floor_order_id')
                    ->on('i.item_id', '=', 'd.item_id');
            })
            ->distinct()
            ->pluck('i.floor_order_id')
            ->unique()
            ->values()
            ->all();
    }
}
