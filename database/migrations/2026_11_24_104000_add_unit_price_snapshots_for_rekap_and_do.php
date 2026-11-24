<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('outlet_serial_receive_items')
            && ! Schema::hasColumn('outlet_serial_receive_items', 'unit_price')) {
            Schema::table('outlet_serial_receive_items', function (Blueprint $table) {
                $table->decimal('unit_price', 18, 4)->nullable()->after('cost_small');
            });
        }

        if (Schema::hasTable('outlet_food_good_receive_items')
            && ! Schema::hasColumn('outlet_food_good_receive_items', 'unit_price')) {
            Schema::table('outlet_food_good_receive_items', function (Blueprint $table) {
                $table->decimal('unit_price', 18, 4)->nullable()->after('received_qty');
            });
        }

        if (Schema::hasTable('delivery_order_items')
            && ! Schema::hasColumn('delivery_order_items', 'unit_price')) {
            Schema::table('delivery_order_items', function (Blueprint $table) {
                $table->decimal('unit_price', 18, 4)->nullable()->after('qty_scan_barcode');
            });
        }

        $this->backfillSerialUnitPrices();
        $this->backfillFoodUnitPrices();
        $this->backfillDoUnitPricesFromFo();
    }

    public function down(): void
    {
        foreach ([
            'outlet_serial_receive_items' => 'unit_price',
            'outlet_food_good_receive_items' => 'unit_price',
            'delivery_order_items' => 'unit_price',
        ] as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->dropColumn($column);
                });
            }
        }
    }

    /**
     * Snapshot harga tampilan = rumus Rekap FJ saat ini agar cutover tidak menggeser total.
     */
    private function backfillSerialUnitPrices(): void
    {
        if (! Schema::hasTable('outlet_serial_receive_items')
            || ! Schema::hasColumn('outlet_serial_receive_items', 'unit_price')) {
            return;
        }

        $live = '(CASE
            WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1) * COALESCE(it.medium_conversion_qty, 1)
            WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1)
            ELSE COALESCE(si.cost_small, 0)
        END)';

        DB::update("
            UPDATE outlet_serial_receive_items si
            INNER JOIN items it ON it.id = si.item_id
            SET si.unit_price = ROUND({$live}, 4)
            WHERE si.unit_price IS NULL
        ");
    }

    private function backfillFoodUnitPrices(): void
    {
        if (! Schema::hasTable('outlet_food_good_receive_items')
            || ! Schema::hasColumn('outlet_food_good_receive_items', 'unit_price')) {
            return;
        }

        DB::update("
            UPDATE outlet_food_good_receive_items gri
            INNER JOIN outlet_food_good_receives gr ON gr.id = gri.outlet_food_good_receive_id
            LEFT JOIN delivery_orders do_row ON do_row.id = gr.delivery_order_id
            LEFT JOIN food_floor_order_items fo ON fo.id = (
                SELECT fo2.id
                FROM food_floor_order_items fo2
                WHERE fo2.floor_order_id = do_row.floor_order_id
                  AND fo2.item_id = gri.item_id
                ORDER BY fo2.id DESC
                LIMIT 1
            )
            SET gri.unit_price = ROUND(COALESCE(fo.price, 0), 4)
            WHERE gri.unit_price IS NULL
        ");
    }

    /**
     * Snapshot harga bill DO: prefer rata-rata unit_price GSR (selaras Rekap), lalu FO.
     */
    private function backfillDoUnitPricesFromFo(): void
    {
        if (! Schema::hasTable('delivery_order_items')
            || ! Schema::hasColumn('delivery_order_items', 'unit_price')) {
            return;
        }

        if (Schema::hasTable('outlet_serial_receive_items')
            && Schema::hasColumn('outlet_serial_receive_items', 'unit_price')) {
            DB::update("
                UPDATE delivery_order_items doi
                INNER JOIN (
                    SELECT delivery_order_id, item_id,
                           ROUND(AVG(unit_price), 4) AS avg_unit_price
                    FROM outlet_serial_receive_items
                    WHERE unit_price IS NOT NULL AND unit_price > 0
                      AND delivery_order_id IS NOT NULL
                    GROUP BY delivery_order_id, item_id
                ) gsr ON gsr.delivery_order_id = doi.delivery_order_id
                    AND gsr.item_id = doi.item_id
                SET doi.unit_price = gsr.avg_unit_price
                WHERE doi.unit_price IS NULL
            ");
        }

        DB::update("
            UPDATE delivery_order_items doi
            INNER JOIN delivery_orders do_row ON do_row.id = doi.delivery_order_id
            LEFT JOIN food_floor_order_items fo ON fo.id = (
                SELECT fo2.id
                FROM food_floor_order_items fo2
                WHERE fo2.floor_order_id = do_row.floor_order_id
                  AND fo2.item_id = doi.item_id
                ORDER BY fo2.id DESC
                LIMIT 1
            )
            SET doi.unit_price = ROUND(COALESCE(fo.price, 0), 4)
            WHERE doi.unit_price IS NULL
              AND fo.price IS NOT NULL
              AND fo.price > 0
        ");
    }
};
