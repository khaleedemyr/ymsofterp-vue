<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omni_social_comment_seen', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->string('account_id', 64);
            $table->string('account_label', 120)->nullable();
            $table->string('post_meta_id', 128);
            $table->string('post_preview', 500)->nullable();
            $table->string('comment_meta_id', 128);
            $table->string('commenter_name', 255)->nullable();
            $table->string('comment_preview', 500)->nullable();
            $table->timestamp('comment_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'comment_meta_id'], 'omni_social_comment_seen_unique');
            $table->index(['platform', 'account_id', 'post_meta_id'], 'omni_social_comment_seen_post_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omni_social_comment_seen');
    }
};
