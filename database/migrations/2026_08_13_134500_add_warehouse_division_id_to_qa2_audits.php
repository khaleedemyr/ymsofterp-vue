<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('qa2_audits') || Schema::hasColumn('qa2_audits', 'warehouse_division_id')) {
            return;
        }

        Schema::table('qa2_audits', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_division_id')->nullable()->after('outlet_id');
            $table->index('warehouse_division_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('qa2_audits') || !Schema::hasColumn('qa2_audits', 'warehouse_division_id')) {
            return;
        }

        Schema::table('qa2_audits', function (Blueprint $table) {
            $table->dropIndex(['warehouse_division_id']);
            $table->dropColumn('warehouse_division_id');
        });
    }
};
