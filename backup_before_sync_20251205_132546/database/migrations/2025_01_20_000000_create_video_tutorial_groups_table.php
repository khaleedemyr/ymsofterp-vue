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
        Schema::create('video_tutorial_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('Nama group video tutorial');
            $table->text('description')->nullable()->comment('Deskripsi group');
            $table->enum('status', ['A', 'N'])->default('A')->comment('A=Active, N=Inactive');
            $table->unsignedBigInteger('created_by')->comment('User yang membuat');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_tutorial_groups');
    }
}; 