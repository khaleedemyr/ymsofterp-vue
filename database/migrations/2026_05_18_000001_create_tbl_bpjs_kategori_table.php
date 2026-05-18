<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_bpjs_kategori', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kategori', 150);
            $table->decimal('pct_kes_perusahaan', 8, 4)->default(0);
            $table->decimal('pct_kes_karyawan', 8, 4)->default(0);
            $table->decimal('pct_jht_perusahaan', 8, 4)->default(0);
            $table->decimal('pct_jp_perusahaan', 8, 4)->default(0);
            $table->decimal('pct_jkk_perusahaan', 8, 4)->default(0);
            $table->decimal('pct_jkm_perusahaan', 8, 4)->default(0);
            $table->decimal('pct_jht_karyawan', 8, 4)->default(0);
            $table->decimal('pct_jp_karyawan', 8, 4)->default(0);
            $table->char('status', 1)->default('A');
        });

        DB::table('tbl_bpjs_kategori')->insert([
            [
                'nama_kategori' => 'Leader Outlet (dengan JHT)',
                'pct_kes_perusahaan' => 4,
                'pct_kes_karyawan' => 1,
                'pct_jht_perusahaan' => 3.7,
                'pct_jp_perusahaan' => 0,
                'pct_jkk_perusahaan' => 0.54,
                'pct_jkm_perusahaan' => 0.3,
                'pct_jht_karyawan' => 2,
                'pct_jp_karyawan' => 0,
                'status' => 'A',
            ],
            [
                'nama_kategori' => 'Crew (tanpa JHT)',
                'pct_kes_perusahaan' => 4,
                'pct_kes_karyawan' => 1,
                'pct_jht_perusahaan' => 0,
                'pct_jp_perusahaan' => 0,
                'pct_jkk_perusahaan' => 0.54,
                'pct_jkm_perusahaan' => 0.3,
                'pct_jht_karyawan' => 0,
                'pct_jp_karyawan' => 0,
                'status' => 'A',
            ],
            [
                'nama_kategori' => 'HO (JHT + JP)',
                'pct_kes_perusahaan' => 4,
                'pct_kes_karyawan' => 1,
                'pct_jht_perusahaan' => 3.7,
                'pct_jp_perusahaan' => 2,
                'pct_jkk_perusahaan' => 0.54,
                'pct_jkm_perusahaan' => 0.3,
                'pct_jht_karyawan' => 2,
                'pct_jp_karyawan' => 1,
                'status' => 'A',
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_bpjs_kategori');
    }
};
