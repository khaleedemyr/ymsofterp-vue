<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feedback_cases') || Schema::hasColumn('feedback_cases', 'share_token')) {
            return;
        }

        Schema::table('feedback_cases', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('meta');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('feedback_cases') || ! Schema::hasColumn('feedback_cases', 'share_token')) {
            return;
        }

        Schema::table('feedback_cases', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
