<?php

use App\Services\FoodFloorOrderItemDedupeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris per (floor_order_id, item_id) — cegah race autosave delete+insert dobel.
     */
    public function up(): void
    {
        $dedupe = app(FoodFloorOrderItemDedupeService::class);
        $orderIds = DB::table('food_floor_order_items')
            ->select('floor_order_id')
            ->groupBy('floor_order_id', 'item_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('floor_order_id')
            ->unique()
            ->values()
            ->all();

        foreach ($orderIds as $floorOrderId) {
            $dedupe->dedupeFloorOrder((int) $floorOrderId);
        }

        $this->dropIndexIfExists('food_floor_order_items', 'idx_food_floor_order_items_floor_order_id_item_id');
        $this->dropIndexIfExists('food_floor_order_items', 'food_floor_order_items_floor_order_id_item_id_unique');

        Schema::table('food_floor_order_items', function (Blueprint $table) {
            $table->unique(
                ['floor_order_id', 'item_id'],
                'food_floor_order_items_floor_order_id_item_id_unique'
            );
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('food_floor_order_items', 'food_floor_order_items_floor_order_id_item_id_unique');

        Schema::table('food_floor_order_items', function (Blueprint $table) {
            $table->index(
                ['floor_order_id', 'item_id'],
                'idx_food_floor_order_items_floor_order_id_item_id'
            );
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => ($row->Key_name ?? '') === $indexName);

        if (! $exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }
};
