<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_regional', function (Blueprint $table) {
            if (! Schema::hasColumn('user_regional', 'outlet_visit_targets')) {
                $table->json('outlet_visit_targets')->nullable()->after('target_outlet_visits');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_regional', function (Blueprint $table) {
            if (Schema::hasColumn('user_regional', 'outlet_visit_targets')) {
                $table->dropColumn('outlet_visit_targets');
            }
        });
    }
};
