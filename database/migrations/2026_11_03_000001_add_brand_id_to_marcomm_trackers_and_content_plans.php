<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcomm_trackers', function (Blueprint $table) {
            if (! Schema::hasColumn('marcomm_trackers', 'brand_id')) {
                $table->unsignedBigInteger('brand_id')->nullable()->after('number');
                $table->index('brand_id');
            }
        });

        Schema::table('content_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('content_plans', 'brand_id')) {
                $table->unsignedBigInteger('brand_id')->nullable()->after('number');
                $table->index('brand_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marcomm_trackers', function (Blueprint $table) {
            if (Schema::hasColumn('marcomm_trackers', 'brand_id')) {
                $table->dropIndex(['brand_id']);
                $table->dropColumn('brand_id');
            }
        });

        Schema::table('content_plans', function (Blueprint $table) {
            if (Schema::hasColumn('content_plans', 'brand_id')) {
                $table->dropIndex(['brand_id']);
                $table->dropColumn('brand_id');
            }
        });
    }
};
