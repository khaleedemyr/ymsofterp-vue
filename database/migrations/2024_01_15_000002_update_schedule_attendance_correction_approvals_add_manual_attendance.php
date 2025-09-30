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
        // Update the type enum to include manual_attendance
        DB::statement("ALTER TABLE schedule_attendance_correction_approvals MODIFY COLUMN type ENUM('schedule','attendance','manual_attendance') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum
        DB::statement("ALTER TABLE schedule_attendance_correction_approvals MODIFY COLUMN type ENUM('schedule','attendance') NOT NULL");
    }
};
