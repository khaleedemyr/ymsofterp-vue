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
            if (! Schema::hasColumn('payroll_generated_details', 'lb_by_point')) {
                $table->decimal('lb_by_point', 15, 2)->default(0)->after('bpjs_tk');
            }
            if (! Schema::hasColumn('payroll_generated_details', 'lb_pro_rate')) {
                $table->decimal('lb_pro_rate', 15, 2)->default(0)->after('lb_by_point');
            }
            if (! Schema::hasColumn('payroll_generated_details', 'deviasi_by_point')) {
                $table->decimal('deviasi_by_point', 15, 2)->default(0)->after('lb_total');
            }
            if (! Schema::hasColumn('payroll_generated_details', 'deviasi_pro_rate')) {
                $table->decimal('deviasi_pro_rate', 15, 2)->default(0)->after('deviasi_by_point');
            }
            if (! Schema::hasColumn('payroll_generated_details', 'city_ledger_by_point')) {
                $table->decimal('city_ledger_by_point', 15, 2)->default(0)->after('deviasi_total');
            }
            if (! Schema::hasColumn('payroll_generated_details', 'city_ledger_pro_rate')) {
                $table->decimal('city_ledger_pro_rate', 15, 2)->default(0)->after('city_ledger_by_point');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_generated_details')) {
            return;
        }

        Schema::table('payroll_generated_details', function (Blueprint $table) {
            $columns = [
                'lb_by_point',
                'lb_pro_rate',
                'deviasi_by_point',
                'deviasi_pro_rate',
                'city_ledger_by_point',
                'city_ledger_pro_rate',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('payroll_generated_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
