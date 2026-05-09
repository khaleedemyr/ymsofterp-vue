<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_review_ai_items')) {
            Schema::table('google_review_ai_items', function (Blueprint $table) {
                if (! Schema::hasColumn('google_review_ai_items', 'follow_up_target')) {
                    $table->string('follow_up_target', 16)->nullable()->after('severity');
                }
                if (! Schema::hasColumn('google_review_ai_items', 'impact')) {
                    $table->json('impact')->nullable()->after('follow_up_target');
                }
            });
        }

        if (Schema::hasTable('guest_comment_forms')) {
            Schema::table('guest_comment_forms', function (Blueprint $table) {
                if (! Schema::hasColumn('guest_comment_forms', 'issue_follow_up_target')) {
                    $table->string('issue_follow_up_target', 16)->nullable()->after('issue_severity');
                }
                if (! Schema::hasColumn('guest_comment_forms', 'issue_impact')) {
                    $table->json('issue_impact')->nullable()->after('issue_follow_up_target');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('google_review_ai_items')) {
            Schema::table('google_review_ai_items', function (Blueprint $table) {
                if (Schema::hasColumn('google_review_ai_items', 'impact')) {
                    $table->dropColumn('impact');
                }
                if (Schema::hasColumn('google_review_ai_items', 'follow_up_target')) {
                    $table->dropColumn('follow_up_target');
                }
            });
        }

        if (Schema::hasTable('guest_comment_forms')) {
            Schema::table('guest_comment_forms', function (Blueprint $table) {
                if (Schema::hasColumn('guest_comment_forms', 'issue_impact')) {
                    $table->dropColumn('issue_impact');
                }
                if (Schema::hasColumn('guest_comment_forms', 'issue_follow_up_target')) {
                    $table->dropColumn('issue_follow_up_target');
                }
            });
        }
    }
};
