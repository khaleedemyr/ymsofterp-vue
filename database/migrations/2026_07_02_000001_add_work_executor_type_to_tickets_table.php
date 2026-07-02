<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets') || Schema::hasColumn('tickets', 'work_executor_type')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('work_executor_type', 32)->nullable()->after('divisi_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tickets') || ! Schema::hasColumn('tickets', 'work_executor_type')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('work_executor_type');
        });
    }
};
