<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qty ditolak opsional per baris GR — hanya catatan, tidak mempengaruhi stok/inventory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_good_receive_items', function (Blueprint $table) {
            if (! Schema::hasColumn('food_good_receive_items', 'qty_rejected')) {
                $table->decimal('qty_rejected', 18, 4)->nullable()->after('qty_received');
            }
        });
    }

    public function down(): void
    {
        Schema::table('food_good_receive_items', function (Blueprint $table) {
            if (Schema::hasColumn('food_good_receive_items', 'qty_rejected')) {
                $table->dropColumn('qty_rejected');
            }
        });
    }
};
