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
        // Add fields to payroll_generated table
        Schema::table('payroll_generated', function (Blueprint $table) {
            $table->decimal('lb_amount', 15, 2)->default(0)->after('service_charge');
            $table->decimal('deviasi_amount', 15, 2)->default(0)->after('lb_amount');
            $table->decimal('city_ledger_amount', 15, 2)->default(0)->after('deviasi_amount');
        });

        // Add fields to payroll_generated_details table if exists
        if (Schema::hasTable('payroll_generated_details')) {
            Schema::table('payroll_generated_details', function (Blueprint $table) {
                $table->decimal('lb_total', 15, 2)->default(0)->after('bpjs_tk');
                $table->decimal('deviasi_total', 15, 2)->default(0)->after('lb_total');
                $table->decimal('city_ledger_total', 15, 2)->default(0)->after('deviasi_total');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_generated', function (Blueprint $table) {
            $table->dropColumn(['lb_amount', 'deviasi_amount', 'city_ledger_amount']);
        });

        if (Schema::hasTable('payroll_generated_details')) {
            Schema::table('payroll_generated_details', function (Blueprint $table) {
                $table->dropColumn(['lb_total', 'deviasi_total', 'city_ledger_total']);
            });
        }
    }
};

