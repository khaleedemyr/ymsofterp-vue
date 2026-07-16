<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_review_ai_reports')) {
            return;
        }
        if (Schema::hasColumn('google_review_ai_reports', 'classification_mode')) {
            return;
        }

        Schema::table('google_review_ai_reports', function (Blueprint $table) {
            $table->string('classification_mode', 16)->default('ai')->after('source')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('google_review_ai_reports')) {
            return;
        }
        if (! Schema::hasColumn('google_review_ai_reports', 'classification_mode')) {
            return;
        }

        Schema::table('google_review_ai_reports', function (Blueprint $table) {
            $table->dropColumn('classification_mode');
        });
    }
};
