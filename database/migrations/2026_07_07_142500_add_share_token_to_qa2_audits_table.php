<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qa2_audits', function (Blueprint $table) {
            if (!Schema::hasColumn('qa2_audits', 'share_token')) {
                $table->string('share_token', 64)->nullable()->unique()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('qa2_audits', function (Blueprint $table) {
            if (Schema::hasColumn('qa2_audits', 'share_token')) {
                $table->dropUnique('qa2_audits_share_token_unique');
                $table->dropColumn('share_token');
            }
        });
    }
};
