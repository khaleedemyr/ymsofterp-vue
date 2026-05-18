<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_generated_details')) {
            return;
        }
        Schema::table('payroll_generated_details', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_generated_details', 'bpjs_perusahaan_detail')) {
                $table->longText('bpjs_perusahaan_detail')->nullable()->after('bpjs_tk');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_generated_details')) {
            return;
        }
        Schema::table('payroll_generated_details', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_generated_details', 'bpjs_perusahaan_detail')) {
                $table->dropColumn('bpjs_perusahaan_detail');
            }
        });
    }
};
