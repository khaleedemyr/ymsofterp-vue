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
        // Create void_bill_logs table
        Schema::create('void_bill_logs', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 100)->index();
            $table->string('order_nomor', 100)->index();
            $table->string('kode_outlet', 50)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('username', 255)->nullable();
            $table->text('reason');
            $table->timestamp('waktu');
            $table->text('extra_info')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['order_id', 'kode_outlet']);
            $table->index('waktu');
        });

        // Create void_bill_detail_logs table
        Schema::create('void_bill_detail_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('void_log_id')->index();
            $table->string('order_id', 100)->index();
            $table->string('order_nomor', 100)->index();
            $table->longText('order_data'); // JSON data of the order
            $table->longText('items_data'); // JSON data of the items
            $table->timestamps();

            // Foreign key
            $table->foreign('void_log_id')->references('id')->on('void_bill_logs')->onDelete('cascade');

            // Indexes
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('void_bill_detail_logs');
        Schema::dropIfExists('void_bill_logs');
    }
};
