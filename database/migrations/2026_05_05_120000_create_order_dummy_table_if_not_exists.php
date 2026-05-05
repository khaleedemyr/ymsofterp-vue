<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel contoh agregat harian per outlet untuk laporan Rekap PB1.
 * Jika tabel order_dummy sudah ada di lingkungan Anda, migration ini tidak mengubah apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_dummy')) {
            return;
        }

        Schema::create('order_dummy', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->index();
            $table->unsignedInteger('id_outlet')->index();
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('disc', 18, 2)->default(0);
            $table->decimal('dpp', 18, 2)->default(0);
            $table->decimal('pb1', 18, 2)->default(0);
            $table->decimal('service_amount', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->unsignedInteger('pax')->default(0);
            $table->decimal('commfee', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Jangan drop jika sudah dipakai — uncomment hanya untuk rollback berbahaya di dev.
        // Schema::dropIfExists('order_dummy');
    }
};
