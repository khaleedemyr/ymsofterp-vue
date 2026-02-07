<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlet_internal_use_waste_headers', function (Blueprint $table) {
            if (!Schema::hasColumn('outlet_internal_use_waste_headers', 'subtotal_mac')) {
                $table->decimal('subtotal_mac', 18, 2)->default(0)->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outlet_internal_use_waste_headers', function (Blueprint $table) {
            if (Schema::hasColumn('outlet_internal_use_waste_headers', 'subtotal_mac')) {
                $table->dropColumn('subtotal_mac');
            }
        });
    }
};
