<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_master', function (Blueprint $table) {
            $table->tinyInteger('deviasi')->default(0)->after('lb');
            $table->tinyInteger('city_ledger')->default(0)->after('deviasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_master', function (Blueprint $table) {
            $table->dropColumn(['deviasi', 'city_ledger']);
        });
    }
};

