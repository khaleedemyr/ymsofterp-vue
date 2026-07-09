<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_regional', function (Blueprint $table) {
            if (! Schema::hasColumn('user_regional', 'supervisor_position_id')) {
                $table->unsignedInteger('supervisor_position_id')->nullable()->after('outlet_visit_targets');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_regional', function (Blueprint $table) {
            if (Schema::hasColumn('user_regional', 'supervisor_position_id')) {
                $table->dropColumn('supervisor_position_id');
            }
        });
    }
};
