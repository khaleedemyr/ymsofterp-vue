<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets') || Schema::hasColumn('tickets', 'vendor_name')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('vendor_name', 255)->nullable()->after('work_executor_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tickets') || ! Schema::hasColumn('tickets', 'vendor_name')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('vendor_name');
        });
    }
};
