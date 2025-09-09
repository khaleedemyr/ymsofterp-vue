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
        Schema::create('schedule_attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['schedule', 'attendance']);
            $table->unsignedBigInteger('record_id'); // ID dari user_shifts atau att_log
            $table->text('old_value'); // Nilai lama (JSON untuk attendance)
            $table->text('new_value'); // Nilai baru (JSON untuk attendance)
            $table->text('reason'); // Alasan koreksi
            $table->unsignedBigInteger('corrected_by'); // User yang melakukan koreksi
            $table->timestamp('corrected_at');
            $table->timestamps();
            
            // Indexes
            $table->index(['type', 'record_id']);
            $table->index('corrected_by');
            $table->index('corrected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_attendance_corrections');
    }
};
