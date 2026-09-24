<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_onboardings') || Schema::hasColumn('employee_onboardings', 'end_date')) {
            return;
        }

        Schema::table('employee_onboardings', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('start_date');
        });

        // Backfill: start + (total_weeks * 7) - 1 hari
        DB::table('employee_onboardings')
            ->whereNotNull('start_date')
            ->whereNull('end_date')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $weeks = max(1, (int) ($row->total_weeks ?? 1));
                    $end = date('Y-m-d', strtotime($row->start_date.' +'.(($weeks * 7) - 1).' days'));
                    DB::table('employee_onboardings')->where('id', $row->id)->update(['end_date' => $end]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_onboardings') || ! Schema::hasColumn('employee_onboardings', 'end_date')) {
            return;
        }

        Schema::table('employee_onboardings', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });
    }
};
