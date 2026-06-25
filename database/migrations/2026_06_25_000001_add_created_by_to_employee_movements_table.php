<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_movements') || Schema::hasColumn('employee_movements', 'created_by')) {
            return;
        }

        Schema::table('employee_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('employee_id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_movements') || ! Schema::hasColumn('employee_movements', 'created_by')) {
            return;
        }

        Schema::table('employee_movements', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
    }
};
