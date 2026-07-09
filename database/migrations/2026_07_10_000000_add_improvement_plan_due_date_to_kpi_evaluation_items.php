<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kpi_evaluation_items')) {
            return;
        }

        Schema::table('kpi_evaluation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('kpi_evaluation_items', 'improvement_plan_due_date')) {
                $table->date('improvement_plan_due_date')->nullable()->after('improvement_plan');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kpi_evaluation_items')) {
            return;
        }

        Schema::table('kpi_evaluation_items', function (Blueprint $table) {
            if (Schema::hasColumn('kpi_evaluation_items', 'improvement_plan_due_date')) {
                $table->dropColumn('improvement_plan_due_date');
            }
        });
    }
};
