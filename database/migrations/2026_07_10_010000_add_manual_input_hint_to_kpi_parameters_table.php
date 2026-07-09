<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kpi_parameters')) {
            return;
        }

        Schema::table('kpi_parameters', function (Blueprint $table) {
            if (! Schema::hasColumn('kpi_parameters', 'manual_input_hint')) {
                $table->text('manual_input_hint')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kpi_parameters')) {
            return;
        }

        Schema::table('kpi_parameters', function (Blueprint $table) {
            if (Schema::hasColumn('kpi_parameters', 'manual_input_hint')) {
                $table->dropColumn('manual_input_hint');
            }
        });
    }
};
