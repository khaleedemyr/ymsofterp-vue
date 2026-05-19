<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_data_level', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_data_level', 'nilai_dasar_potongan_bpjs_kesehatan')) {
                $table->unsignedInteger('nilai_dasar_potongan_bpjs_kesehatan')
                    ->default(0)
                    ->after('nilai_dasar_potongan_bpjs');
            }
            if (! Schema::hasColumn('tbl_data_level', 'nilai_dasar_potongan_bpjs_ketenagakerjaan')) {
                $table->unsignedInteger('nilai_dasar_potongan_bpjs_ketenagakerjaan')
                    ->default(0)
                    ->after('nilai_dasar_potongan_bpjs_kesehatan');
            }
        });

        if (Schema::hasColumn('tbl_data_level', 'nilai_dasar_potongan_bpjs')) {
            DB::table('tbl_data_level')
                ->where(function ($q) {
                    $q->where('nilai_dasar_potongan_bpjs_kesehatan', 0)
                        ->orWhereNull('nilai_dasar_potongan_bpjs_kesehatan');
                })
                ->update([
                    'nilai_dasar_potongan_bpjs_kesehatan' => DB::raw('nilai_dasar_potongan_bpjs'),
                ]);

            DB::table('tbl_data_level')
                ->where(function ($q) {
                    $q->where('nilai_dasar_potongan_bpjs_ketenagakerjaan', 0)
                        ->orWhereNull('nilai_dasar_potongan_bpjs_ketenagakerjaan');
                })
                ->update([
                    'nilai_dasar_potongan_bpjs_ketenagakerjaan' => DB::raw('nilai_dasar_potongan_bpjs'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('tbl_data_level', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_data_level', 'nilai_dasar_potongan_bpjs_ketenagakerjaan')) {
                $table->dropColumn('nilai_dasar_potongan_bpjs_ketenagakerjaan');
            }
            if (Schema::hasColumn('tbl_data_level', 'nilai_dasar_potongan_bpjs_kesehatan')) {
                $table->dropColumn('nilai_dasar_potongan_bpjs_kesehatan');
            }
        });
    }
};
