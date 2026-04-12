<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_apps_point_transactions')) {
            return;
        }

        if (! Schema::hasColumn('member_apps_point_transactions', 'outlet_id')) {
            Schema::table('member_apps_point_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('outlet_id')->nullable()->after('member_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('member_apps_point_transactions')) {
            return;
        }

        if (Schema::hasColumn('member_apps_point_transactions', 'outlet_id')) {
            Schema::table('member_apps_point_transactions', function (Blueprint $table) {
                $table->dropColumn('outlet_id');
            });
        }
    }
};
