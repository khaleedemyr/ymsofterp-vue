<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_content_posts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->string('account_id', 64);
            $table->string('account_label', 120)->nullable();
            $table->string('external_id', 128);
            $table->text('caption')->nullable();
            $table->string('permalink', 500)->nullable();
            $table->string('thumbnail_url', 1000)->nullable();
            $table->string('media_type', 40)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'external_id'], 'social_content_posts_platform_ext_unique');
            $table->index(['platform', 'account_id', 'posted_at'], 'social_content_posts_acct_posted_idx');
            $table->index(['posted_at'], 'social_content_posts_posted_idx');
        });

        Schema::create('social_content_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_content_post_id')
                ->constrained('social_content_posts')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->unsignedBigInteger('saved')->nullable();
            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('clicks')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique('social_content_post_id', 'social_content_metrics_post_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_content_metrics');
        Schema::dropIfExists('social_content_posts');
    }
};
