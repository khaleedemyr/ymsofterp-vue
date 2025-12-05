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
        Schema::create('holiday_attendance_compensations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('holiday_date');
            $table->enum('compensation_type', ['extra_off', 'bonus']);
            $table->decimal('compensation_amount', 10, 2);
            $table->text('compensation_description');
            $table->enum('status', ['pending', 'approved', 'used', 'cancelled'])->default('pending');
            $table->date('used_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index(['user_id', 'holiday_date']);
            $table->index(['compensation_type', 'status']);
            $table->index('holiday_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday_attendance_compensations');
    }
};
