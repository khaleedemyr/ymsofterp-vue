<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_reports') || Schema::hasColumn('daily_reports', 'share_token')) {
            return;
        }

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('daily_reports') || ! Schema::hasColumn('daily_reports', 'share_token')) {
            return;
        }

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
