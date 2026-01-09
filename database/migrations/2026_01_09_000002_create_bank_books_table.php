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
        Schema::create('bank_books', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_account_id');
            $table->date('transaction_date');
            $table->enum('transaction_type', ['debit', 'credit']); // debit = keluar, credit = masuk
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->string('reference_type')->nullable(); // outlet_payment, food_payment, non_food_payment, manual, dll
            $table->unsignedBigInteger('reference_id')->nullable(); // ID dari transaksi yang direferensikan
            $table->decimal('balance', 15, 2)->default(0); // Saldo setelah transaksi
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('bank_account_id');
            $table->index('transaction_date');
            $table->index(['reference_type', 'reference_id']);
            $table->index('transaction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_books');
    }
};
