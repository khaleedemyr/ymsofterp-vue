<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('content_plans', 'pic_user_id')) {
                $table->unsignedBigInteger('pic_user_id')->nullable()->after('brand_id');
                $table->index('pic_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            if (Schema::hasColumn('content_plans', 'pic_user_id')) {
                $table->dropIndex(['pic_user_id']);
                $table->dropColumn('pic_user_id');
            }
        });
    }
};
