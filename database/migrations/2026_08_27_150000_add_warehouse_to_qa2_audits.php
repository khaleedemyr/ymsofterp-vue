<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qa2_audits') || Schema::hasColumn('qa2_audits', 'warehouse')) {
            return;
        }

        Schema::table('qa2_audits', function (Blueprint $table) {
            $table->string('warehouse', 20)->nullable()->after('warehouse_division_id');
            $table->index('warehouse');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('qa2_audits') || ! Schema::hasColumn('qa2_audits', 'warehouse')) {
            return;
        }

        Schema::table('qa2_audits', function (Blueprint $table) {
            $table->dropIndex(['warehouse']);
            $table->dropColumn('warehouse');
        });
    }
};
