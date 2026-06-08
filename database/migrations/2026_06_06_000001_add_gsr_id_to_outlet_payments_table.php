<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('outlet_payments') || Schema::hasColumn('outlet_payments', 'gsr_id')) {
            return;
        }

        Schema::table('outlet_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('gsr_id')->nullable()->after('gr_id')->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('outlet_payments') || !Schema::hasColumn('outlet_payments', 'gsr_id')) {
            return;
        }

        Schema::table('outlet_payments', function (Blueprint $table) {
            $table->dropColumn('gsr_id');
        });
    }
};
