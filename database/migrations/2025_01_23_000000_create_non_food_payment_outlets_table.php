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
        Schema::create('non_food_payment_outlets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('non_food_payment_id');
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->timestamps();

            $table->foreign('non_food_payment_id')
                ->references('id')
                ->on('non_food_payments')
                ->onDelete('cascade');

            $table->foreign('outlet_id')
                ->references('id_outlet')
                ->on('tbl_data_outlet')
                ->onDelete('set null');

            $table->foreign('category_id')
                ->references('id')
                ->on('purchase_requisition_categories')
                ->onDelete('set null');

            $table->foreign('bank_id')
                ->references('id')
                ->on('bank_accounts')
                ->onDelete('set null');

            $table->index(['non_food_payment_id']);
            $table->index(['outlet_id']);
            $table->index(['category_id']);
            $table->index(['bank_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('non_food_payment_outlets');
    }
};
