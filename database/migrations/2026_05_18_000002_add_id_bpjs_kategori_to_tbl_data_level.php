<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_data_level', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_data_level', 'id_bpjs_kategori')) {
                $table->unsignedBigInteger('id_bpjs_kategori')->nullable()->after('nilai_dasar_potongan_bpjs');
                $table->foreign('id_bpjs_kategori')
                    ->references('id')
                    ->on('tbl_bpjs_kategori')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_data_level', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_data_level', 'id_bpjs_kategori')) {
                $table->dropForeign(['id_bpjs_kategori']);
                $table->dropColumn('id_bpjs_kategori');
            }
        });
    }
};
