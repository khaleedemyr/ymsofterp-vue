<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['qa2_templates', 'qa2_audits'] as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'scoring_mode')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('scoring_mode', 20)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['qa2_templates', 'qa2_audits'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'scoring_mode')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('scoring_mode');
                });
            }
        }
    }
};