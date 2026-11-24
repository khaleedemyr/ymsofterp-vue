<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlet_internal_use_waste_details', function (Blueprint $table) {
            $table->string('line_mode', 20)->default('material')->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('outlet_internal_use_waste_details', function (Blueprint $table) {
            $table->dropColumn('line_mode');
        });
    }
};
