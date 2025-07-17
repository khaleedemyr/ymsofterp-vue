<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_tutorials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->comment('ID group video tutorial');
            $table->string('title', 255)->comment('Judul video tutorial');
            $table->text('description')->nullable()->comment('Deskripsi video');
            $table->string('video_path')->comment('Path file video');
            $table->string('video_name')->comment('Nama file video');
            $table->string('video_type')->comment('Tipe file video (mp4, webm, etc)');
            $table->bigInteger('video_size')->comment('Ukuran file video dalam bytes');
            $table->string('thumbnail_path')->nullable()->comment('Path thumbnail video');
            $table->integer('duration')->nullable()->comment('Durasi video dalam detik');
            $table->enum('status', ['A', 'N'])->default('A')->comment('A=Active, N=Inactive');
            $table->unsignedBigInteger('created_by')->comment('User yang membuat');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('group_id')->references('id')->on('video_tutorial_groups')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['group_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_tutorials');
    }
}; 