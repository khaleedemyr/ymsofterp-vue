<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_review_ai_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status', 24)->default('pending')->index();
            $table->string('source', 32);
            $table->string('place_id', 255)->nullable();
            $table->unsignedBigInteger('id_outlet')->nullable();
            $table->string('nama_outlet', 255)->nullable();
            $table->string('dataset_id', 128)->nullable();
            $table->string('place_name', 512)->nullable();
            $table->string('place_address', 1024)->nullable();
            $table->string('place_rating', 64)->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->text('error_message')->nullable();
            $table->longText('source_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('google_review_ai_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('google_review_ai_reports')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('author', 255)->nullable();
            $table->string('rating', 32)->nullable();
            $table->string('review_date', 255)->nullable();
            $table->text('text')->nullable();
            $table->string('profile_photo', 1024)->nullable();
            $table->string('severity', 32)->nullable();
            $table->json('topics')->nullable();
            $table->string('summary_id', 500)->nullable();
            $table->timestamps();
            $table->index(['report_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_review_ai_items');
        Schema::dropIfExists('google_review_ai_reports');
    }
};
