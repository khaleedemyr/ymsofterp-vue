<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlet_internal_use_waste_details', function (Blueprint $table) {
            if (!Schema::hasColumn('outlet_internal_use_waste_details', 'stock_on_hand')) {
                $table->decimal('stock_on_hand', 18, 4)->nullable()->after('qty')
                    ->comment('Snapshot stock on hand (small unit) saat input usage');
            }
            if (!Schema::hasColumn('outlet_internal_use_waste_details', 'physical_qty')) {
                $table->decimal('physical_qty', 18, 4)->nullable()->after('stock_on_hand')
                    ->comment('Stock fisik hasil hitung outlet (small unit) untuk tipe usage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outlet_internal_use_waste_details', function (Blueprint $table) {
            if (Schema::hasColumn('outlet_internal_use_waste_details', 'physical_qty')) {
                $table->dropColumn('physical_qty');
            }
            if (Schema::hasColumn('outlet_internal_use_waste_details', 'stock_on_hand')) {
                $table->dropColumn('stock_on_hand');
            }
        });
    }
};
