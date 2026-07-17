<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('submission_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['submission_date']);
            $table->index(['created_by']);
        });

        Schema::create('overtime_submission_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('user_id');
            $table->date('overtime_date');
            $table->decimal('requested_hours', 8, 2);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('submission_id')->references('id')->on('overtime_submissions')->onDelete('cascade');
            $table->index(['user_id', 'overtime_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_submission_items');
        Schema::dropIfExists('overtime_submissions');
    }
};
