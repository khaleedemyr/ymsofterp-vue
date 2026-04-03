<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_review_ai_reports', function (Blueprint $table) {
            $table->unsignedInteger('raw_review_count')->default(0);
            $table->unsignedInteger('dedupe_removed_count')->default(0);
            $table->unsignedInteger('progress_total')->default(0);
            $table->unsignedInteger('progress_done')->default(0);
            $table->string('progress_phase', 48)->nullable();
            $table->longText('progress_log')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('google_review_ai_reports', function (Blueprint $table) {
            $table->dropColumn([
                'raw_review_count',
                'dedupe_removed_count',
                'progress_total',
                'progress_done',
                'progress_phase',
                'progress_log',
            ]);
        });
    }
};
